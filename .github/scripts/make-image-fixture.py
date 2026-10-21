#!/usr/bin/env python3
"""Writes tests/fixtures/images.pdf: one page with a JPEG 2000 image (left) and a JBIG2 image (right).

PDF.js 5 and later decodes both formats with WebAssembly decoders (vendor/pdfjs/wasm); without them
the images stay empty. The Behat feature "leafr_rendering" checks that both halves of the rendered
page contain dark pixels. The images are drawn here, so the file is free of third-party content.

Usage: python3 .github/scripts/make-image-fixture.py   (needs Pillow with JPEG 2000 and libtiff)
"""
import io
import os
import struct

from PIL import Image, ImageDraw, TiffImagePlugin

W, H = 240, 240


def picture():
    """A black frame, a filled disc and a diagonal bar on white, as a 1-bit drawing."""
    img = Image.new('L', (W, H), 255)
    d = ImageDraw.Draw(img)
    d.rectangle([8, 8, W - 9, H - 9], outline=0, width=10)
    d.ellipse([50, 50, 150, 150], fill=0)
    d.polygon([(130, 200), (200, 120), (215, 135), (145, 215)], fill=0)
    return img


def jpx_bytes():
    out = io.BytesIO()
    rgb = Image.merge('RGB', [picture()] * 3)
    rgb.save(out, 'JPEG2000', irreversible=False)
    return out.getvalue()


def mmr_bytes(img):
    """CCITT T.6 (MMR) data of a 1-bit image, taken from a single-strip Group 4 TIFF."""
    out = io.BytesIO()
    TiffImagePlugin.WRITE_LIBTIFF = True
    img.convert('1').save(out, 'TIFF', compression='group4')
    TiffImagePlugin.WRITE_LIBTIFF = False
    tif = Image.open(io.BytesIO(out.getvalue()))
    offsets, counts = tif.tag_v2[273], tif.tag_v2[279]
    assert len(offsets) == 1, 'expected one strip'
    photometric = tif.tag_v2[262]
    return out.getvalue()[offsets[0]:offsets[0] + counts[0]], photometric


def segment(number, kind, data, page=1):
    """A JBIG2 segment header (7.2) without referred-to segments, plus its data."""
    return struct.pack('>IBBBI', number, kind, 0, page, len(data)) + data


def jbig2_bytes():
    img = picture()
    data, photometric = mmr_bytes(img)
    if photometric != 0:
        # MMR codes runs of "white" and "black"; JBIG2 treats 1 as black, TIFF WhiteIsZero does too.
        # Re-encode the inverted picture if libtiff chose BlackIsZero.
        data, photometric = mmr_bytes(Image.eval(img, lambda v: 255 - v))
    pageinfo = struct.pack('>IIIIBH', W, H, 0, 0, 0, 0)
    region = struct.pack('>IIIIB', W, H, 0, 0, 0) + bytes([1]) + data  # Generic region flags: MMR = 1.
    return segment(0, 48, pageinfo) + segment(1, 38, region) + segment(2, 49, b'')


def pdf(objects):
    out = io.BytesIO()
    out.write(b'%PDF-1.5\n%\xe2\xe3\xcf\xd3\n')
    offsets = []
    for i, obj in enumerate(objects, 1):
        offsets.append(out.tell())
        out.write(b'%d 0 obj\n' % i + obj + b'\nendobj\n')
    xref = out.tell()
    out.write(b'xref\n0 %d\n0000000000 65535 f \n' % (len(objects) + 1))
    for off in offsets:
        out.write(b'%010d 00000 n \n' % off)
    out.write(b'trailer\n<< /Size %d /Root 1 0 R >>\nstartxref\n%d\n%%%%EOF\n' % (len(objects) + 1, xref))
    return out.getvalue()


def stream(head, data):
    return head + b' /Length %d >>\nstream\n' % len(data) + data + b'\nendstream'


def main():
    jpx = jpx_bytes()
    jb2 = jbig2_bytes()
    content = b'q 240 0 0 240 20 30 cm /Jpx Do Q q 240 0 0 240 300 30 cm /Jb2 Do Q'
    objects = [
        b'<< /Type /Catalog /Pages 2 0 R >>',
        b'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        b'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 560 300] /Contents 4 0 R'
        b' /Resources << /XObject << /Jpx 5 0 R /Jb2 6 0 R >> >> >>',
        stream(b'<<', content),
        stream(b'<< /Type /XObject /Subtype /Image /Width %d /Height %d /Filter /JPXDecode' % (W, H), jpx),
        stream(b'<< /Type /XObject /Subtype /Image /Width %d /Height %d /ColorSpace /DeviceGray'
               b' /BitsPerComponent 1 /Filter /JBIG2Decode' % (W, H), jb2),
    ]
    target = os.path.join(os.path.dirname(__file__), '..', '..', 'tests', 'fixtures', 'images.pdf')
    with open(target, 'wb') as f:
        f.write(pdf(objects))
    print('Wrote %s (%d bytes: JPX %d, JBIG2 %d)' % (os.path.normpath(target), os.path.getsize(target), len(jpx), len(jb2)))


if __name__ == '__main__':
    main()

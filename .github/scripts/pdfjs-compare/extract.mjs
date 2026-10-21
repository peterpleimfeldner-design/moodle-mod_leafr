// Extracts page count, page sizes, resolved outline and per-page text (plus text item
// positions, as mod_leafr/pdf getTextItems computes them) from PDFs with a given PDF.js build.
// Usage: node extract.mjs <path to pdf.mjs or pdf.js> <out.json> <pdf> [<pdf> ...]
import {readFileSync, writeFileSync} from 'node:fs';
import {pathToFileURL} from 'node:url';
import {createRequire} from 'node:module';
import {basename} from 'node:path';

const [libPath, outPath, ...pdfs] = process.argv.slice(2);
let lib;
if (libPath.endsWith('.mjs')) {
    lib = await import(pathToFileURL(libPath).href);
} else {
    lib = createRequire(import.meta.url)(libPath);
}

const resolveOutline = async(pdfDoc, items, depth, maxDepth) => {
    const result = [];
    for (const item of items || []) {
        let page = 0;
        try {
            const dest = typeof item.dest === 'string' ? await pdfDoc.getDestination(item.dest) : item.dest;
            if (Array.isArray(dest) && dest[0]) {
                page = typeof dest[0] === 'number' ? dest[0] + 1 : await pdfDoc.getPageIndex(dest[0]) + 1;
            }
        } catch (e) {
            page = 0;
        }
        const children = depth < maxDepth ? await resolveOutline(pdfDoc, item.items, depth + 1, maxDepth) : [];
        if (page > 0 || children.length) {
            result.push({title: item.title || '', page: page || (children[0] && children[0].page) || 1, children});
        }
    }
    return result;
};

const out = {version: lib.version, documents: {}};
for (const file of pdfs) {
    const t0 = performance.now();
    const pdfDoc = await lib.getDocument({
        data: new Uint8Array(readFileSync(file)),
        isEvalSupported: false,
        enableXfa: false,
        verbosity: 0,
    }).promise;
    const doc = {numPages: pdfDoc.numPages, pages: []};
    doc.outline = await resolveOutline(pdfDoc, await pdfDoc.getOutline(), 1, 3);
    for (let n = 1; n <= pdfDoc.numPages; n++) {
        const page = await pdfDoc.getPage(n);
        const vp = page.getViewport({scale: 1});
        const content = await page.getTextContent();
        const text = content.items.map((i) => i.str + (i.hasEOL ? '\n' : ' ')).join('').replace(/[ \t]+/g, ' ').trim();
        const boxes = content.items.filter((i) => i.str && i.str.trim() !== '').map((i) => {
            const t = lib.Util.transform(vp.transform, i.transform);
            return [i.str, +t[4].toFixed(2), +(t[5] - i.height).toFixed(2), +i.width.toFixed(2), +i.height.toFixed(2)];
        });
        doc.pages.push({width: +vp.width.toFixed(2), height: +vp.height.toFixed(2), text, boxes});
        page.cleanup();
    }
    doc.ms = Math.round(performance.now() - t0);
    out.documents[basename(file)] = doc;
    // PDFDocumentProxy.destroy() was removed in 6.0; the loading task owns the document.
    await pdfDoc.loadingTask.destroy();
}
writeFileSync(outPath, JSON.stringify(out, null, 1));
console.log(`${lib.version}: ${pdfs.length} documents -> ${outPath}`);

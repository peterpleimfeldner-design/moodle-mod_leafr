PDF.js 6.4.299 (Mozilla, Apache-2.0), legacy build from the npm package pdfjs-dist.
Source: https://registry.npmjs.org/pdfjs-dist/-/pdfjs-dist-6.4.299.tgz
Project: https://github.com/mozilla/pdf.js

Update: bash .github/scripts/update-pdfjs.sh <version>, then set the same version in
thirdpartylibs.xml and PDFJS_VERSION in amd/src/pdf.js, rebuild the AMD modules and run
bash .github/scripts/check-pdfjs.sh.

The files are unmodified. Renamed: legacy/build/pdf.min.mjs -> pdf.min.js,
legacy/build/pdf.worker.min.mjs -> pdf.worker.min.js (servers do not always send a
JavaScript MIME type for .mjs). wasm/ is a subset of the package's wasm/ directory.

SHA-256:
0d542e0c8804e39aa7f37eb00da5a762149dc682d7829451287e11b938e94594 *LICENSE
bccc24ea711db8e44503629519904a5292d73b9daaa214bbe7cdcc282b0f4259 *pdf.min.js
145d2dd3ab0c86151011dba95acfa2d5336e2accd59388ea43dbee0efddaaec6 *pdf.worker.min.js
9e66b7f1b934a28b37f3bc4dac97915de1674271e79a0a88182a18ed9731b4d1 *wasm/LICENSE_JBIG2
a6af136f3e15038a666b61f376612a07d9a4e48cb7c01adbf3e33b3f14ab49b6 *wasm/LICENSE_OPENJPEG
aad3cce09842e00e9e11ad5e8fef8cc02fbc3a3768fe2f007443b9cee37aaee5 *wasm/LICENSE_PDFJS_JBIG2
717fc62da03292dbb4dd0c8280bd4ce7bb8550dcf31d772bc93455fb50313425 *wasm/LICENSE_PDFJS_OPENJPEG
508a77d2e7b51d98adeed32648ad124b7b30241a8e70b2e72c99f92d8e5874d1 *wasm/LICENSE_PDFJS_QCMS
36d847ae882f6574ebc72f56a4f354e4f104fde4a584373496482e97d52d31bc *wasm/LICENSE_QCMS
466f45c2a61a698152fb5400c27e56ff2ceb73dcb71fde3fc366a502308eaab0 *wasm/jbig2.wasm
68686ced624a48c42f84cdaa482ec44b72b0de4dc84a0df903af1fb7b45b5d9a *wasm/jbig2_nowasm_fallback.js
95e5002597af0824004aa57b1900fe019715db389e37640b1e58395e42f00cc5 *wasm/openjpeg.wasm
9047b34b6bc142d85e4b0aa1b08b0b9baad4c6f8b7ac87641b9e8f90eb6fd23e *wasm/openjpeg_nowasm_fallback.js
663d86126d5f5fcb1c61490f94353e2a8375660b8c5498ab3ebab5a34b08800e *wasm/qcms_bg.wasm

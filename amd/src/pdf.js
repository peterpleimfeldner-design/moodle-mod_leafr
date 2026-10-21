// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Thin wrapper around PDF.js: loads documents and renders pages to canvases.
 *
 * @module     mod_leafr/pdf
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';

/**
 * Version of the bundled PDF.js build in vendor/pdfjs (must match thirdpartylibs.xml).
 *
 * It is also appended to the library and worker URLs, so that browsers never combine a cached
 * library of one version with the worker of another one (PDF.js refuses such a mix).
 */
export const PDFJS_VERSION = '6.4.299';

/** Largest canvas edge in device pixels, keeps memory use of a single page bounded. */
const MAX_CANVAS_EDGE = 4096;

/** @type {WeakMap<HTMLCanvasElement, Object>} Running render task per canvas. */
const renderTasks = new WeakMap();

/** @type {Map<number, Object>} Size of page 1-based n at scale 1. */
const pageSizes = new Map();

/** @type {Promise<Object>|null} The loaded PDF.js library. */
let libraryPromise = null;

/** @type {Object|null} The loaded PDF.js library, set once libraryPromise has resolved. */
let pdfjsLib = null;

/**
 * Returns the URL of a file in the PDF.js vendor directory.
 *
 * @param {string} path Path relative to vendor/pdfjs
 * @returns {string}
 */
const vendorUrl = (path) => Config.wwwroot + '/mod/leafr/vendor/pdfjs/' + path;

/**
 * Loads the PDF.js library once.
 *
 * Since version 4 PDF.js is only published as a JavaScript module, which RequireJS cannot load
 * (and Moodle's build turns import() into a RequireJS call). The unmodified library file is
 * therefore added as a module script; it publishes its API as globalThis.pdfjsLib. The files
 * are shipped with a .js extension, because every web server sends the JavaScript MIME type for
 * .js, which browsers require for module scripts and module workers (.mjs is often unknown).
 *
 * @returns {Promise<Object>} The PDF.js library
 */
export const loadLibrary = () => {
    if (!libraryPromise) {
        libraryPromise = new Promise((resolve, reject) => {
            const loaded = () => window.pdfjsLib && window.pdfjsLib.version === PDFJS_VERSION;
            const finish = () => {
                if (!loaded()) {
                    reject(new Error('PDF.js ' + PDFJS_VERSION + ' could not be loaded'));
                    return;
                }
                pdfjsLib = window.pdfjsLib;
                pdfjsLib.GlobalWorkerOptions.workerSrc = vendorUrl('pdf.worker.min.js?v=' + PDFJS_VERSION);
                resolve(pdfjsLib);
            };
            if (loaded()) {
                finish();
                return;
            }
            const script = document.createElement('script');
            script.type = 'module';
            script.src = vendorUrl('pdf.min.js?v=' + PDFJS_VERSION);
            script.addEventListener('load', finish);
            script.addEventListener('error', () => reject(new Error('PDF.js could not be loaded from ' + script.src)));
            document.head.appendChild(script);
        });
        // Allow a later retry if loading failed (for example after a network error).
        libraryPromise.catch(() => {
            libraryPromise = null;
        });
    }
    return libraryPromise;
};

/**
 * Loads a PDF document.
 *
 * @param {string} url URL of the PDF file
 * @returns {Promise<Object>} PDF.js document proxy
 */
export const loadDocument = async(url) => {
    const lib = await loadLibrary();
    pageSizes.clear();
    return lib.getDocument({
        url: url,
        // Never evaluate code from the PDF. PDF.js 5.7 and later no longer contain any code path that
        // uses eval (the option was removed with it); it is kept so that the intent stays explicit.
        isEvalSupported: false,
        enableXfa: false,
        // Decoders for JBIG2 and JPEG 2000 images and for ICC colour profiles (WebAssembly, with a
        // JavaScript fallback). Without them such images would stay empty.
        wasmUrl: vendorUrl('wasm/'),
    }).promise;
};

/**
 * Returns the size of a page at scale 1 in CSS pixels.
 *
 * @param {Object} pdfDoc PDF.js document proxy
 * @param {number} pageNum 1-based page number
 * @returns {Promise<{width: number, height: number}>}
 */
export const getPageSize = async(pdfDoc, pageNum) => {
    if (!pageSizes.has(pageNum)) {
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({scale: 1});
        pageSizes.set(pageNum, {width: viewport.width, height: viewport.height});
    }
    return pageSizes.get(pageNum);
};

/**
 * Renders a page into a canvas so that it looks sharp at the given CSS width.
 *
 * A render that is still running for the same canvas is cancelled first.
 *
 * @param {Object} pdfDoc PDF.js document proxy
 * @param {number} pageNum 1-based page number
 * @param {HTMLCanvasElement} canvas Target canvas
 * @param {number} cssWidth Displayed width of the canvas in CSS pixels
 * @returns {Promise<boolean>} True if the page was rendered, false if the render was cancelled
 */
export const renderPage = async(pdfDoc, pageNum, canvas, cssWidth) => {
    cancelRender(canvas);

    const page = await pdfDoc.getPage(pageNum);
    const base = page.getViewport({scale: 1});
    const ratio = window.devicePixelRatio || 1;
    let scale = Math.max(cssWidth, 1) * ratio / base.width;
    const longest = Math.max(base.width, base.height) * scale;
    if (longest > MAX_CANVAS_EDGE) {
        scale *= MAX_CANVAS_EDGE / longest;
    }
    const viewport = page.getViewport({scale: scale});

    // Render off-screen first so the old image stays visible until the new one is ready.
    const buffer = document.createElement('canvas');
    buffer.width = Math.floor(viewport.width);
    buffer.height = Math.floor(viewport.height);
    const context = buffer.getContext('2d', {alpha: false});
    if (!context) {
        return false;
    }
    const task = page.render({canvasContext: context, viewport: viewport});
    renderTasks.set(canvas, task);
    try {
        await task.promise;
    } catch (error) {
        if (error && error.name === 'RenderingCancelledException') {
            return false;
        }
        throw error;
    } finally {
        if (renderTasks.get(canvas) === task) {
            renderTasks.delete(canvas);
        }
    }

    canvas.width = buffer.width;
    canvas.height = buffer.height;
    canvas.getContext('2d', {alpha: false}).drawImage(buffer, 0, 0);
    buffer.width = 0;
    buffer.height = 0;
    return true;
};

/**
 * Cancels a running render of a canvas.
 *
 * @param {HTMLCanvasElement} canvas Canvas
 */
export const cancelRender = (canvas) => {
    const task = renderTasks.get(canvas);
    if (task) {
        task.cancel();
        renderTasks.delete(canvas);
    }
};

/**
 * Frees the memory of a rendered canvas.
 *
 * @param {HTMLCanvasElement} canvas Canvas
 */
export const releaseCanvas = (canvas) => {
    cancelRender(canvas);
    canvas.width = 0;
    canvas.height = 0;
};

/**
 * Returns the plain text of a page, used as text alternative for screen readers.
 *
 * @param {Object} pdfDoc PDF.js document proxy
 * @param {number} pageNum 1-based page number
 * @returns {Promise<string>}
 */
export const getPageText = async(pdfDoc, pageNum) => {
    const page = await pdfDoc.getPage(pageNum);
    const content = await page.getTextContent();
    return content.items.map((item) => item.str + (item.hasEOL ? '\n' : ' ')).join('').replace(/[ \t]+/g, ' ').trim();
};

/**
 * Returns the text items of a page with their position and size in PDF page units (the same
 * units {@see getPageSize} uses), so a caller can position a highlight over a match without
 * knowing about the current zoom or the page-turning transform of the flipbook view.
 *
 * This does not build a full, selectable text layer (that would be a separate text-highlighting feature) - it is only precise
 * enough to place a highlight box over a search match.
 *
 * @param {Object} pdfDoc PDF.js document proxy
 * @param {number} pageNum 1-based page number
 * @returns {Promise<Array<{str: string, left: number, top: number, width: number, height: number}>>}
 */
export const getTextItems = async(pdfDoc, pageNum) => {
    const page = await pdfDoc.getPage(pageNum);
    const viewport = page.getViewport({scale: 1});
    const content = await page.getTextContent();
    return content.items.filter((item) => item.str && item.str.trim() !== '').map((item) => {
        // Item.width/height are already in page units; only the origin needs the viewport
        // transform (which accounts for the page's y-flip and any rotation).
        const t = pdfjsLib.Util.transform(viewport.transform, item.transform);
        return {
            str: item.str,
            left: t[4],
            top: t[5] - item.height,
            width: item.width,
            height: item.height,
        };
    });
};

/**
 * Resolves a PDF outline (bookmarks) into a tree of titles and page numbers.
 *
 * @param {Object} pdfDoc PDF.js document proxy
 * @param {number} maxDepth Maximum nesting depth
 * @returns {Promise<Array<{title: string, page: number, children: Array}>>}
 */
export const getOutline = async(pdfDoc, maxDepth = 3) => {
    const outline = await pdfDoc.getOutline();
    const resolve = async(items, depth) => {
        const result = [];
        for (const item of items || []) {
            let page = 0;
            try {
                const dest = typeof item.dest === 'string' ? await pdfDoc.getDestination(item.dest) : item.dest;
                if (Array.isArray(dest) && dest[0]) {
                    page = typeof dest[0] === 'number' ? dest[0] + 1 : await pdfDoc.getPageIndex(dest[0]) + 1;
                }
            } catch (error) {
                page = 0;
            }
            const children = depth < maxDepth ? await resolve(item.items, depth + 1) : [];
            if (page > 0 || children.length) {
                result.push({title: item.title || '', page: page || (children[0] && children[0].page) || 1, children});
            }
        }
        return result;
    };
    return resolve(outline, 1);
};

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * PDF.js wrapper for mod_leafr.
 * Handles PDF document loading and page rendering.
 *
 * PDF.js is loaded as an AMD dependency via requirejs path "mod_leafr/vendor-pdfjs"
 * configured in view.php. This avoids the UMD/AMD detection issue where PDF.js
 * would detect window.define and register as AMD instead of setting window.pdfjsLib.
 *
 * @module     mod_leafr/pdfloader
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// PDF.js 3.x registers itself as "pdfjs-dist/build/pdf" via its named AMD define.
// The require.config path in view.php maps this exact name to the local file.
define(['pdfjs-dist/build/pdf'], function(pdfjsLib) {

    'use strict';

    // Configure the worker once at module load time.
    pdfjsLib.GlobalWorkerOptions.workerSrc =
        M.cfg.wwwroot + '/mod/leafr/vendor/pdfjs/pdf.worker.min.js';

    /**
     * Load a PDF document from a URL.
     *
     * @param {string} url PDF file URL (authenticated via Moodle File API)
     * @return {Promise<PDFDocumentProxy>} PDF document proxy
     */
    async function load(url) {
        const loadingTask = pdfjsLib.getDocument({
            url,
            withCredentials: true, // Required for Moodle file API auth.
            cMapUrl:         M.cfg.wwwroot + '/mod/leafr/vendor/pdfjs/cmaps/',
            cMapPacked:      true,
        });

        return await loadingTask.promise;
    }

    /**
     * Render a single PDF page to a canvas element.
     *
     * @param {PDFPageProxy} page The PDF page proxy
     * @param {HTMLCanvasElement} canvas Target canvas element
     * @param {number} scale Render scale (default: 1.5)
     * @return {Promise<void>}
     */
    async function renderPage(page, canvas, scale = 1.5) {
        const viewport = page.getViewport({scale});

        canvas.width  = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);

        const context = canvas.getContext('2d');
        const renderContext = {
            canvasContext: context,
            viewport,
        };

        await page.render(renderContext).promise;
    }

    /**
     * Get page dimensions for a PDF page.
     *
     * @param {PDFPageProxy} page The PDF page proxy
     * @param {number} scale Render scale
     * @return {{width: number, height: number}}
     */
    function getPageDimensions(page, scale = 1.5) {
        const viewport = page.getViewport({scale});
        return {
            width:  Math.floor(viewport.width),
            height: Math.floor(viewport.height),
        };
    }

    return {
        load,
        renderPage,
        getPageDimensions,
    };
});

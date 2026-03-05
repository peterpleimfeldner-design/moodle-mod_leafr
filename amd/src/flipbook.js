// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * StPageFlip integration for mod_leafr.
 * Manages the animated page-flip book rendering.
 *
 * StPageFlip is loaded as an AMD dependency via requirejs path "mod_leafr/vendor-stpageflip"
 * configured in view.php. This avoids the UMD/AMD detection issue.
 *
 * @module     mod_leafr/flipbook
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// "mod_leafr/vendor-stpageflip" path is configured via require.config() in view.php.
define(['mod_leafr/pdfloader', 'mod_leafr/vendor-stpageflip'], function(PdfLoader, St) {

    'use strict';

    /** Render scale for PDF pages */
    const RENDER_SCALE = 1.5;

    /** Buffer: pre-render N pages ahead/behind */
    const RENDER_BUFFER = 3;

    /** Set of rendered page numbers */
    const renderedPages = new Set();

    /** The PDF document proxy */
    let pdfDoc = null;

    /** The PageFlip instance */
    let pageFlip = null;

    /** Callback when page changes */
    let onFlipCallback = null;

    /**
     * Initialize the StPageFlip instance.
     *
     * @param {Object} options Initialization options
     * @param {HTMLElement} options.container Flipbook container element
     * @param {PDFDocumentProxy} options.pdfDoc PDF document
     * @param {number} options.startPage Starting page number
     * @param {number} options.totalPages Total number of pages
     * @param {Object} options.strings Language strings
     * @param {Function} options.onFlip Callback on page flip
     * @param {Function} options.onReady Callback when flipbook is ready
     * @return {Promise<PageFlip>}
     */
    async function init(options) {
        pdfDoc = options.pdfDoc;
        onFlipCallback = options.onFlip;

        const container = options.container;
        if (!container) {
            throw new Error('Flipbook container element not found.');
        }

        // Detect single page vs spread layout.
        const isMobile = window.innerWidth < 768;

        // Get first page dimensions for sizing.
        const firstPage = await pdfDoc.getPage(1);
        const dims = PdfLoader.getPageDimensions(firstPage, RENDER_SCALE);

        // Landscape PDFs (width > height) use single-page display.
        // Portrait PDFs on desktop show a two-page spread (book layout).
        const isLandscape  = dims.width > dims.height;
        const useSinglePage = isMobile || isLandscape;
        const flipbookWidth  = useSinglePage ? dims.width : dims.width * 2;
        const flipbookHeight = dims.height;

        container.style.width  = flipbookWidth  + 'px';
        container.style.height = flipbookHeight + 'px';

        // Create page canvases.
        const pages = createPageElements(options.totalPages);
        container.innerHTML = '';
        pages.forEach(p => container.appendChild(p));

        // Initialize StPageFlip.
        pageFlip = new St.PageFlip(container, {
            width:           dims.width,
            height:          dims.height,
            size:            'fixed',
            minWidth:        dims.width,
            maxWidth:        useSinglePage ? dims.width : dims.width * 2,
            minHeight:       dims.height,
            maxHeight:       dims.height,
            maxShadowOpacity: 0.5,
            showCover:       false,
            mobileScrollSupport: true,
            usePortrait:     useSinglePage,
            startPage:       options.startPage - 1, // 0-based
            drawShadow:      true,
            flippingTime:    600,
            useMouseEvents:  true,
            swipeDistance:   30,
            clickEventForward: true,
            autoSize:        false,
        });

        // Load pages from HTML elements.
        pageFlip.loadFromHTML(pages);

        // Bind events.
        pageFlip.on('flip', async (e) => {
            const pageNo = e.data + 1; // Convert to 1-based.
            if (onFlipCallback) {
                onFlipCallback(pageNo);
            }
            // Render current and buffer pages.
            await renderPageRange(e.data, RENDER_BUFFER);
        });

        pageFlip.on('changeState', (e) => {
            // Handle state changes if needed.
        });

        // Render initial pages.
        await renderPageRange(options.startPage - 1, RENDER_BUFFER);

        if (options.onReady) {
            options.onReady();
        }

        // Swipe support for mobile (touch events).
        setupTouchNavigation(container);

        return pageFlip;
    }

    /**
     * Create placeholder canvas elements for all pages.
     *
     * @param {number} total Total number of pages
     * @return {HTMLElement[]} Array of page elements
     */
    function createPageElements(total) {
        const pages = [];
        for (let i = 1; i <= total; i++) {
            const div = document.createElement('div');
            div.className = 'leafr-page';
            div.dataset.pageNum = i;
            div.setAttribute('aria-label', 'Seite ' + i);

            const canvas = document.createElement('canvas');
            canvas.className = 'leafr-page-canvas';
            canvas.id = 'leafr-canvas-' + i;
            div.appendChild(canvas);

            pages.push(div);
        }
        return pages;
    }

    /**
     * Render a range of PDF pages to canvases.
     *
     * @param {number} centerIdx 0-based center page index
     * @param {number} buffer Number of pages to render on each side
     */
    async function renderPageRange(centerIdx, buffer) {
        if (!pdfDoc) {
            return;
        }

        const start = Math.max(0, centerIdx - buffer);
        const end   = Math.min(pdfDoc.numPages - 1, centerIdx + buffer + 1);

        for (let i = start; i <= end; i++) {
            if (!renderedPages.has(i)) {
                renderedPages.add(i);
                await renderSinglePage(i + 1); // Convert to 1-based.
            }
        }
    }

    /**
     * Render a single PDF page to its canvas.
     *
     * @param {number} pageNum 1-based page number
     */
    async function renderSinglePage(pageNum) {
        const canvas = document.getElementById('leafr-canvas-' + pageNum);
        if (!canvas || !pdfDoc) {
            return;
        }

        try {
            const page = await pdfDoc.getPage(pageNum);
            await PdfLoader.renderPage(page, canvas, RENDER_SCALE);
        } catch (e) {
            // Page render failed - leave blank.
            console.warn('Leafr: Failed to render page ' + pageNum, e);
        }
    }

    /**
     * Navigate to a specific page.
     *
     * @param {PageFlip} flipInstance The PageFlip instance
     * @param {number} pageNum 1-based page number
     */
    function goToPage(flipInstance, pageNum) {
        if (!flipInstance) {
            return;
        }
        flipInstance.turnToPage(pageNum - 1); // Convert to 0-based.
    }

    /**
     * Setup touch/swipe navigation for mobile.
     *
     * @param {HTMLElement} container The flipbook container
     */
    function setupTouchNavigation(container) {
        let touchStartX = 0;
        let touchStartY = 0;

        container.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        }, {passive: true});

        container.addEventListener('touchend', (e) => {
            const dx = e.changedTouches[0].clientX - touchStartX;
            const dy = e.changedTouches[0].clientY - touchStartY;

            // Only horizontal swipes (ignore vertical scroll).
            if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 30) {
                if (pageFlip) {
                    if (dx < 0) {
                        pageFlip.flipNext();
                    } else {
                        pageFlip.flipPrev();
                    }
                }
            }
        }, {passive: true});
    }

    return {
        init,
        goToPage,
    };
});

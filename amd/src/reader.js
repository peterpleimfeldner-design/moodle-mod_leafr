// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Main Reader AMD module for mod_leafr.
 * Orchestrates PDF loading, flipbook rendering, toolbar and completion tracking.
 *
 * @module     mod_leafr/reader
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
    'mod_leafr/pdfloader',
    'mod_leafr/flipbook',
    'mod_leafr/toolbar',
    'mod_leafr/completion',
    'mod_leafr/bookmarks'
], function(PdfLoader, Flipbook, Toolbar, Completion, Bookmarks) {

    'use strict';

    /** @type {Object} Current configuration */
    let cfg = {};

    /** @type {number} Total pages in the PDF */
    let totalPages = 0;

    /** @type {number} Current visible page */
    let currentPage = 1;

    /** @type {Object} Flipbook instance */
    let flipbookInstance = null;

    /** @type {boolean} Resume toast already handled */
    let toastHandled = false;

    /**
     * Initialize the Leafr reader.
     *
     * @param {Object} config Configuration object from PHP
     */
    async function init(config) {
        cfg = config;

        const container = document.getElementById('leafr-reader-container');
        if (!container) {
            return;
        }

        // Measure and apply Moodle navbar height as CSS variable.
        // This allows the reader to fill exactly the remaining viewport.
        measureNavHeight();

        // Accessibility: simple view detection.
        const preferReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (cfg.config.simpleView || preferReducedMotion) {
            await initSimpleView(config);
        } else {
            await initFlipbook(config);
        }

        // Resume reading toast.
        if (cfg.savedPage > 1) {
            scheduleResumeToast(cfg.savedPage, cfg.strings);
        }

        // Global keyboard navigation.
        setupKeyboardNavigation();
    }

    /**
     * Initialize the flipbook (animated) view.
     *
     * @param {Object} config
     */
    async function initFlipbook(config) {
        try {
            showLoading(true);

            // Load PDF via PDF.js.
            const pdfDoc = await PdfLoader.load(config.fileurl);
            totalPages = pdfDoc.numPages;

            // Update toolbar with total pages.
            Toolbar.init({
                totalPages,
                startPage:   config.startPage,
                downloadAllowed: config.config.downloadAllowed,
                fileurl:     config.fileurl,
                strings:     config.strings,
                onPageChange:   goToPage,
                onFullscreen:   toggleFullscreen,
                onToggleSimple: switchToSimpleView,
                onToggleToc:    toggleToc,
            });

            // Initialize StPageFlip.
            flipbookInstance = await Flipbook.init({
                container:   document.getElementById('leafr-flipbook'),
                pdfDoc,
                startPage:   config.startPage,
                totalPages,
                strings:     config.strings,
                onFlip:      onPageFlipped,
                onReady:     onFlipbookReady,
            });

            // Initialize completion tracking.
            Completion.init({
                cmid:             config.cmid,
                totalPages,
                config:           config.config,
                wwwroot:          config.wwwroot,
            });

            // Initialize TOC if enabled.
            if (config.config.showToc) {
                await initToc(pdfDoc);
            }

            // Initialize bookmarks if Pro available.
            if (config.config.proAvailable) {
                Bookmarks.init({
                    cmid:    config.cmid,
                    strings: config.strings,
                    onNavigate: goToPage,
                });
            }

            showLoading(false);

        } catch (err) {
            showError(config.strings.errordocument + ' (' + err.message + ')', config.fileurl, config.config.downloadAllowed);
        }
    }

    /**
     * Initialize the simple (non-animated, accessible) view.
     *
     * @param {Object} config
     */
    async function initSimpleView(config) {
        try {
            showLoading(true);

            const pdfDoc = await PdfLoader.load(config.fileurl);
            totalPages = pdfDoc.numPages;

            const container = document.getElementById('leafr-simple-view');
            if (container) {
                container.style.display = 'block';
            }
            const flipbookEl = document.getElementById('leafr-flipbook');
            if (flipbookEl) {
                flipbookEl.style.display = 'none';
            }

            // Render all pages as stacked canvases (scrollable).
            for (let pageNum = 1; pageNum <= totalPages; pageNum++) {
                const pageEl = await renderSimplePage(pdfDoc, pageNum, totalPages);
                if (container) {
                    container.appendChild(pageEl);
                }
            }

            // Toolbar with simplified controls.
            Toolbar.init({
                totalPages,
                startPage:       config.startPage,
                downloadAllowed: config.config.downloadAllowed,
                fileurl:         config.fileurl,
                strings:         config.strings,
                simpleView:      true,
                onPageChange:    scrollToPage,
                onFullscreen:    null,
                onToggleSimple:  null,
                onToggleToc:     config.config.showToc ? toggleToc : null,
            });

            // Completion via IntersectionObserver.
            Completion.init({
                cmid:             config.cmid,
                totalPages,
                config:           config.config,
                wwwroot:          config.wwwroot,
                simpleView:       true,
            });

            showLoading(false);

            // Scroll to start page.
            scrollToPage(config.startPage);

        } catch (err) {
            showError(config.strings.errordocument + ' (' + err.message + ')', config.fileurl, config.config.downloadAllowed);
        }
    }

    /**
     * Render a single page in simple view.
     *
     * @param {Object} pdfDoc PDF document
     * @param {number} pageNum Page number (1-based)
     * @param {number} totalPagesCount Total number of pages
     * @return {Promise<HTMLElement>}
     */
    async function renderSimplePage(pdfDoc, pageNum, totalPagesCount) {
        const page = await pdfDoc.getPage(pageNum);
        const viewport = page.getViewport({scale: 1.5});

        const wrapper = document.createElement('div');
        wrapper.className = 'leafr-simple-page';
        wrapper.id = 'leafr-page-' + pageNum;
        wrapper.setAttribute('role', 'region');
        wrapper.setAttribute('aria-label', 'Seite ' + pageNum + ' von ' + totalPagesCount);
        wrapper.setAttribute('tabindex', '-1');

        const canvas = document.createElement('canvas');
        canvas.width  = viewport.width;
        canvas.height = viewport.height;

        const ctx = canvas.getContext('2d');
        await page.render({canvasContext: ctx, viewport}).promise;

        // Add text layer for accessibility and selection.
        const textContent = await page.getTextContent();
        const textLayer = document.createElement('div');
        textLayer.className = 'leafr-text-layer';
        textLayer.setAttribute('aria-hidden', 'true');

        const pageLabel = document.createElement('span');
        pageLabel.className = 'sr-only';
        pageLabel.textContent = 'Seite ' + pageNum;
        textLayer.appendChild(pageLabel);

        wrapper.appendChild(canvas);
        wrapper.appendChild(textLayer);

        return wrapper;
    }

    /**
     * Called when the flipbook flips to a new page.
     *
     * @param {number} pageNum The new page number
     */
    function onPageFlipped(pageNum) {
        currentPage = pageNum;
        Toolbar.updatePage(pageNum);

        // Track seen pages (current + adjacent visible page for spreads).
        Completion.trackPage(pageNum);
        if (pageNum < totalPages) {
            Completion.trackPage(pageNum + 1);
        }

        // Save reading position via AJAX (debounced).
        Completion.savePosition(cfg.cmid, pageNum);

        // ARIA announcement.
        announcePageChange(pageNum, totalPages, cfg.strings);
    }

    /**
     * Called when flipbook is fully ready.
     */
    function onFlipbookReady() {
        // nothing extra needed
    }

    /**
     * Navigate to a specific page.
     *
     * @param {number} pageNum Target page number
     */
    function goToPage(pageNum) {
        const target = Math.max(1, Math.min(pageNum, totalPages));
        if (flipbookInstance) {
            Flipbook.goToPage(flipbookInstance, target);
        }
    }

    /**
     * Scroll to a page in simple view.
     *
     * @param {number} pageNum Target page number
     */
    function scrollToPage(pageNum) {
        const el = document.getElementById('leafr-page-' + pageNum);
        if (el) {
            el.scrollIntoView({behavior: 'smooth', block: 'start'});
            el.focus();
        }
    }

    /**
     * Toggle fullscreen mode.
     */
    function toggleFullscreen() {
        const readerEl = document.getElementById('leafr-reader-container');
        if (!document.fullscreenElement) {
            readerEl.requestFullscreen().catch(() => {});
        } else {
            document.exitFullscreen().catch(() => {});
        }
    }

    /**
     * Switch to simple view.
     */
    function switchToSimpleView() {
        // Save preference and reload.
        fetch(M.cfg.wwwroot + '/lib/ajax/service.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify([{
                methodname: 'core_user_update_user_preferences',
                args: {preferences: [{type: 'leafr_simpleview_' + cfg.cmid, value: '1'}]}
            }])
        }).then(() => window.location.reload());
    }

    /**
     * Toggle the TOC panel.
     */
    function toggleToc() {
        const panel = document.getElementById('leafr-toc-panel');
        if (!panel) {
            return;
        }
        const isOpen = panel.classList.contains('is-open');
        panel.classList.toggle('is-open', !isOpen);
        panel.setAttribute('aria-hidden', isOpen ? 'true' : 'false');

        if (!isOpen) {
            // Focus first TOC item.
            const first = panel.querySelector('.leafr-toc-item');
            if (first) {
                first.focus();
            }
        }
    }

    /**
     * Initialize the TOC from PDF bookmarks.
     *
     * @param {Object} pdfDoc
     */
    async function initToc(pdfDoc) {
        const panel = document.getElementById('leafr-toc-panel');
        if (!panel) {
            return;
        }

        const outline = await pdfDoc.getOutline();
        const list = panel.querySelector('.leafr-toc-list');
        if (!list) {
            return;
        }

        if (!outline || outline.length === 0) {
            list.innerHTML = '<li class="leafr-toc-empty">' + cfg.strings.toc_empty + '</li>';
            return;
        }

        list.innerHTML = '';
        for (const item of outline) {
            const li = document.createElement('li');
            li.className = 'leafr-toc-item';
            li.setAttribute('tabindex', '0');
            li.setAttribute('role', 'button');

            if (item.dest) {
                try {
                    const ref = await pdfDoc.getDestination(item.dest);
                    const pageIndex = await pdfDoc.getPageIndex(ref[0]);
                    li.dataset.page = pageIndex + 1;
                    li.textContent = item.title;
                    li.addEventListener('click', () => goToPage(parseInt(li.dataset.page)));
                    li.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' || e.key === ' ') {
                            goToPage(parseInt(li.dataset.page));
                        }
                    });
                } catch (e) {
                    li.textContent = item.title;
                }
            } else {
                li.textContent = item.title;
            }

            list.appendChild(li);
        }
    }

    /**
     * Show/hide loading skeleton.
     *
     * @param {boolean} loading
     */
    function showLoading(loading) {
        const skeleton = document.getElementById('leafr-skeleton');
        const flipbook = document.getElementById('leafr-flipbook');
        if (skeleton) {
            skeleton.style.display = loading ? 'flex' : 'none';
        }
        if (flipbook) {
            flipbook.style.visibility = loading ? 'hidden' : 'visible';
        }
    }

    /**
     * Show error state.
     *
     * @param {string} message Error message
     * @param {string} fileurl PDF file URL for fallback download
     * @param {boolean} downloadAllowed Whether download is allowed
     */
    function showError(message, fileurl, downloadAllowed) {
        showLoading(false);
        const errorEl = document.getElementById('leafr-error');
        if (errorEl) {
            errorEl.style.display = 'flex';
            const msgEl = errorEl.querySelector('.leafr-error-message');
            if (msgEl) {
                msgEl.textContent = message;
            }
            if (downloadAllowed && fileurl) {
                const dl = errorEl.querySelector('.leafr-error-download');
                if (dl) {
                    dl.style.display = 'inline-block';
                    dl.href = fileurl + '?forcedownload=1';
                }
            }
        }
    }

    /**
     * Show the "resume reading" toast notification.
     *
     * @param {number} savedPage The saved page number
     * @param {Object} strings Language strings
     */
    function scheduleResumeToast(savedPage, strings) {
        if (toastHandled) {
            return;
        }

        setTimeout(() => {
            const toast = document.getElementById('leafr-resume-toast');
            if (!toast || toastHandled) {
                return;
            }

            const pageLabel = toast.querySelector('.leafr-toast-page');
            if (pageLabel) {
                pageLabel.textContent = strings.pageof.replace('{page}', savedPage).replace('{total}', totalPages);
            }

            toast.style.display = 'flex';
            toast.setAttribute('aria-hidden', 'false');

            // Auto-dismiss after 8 seconds.
            const timer = setTimeout(() => dismissToast(1), 8000);

            const btnContinue = toast.querySelector('.leafr-toast-continue');
            const btnRestart  = toast.querySelector('.leafr-toast-restart');

            if (btnContinue) {
                btnContinue.addEventListener('click', () => {
                    clearTimeout(timer);
                    goToPage(savedPage);
                    dismissToast();
                });
            }
            if (btnRestart) {
                btnRestart.addEventListener('click', () => {
                    clearTimeout(timer);
                    dismissToast();
                    goToPage(1);
                });
            }

            // ESC key dismisses toast.
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') {
                    clearTimeout(timer);
                    dismissToast();
                    document.removeEventListener('keydown', escHandler);
                }
            });

        }, 800);
    }

    /**
     * Dismiss the resume toast.
     *
     * @param {number} goPage Optional: page to navigate to after dismiss
     */
    function dismissToast(goPage) {
        toastHandled = true;
        const toast = document.getElementById('leafr-resume-toast');
        if (toast) {
            toast.style.display = 'none';
            toast.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Set up global keyboard navigation.
     */
    function setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Only handle when flipbook area has focus or no input is focused.
            const tag = document.activeElement?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA') {
                return;
            }

            switch (e.key) {
                case 'ArrowLeft':
                case 'PageUp':
                    e.preventDefault();
                    goToPage(currentPage - 2); // Move back by 2 (spread).
                    break;
                case 'ArrowRight':
                case 'PageDown':
                    e.preventDefault();
                    goToPage(currentPage + 2); // Move forward by 2 (spread).
                    break;
                case 'Home':
                    e.preventDefault();
                    goToPage(1);
                    break;
                case 'End':
                    e.preventDefault();
                    goToPage(totalPages);
                    break;
                case 'Escape':
                    if (document.fullscreenElement) {
                        document.exitFullscreen();
                    }
                    break;
                case 't':
                case 'T':
                    toggleToc();
                    break;
                case 'f':
                case 'F':
                    toggleFullscreen();
                    break;
                case '?':
                    showHelp();
                    break;
            }
        });
    }

    /**
     * Show keyboard shortcuts help overlay.
     */
    function showHelp() {
        const help = document.getElementById('leafr-help-overlay');
        if (help) {
            help.style.display = help.style.display === 'none' ? 'flex' : 'none';
        }
    }

    /**
     * Measure the Moodle page header/navbar height and set --leafr-nav-height.
     * Checks common selectors used across Moodle themes.
     */
    function measureNavHeight() {
        // Try common Moodle navbar selectors (Boost, Classic, Moove, etc.).
        const headerEl = document.querySelector(
            '#page-header, .navbar.fixed-top, .navbar, header[role="banner"], #header'
        );
        const offsetTop = document.getElementById('leafr-reader-container')
            ? document.getElementById('leafr-reader-container').getBoundingClientRect().top
            : (headerEl ? headerEl.offsetHeight : 64);
        const navH = Math.max(offsetTop, 56); // at least 56px
        document.documentElement.style.setProperty('--leafr-nav-height', navH + 'px');
    }

    /**
     * Announce page change to screenreaders.
     *
     * @param {number} page Current page
     * @param {number} total Total pages
     * @param {Object} strings Language strings
     */
    function announcePageChange(page, total, strings) {
        const liveRegion = document.getElementById('leafr-aria-live');
        if (liveRegion) {
            liveRegion.textContent = strings.pageof
                .replace('{page}', page)
                .replace('{total}', total);
        }
    }

    return {init};
});

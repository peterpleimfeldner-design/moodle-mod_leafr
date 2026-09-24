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
 * Simple view: all pages below each other, scrollable, without animation.
 *
 * Pages are rendered lazily when they come near the visible area and released again when far away,
 * so long documents do not exhaust the memory of the browser. Each page carries its text as a
 * text alternative for screen readers.
 *
 * @module     mod_leafr/scrollview
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getPageSize, getPageText, releaseCanvas, renderPage} from 'mod_leafr/pdf';

/** Maximum width of a page at zoom 1 in CSS pixels. */
const MAX_PAGE_WIDTH = 960;

/** Space around the pages in CSS pixels. */
const PADDING = 16;

/** Pages further away than this from the current page are released from memory. */
const KEEP_RENDERED = 5;

export default class ScrollView {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {HTMLElement} options.stage Scrollable element that contains the view
     * @param {HTMLElement} options.host Element the view is rendered into
     * @param {Object} options.pdfDoc PDF.js document proxy
     * @param {number} options.startPage First page to show
     * @param {Object} options.strings Language strings
     * @param {Function} options.onPageChange Called with (currentPage, visiblePages)
     */
    constructor(options) {
        this.stage = options.stage;
        this.host = options.host;
        this.pdfDoc = options.pdfDoc;
        this.total = options.pdfDoc.numPages;
        this.strings = options.strings;
        this.onPageChange = options.onPageChange;
        this.page = Math.min(Math.max(1, options.startPage), this.total);
        this.zoom = 1;
        this.pages = [];
        this.rendered = new Map();
        this.visibility = new Map();
        this.textLoaded = new Set();
        this.near = new Set();
        this.resizeTimer = null;
        this.scrollTimer = null;
        this.destroyed = false;
        this.handleResize = this.handleResize.bind(this);
    }

    /**
     * Builds the view.
     *
     * @returns {Promise<void>}
     */
    async init() {
        const firstSize = await getPageSize(this.pdfDoc, 1);
        this.ratio = firstSize.height / firstSize.width;

        this.list = document.createElement('div');
        this.list.className = 'leafr-scroll';
        for (let i = 1; i <= this.total; i++) {
            const pageEl = document.createElement('div');
            pageEl.className = 'leafr-scroll-page';
            pageEl.setAttribute('role', 'group');
            pageEl.dataset.page = i;
            pageEl.setAttribute('aria-label', this.strings.pagelabel.replaceAll('{$a}', i));
            pageEl.style.aspectRatio = '1 / ' + this.ratio;
            const canvas = document.createElement('canvas');
            canvas.className = 'leafr-page-canvas';
            canvas.setAttribute('aria-hidden', 'true');
            const text = document.createElement('div');
            text.className = 'sr-only leafr-page-text';
            pageEl.appendChild(canvas);
            pageEl.appendChild(text);
            this.list.appendChild(pageEl);
            this.pages.push({element: pageEl, canvas, text});
        }
        this.host.appendChild(this.list);
        this.applyZoom();

        // Render pages that come within one screen height of the visible area.
        this.renderObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const pageNum = parseInt(entry.target.dataset.page, 10);
                if (entry.isIntersecting) {
                    this.near.add(pageNum);
                    this.renderPageLazy(pageNum);
                } else {
                    this.near.delete(pageNum);
                }
            });
        }, {root: this.stage, rootMargin: '100% 0px'});

        // Track how much of each page is visible to determine the current and the seen pages.
        this.visibilityObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                const pageNum = parseInt(entry.target.dataset.page, 10);
                const rootHeight = entry.rootBounds ? entry.rootBounds.height : this.stage.clientHeight;
                const fillsView = entry.intersectionRect.height >= rootHeight * 0.5;
                this.visibility.set(pageNum, {
                    ratio: entry.intersectionRatio,
                    height: entry.intersectionRect.height,
                    seen: entry.intersectionRatio >= 0.5 || fillsView,
                });
            });
            clearTimeout(this.scrollTimer);
            this.scrollTimer = setTimeout(() => this.updateCurrentPage(), 80);
        }, {root: this.stage, threshold: [0, 0.25, 0.5, 0.75, 1]});

        this.pages.forEach(({element}) => {
            this.renderObserver.observe(element);
            this.visibilityObserver.observe(element);
        });

        this.resizeObserver = new ResizeObserver(this.handleResize);
        this.resizeObserver.observe(this.stage);

        this.goTo(this.page, true);
    }

    /**
     * Determines the current page from the visibility of the pages.
     */
    updateCurrentPage() {
        let current = 0;
        let bestHeight = 0;
        const seen = [];
        this.visibility.forEach((info, pageNum) => {
            if (info.height > bestHeight) {
                bestHeight = info.height;
                current = pageNum;
            }
            if (info.seen) {
                seen.push(pageNum);
            }
        });
        if (!current) {
            return;
        }
        this.page = current;
        this.releaseFarPages();
        this.onPageChange(current, seen.sort((a, b) => a - b));
    }

    /**
     * Returns the CSS width of a page.
     *
     * @returns {number}
     */
    getPageWidth() {
        const available = Math.max(this.stage.clientWidth - 2 * PADDING, 120);
        return Math.floor(Math.min(available, MAX_PAGE_WIDTH) * this.zoom);
    }

    /**
     * Renders a page and loads its text alternative.
     *
     * @param {number} pageNum 1-based page number
     */
    renderPageLazy(pageNum) {
        const cssWidth = this.getPageWidth();
        const page = this.pages[pageNum - 1];
        if ((this.rendered.get(pageNum) || 0) < cssWidth) {
            this.rendered.set(pageNum, cssWidth);
            renderPage(this.pdfDoc, pageNum, page.canvas, cssWidth).then((done) => {
                if (done && page.canvas.width) {
                    // Pages may differ in size; use the real aspect ratio once known.
                    page.element.style.aspectRatio = page.canvas.width + ' / ' + page.canvas.height;
                }
                return done;
            }).catch(() => {
                this.rendered.delete(pageNum);
            });
        }
        if (!this.textLoaded.has(pageNum)) {
            this.textLoaded.add(pageNum);
            getPageText(this.pdfDoc, pageNum).then((text) => {
                page.text.textContent = text;
                return text;
            }).catch(() => {
                this.textLoaded.delete(pageNum);
            });
        }
    }

    /**
     * Releases the canvases of pages far away from the current page.
     */
    releaseFarPages() {
        this.rendered.forEach((width, pageNum) => {
            if (Math.abs(pageNum - this.page) > KEEP_RENDERED && !this.near.has(pageNum)) {
                releaseCanvas(this.pages[pageNum - 1].canvas);
                this.rendered.delete(pageNum);
            }
        });
    }

    /**
     * Scrolls to a page.
     *
     * @param {number} pageNum 1-based page number
     * @param {boolean} instant Jump without smooth scrolling
     */
    goTo(pageNum, instant = false) {
        const target = Math.min(Math.max(1, pageNum), this.total);
        const element = this.pages[target - 1].element;
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.stage.scrollTo({
            top: element.offsetTop - PADDING,
            left: this.stage.scrollLeft,
            behavior: (instant || reduceMotion) ? 'auto' : 'smooth',
        });
        this.page = target;
    }

    /**
     * Goes to the next page.
     */
    next() {
        this.goTo(this.page + 1);
    }

    /**
     * Goes to the previous page.
     */
    prev() {
        this.goTo(this.page - 1);
    }

    /**
     * Returns the pages currently considered visible.
     *
     * @returns {number[]}
     */
    getVisiblePages() {
        return [this.page];
    }

    /**
     * Sets the zoom factor.
     *
     * @param {number} zoom Zoom factor, 1 = fit to the width
     */
    setZoom(zoom) {
        const current = this.page;
        this.zoom = zoom;
        this.applyZoom();
        this.goTo(current, true);
        this.rerenderVisible();
    }

    /**
     * Applies the page width for the current zoom factor.
     */
    applyZoom() {
        this.list.style.setProperty('--leafr-page-width', this.getPageWidth() + 'px');
    }

    /**
     * Renders the pages around the current page again, e.g. after zooming.
     */
    rerenderVisible() {
        for (let p = Math.max(1, this.page - 1); p <= Math.min(this.total, this.page + 2); p++) {
            this.renderPageLazy(p);
        }
    }

    /**
     * Adapts the page width when the available space changes.
     */
    handleResize() {
        clearTimeout(this.resizeTimer);
        this.resizeTimer = setTimeout(() => {
            if (!this.destroyed) {
                this.applyZoom();
                this.rerenderVisible();
            }
        }, 200);
    }

    /**
     * Destroys the view.
     */
    destroy() {
        this.destroyed = true;
        clearTimeout(this.resizeTimer);
        clearTimeout(this.scrollTimer);
        [this.renderObserver, this.visibilityObserver, this.resizeObserver].forEach((observer) => {
            if (observer) {
                observer.disconnect();
            }
        });
        this.pages.forEach(({canvas}) => releaseCanvas(canvas));
        this.pages = [];
        this.rendered.clear();
        this.host.innerHTML = '';
    }
}

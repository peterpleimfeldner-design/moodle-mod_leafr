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
 * Book view with page-turning animation, based on StPageFlip.
 *
 * @module     mod_leafr/flipbook
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {PageFlip} from 'mod_leafr/vendor-stpageflip';
import {getPageSize, releaseCanvas, renderPage} from 'mod_leafr/pdf';

/** Pages rendered before and after the visible pages. */
const RENDER_AHEAD = 2;

/** Pages further away than this from the visible pages are released from memory. */
const KEEP_RENDERED = 6;

/** Viewports narrower than this show one page at a time. */
const SPREAD_MIN_WIDTH = 768;

/** Space around the book in CSS pixels. */
const PADDING = 16;

export default class FlipbookView {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {HTMLElement} options.stage Scrollable element that contains the view
     * @param {HTMLElement} options.host Element the view is rendered into
     * @param {Object} options.pdfDoc PDF.js document proxy
     * @param {number} options.startPage First page to show
     * @param {Object} options.strings Language strings
     * @param {Function} options.onPageChange Called with (firstVisiblePage, visiblePages)
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
        this.pageFlip = null;
        this.canvases = [];
        this.rendered = new Map();
        this.layout = null;
        this.resizeTimer = null;
        this.zoomTimer = null;
        this.pan = null;
        this.destroyed = false;

        this.handleResize = this.handleResize.bind(this);
        this.blockFlipGesture = this.blockFlipGesture.bind(this);
        this.handlePanMove = this.handlePanMove.bind(this);
        this.handlePanEnd = this.handlePanEnd.bind(this);
    }

    /**
     * Builds the view.
     *
     * @returns {Promise<void>}
     */
    async init() {
        this.pageSize = await getPageSize(this.pdfDoc, 1);
        this.build();
        this.resizeObserver = new ResizeObserver(this.handleResize);
        this.resizeObserver.observe(this.stage);
    }

    /**
     * Computes the size of the pages for the available space.
     *
     * @returns {{single: boolean, pageWidth: number, pageHeight: number}}
     */
    computeLayout() {
        const ratio = this.pageSize.width / this.pageSize.height;
        const availableWidth = Math.max(this.stage.clientWidth - 2 * PADDING, 120);
        const availableHeight = Math.max(this.stage.clientHeight - 2 * PADDING, 160);
        const single = this.stage.clientWidth < SPREAD_MIN_WIDTH || ratio > 1 || this.total === 1;
        const columns = single ? 1 : 2;

        let pageHeight = availableHeight;
        let pageWidth = pageHeight * ratio;
        if (pageWidth * columns > availableWidth) {
            pageWidth = availableWidth / columns;
            pageHeight = pageWidth / ratio;
        }
        return {single, pageWidth: Math.floor(pageWidth), pageHeight: Math.floor(pageHeight)};
    }

    /**
     * (Re)creates the StPageFlip instance for the current layout.
     */
    build() {
        this.teardownBook();
        this.layout = this.computeLayout();
        const {single, pageWidth, pageHeight} = this.layout;

        this.zoomBox = document.createElement('div');
        this.zoomBox.className = 'leafr-zoombox';
        this.book = document.createElement('div');
        this.book.className = 'leafr-book';
        this.zoomBox.appendChild(this.book);
        this.host.appendChild(this.zoomBox);

        const pages = [];
        this.canvases = [];
        for (let i = 1; i <= this.total; i++) {
            const pageEl = document.createElement('div');
            pageEl.className = 'leafr-page';
            pageEl.dataset.page = i;
            const canvas = document.createElement('canvas');
            canvas.className = 'leafr-page-canvas';
            canvas.setAttribute('role', 'img');
            canvas.setAttribute('aria-label', this.strings.pagelabel.replace('{$a}', i));
            pageEl.appendChild(canvas);
            pages.push(pageEl);
            this.canvases.push(canvas);
        }
        this.rendered.clear();

        this.pageFlip = new PageFlip(this.book, {
            width: pageWidth,
            height: pageHeight,
            size: 'fixed',
            autoSize: false,
            usePortrait: single,
            showCover: false,
            startPage: this.page - 1,
            drawShadow: true,
            maxShadowOpacity: 0.35,
            flippingTime: 700,
            mobileScrollSupport: false,
            swipeDistance: 40,
            showPageCorners: true,
            disableFlipByClick: false,
            clickEventForward: true,
            useMouseEvents: true,
        });
        this.pageFlip.loadFromHTML(pages);
        this.pageFlip.on('flip', (event) => this.handleFlip(event.data));

        // Block the flip gestures of StPageFlip while the page is zoomed; dragging pans instead.
        this.zoomBox.addEventListener('mousedown', this.blockFlipGesture, true);
        this.zoomBox.addEventListener('touchstart', this.blockFlipGesture, true);

        this.applyZoom();
        this.handleFlip(this.pageFlip.getCurrentPageIndex());
    }

    /**
     * Returns the 1-based pages currently visible.
     *
     * @returns {number[]}
     */
    getVisiblePages() {
        if (!this.pageFlip) {
            return [this.page];
        }
        const index = this.pageFlip.getCurrentPageIndex();
        const pages = [index + 1];
        if (this.pageFlip.getOrientation() === 'landscape' && index + 2 <= this.total) {
            pages.push(index + 2);
        }
        return pages;
    }

    /**
     * Called by StPageFlip whenever the visible pages change.
     *
     * @param {number} index 0-based index of the first visible page
     */
    handleFlip(index) {
        this.page = index + 1;
        const visible = this.getVisiblePages();
        this.renderAround(visible);
        this.onPageChange(this.page, visible);
    }

    /**
     * Renders the visible pages and a few pages around them, and releases pages far away.
     *
     * @param {number[]} visible Visible pages
     */
    renderAround(visible) {
        const first = visible[0];
        const last = visible[visible.length - 1];
        const cssWidth = this.layout.pageWidth * Math.max(1, this.zoom);

        // Visible pages first, then the neighbours.
        const order = [...visible];
        for (let i = 1; i <= RENDER_AHEAD * 2; i++) {
            order.push(last + i, first - i);
        }
        order.filter((p) => p >= 1 && p <= this.total).forEach((pageNum) => {
            const renderedWidth = this.rendered.get(pageNum) || 0;
            if (renderedWidth < cssWidth) {
                this.rendered.set(pageNum, cssWidth);
                renderPage(this.pdfDoc, pageNum, this.canvases[pageNum - 1], cssWidth).catch(() => {
                    this.rendered.delete(pageNum);
                });
            }
        });

        this.rendered.forEach((width, pageNum) => {
            if (pageNum < first - KEEP_RENDERED || pageNum > last + KEEP_RENDERED) {
                releaseCanvas(this.canvases[pageNum - 1]);
                this.rendered.delete(pageNum);
            }
        });
    }

    /**
     * Shows a page.
     *
     * @param {number} pageNum 1-based page number
     */
    goTo(pageNum) {
        if (!this.pageFlip) {
            return;
        }
        const target = Math.min(Math.max(1, pageNum), this.total);
        const visible = this.getVisiblePages();
        if (visible.includes(target)) {
            return;
        }
        // StPageFlip's animated flip() pairs pages internally for its page-turning animation and
        // can overshoot by one page for some short jumps; turnToPage() always lands exactly.
        this.pageFlip.turnToPage(target - 1);
    }

    /**
     * Turns to the next page or spread.
     */
    next() {
        if (this.pageFlip) {
            if (this.zoom === 1) {
                this.pageFlip.flipNext();
            } else {
                this.pageFlip.turnToNextPage();
            }
        }
    }

    /**
     * Turns to the previous page or spread.
     */
    prev() {
        if (this.pageFlip) {
            if (this.zoom === 1) {
                this.pageFlip.flipPrev();
            } else {
                this.pageFlip.turnToPrevPage();
            }
        }
    }

    /**
     * Sets the zoom factor.
     *
     * @param {number} zoom Zoom factor, 1 = fit to the available space
     */
    setZoom(zoom) {
        this.zoom = zoom;
        this.applyZoom();
        clearTimeout(this.zoomTimer);
        this.zoomTimer = setTimeout(() => this.renderAround(this.getVisiblePages()), 200);
    }

    /**
     * Applies the zoom factor to the book element.
     */
    applyZoom() {
        const {single, pageWidth, pageHeight} = this.layout;
        const bookWidth = pageWidth * (single ? 1 : 2);
        this.zoomBox.style.width = Math.round(bookWidth * this.zoom) + 'px';
        this.zoomBox.style.height = Math.round(pageHeight * this.zoom) + 'px';
        this.book.style.width = bookWidth + 'px';
        this.book.style.height = pageHeight + 'px';
        this.book.style.transform = this.zoom === 1 ? '' : 'scale(' + this.zoom + ')';
        this.zoomBox.classList.toggle('is-zoomed', this.zoom !== 1);
    }

    /**
     * Prevents StPageFlip from starting a page turn while zoomed and starts panning instead.
     *
     * @param {Event} event Mouse or touch event
     */
    blockFlipGesture(event) {
        if (this.zoom === 1) {
            return;
        }
        event.stopPropagation();
        if (event.type === 'mousedown' && event.button === 0) {
            event.preventDefault();
            this.pan = {x: event.clientX, y: event.clientY, left: this.stage.scrollLeft, top: this.stage.scrollTop};
            this.zoomBox.classList.add('is-panning');
            window.addEventListener('mousemove', this.handlePanMove);
            window.addEventListener('mouseup', this.handlePanEnd);
        }
    }

    /**
     * Pans the zoomed page while the mouse is dragged.
     *
     * @param {MouseEvent} event Mouse event
     */
    handlePanMove(event) {
        if (this.pan) {
            this.stage.scrollLeft = this.pan.left - (event.clientX - this.pan.x);
            this.stage.scrollTop = this.pan.top - (event.clientY - this.pan.y);
        }
    }

    /**
     * Ends panning.
     */
    handlePanEnd() {
        this.pan = null;
        if (this.zoomBox) {
            this.zoomBox.classList.remove('is-panning');
        }
        window.removeEventListener('mousemove', this.handlePanMove);
        window.removeEventListener('mouseup', this.handlePanEnd);
    }

    /**
     * Rebuilds the book when the available space changes noticeably.
     */
    handleResize() {
        clearTimeout(this.resizeTimer);
        this.resizeTimer = setTimeout(() => {
            if (this.destroyed || !this.layout) {
                return;
            }
            const layout = this.computeLayout();
            if (layout.single !== this.layout.single || Math.abs(layout.pageHeight - this.layout.pageHeight) > 8 ||
                    Math.abs(layout.pageWidth - this.layout.pageWidth) > 8) {
                this.build();
            }
        }, 200);
    }

    /**
     * Removes the StPageFlip instance and the page canvases.
     */
    teardownBook() {
        this.handlePanEnd();
        this.canvases.forEach(releaseCanvas);
        this.canvases = [];
        this.rendered.clear();
        if (this.pageFlip) {
            try {
                this.pageFlip.destroy();
            } catch (error) {
                // StPageFlip may throw if the DOM was already removed; nothing left to clean up then.
            }
            this.pageFlip = null;
        }
        this.host.innerHTML = '';
    }

    /**
     * Destroys the view.
     */
    destroy() {
        this.destroyed = true;
        clearTimeout(this.resizeTimer);
        clearTimeout(this.zoomTimer);
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
        }
        this.teardownBook();
    }
}

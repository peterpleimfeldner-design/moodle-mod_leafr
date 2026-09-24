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

/**
 * Fraction of the page height, measured from the top and bottom, within which a page can be
 * dragged to turn it (StPageFlip's own "grab and follow the cursor" behaviour). StPageFlip itself
 * does not confine dragging to the corners - pressing down anywhere on the page and moving the
 * mouse grabs the nearest corner - so this is enforced in {@see FlipbookView#blockFlipGesture}
 * before the gesture ever reaches the library.
 */
const CORNER_ZONE_RATIO = 0.15;

/**
 * Fraction of the book's width, measured from each outer edge, that turns the page on a plain
 * click/tap (no drag), independently of {@see CORNER_ZONE_RATIO} - covers the full height, not
 * just the corners (Peter's feedback, 24.09.2026: the drag "attraction" should stay limited to the
 * corners, but a click anywhere along the left/right edge should still turn the page).
 */
const EDGE_CLICK_RATIO = 0.2;

/** Maximum pointer movement (CSS pixels) between press and release that still counts as a click. */
const CLICK_MOVE_TOLERANCE = 8;

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
     * @param {string} [options.spreadMode] 'auto' (default), 'single' or 'double'
     * @param {string} [options.fitMode] 'page' (default, fits the whole page) or 'width'
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
        this.spreadMode = options.spreadMode || 'auto';
        this.fitMode = options.fitMode || 'page';
        this.zoom = 1;
        this.pageFlip = null;
        this.canvases = [];
        this.rendered = new Map();
        this.layout = null;
        this.resizeTimer = null;
        this.zoomTimer = null;
        this.pan = null;
        this.edgeClickCleanups = [];
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
     * A forced "double" spread is still overridden to a single page below {@see SPREAD_MIN_WIDTH}:
     * two columns squeezed into a phone-sized screen would be unreadable, so the width floor wins
     * even when the person chose "double" on a larger screen before switching devices.
     *
     * @returns {{single: boolean, pageWidth: number, pageHeight: number}}
     */
    computeLayout() {
        const ratio = this.pageSize.width / this.pageSize.height;
        const availableWidth = Math.max(this.stage.clientWidth - 2 * PADDING, 120);
        const availableHeight = Math.max(this.stage.clientHeight - 2 * PADDING, 160);
        const tooNarrowForSpread = this.stage.clientWidth < SPREAD_MIN_WIDTH || ratio > 1 || this.total === 1;
        const single = this.spreadMode === 'single' || tooNarrowForSpread;
        const columns = single ? 1 : 2;

        let pageWidth;
        let pageHeight;
        if (this.fitMode === 'width') {
            pageWidth = availableWidth / columns;
            pageHeight = pageWidth / ratio;
        } else {
            pageHeight = availableHeight;
            pageWidth = pageHeight * ratio;
            if (pageWidth * columns > availableWidth) {
                pageWidth = availableWidth / columns;
                pageHeight = pageWidth / ratio;
            }
        }
        return {single, pageWidth: Math.floor(pageWidth), pageHeight: Math.floor(pageHeight)};
    }

    /**
     * Changes the spread mode ('auto', 'single' or 'double') and rebuilds the book.
     *
     * @param {string} mode New spread mode
     */
    setSpreadMode(mode) {
        this.spreadMode = mode;
        this.build();
    }

    /**
     * Changes the fit mode ('page' or 'width') and rebuilds the book.
     *
     * @param {string} mode New fit mode
     */
    setFitMode(mode) {
        this.fitMode = mode;
        this.build();
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
        // StPageFlip only honours usePortrait (single page) if its wrapper is already narrower
        // than two page widths *at construction time* - it measures this once, not continuously,
        // and applyZoom() below (which sets the same width) runs too late to matter to that first
        // measurement. Without this, single-page mode still behaved like double-page internally: a
        // phantom, hoverable/clickable second page slot remained where it would have been, showing
        // its own corner-flip preview and letting a click there flip through it (Peter's feedback,
        // 24.09.2026).
        this.book.style.width = pageWidth * (single ? 1 : 2) + 'px';
        this.book.style.height = pageHeight + 'px';
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
            // A low but non-zero shadow keeps a hint of depth without the harsh, high-contrast
            // gradient that reads as shiny foil rather than paper (Peter's feedback, 24.09.2026,
            // confirmed in an isolated test page against the library's own default of 1).
            maxShadowOpacity: 0.2,
            // Close to StPageFlip's own default (1000ms). A shorter value made the turn feel rushed
            // rather than deliberate.
            flippingTime: 900,
            mobileScrollSupport: false,
            swipeDistance: 60,
            // REVERTED to true (24.09.2026): false stopped the hover-preview "jump", but broke
            // forward page turns from the toolbar AND the keyboard - neither goes anywhere near our
            // own mousedown-gating code below, so the only plausible explanation is that this
            // setting also affects StPageFlip's own flip-completion logic internally, not just the
            // cosmetic hover preview as its name suggests. A correctness regression outweighs a
            // cosmetic one; the hover "jump" needs a different fix that doesn't touch this setting.
            showPageCorners: true,
            disableFlipByClick: true,
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
            if (this.zoom === 1 && !this.layout.single) {
                this.pageFlip.flipPrev();
            } else {
                // Two different reasons land here: while zoomed, turnToPrevPage() is used
                // everywhere already (see next()/goTo()). In single-page ("portrait") mode,
                // flipPrev() is a no-op - it starts its simulated drag gesture near x=10 of
                // StPageFlip's internal bounds rectangle, which in portrait mode is shifted left
                // by a page-and-a-half to make room for the (here invisible) phantom second page,
                // landing that coordinate outside wherever the gesture is actually recognised
                // (confirmed directly against the library). turnToPrevPage() targets a page
                // directly instead of simulating a screen-position drag, and works correctly there
                // - without the flip animation, the best available trade-off (Peter's feedback,
                // 24.09.2026).
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
     * Prevents StPageFlip from starting a page turn while zoomed (panning starts instead) or when
     * a drag starts outside the corner zone (see {@see CORNER_ZONE_RATIO}). Outside the corner
     * zone, a plain click/tap along the edge still turns the page via {@see watchForEdgeClick} -
     * only the "grab and follow the cursor" behaviour is confined to the corners.
     *
     * @param {Event} event Mouse or touch event
     */
    blockFlipGesture(event) {
        if (this.zoom !== 1) {
            event.stopPropagation();
            if (event.type === 'mousedown' && event.button === 0) {
                event.preventDefault();
                this.pan = {x: event.clientX, y: event.clientY, left: this.stage.scrollLeft, top: this.stage.scrollTop};
                this.zoomBox.classList.add('is-panning');
                window.addEventListener('mousemove', this.handlePanMove);
                window.addEventListener('mouseup', this.handlePanEnd);
            }
            return;
        }
        if (this.isInCornerZone(event)) {
            // Let StPageFlip handle it natively (corner drag-follow and click both work as usual).
            return;
        }
        // Swallowed here, in the capture phase, before StPageFlip's own listener (bound directly to
        // the book element) ever sees it - this is what keeps the drag-follow confined to the
        // corners. A plain click is still turned into a page turn separately, below.
        event.stopPropagation();
        this.watchForEdgeClick(event);
    }

    /**
     * Whether a mouse/touch event started within the top or bottom corner band of the book.
     *
     * @param {Event} event Mouse or touch event
     * @returns {boolean}
     */
    isInCornerZone(event) {
        const point = event.touches && event.touches.length ? event.touches[0] : event;
        const rect = this.book.getBoundingClientRect();
        if (!rect.height) {
            return true;
        }
        const relativeY = (point.clientY - rect.top) / rect.height;
        return relativeY <= CORNER_ZONE_RATIO || relativeY >= (1 - CORNER_ZONE_RATIO);
    }

    /**
     * Watches a press that started outside the corner zone and, if it ends without much movement
     * (a click, not a drag), turns the page if it was near the left or right edge.
     *
     * @param {Event} startEvent The mousedown/touchstart that started the press
     */
    watchForEdgeClick(startEvent) {
        const start = startEvent.touches && startEvent.touches.length ? startEvent.touches[0] : startEvent;
        const startX = start.clientX;
        const startY = start.clientY;
        const isTouch = startEvent.type === 'touchstart';
        const moveType = isTouch ? 'touchmove' : 'mousemove';
        const endType = isTouch ? 'touchend' : 'mouseup';
        let moved = false;

        const onMove = (event) => {
            const point = event.touches && event.touches.length ? event.touches[0] : event;
            if (Math.hypot(point.clientX - startX, point.clientY - startY) > CLICK_MOVE_TOLERANCE) {
                moved = true;
            }
        };
        const onEnd = () => {
            window.removeEventListener(moveType, onMove);
            window.removeEventListener(endType, onEnd);
            this.edgeClickCleanups = this.edgeClickCleanups.filter((cleanup) => cleanup !== cleanupWatch);
            if (!moved) {
                this.handleEdgeClick(startX);
            }
        };
        const cleanupWatch = () => {
            window.removeEventListener(moveType, onMove);
            window.removeEventListener(endType, onEnd);
        };
        window.addEventListener(moveType, onMove);
        window.addEventListener(endType, onEnd);
        this.edgeClickCleanups.push(cleanupWatch);
    }

    /**
     * Turns the page if a click landed near the left or right edge of the book.
     *
     * @param {number} clientX Horizontal click position, in viewport pixels
     */
    handleEdgeClick(clientX) {
        const rect = this.book.getBoundingClientRect();
        if (!rect.width) {
            return;
        }
        const relativeX = (clientX - rect.left) / rect.width;
        if (relativeX <= EDGE_CLICK_RATIO) {
            this.prev();
        } else if (relativeX >= (1 - EDGE_CLICK_RATIO)) {
            this.next();
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
        this.edgeClickCleanups.forEach((cleanup) => cleanup());
        this.edgeClickCleanups = [];
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

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
 * The Leafr reader: loads the PDF, shows it as flipbook or simple view and wires up the toolbar.
 *
 * @module     mod_leafr/reader
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Pending from 'core/pending';
import * as Str from 'core/str';
import BookmarkList from 'mod_leafr/bookmarklist';
import FlipbookView from 'mod_leafr/flipbook';
import ScrollView from 'mod_leafr/scrollview';
import Sidebar from 'mod_leafr/sidebar';
import Thumbnails from 'mod_leafr/thumbnails';
import Search from 'mod_leafr/search';
import Toc from 'mod_leafr/toc';
import Tracker from 'mod_leafr/tracker';
import {getOutline, getPageSize, loadDocument} from 'mod_leafr/pdf';

/**
 * Time a page turn animation needs, in milliseconds. Kept a margin above FlipbookView's
 * `flippingTime` (900ms) - waiting for exactly the animation's own duration is racy: a rapid
 * second turn can arrive while StPageFlip is still finishing the first and gets dropped (this
 * broke the CI Behat scenario that presses "Next page" three times in a row, 24.09.2026, when
 * flippingTime was raised from 700ms to the same 900ms this constant already used).
 */
const TURN_DURATION = 1100;

/**
 * Reader widths (CSS pixels) at which the toolbar changes: from WIDE_READER up, the main buttons
 * show a text label; below COMPACT_READER the first/last page and help buttons are hidden; below
 * NARROW_READER the buttons shrink, "of N" is hidden and download moves into the view menu. Kept
 * in sync with the `.is-wide`, `.is-compact` and `.is-narrow` rules in styles.css.
 */
const WIDE_READER = 760;
const COMPACT_READER = 580;
const NARROW_READER = 460;

/** Browser storage key remembering that the one-time full screen tip was shown. */
const FULLSCREEN_TIP_KEY = 'mod_leafr_fullscreentip';

/** How long the full screen tip stays visible, in milliseconds. */
const FULLSCREEN_TIP_DURATION = 10000;

/** Available zoom factors. */
const ZOOM_STEPS = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3];

/** How long a search match stays highlighted, in milliseconds. */
const HIGHLIGHT_DURATION = 4000;

/** Language strings used by the reader. */
const STRING_KEYS = [
    'pagelabel', 'pageofpages', 'pagesofpages', 'totalpages', 'toc_empty',
    'fullscreen_enter', 'fullscreen_exit', 'zoomlevel', 'continuenotice', 'progresssummary', 'requiredsummary',
    'search_label', 'search_placeholder', 'search_indexing', 'search_noresults',
    'search_noresults_scan', 'search_next', 'search_prev', 'matchofmatches',
    'bookmark_empty', 'bookmark_note_label', 'bookmark_note_placeholder', 'bookmark_remove',
    'bookmark_page', 'bookmark_note_chars', 'bookmark_note_saved', 'bookmark_print', 'required_badge',
    'search_hint', 'thumbs_legend_required', 'thumbs_legend_seen',
];

class Reader {

    /**
     * Constructor.
     *
     * @param {HTMLElement} root The reader element
     */
    constructor(root) {
        this.root = root;
        this.cmid = parseInt(root.dataset.cmid, 10);
        this.fileurl = root.dataset.fileurl;
        this.page = Math.max(1, parseInt(root.dataset.startpage, 10) || 1);
        this.completed = root.dataset.completed === '1';
        this.readingRule = root.dataset.readingrule === '1';
        this.stage = root.querySelector('[data-region="stage"]');
        this.viewHost = root.querySelector('[data-region="view"]');
        this.pageInput = root.querySelector('[data-region="page-input"]');
        this.pageTotal = root.querySelector('[data-region="page-total"]');
        this.progressBar = root.querySelector('[data-region="progress"]');
        this.progressFill = root.querySelector('[data-region="progress-fill"]');
        this.progressSummary = root.querySelector('[data-region="progress-summary"]');
        this.continueToast = root.querySelector('[data-region="continue"]');
        this.fullscreenTip = root.querySelector('[data-region="fullscreen-tip"]');
        this.live = root.querySelector('[data-region="live"]');
        this.helpDialog = root.querySelector('[data-region="help"]');
        this.sidebarRoot = root.querySelector('[data-region="sidebar"]');
        this.sidebarToggle = root.querySelector('[data-action="sidebar"]');
        this.zoomLabel = root.querySelector('[data-region="zoom-label"]');
        this.viewMenu = root.querySelector('[data-region="view-menu"]');
        this.viewMenuButton = root.querySelector('[data-action="view-menu"]');
        this.spreadMode = ['auto', 'single', 'double'].includes(root.dataset.spreadmode) ? root.dataset.spreadmode : 'auto';
        // On a phone-sized screen, a full-width single page reads better than one that is
        // height-fitted and leaves the sides empty; larger screens still default to fitting the
        // whole page so both pages of a spread stay fully visible without scrolling.
        this.fitMode = window.innerWidth < 768 ? 'width' : 'page';
        this.handleViewMenuOutsideClick = (event) => {
            if (!this.viewMenu.contains(event.target) && event.target !== this.viewMenuButton &&
                    !this.viewMenuButton.contains(event.target)) {
                this.closeViewMenu();
            }
        };
        this.view = null;
        this.sidebar = null;
        this.toc = null;
        this.thumbnails = null;
        this.search = null;
        this.bookmarkList = null;
        this.highlightTimer = null;
        this.bookmarkButton = root.querySelector('[data-action="bookmark"]');
        this.pageBookmarkBar = root.querySelector('[data-region="page-bookmarks"]');
        this.bookmarks = new Map(Reader.parseBookmarks(root.dataset.bookmarks).map((b) => [b.page, b.note]));
        this.maxNoteLength = parseInt(root.dataset.bookmarknotemaxlength, 10) || 500;
        this.zoomIndex = ZOOM_STEPS.indexOf(1);
        this.announceTimer = null;
        this.resizeTimer = null;
        this.continueTimer = null;
        this.helpReturnFocus = null;
        this.visiblePages = [this.page];
        this.initialPage = Math.max(1, parseInt(root.dataset.initialpage, 10) || 1);
        this.continuePage = parseInt(root.dataset.lastpage, 10) || 0;
        this.seenPages = Reader.parsePageRanges(root.dataset.seenpages);
        this.requiredPages = Reader.parsePageRanges(root.dataset.requiredpages);
        this.manualChapters = Reader.parseBookmarks(root.dataset.manualchapters);
        this.useManualChapters = root.dataset.usemanualchapters === '1';
        this.requiredSummary = root.querySelector('[data-region="required-summary"]');

        const stored = root.dataset.simpleview;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.simpleView = stored === '' ? reducedMotion : stored === '1';

        Reader.applyPageTheme(root);
    }

    /**
     * Relative luminance (WCAG) of an sRGB colour given as 0-255 channel values.
     *
     * @param {number[]} rgb Red, green and blue, 0-255
     * @returns {number} Luminance between 0 (black) and 1 (white)
     */
    static luminance(rgb) {
        const linear = (c) => (c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4));
        const [r, g, b] = rgb.map((c) => linear(c / 255));
        return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    }

    /**
     * Whether the Moodle page around the reader is dark: the first non-transparent background
     * found going up from the reader's container decides.
     *
     * @param {HTMLElement} root The reader element
     * @returns {boolean}
     */
    static isPageDark(root) {
        for (let el = root.parentElement; el; el = el.parentElement) {
            const match = /rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/.exec(
                getComputedStyle(el).backgroundColor);
            if (match && (match[4] === undefined || parseFloat(match[4]) > 0.5)) {
                return Reader.luminance([match[1], match[2], match[3]].map(Number)) < 0.2;
            }
        }
        return false;
    }

    /**
     * Matches the reader to the Moodle page around it. The reader only turns dark when the page
     * itself is dark (a dark Moodle theme), not merely because the operating system prefers dark
     * mode: Moodle's usual themes stay white, and a dark reader inside a white page looked out of
     * place. In the light case, the theme's primary colour (Boost's `--bs-primary`) replaces the
     * built-in Leafr petrol as accent colour, but only if it is a plain hex colour with enough
     * contrast against white (WCAG AA, 4.5:1) to stay safe as text. A dark page keeps the fixed
     * dark accent from {@see styles.css}: a theme's primary is rarely tuned for dark backgrounds.
     *
     * @param {HTMLElement} root The reader element
     */
    static applyPageTheme(root) {
        if (Reader.isPageDark(root)) {
            root.classList.add('is-dark');
            return;
        }
        const raw = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim();
        const match = /^#([0-9a-f]{6}|[0-9a-f]{3})$/i.exec(raw);
        if (!match) {
            return;
        }
        const hex = match[1].length === 3 ? [...match[1]].map((c) => c + c).join('') : match[1];
        const rgb = [0, 2, 4].map((i) => parseInt(hex.substring(i, i + 2), 16));
        const contrastOnWhite = 1.05 / (Reader.luminance(rgb) + 0.05);
        if (contrastOnWhite >= 4.5) {
            root.style.setProperty('--leafr-accent', raw);
        }
    }

    /**
     * Loads the document and shows it.
     *
     * @returns {Promise<void>}
     */
    async init() {
        const pending = new Pending('mod_leafr/reader:init');
        this.observeWidth();
        this.fitHeight();
        window.addEventListener('resize', () => {
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(() => this.fitHeight(), 100);
        });
        this.bindEvents();

        try {
            const [strings, pdfDoc] = await Promise.all([
                Str.getStrings(STRING_KEYS.map((key) => ({key, component: 'mod_leafr'}))),
                loadDocument(this.fileurl),
            ]);
            this.strings = {};
            STRING_KEYS.forEach((key, index) => {
                this.strings[key] = strings[index];
            });
            this.pdfDoc = pdfDoc;
            this.total = pdfDoc.numPages;
            this.page = Math.min(this.page, this.total);

            this.tracker = new Tracker({
                cmid: this.cmid,
                totalPages: this.total,
                onCompleted: () => this.showCompletion(),
            });

            this.pageTotal.textContent = this.strings.totalpages.replace('{$a}', this.total);
            this.pageInput.setAttribute('max', this.total);
            this.progressBar.setAttribute('aria-valuemax', this.total);
            this.root.querySelectorAll('.leafr-toolbar button[disabled], .leafr-pageinput').forEach((el) => {
                el.disabled = false;
            });
            const fullscreenButton = this.root.querySelector('[data-action="fullscreen"]');
            fullscreenButton.hidden = !this.root.requestFullscreen;

            this.initSidebar();
            await this.showView();
            this.setLoading(false);
            await this.initToc();
            this.updateProgressSummary();
            this.showContinueNotice();
            this.showFullscreenTip();
            if (!this.readingRule && this.seenPages.has(this.total) && !this.announced) {
                // No page based rule: having seen the last page before counts as having read it.
                this.announceCompletion();
            } else if (this.completed) {
                // Already complete when the page loaded (e.g. a returning user): showCompletion()
                // only fires for a *newly reached* completion, so subplugins listening for
                // "leafr:reading-complete" (e.g. leafrtool_confirm) still need to hear about it.
                this.announceCompletion();
            }
        } catch (error) {
            this.showError();
        }
        pending.resolve();
    }

    /**
     * Lets automated tests wait until a page turn has finished.
     */
    waitForTurn() {
        const pending = new Pending('mod_leafr/reader:turn');
        setTimeout(() => pending.resolve(), TURN_DURATION);
    }

    /**
     * Makes the reader as high as the window below whatever sits above it, so its own toolbar
     * always stays visible without having to scroll the page. Two things can push it down: fixed
     * or sticky bars (some themes stack more than one, e.g. a site navbar plus a second,
     * theme-specific course navigation bar), and ordinary page content in normal flow (the course
     * heading/breadcrumb above the activity) - {@see getFixedTopOffset} only measures the former,
     * so the reader's own current position is used as well, whichever is larger (feedback from testing,
     * 24.09.2026: the page still needed scrolling to see the heading or the toolbar).
     */
    fitHeight() {
        if (document.fullscreenElement === this.root) {
            this.root.style.removeProperty('--leafr-height');
            return;
        }
        // With an activity description above the reader (Moodle's ".activity-description"), the reader
        // is sized to the whole window below the fixed bars instead: the page scrolls past the
        // description and the book then fills the screen. Fitting it into the space left below a long
        // description had left only about ten lines of a page to read (feedback from testing, GitHub
        // issues #1 and #2).
        const fixedtop = this.getFixedTopOffset();
        const offset = this.hasDescriptionAbove() ? fixedtop : Math.max(fixedtop, this.getPageTop());
        this.root.style.setProperty('--leafr-height', Math.max(window.innerHeight - offset - 32, 420) + 'px');
    }

    /**
     * Distance of the reader from the top of the page, independent of how far the page is currently
     * scrolled (Moodle's themes scroll either the window or an inner container such as "#page").
     *
     * @returns {number} Offset in pixels
     */
    getPageTop() {
        let scrolled = window.scrollY;
        for (let el = this.root.parentElement; el && el !== document.body; el = el.parentElement) {
            const overflow = window.getComputedStyle(el).overflowY;
            if ((overflow === 'auto' || overflow === 'scroll') && el.scrollHeight > el.clientHeight) {
                scrolled = el.scrollTop;
                break;
            }
        }
        return this.root.getBoundingClientRect().top + scrolled;
    }

    /**
     * Whether a visible activity description is shown above the reader.
     *
     * @returns {boolean}
     */
    hasDescriptionAbove() {
        return [...document.querySelectorAll('.activity-description')].some((el) =>
            // eslint-disable-next-line no-bitwise
            (el.compareDocumentPosition(this.root) & Node.DOCUMENT_POSITION_FOLLOWING) && el.offsetHeight > 0);
    }

    /**
     * Adapts the toolbar to the reader's own width rather than the screen's: inside Moodle's page
     * margins and drawers, the reader is often far narrower than the viewport (a 768px tablet
     * leaves it about 530px), which used to push the toolbar's right-hand buttons out of reach.
     * Done here instead of with CSS container queries, which Moodle's CSS linter does not accept.
     */
    observeWidth() {
        const apply = () => {
            const width = this.root.clientWidth;
            this.root.classList.toggle('is-wide', width >= WIDE_READER);
            this.root.classList.toggle('is-compact', width < COMPACT_READER);
            this.root.classList.toggle('is-narrow', width < NARROW_READER);
        };
        apply();
        new ResizeObserver(apply).observe(this.root);
    }

    /**
     * Measures how far down the fixed or sticky bars anchored to the top of the page reach.
     *
     * @returns {number} Offset in pixels
     */
    getFixedTopOffset() {
        const bars = [];
        document.body.querySelectorAll('*').forEach((el) => {
            if (this.root.contains(el) || !el.offsetHeight) {
                return;
            }
            const position = window.getComputedStyle(el).position;
            if (position !== 'fixed' && position !== 'sticky') {
                return;
            }
            // Only wide, flat bars count; Moodle's drawers (course index, blocks) are fixed too and start
            // right below the navbar, but they are narrow and as tall as the window.
            const rect = el.getBoundingClientRect();
            if (rect.width >= window.innerWidth / 2 && rect.height <= window.innerHeight / 3) {
                bars.push(rect);
            }
        });
        // Bars can be stacked: a sticky secondary navigation directly below the fixed site navbar
        // (theme Moove) stays at the top while scrolling too, so each bar that starts where the
        // previous one ends extends the covered area.
        bars.sort((a, b) => a.top - b.top);
        let bottom = 0;
        for (const rect of bars) {
            if (rect.top <= bottom + 4 && rect.bottom > bottom) {
                bottom = rect.bottom;
            }
        }
        return Math.max(bottom, 0);
    }

    /**
     * Shows the document in the current view mode.
     *
     * @returns {Promise<void>}
     */
    async showView() {
        if (this.view) {
            this.view.destroy();
        }
        const ViewClass = this.simpleView ? ScrollView : FlipbookView;
        this.root.classList.toggle('is-simpleview', this.simpleView);
        this.root.querySelectorAll('[data-action="simpleview"]').forEach((button) => {
            button.setAttribute('aria-checked', this.simpleView ? 'true' : 'false');
        });
        this.stage.scrollTo(0, 0);

        this.view = new ViewClass({
            stage: this.stage,
            host: this.viewHost,
            pdfDoc: this.pdfDoc,
            startPage: this.page,
            strings: this.strings,
            spreadMode: this.spreadMode,
            fitMode: this.fitMode,
            onPageChange: (page, visible) => this.handlePageChange(page, visible),
        });
        await this.view.init();
        if (ZOOM_STEPS[this.zoomIndex] !== 1) {
            this.view.setZoom(ZOOM_STEPS[this.zoomIndex]);
        }
        this.syncAllPagesBookmarkUI();
        this.updateViewMenuState();
    }

    /**
     * Builds the sidebar with its tabs. Thumbnails and search are only set up once their tab is
     * opened for the first time, since building them is not free.
     */
    initSidebar() {
        const tabs = [...this.root.querySelectorAll('[data-region="sidebar"] [data-tab]')].map((button) => ({
            name: button.dataset.tab,
            button: button,
            panel: this.root.querySelector('[data-panel="' + button.dataset.tab + '"]'),
        }));
        this.sidebar = new Sidebar({root: this.sidebarRoot, toggle: this.sidebarToggle, tabs});

        this.sidebar.onFirstActivate('thumbs', () => {
            this.thumbnails = new Thumbnails({
                host: this.root.querySelector('[data-panel="thumbs"]'),
                pdfDoc: this.pdfDoc,
                total: this.total,
                seenPages: this.seenPages,
                requiredPages: this.requiredPages,
                currentPage: this.page,
                strings: this.strings,
                onNavigate: (page) => {
                    this.goTo(page);
                    this.sidebar.closeOnNarrowScreen();
                },
            });
            this.thumbnails.init();
        });

        this.sidebar.onFirstActivate('search', () => {
            this.search = new Search({
                host: this.root.querySelector('[data-panel="search"]'),
                pdfDoc: this.pdfDoc,
                total: this.total,
                strings: this.strings,
                onNavigate: (page, item) => {
                    this.goTo(page);
                    this.highlightMatch(page, item);
                },
            });
            this.search.init();
            this.search.focus();
        });

        this.sidebar.onFirstActivate('bookmarks', () => {
            this.bookmarkList = new BookmarkList({
                host: this.root.querySelector('[data-panel="bookmarks"]'),
                pdfDoc: this.pdfDoc,
                strings: this.strings,
                items: this.bookmarks,
                maxNoteLength: this.maxNoteLength,
                printUrl: this.root.dataset.printurl,
                onNavigate: (page) => {
                    this.goTo(page);
                    this.sidebar.closeOnNarrowScreen();
                },
                onSetNote: (page, note) => this.saveBookmarkNote(page, note),
                onRemove: (page) => this.removeBookmark(page),
            });
            this.bookmarkList.init();
        });
    }

    /**
     * Loads the outline of the PDF into the "Contents" sidebar tab, if there is one.
     */
    async initToc() {
        const button = this.root.querySelector('[data-tab="toc"]');
        const panel = this.root.querySelector('[data-panel="toc"]');
        if (!button || !panel) {
            return;
        }
        let entries = [];
        if (!this.useManualChapters) {
            try {
                entries = await getOutline(this.pdfDoc);
            } catch (error) {
                entries = [];
            }
        }
        if (!entries.length && this.manualChapters.length) {
            entries = this.manualChapters.map((chapter) => ({title: chapter.title, page: chapter.page, children: []}));
        }
        if (!entries.length) {
            button.title = this.strings.toc_empty;
            return;
        }
        this.toc = new Toc({
            panel: panel,
            entries: entries,
            requiredPages: this.requiredPages,
            requiredBadge: this.strings.required_badge,
            onNavigate: (page) => {
                this.goTo(page);
                this.sidebar.closeOnNarrowScreen();
            },
        });
        this.toc.setCurrentPage(this.page);
        button.disabled = false;
    }

    /**
     * Briefly highlights a search match on the page it was found on.
     *
     * The box is positioned with percentages of the page size, so it lines up correctly
     * regardless of the current zoom or the page-turning transform of the flipbook view.
     *
     * @param {number} page 1-based page number
     * @param {{left: number, top: number, width: number, height: number}} item Match position
     * @returns {Promise<void>}
     */
    async highlightMatch(page, item) {
        const target = this.viewHost.querySelector('[data-page="' + page + '"]');
        if (!target) {
            return;
        }
        target.querySelectorAll('.leafr-search-highlight').forEach((el) => el.remove());
        const pageSize = await getPageSize(this.pdfDoc, page);
        const mark = document.createElement('div');
        mark.className = 'leafr-search-highlight';
        mark.style.left = (item.left / pageSize.width * 100) + '%';
        mark.style.top = (item.top / pageSize.height * 100) + '%';
        mark.style.width = (item.width / pageSize.width * 100) + '%';
        mark.style.height = (item.height / pageSize.height * 100) + '%';
        target.appendChild(mark);
        clearTimeout(this.highlightTimer);
        this.highlightTimer = setTimeout(() => mark.remove(), HIGHLIGHT_DURATION);
    }

    /**
     * Adds a bookmark for a page, or removes it if there already is one.
     *
     * @param {number} page Page number, defaults to the current reading position. In the
     * two-page flipbook view, "current" is ambiguous (it is the left of the two visible pages),
     * so the page-bookmark bar buttons built by {@see updatePageBookmarkBar} pass the exact page
     * instead.
     */
    toggleBookmark(page = this.page) {
        if (this.bookmarks.has(page)) {
            this.removeBookmark(page);
        } else {
            this.addBookmark(page, '');
        }
    }

    /**
     * Creates or updates a bookmark and reflects it in the toolbar, the page flag and the
     * sidebar list.
     *
     * @param {number} page Page number
     * @param {string} note Note
     * @returns {Promise<void>}
     */
    async addBookmark(page, note) {
        try {
            const result = await Ajax.call([{
                methodname: 'mod_leafr_bookmark_set',
                args: {cmid: this.cmid, pageno: page, note: note},
            }])[0];
            this.bookmarks.set(page, result.note);
            this.syncPageBookmarkUI(page);
            this.updateBookmarkButton();
            this.updatePageBookmarkBar(this.visiblePages);
            if (this.bookmarkList) {
                this.bookmarkList.set(page, result.note);
            }
        } catch (error) {
            // Keep the previous state; the user can try again.
            return;
        }
    }

    /**
     * Saves an edited note of an existing bookmark without touching the toolbar or the flag.
     *
     * @param {number} page Page number
     * @param {string} note Note
     * @returns {Promise<boolean>} Whether the note was saved
     */
    async saveBookmarkNote(page, note) {
        try {
            const result = await Ajax.call([{
                methodname: 'mod_leafr_bookmark_set',
                args: {cmid: this.cmid, pageno: page, note: note},
            }])[0];
            this.bookmarks.set(page, result.note);
            return true;
        } catch (error) {
            return false;
        }
    }

    /**
     * Removes a bookmark and reflects it in the toolbar, the page flag and the sidebar list.
     *
     * @param {number} page Page number
     * @returns {Promise<void>}
     */
    async removeBookmark(page) {
        try {
            await Ajax.call([{methodname: 'mod_leafr_bookmark_delete', args: {cmid: this.cmid, pageno: page}}])[0];
            this.bookmarks.delete(page);
            this.syncPageBookmarkUI(page);
            this.updateBookmarkButton();
            this.updatePageBookmarkBar(this.visiblePages);
            if (this.bookmarkList) {
                this.bookmarkList.remove(page);
            }
        } catch (error) {
            return;
        }
    }

    /**
     * Shows or hides the corner flag of a page depending on whether it is bookmarked. This is a
     * purely visual indicator (like a ribbon bookmark in a physical book) and has no click
     * handler of its own: a clickable element on the page corner used to overlap StPageFlip's own
     * click/drag zone for turning pages there, which looked broken and made it unclear whether a
     * click bookmarked the page or turned it. The actual controls live in the page-bookmark bar
     * above the book, see {@see updatePageBookmarkBar}.
     *
     * @param {number} page Page number
     */
    syncPageBookmarkUI(page) {
        const target = this.viewHost.querySelector('[data-page="' + page + '"]');
        if (!target) {
            return;
        }
        target.classList.toggle('is-bookmarked', this.bookmarks.has(page));
    }

    /**
     * Re-applies the corner flags of every page currently in the view, e.g. after it was rebuilt
     * following a resize.
     */
    syncAllPagesBookmarkUI() {
        this.viewHost.querySelectorAll('[data-page]').forEach((el) => {
            this.syncPageBookmarkUI(parseInt(el.dataset.page, 10));
        });
    }

    /**
     * Fills the bar above the book with one bookmark button per currently visible page. It is
     * only shown for an actual two-page spread in the flipbook view: that is the one case where
     * the toolbar button and the B key are ambiguous (they only ever reach the left page), so a
     * single visible page does not need a second, redundant control.
     *
     * @param {number[]} visible Visible pages
     */
    updatePageBookmarkBar(visible) {
        const bar = this.pageBookmarkBar;
        if (!bar) {
            return;
        }
        bar.innerHTML = '';
        if (this.simpleView || visible.length < 2) {
            bar.hidden = true;
            return;
        }
        bar.hidden = false;
        visible.forEach((page) => {
            const bookmarked = this.bookmarks.has(page);
            const label = this.strings.bookmark_page.replace('{$a}', page);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'leafr-page-bookmark-btn';
            button.classList.toggle('is-bookmarked', bookmarked);
            button.setAttribute('aria-pressed', bookmarked ? 'true' : 'false');
            button.title = label;
            button.setAttribute('aria-label', label);
            button.textContent = page;
            button.addEventListener('click', () => this.toggleBookmark(page));
            bar.appendChild(button);
        });
    }

    /**
     * Reflects whether the current page is bookmarked in the toolbar button.
     */
    updateBookmarkButton() {
        this.bookmarkButton.setAttribute('aria-pressed', this.bookmarks.has(this.page) ? 'true' : 'false');
    }

    /**
     * Updates toolbar, progress and tracking when the visible pages change.
     *
     * @param {number} page First visible page
     * @param {number[]} visible Visible pages
     */
    handlePageChange(page, visible) {
        this.page = page;
        this.visiblePages = visible;
        const last = visible.length ? visible[visible.length - 1] : page;
        if (!this.readingRule && !this.announced && last >= this.total) {
            // Without a page based rule, reaching the last page is the end of reading, e.g. to reveal
            // a read confirmation that is the activity's only completion condition (issue #10).
            this.announceCompletion();
        }
        if (document.activeElement !== this.pageInput) {
            this.pageInput.value = page;
        }
        this.progressFill.style.width = (this.total > 1 ? (last - 1) / (this.total - 1) * 100 : 100) + '%';
        this.progressBar.setAttribute('aria-valuenow', page);
        this.progressBar.setAttribute(
            'aria-valuetext',
            this.strings.pageofpages.replace('{$a->page}', page).replace('{$a->total}', this.total)
        );
        this.root.querySelector('[data-action="first"]').disabled = page <= 1;
        this.root.querySelector('[data-action="prev"]').disabled = page <= 1;
        this.root.querySelector('[data-action="next"]').disabled = last >= this.total;
        this.root.querySelector('[data-action="last"]').disabled = last >= this.total;
        if (this.toc) {
            this.toc.setCurrentPage(page);
        }
        if (this.thumbnails) {
            this.thumbnails.setCurrentPage(page, visible);
            this.thumbnails.markSeen(visible);
        }
        visible.forEach((visiblepage) => this.seenPages.add(visiblepage));
        this.updateProgressSummary();
        this.updateBookmarkButton();
        this.syncAllPagesBookmarkUI();
        this.updatePageBookmarkBar(visible);
        this.tracker.record(page, visible);

        // Announce the new page to screen readers once the user stops turning pages.
        clearTimeout(this.announceTimer);
        this.announceTimer = setTimeout(() => {
            const text = visible.length > 1 && !this.simpleView
                ? this.strings.pagesofpages.replace('{$a->first}', visible[0]).replace('{$a->last}', last)
                : this.strings.pageofpages.replace('{$a->page}', page);
            this.live.textContent = text.replace('{$a->total}', this.total);
        }, 400);
    }

    /**
     * Updates the "X of Y pages read" text next to the progress bar.
     */
    updateProgressSummary() {
        if (!this.total) {
            return;
        }
        this.progressSummary.textContent = this.strings.progresssummary
            .replace('{$a->seen}', this.seenPages.size)
            .replace('{$a->total}', this.total);

        if (this.requiredPages.size) {
            const seenRequired = [...this.requiredPages].filter((page) => this.seenPages.has(page)).length;
            this.requiredSummary.textContent = this.strings.requiredsummary
                .replace('{$a->seen}', seenRequired)
                .replace('{$a->total}', this.requiredPages.size);
            this.requiredSummary.hidden = false;
        }
    }

    /**
     * Shows a dismissible notice when the reader opened at a previously reached page instead of
     * silently jumping there, offering a way back to the configured start page.
     */
    showContinueNotice() {
        if (this.continuePage <= 1 || this.continuePage === this.initialPage) {
            return;
        }
        this.continueToast.querySelector('[data-region="continue-text"]').textContent =
            this.strings.continuenotice.replace('{$a}', this.continuePage);
        this.continueToast.hidden = false;
        this.continueTimer = setTimeout(() => this.hideContinueNotice(), 8000);
    }

    /**
     * Shows a one-time tip about full screen mode (once per browser): embedded in a Moodle page,
     * the reader often has little height left on a laptop screen, and full screen is easy to miss
     * inside the "View" menu. Skipped when the "continue reading" notice is already showing.
     */
    showFullscreenTip() {
        if (!this.fullscreenTip || !this.root.requestFullscreen || !this.continueToast.hidden ||
                document.fullscreenElement === this.root) {
            return;
        }
        try {
            if (window.localStorage.getItem(FULLSCREEN_TIP_KEY)) {
                return;
            }
            window.localStorage.setItem(FULLSCREEN_TIP_KEY, '1');
        } catch (error) {
            // Without browser storage the tip would appear on every visit, so it is left out.
            return;
        }
        this.fullscreenTip.hidden = false;
        this.fullscreenTipTimer = setTimeout(() => this.hideFullscreenTip(), FULLSCREEN_TIP_DURATION);
    }

    /**
     * Hides the full screen tip.
     */
    hideFullscreenTip() {
        clearTimeout(this.fullscreenTipTimer);
        if (this.fullscreenTip) {
            this.fullscreenTip.hidden = true;
        }
    }

    /**
     * Hides the "continue reading" notice.
     */
    hideContinueNotice() {
        clearTimeout(this.continueTimer);
        this.continueToast.hidden = true;
    }

    /**
     * Shows a page.
     *
     * @param {number} page 1-based page number
     */
    goTo(page) {
        if (this.view) {
            this.waitForTurn();
            this.view.goTo(Math.min(Math.max(1, page), this.total));
        }
    }

    /**
     * Registers the event handlers of the toolbar, keyboard and dialogs.
     */
    bindEvents() {
        this.root.addEventListener('click', (event) => {
            const button = event.target.closest('[data-action]');
            if (!button || !this.root.contains(button) || button.disabled) {
                return;
            }
            this.handleAction(button.dataset.action);
        });

        this.pageInput.addEventListener('change', () => this.submitPageInput());
        this.pageInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                this.submitPageInput();
            }
        });

        this.root.addEventListener('keydown', (event) => this.handleKey(event));
        this.stage.addEventListener('mousedown', () => this.stage.focus({preventScroll: true}));

        this.progressBar.addEventListener('click', (event) => this.handleProgressClick(event));
        this.progressBar.addEventListener('keydown', (event) => this.handleProgressKey(event));

        document.addEventListener('fullscreenchange', () => {
            const active = document.fullscreenElement === this.root;
            this.root.classList.toggle('is-fullscreen', active);
            if (active) {
                this.hideFullscreenTip();
            }
            const button = this.root.querySelector('[data-action="fullscreen"]');
            const label = active ? this.strings.fullscreen_exit : this.strings.fullscreen_enter;
            button.setAttribute('aria-checked', active ? 'true' : 'false');
            button.title = label;
            button.querySelector('[data-region="fullscreen-label"]').textContent = label;
            this.fitHeight();
        });
    }

    /**
     * Executes a toolbar action.
     *
     * @param {string} action Action name
     */
    handleAction(action) {
        // The view menu contributes several closely related actions (page layout, zoom fit); kept
        // as a lookup instead of more switch cases so this method's complexity stays manageable.
        const viewMenuActions = {
            'view-menu': () => this.toggleViewMenu(),
            'spread-auto': () => this.setSpreadMode('auto'),
            'spread-single': () => this.setSpreadMode('single'),
            'spread-double': () => this.setSpreadMode('double'),
            'fit-page': () => this.setFitMode('page'),
            'fit-width': () => this.setFitMode('width'),
            'fullscreen-tip': () => {
                this.hideFullscreenTip();
                this.toggleFullscreen();
            },
            'fullscreen-tip-close': () => this.hideFullscreenTip(),
        };
        if (viewMenuActions[action]) {
            viewMenuActions[action]();
            return;
        }
        switch (action) {
            case 'first':
                this.goTo(1);
                break;
            case 'prev':
                this.waitForTurn();
                this.view.prev();
                break;
            case 'next':
                this.waitForTurn();
                this.view.next();
                break;
            case 'last':
                this.goTo(this.total);
                break;
            case 'zoom-in':
                this.changeZoom(1);
                break;
            case 'zoom-out':
                this.changeZoom(-1);
                break;
            case 'zoom-reset':
                this.changeZoom(0);
                break;
            case 'simpleview':
                this.toggleSimpleView();
                break;
            case 'fullscreen':
                this.toggleFullscreen();
                break;
            case 'bookmark':
                this.toggleBookmark();
                break;
            case 'sidebar':
                if (this.sidebar.isOpen()) {
                    this.sidebar.close();
                } else {
                    this.sidebar.open();
                }
                break;
            case 'sidebar-close':
                this.sidebar.close();
                break;
            case 'help':
                this.openHelp();
                break;
            case 'help-close':
                this.closeHelp();
                break;
            case 'reload':
                window.location.reload();
                break;
            case 'restart':
                this.hideContinueNotice();
                this.goTo(this.initialPage);
                break;
        }
    }

    /**
     * Jumps to the page corresponding to a click position on the progress bar.
     *
     * @param {MouseEvent} event Click event
     */
    handleProgressClick(event) {
        if (!this.total) {
            return;
        }
        const rect = this.progressBar.getBoundingClientRect();
        const ratio = rect.width ? Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width)) : 0;
        this.goTo(Math.round(1 + ratio * (this.total - 1)));
    }

    /**
     * Moves the reading position with the keyboard while the progress bar (an ARIA slider) has
     * the focus.
     *
     * @param {KeyboardEvent} event Key event
     */
    handleProgressKey(event) {
        // Use the same actions as the toolbar buttons: in the flipbook view, a "page" is often a
        // two-page spread, so next/prev is not always the same as goTo(page +/- 1).
        const actions = {
            ArrowRight: () => this.handleAction('next'),
            ArrowUp: () => this.handleAction('next'),
            ArrowLeft: () => this.handleAction('prev'),
            ArrowDown: () => this.handleAction('prev'),
            Home: () => this.goTo(1),
            End: () => this.goTo(this.total),
        };
        if (actions[event.key]) {
            event.preventDefault();
            actions[event.key]();
        }
    }

    /**
     * Handles keyboard shortcuts while the reader has the focus.
     *
     * @param {KeyboardEvent} event Key event
     */
    handleKey(event) {
        if (!this.view || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }
        if (!this.helpDialog.hidden) {
            if (event.key === 'Escape' || event.key === 'Tab') {
                event.preventDefault();
                this.closeHelp();
            }
            return;
        }
        if (!this.viewMenu.hidden && event.key === 'Escape') {
            event.preventDefault();
            this.closeViewMenu();
            this.viewMenuButton.focus();
            return;
        }
        if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName) || event.target === this.progressBar) {
            return;
        }
        const action = this.getKeyAction(event);
        if (action) {
            event.preventDefault();
            action();
        }
    }

    /**
     * Returns the function for a keyboard shortcut.
     *
     * @param {KeyboardEvent} event Key event
     * @returns {Function|null} The action, null if the key is not a shortcut here
     */
    getKeyAction(event) {
        // In the simple view the browser scrolls the stage itself with Page Up and Page Down.
        const pageKeys = !this.simpleView || event.target !== this.stage;
        const actions = {
            ArrowRight: () => this.handleAction('next'),
            ArrowLeft: () => this.handleAction('prev'),
            PageDown: pageKeys ? () => this.handleAction('next') : null,
            PageUp: pageKeys ? () => this.handleAction('prev') : null,
            Home: () => this.goTo(1),
            End: () => this.goTo(this.total),
            '+': () => this.changeZoom(1),
            '-': () => this.changeZoom(-1),
            t: this.toc ? () => this.sidebar.toggle('toc') : null,
            b: () => this.toggleBookmark(),
            f: () => this.toggleFullscreen(),
            '?': () => this.openHelp(),
            Escape: this.sidebar && this.sidebar.isOpen() ? () => this.sidebar.close() : null,
        };
        const key = event.key.length === 1 && event.key !== '?' ? event.key.toLowerCase() : event.key;
        return actions[key] || null;
    }

    /**
     * Goes to the page typed into the page field.
     */
    submitPageInput() {
        const page = parseInt(this.pageInput.value, 10);
        if (page >= 1 && page <= this.total) {
            this.goTo(page);
        } else {
            this.pageInput.value = this.page;
        }
    }

    /**
     * Changes the zoom factor.
     *
     * @param {number} direction 1 = zoom in, -1 = zoom out, 0 = reset
     */
    changeZoom(direction) {
        const reset = ZOOM_STEPS.indexOf(1);
        const index = direction === 0 ? reset : Math.min(Math.max(0, this.zoomIndex + direction), ZOOM_STEPS.length - 1);
        if (index === this.zoomIndex || !this.view) {
            return;
        }
        this.zoomIndex = index;
        const zoom = ZOOM_STEPS[index];
        this.view.setZoom(zoom);
        const percent = Math.round(zoom * 100);
        this.zoomLabel.textContent = percent + '%';
        this.live.textContent = this.strings.zoomlevel.replace('{$a}', percent);
        this.root.querySelector('[data-action="zoom-in"]').disabled = index === ZOOM_STEPS.length - 1;
        this.root.querySelector('[data-action="zoom-out"]').disabled = index === 0;
    }

    /**
     * Switches between flipbook and simple view and remembers the choice.
     */
    async toggleSimpleView() {
        await this.setSimpleView(!this.simpleView);
    }

    /**
     * Switches to/from the simple view and remembers the choice. Also used by
     * {@see setSpreadMode} so picking a page layout while in the simple view switches straight to
     * the flipbook instead of requiring the simple view to be turned off first (feedback from testing,
     * 24.09.2026).
     *
     * @param {boolean} value New simple-view state
     */
    async setSimpleView(value) {
        if (this.simpleView === value) {
            return;
        }
        const pending = new Pending('mod_leafr/reader:toggleview');
        this.simpleView = value;
        this.setLoading(true);
        try {
            await this.showView();
        } finally {
            this.setLoading(false);
            pending.resolve();
        }
        this.stage.focus({preventScroll: true});
        Ajax.call([{
            methodname: 'core_user_update_user_preferences',
            args: {preferences: [{type: 'mod_leafr_simpleview', value: this.simpleView ? '1' : '0'}]},
        }])[0].catch(() => null);
    }

    /**
     * Opens or closes the "view" menu (page layout, zoom and full screen).
     */
    toggleViewMenu() {
        if (this.viewMenu.hidden) {
            this.openViewMenu();
        } else {
            this.closeViewMenu();
        }
    }

    /**
     * Opens the view menu.
     */
    openViewMenu() {
        this.updateViewMenuState();
        this.viewMenu.hidden = false;
        this.viewMenuButton.setAttribute('aria-expanded', 'true');
        // The listener is added on open (not once in bindEvents) so it never fires for the very
        // click that opened the menu, and is removed again on close to avoid piling up handlers.
        document.addEventListener('click', this.handleViewMenuOutsideClick, true);
    }

    /**
     * Closes the view menu.
     */
    closeViewMenu() {
        this.viewMenu.hidden = true;
        this.viewMenuButton.setAttribute('aria-expanded', 'false');
        document.removeEventListener('click', this.handleViewMenuOutsideClick, true);
    }

    /**
     * Reflects the current spread mode, fit mode and simple-view state in the view menu, and
     * disables the flipbook-only options (page layout, zoom fit) while the simple view is active.
     */
    updateViewMenuState() {
        // "Double" is greyed out where a spread cannot be shown anyway (landscape pages, a one-page
        // document, a narrow stage), instead of silently doing nothing (feedback from testing, issue #12).
        const nospread = !this.simpleView && this.view && this.view.canSpread && !this.view.canSpread();
        this.viewMenu.querySelectorAll('[data-spread]').forEach((button) => {
            button.setAttribute('aria-checked', button.dataset.spread === this.spreadMode ? 'true' : 'false');
            if (button.dataset.spread === 'double') {
                button.disabled = Boolean(nospread);
            }
        });
        this.viewMenu.querySelectorAll('[data-fit]').forEach((button) => {
            button.setAttribute('aria-checked', button.dataset.fit === this.fitMode ? 'true' : 'false');
            button.disabled = this.simpleView;
        });
    }

    /**
     * Changes the spread mode of the flipbook view ('auto', 'single' or 'double') and remembers
     * the choice. Picking one while the simple view is active switches to the flipbook first
     * (see {@see setSimpleView}), rather than requiring a separate step to leave the simple view.
     *
     * @param {string} mode New spread mode
     */
    async setSpreadMode(mode) {
        this.spreadMode = mode;
        if (this.simpleView) {
            await this.setSimpleView(false);
        }
        this.updateViewMenuState();
        if (this.view && this.view.setSpreadMode) {
            this.view.setSpreadMode(mode);
        }
        Ajax.call([{
            methodname: 'core_user_update_user_preferences',
            args: {preferences: [{type: 'mod_leafr_spreadmode', value: mode}]},
        }])[0].catch(() => null);
    }

    /**
     * Changes how the flipbook fits the available space ('page' fits the whole page, 'width'
     * fills the width and may need vertical scrolling). Not persisted: it is a short-lived reading
     * aid rather than a lasting preference, and always starts back at "page" on the next visit.
     *
     * @param {string} mode New fit mode
     */
    setFitMode(mode) {
        // Fitting always starts from 100 %: "fit to page/width" is meant to show exactly that, and a
        // leftover zoom made the page overflow again (feedback from testing, issue #4). Zooming in again
        // afterwards works as usual.
        this.changeZoom(0);
        this.fitMode = mode;
        this.updateViewMenuState();
        if (this.view && this.view.setFitMode) {
            this.view.setFitMode(mode);
        }
    }

    /**
     * Enters or leaves full screen mode.
     */
    toggleFullscreen() {
        if (!this.root.requestFullscreen) {
            return;
        }
        if (document.fullscreenElement === this.root) {
            document.exitFullscreen().catch(() => null);
        } else {
            this.root.requestFullscreen().catch(() => null);
        }
    }

    /**
     * Opens the dialog with the keyboard shortcuts.
     */
    openHelp() {
        this.helpReturnFocus = document.activeElement;
        this.helpDialog.hidden = false;
        this.helpDialog.querySelector('[data-action="help-close"]').focus();
    }

    /**
     * Closes the dialog with the keyboard shortcuts.
     */
    closeHelp() {
        this.helpDialog.hidden = true;
        if (this.helpReturnFocus && this.root.contains(this.helpReturnFocus)) {
            this.helpReturnFocus.focus();
        } else {
            this.stage.focus({preventScroll: true});
        }
    }

    /**
     * Tells leafrtool subplugins (e.g. leafrtool_confirm) that the reading requirement is met,
     * without the core reader having to know they exist. The event carries the cmid so a page
     * with more than one reader still works.
     */
    announceCompletion() {
        this.announced = true;
        document.dispatchEvent(new CustomEvent('leafr:reading-complete', {detail: {cmid: this.cmid}}));
    }

    /**
     * Shows a short confirmation when the activity has been completed by reading.
     */
    showCompletion() {
        this.announceCompletion();
        if (this.completed) {
            return;
        }
        this.completed = true;
        const toast = this.root.querySelector('[data-region="completion"]');
        toast.hidden = false;
        setTimeout(() => {
            toast.hidden = true;
        }, 6000);
    }

    /**
     * Shows or hides the loading indicator.
     *
     * @param {boolean} loading Whether the document is loading
     */
    setLoading(loading) {
        this.root.querySelector('[data-region="loading"]').hidden = !loading;
        this.root.classList.toggle('is-loading', loading);
    }

    /**
     * Shows the error message.
     */
    showError() {
        this.setLoading(false);
        this.root.querySelector('[data-region="error"]').hidden = false;
        this.viewHost.hidden = true;
    }

    /**
     * Decodes compact page ranges (e.g. "1-5,7") as used by {@see \mod_leafr\local\progress}.
     *
     * @param {string} encoded Encoded ranges
     * @returns {Set<number>}
     */
    static parsePageRanges(encoded) {
        const pages = new Set();
        (encoded || '').split(',').forEach((part) => {
            part = part.trim();
            if (!part) {
                return;
            }
            if (part.includes('-')) {
                const [from, to] = part.split('-').map((value) => parseInt(value, 10));
                for (let p = from; p <= to; p++) {
                    pages.add(p);
                }
            } else {
                pages.add(parseInt(part, 10));
            }
        });
        return pages;
    }

    /**
     * Parses the bookmarks passed by the server as a JSON string.
     *
     * @param {string} encoded JSON array of {page, note}
     * @returns {Array<{page: number, note: string}>}
     */
    static parseBookmarks(encoded) {
        try {
            const parsed = JSON.parse(encoded || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }
}

/**
 * Initialises a reader.
 *
 * @param {string} selector CSS selector of the reader element
 */
export const init = (selector) => {
    const root = document.querySelector(selector);
    if (root && !root.dataset.initialised) {
        root.dataset.initialised = '1';
        new Reader(root).init();
    }
};

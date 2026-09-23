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

/** Time a page turn animation needs, in milliseconds. */
const TURN_DURATION = 900;

/** Available zoom factors. */
const ZOOM_STEPS = [0.5, 0.75, 1, 1.25, 1.5, 2, 2.5, 3];

/** How long a search match stays highlighted, in milliseconds. */
const HIGHLIGHT_DURATION = 4000;

/** Language strings used by the reader. */
const STRING_KEYS = [
    'pagelabel', 'pageofpages', 'pagesofpages', 'totalpages', 'toc_empty',
    'fullscreen_enter', 'fullscreen_exit', 'zoomlevel', 'continuenotice', 'progresssummary',
    'search_label', 'search_placeholder', 'search_indexing', 'search_noresults',
    'search_noresults_scan', 'search_next', 'search_prev', 'matchofmatches',
    'bookmark_empty', 'bookmark_note_label', 'bookmark_note_placeholder', 'bookmark_remove',
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
        this.stage = root.querySelector('[data-region="stage"]');
        this.viewHost = root.querySelector('[data-region="view"]');
        this.pageInput = root.querySelector('[data-region="page-input"]');
        this.pageTotal = root.querySelector('[data-region="page-total"]');
        this.progressBar = root.querySelector('[data-region="progress"]');
        this.progressFill = root.querySelector('[data-region="progress-fill"]');
        this.progressSummary = root.querySelector('[data-region="progress-summary"]');
        this.continueToast = root.querySelector('[data-region="continue"]');
        this.live = root.querySelector('[data-region="live"]');
        this.helpDialog = root.querySelector('[data-region="help"]');
        this.sidebarRoot = root.querySelector('[data-region="sidebar"]');
        this.sidebarToggle = root.querySelector('[data-action="sidebar"]');
        this.zoomLabel = root.querySelector('[data-region="zoom-label"]');
        this.view = null;
        this.sidebar = null;
        this.toc = null;
        this.thumbnails = null;
        this.search = null;
        this.bookmarkList = null;
        this.highlightTimer = null;
        this.bookmarkButton = root.querySelector('[data-action="bookmark"]');
        this.bookmarks = new Map(Reader.parseBookmarks(root.dataset.bookmarks).map((b) => [b.page, b.note]));
        this.zoomIndex = ZOOM_STEPS.indexOf(1);
        this.announceTimer = null;
        this.resizeTimer = null;
        this.continueTimer = null;
        this.helpReturnFocus = null;
        this.initialPage = Math.max(1, parseInt(root.dataset.initialpage, 10) || 1);
        this.continuePage = parseInt(root.dataset.lastpage, 10) || 0;
        this.seenPages = Reader.parsePageRanges(root.dataset.seenpages);

        const stored = root.dataset.simpleview;
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        this.simpleView = stored === '' ? reducedMotion : stored === '1';
    }

    /**
     * Loads the document and shows it.
     *
     * @returns {Promise<void>}
     */
    async init() {
        const pending = new Pending('mod_leafr/reader:init');
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
     * Makes the reader as high as the window below the fixed or sticky bars of the theme, so its
     * own toolbar always stays visible. Some themes stack more than one such bar (e.g. a site
     * navbar plus a second, theme-specific course navigation bar), so all of them are measured.
     */
    fitHeight() {
        if (document.fullscreenElement === this.root) {
            this.root.style.removeProperty('--leafr-height');
            return;
        }
        const offset = this.getFixedTopOffset();
        this.root.style.setProperty('--leafr-height', Math.max(window.innerHeight - offset - 32, 420) + 'px');
    }

    /**
     * Measures how far down the fixed or sticky bars anchored to the top of the page reach.
     *
     * @returns {number} Offset in pixels
     */
    getFixedTopOffset() {
        let bottom = 0;
        document.body.querySelectorAll('*').forEach((el) => {
            if (this.root.contains(el) || !el.offsetHeight) {
                return;
            }
            const position = window.getComputedStyle(el).position;
            if (position !== 'fixed' && position !== 'sticky') {
                return;
            }
            const rect = el.getBoundingClientRect();
            if (rect.top <= 4 && rect.bottom > bottom) {
                bottom = rect.bottom;
            }
        });
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
        this.root.querySelector('[data-action="simpleview"]').setAttribute('aria-pressed', this.simpleView ? 'true' : 'false');
        this.stage.scrollTo(0, 0);

        this.view = new ViewClass({
            stage: this.stage,
            host: this.viewHost,
            pdfDoc: this.pdfDoc,
            startPage: this.page,
            strings: this.strings,
            onPageChange: (page, visible) => this.handlePageChange(page, visible),
        });
        await this.view.init();
        if (ZOOM_STEPS[this.zoomIndex] !== 1) {
            this.view.setZoom(ZOOM_STEPS[this.zoomIndex]);
        }
        this.applyBookmarkFlags();
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
        try {
            entries = await getOutline(this.pdfDoc);
        } catch (error) {
            entries = [];
        }
        if (!entries.length) {
            button.title = this.strings.toc_empty;
            return;
        }
        this.toc = new Toc({
            panel: panel,
            entries: entries,
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
     * Adds a bookmark for the current page, or removes it if there already is one.
     */
    toggleBookmark() {
        if (this.bookmarks.has(this.page)) {
            this.removeBookmark(this.page);
        } else {
            this.addBookmark(this.page, '');
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
            this.updateBookmarkFlag(page);
            this.updateBookmarkButton();
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
     * @returns {Promise<void>}
     */
    async saveBookmarkNote(page, note) {
        try {
            const result = await Ajax.call([{
                methodname: 'mod_leafr_bookmark_set',
                args: {cmid: this.cmid, pageno: page, note: note},
            }])[0];
            this.bookmarks.set(page, result.note);
        } catch (error) {
            return;
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
            this.updateBookmarkFlag(page);
            this.updateBookmarkButton();
            if (this.bookmarkList) {
                this.bookmarkList.remove(page);
            }
        } catch (error) {
            return;
        }
    }

    /**
     * Shows or hides the corner flag of a page depending on whether it is bookmarked.
     *
     * @param {number} page Page number
     */
    updateBookmarkFlag(page) {
        const target = this.viewHost.querySelector('[data-page="' + page + '"]');
        if (target) {
            target.classList.toggle('is-bookmarked', this.bookmarks.has(page));
        }
    }

    /**
     * Re-applies the corner flags of all bookmarked pages, e.g. after the view was rebuilt.
     */
    applyBookmarkFlags() {
        this.bookmarks.forEach((note, page) => this.updateBookmarkFlag(page));
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
        const last = visible.length ? visible[visible.length - 1] : page;
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
            this.thumbnails.setCurrentPage(page);
            this.thumbnails.markSeen(visible);
        }
        visible.forEach((visiblepage) => this.seenPages.add(visiblepage));
        this.updateProgressSummary();
        this.updateBookmarkButton();
        this.applyBookmarkFlags();
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
            const button = this.root.querySelector('[data-action="fullscreen"]');
            const label = active ? this.strings.fullscreen_exit : this.strings.fullscreen_enter;
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
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
        const pending = new Pending('mod_leafr/reader:toggleview');
        this.simpleView = !this.simpleView;
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
     * Shows a short confirmation when the activity has been completed by reading.
     */
    showCompletion() {
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

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
 * Full text search across the whole PDF, built on top of PDF.js getTextContent().
 *
 * The document is indexed lazily, once the search tab is opened for the first time. Matches are
 * found per text item (roughly a line fragment), which is enough to build a short snippet and to
 * place a highlight box over the match (see mod_leafr/pdf getTextItems).
 *
 * @module     mod_leafr/search
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getTextItems} from 'mod_leafr/pdf';

/** Text items kept as context around a match for the snippet. */
const CONTEXT_ITEMS = 4;

export default class Search {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {HTMLElement} options.host Panel the search UI is rendered into
     * @param {Object} options.pdfDoc PDF.js document proxy
     * @param {number} options.total Number of pages
     * @param {Object} options.strings Language strings
     * @param {Function} options.onNavigate Called with (pageNum, rect) to show and highlight a match
     */
    constructor(options) {
        this.host = options.host;
        this.pdfDoc = options.pdfDoc;
        this.total = options.total;
        this.strings = options.strings;
        this.onNavigate = options.onNavigate;
        this.pages = null;
        this.indexing = null;
        this.matches = [];
        this.activeIndex = -1;
        this.searchTimer = null;
    }

    /**
     * Builds the UI. Indexing itself is deferred until the first search.
     */
    init() {
        this.host.innerHTML = '';
        const form = document.createElement('form');
        form.className = 'leafr-search-form';
        form.setAttribute('role', 'search');

        const label = document.createElement('label');
        label.className = 'sr-only';
        label.setAttribute('for', 'leafr-search-input-' + Math.random().toString(36).slice(2));
        label.textContent = this.strings.search_label;
        this.input = document.createElement('input');
        this.input.type = 'search';
        this.input.id = label.htmlFor;
        this.input.className = 'leafr-search-input';
        this.input.placeholder = this.strings.search_placeholder;
        this.input.autocomplete = 'off';

        this.status = document.createElement('div');
        this.status.className = 'leafr-search-status';
        this.status.setAttribute('role', 'status');

        const nav = document.createElement('div');
        nav.className = 'leafr-search-nav';
        // Not "prev"/"next": those data-action values are already used by the toolbar's page
        // navigation buttons, and the reader listens for clicks on [data-action] anywhere in the
        // whole reader element, including inside this sidebar panel.
        this.prevButton = this.makeNavButton('search-prev', this.strings.search_prev);
        this.nextButton = this.makeNavButton('search-next', this.strings.search_next);
        nav.append(this.prevButton, this.nextButton);

        this.results = document.createElement('ul');
        this.results.className = 'leafr-search-results';

        form.append(label, this.input, nav);
        this.host.append(form, this.status, this.results);
        this.status.textContent = this.strings.search_hint;

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            this.runSearch();
        });
        this.input.addEventListener('input', () => {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.runSearch(), 400);
        });
        this.prevButton.addEventListener('click', () => this.step(-1));
        this.nextButton.addEventListener('click', () => this.step(1));
        // Arrow keys move through the result list (from the search field into it and back); Enter or
        // Space on a result jumps to it like a click (Peter's feedback, issue #6: the arrows only
        // scrolled the list).
        this.input.addEventListener('keydown', (event) => {
            if (event.key === 'ArrowDown' && this.matches.length) {
                event.preventDefault();
                this.focusResult(Math.max(this.activeIndex, 0));
            }
        });
        this.results.addEventListener('keydown', (event) => this.handleResultKey(event));
    }

    /**
     * Keyboard navigation inside the result list.
     *
     * @param {KeyboardEvent} event Key event
     */
    handleResultKey(event) {
        const buttons = [...this.results.querySelectorAll('.leafr-search-result')];
        const index = buttons.indexOf(event.target);
        if (index < 0) {
            return;
        }
        const targets = {ArrowDown: index + 1, ArrowUp: index - 1, Home: 0, End: buttons.length - 1};
        if (!(event.key in targets)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        const target = targets[event.key];
        if (target < 0) {
            this.input.focus();
        } else {
            this.focusResult(Math.min(target, buttons.length - 1));
        }
    }

    /**
     * Moves the keyboard focus to one result without jumping to it yet.
     *
     * @param {number} index Index of the result
     */
    focusResult(index) {
        const button = this.results.querySelectorAll('.leafr-search-result')[index];
        if (button) {
            button.focus();
            button.scrollIntoView({block: 'nearest'});
        }
    }

    /**
     * Creates a previous/next match button.
     *
     * @param {string} action Action name
     * @param {string} label Accessible label
     * @returns {HTMLButtonElement}
     */
    makeNavButton(action, label) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'leafr-iconbtn';
        button.dataset.action = action;
        button.title = label;
        button.disabled = true;
        const text = document.createElement('span');
        text.className = 'sr-only';
        text.textContent = label;
        // Same line icons as the toolbar's page navigation, pointing up for the previous match.
        const path = action === 'search-prev'
            ? 'M213.66 165.66a8 8 0 0 1-11.32 0L128 91.31l-74.34 74.35a8 8 0 0 1-11.32-11.32'
                + 'l80-80a8 8 0 0 1 11.32 0l80 80a8 8 0 0 1 0 11.32Z'
            : 'M213.66 101.66l-80 80a8 8 0 0 1-11.32 0l-80-80a8 8 0 0 1 11.32-11.32'
                + 'L128 164.69l74.34-74.35a8 8 0 0 1 11.32 11.32Z';
        const ns = 'http://www.w3.org/2000/svg';
        const arrow = document.createElementNS(ns, 'svg');
        arrow.setAttribute('aria-hidden', 'true');
        arrow.setAttribute('focusable', 'false');
        arrow.setAttribute('width', '18');
        arrow.setAttribute('height', '18');
        arrow.setAttribute('viewBox', '0 0 256 256');
        arrow.setAttribute('fill', 'currentColor');
        const arrowPath = document.createElementNS(ns, 'path');
        arrowPath.setAttribute('d', path);
        arrow.appendChild(arrowPath);
        button.append(text, arrow);
        return button;
    }

    /**
     * Moves the focus into the search field when the tab is opened.
     */
    focus() {
        if (this.input) {
            this.input.focus();
        }
    }

    /**
     * Builds the search index once, the first time it is needed.
     *
     * @returns {Promise<void>}
     */
    async ensureIndex() {
        if (this.pages) {
            return;
        }
        if (!this.indexing) {
            this.indexing = (async() => {
                const pages = [];
                for (let i = 1; i <= this.total; i++) {
                    pages.push({page: i, items: await getTextItems(this.pdfDoc, i)});
                }
                this.pages = pages;
            })();
        }
        await this.indexing;
    }

    /**
     * Runs a search for the current input value.
     *
     * @returns {Promise<void>}
     */
    async runSearch() {
        const query = this.input.value.trim();
        this.results.innerHTML = '';
        this.matches = [];
        this.activeIndex = -1;
        this.updateNavButtons();
        if (!query) {
            this.status.textContent = this.strings.search_hint;
            return;
        }

        this.status.textContent = this.strings.search_indexing;
        await this.ensureIndex();

        const hasAnyText = this.pages.some((page) => page.items.length);
        if (!hasAnyText) {
            this.status.textContent = this.strings.search_noresults_scan;
            return;
        }

        const needle = query.toLocaleLowerCase();
        this.pages.forEach(({page, items}) => {
            items.forEach((item, itemIndex) => {
                const haystack = item.str.toLocaleLowerCase();
                if (haystack.includes(needle)) {
                    this.matches.push({page, item, snippet: this.buildSnippet(items, itemIndex, query)});
                }
            });
        });

        if (!this.matches.length) {
            this.status.textContent = this.strings.search_noresults;
            return;
        }

        this.renderResults();
        this.activeIndex = 0;
        this.updateNavButtons();
        this.showActiveMatch(false);
    }

    /**
     * Builds a text snippet around a match, with the query highlighted.
     *
     * @param {Array} items All text items of the page
     * @param {number} itemIndex Index of the matching item
     * @param {string} query Search query
     * @returns {string} HTML snippet
     */
    buildSnippet(items, itemIndex, query) {
        const from = Math.max(0, itemIndex - CONTEXT_ITEMS);
        const to = Math.min(items.length, itemIndex + CONTEXT_ITEMS + 1);
        const text = items.slice(from, to).map((item) => item.str).join(' ');
        const index = text.toLocaleLowerCase().indexOf(query.toLocaleLowerCase());
        if (index === -1) {
            return this.escapeHtml(text);
        }
        const before = this.escapeHtml(text.slice(0, index));
        const match = this.escapeHtml(text.slice(index, index + query.length));
        const after = this.escapeHtml(text.slice(index + query.length));
        return (from > 0 ? '… ' : '') + before + '<mark>' + match + '</mark>' + after + (to < items.length ? ' …' : '');
    }

    /**
     * Escapes text for use inside HTML.
     *
     * @param {string} text Text
     * @returns {string}
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Renders the result list.
     */
    renderResults() {
        this.results.innerHTML = '';
        this.matches.forEach((match, index) => {
            const li = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'leafr-search-result';
            const page = document.createElement('span');
            page.className = 'leafr-search-result-page';
            page.textContent = this.strings.pagelabel.replace('{$a}', match.page);
            const snippet = document.createElement('span');
            snippet.className = 'leafr-search-result-snippet';
            snippet.innerHTML = match.snippet;
            button.append(page, snippet);
            button.addEventListener('click', () => {
                this.activeIndex = index;
                this.updateNavButtons();
                this.showActiveMatch(true);
            });
            li.appendChild(button);
            this.results.appendChild(li);
        });
    }

    /**
     * Moves to the next or previous match.
     *
     * @param {number} direction 1 = next, -1 = previous
     */
    step(direction) {
        if (!this.matches.length) {
            return;
        }
        this.activeIndex = (this.activeIndex + direction + this.matches.length) % this.matches.length;
        this.updateNavButtons();
        this.showActiveMatch(true);
    }

    /**
     * Navigates to and highlights the active match.
     *
     * @param {boolean} moveFocus Whether to move the focus to the result
     */
    showActiveMatch(moveFocus) {
        const match = this.matches[this.activeIndex];
        if (!match) {
            return;
        }
        this.results.querySelectorAll('.leafr-search-result').forEach((button, index) => {
            button.classList.toggle('is-active', index === this.activeIndex);
            if (index === this.activeIndex && moveFocus) {
                button.focus();
            }
        });
        this.status.textContent = this.strings.matchofmatches
            .replace('{$a->index}', this.activeIndex + 1)
            .replace('{$a->total}', this.matches.length);
        this.onNavigate(match.page, match.item);
    }

    /**
     * Enables or disables the next/previous buttons.
     */
    updateNavButtons() {
        const has = this.matches.length > 0;
        this.prevButton.disabled = !has;
        this.nextButton.disabled = !has;
    }
}

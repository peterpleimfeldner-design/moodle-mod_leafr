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
 * Table of contents built from the bookmarks (outline) of the PDF.
 *
 * @module     mod_leafr/toc
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export default class Toc {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {HTMLElement} options.panel The navigation panel
     * @param {HTMLElement} options.toggle The toolbar button that opens the panel
     * @param {Array} options.entries Outline entries {title, page, children}
     * @param {Function} options.onNavigate Called with the page number of a chosen entry
     */
    constructor(options) {
        this.panel = options.panel;
        this.toggleButton = options.toggle;
        this.onNavigate = options.onNavigate;
        this.content = this.panel.querySelector('[data-region="toc-content"]');
        this.links = [];
        this.render(options.entries);
    }

    /**
     * Renders the entries as nested lists.
     *
     * @param {Array} entries Outline entries
     */
    render(entries) {
        const build = (items) => {
            const list = document.createElement('ol');
            list.className = 'leafr-toc-list';
            items.forEach((item) => {
                const li = document.createElement('li');
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'leafr-toc-link';
                button.dataset.page = item.page;
                const title = document.createElement('span');
                title.className = 'leafr-toc-link-title';
                title.textContent = item.title;
                const page = document.createElement('span');
                page.className = 'leafr-toc-link-page';
                page.textContent = item.page;
                button.append(title, page);
                button.addEventListener('click', () => {
                    this.onNavigate(item.page);
                    if (window.matchMedia('(max-width: 767.98px)').matches) {
                        this.close();
                    }
                });
                li.appendChild(button);
                this.links.push(button);
                if (item.children && item.children.length) {
                    li.appendChild(build(item.children));
                }
                list.appendChild(li);
            });
            return list;
        };
        this.content.innerHTML = '';
        this.content.appendChild(build(entries));
    }

    /**
     * Whether the panel is open.
     *
     * @returns {boolean}
     */
    isOpen() {
        return !this.panel.hidden;
    }

    /**
     * Opens the panel and moves the focus into it.
     */
    open() {
        this.panel.hidden = false;
        this.toggleButton.setAttribute('aria-expanded', 'true');
        const target = this.content.querySelector('.leafr-toc-link.is-current') || this.content.querySelector('.leafr-toc-link');
        if (target) {
            target.focus();
            target.scrollIntoView({block: 'nearest'});
        }
    }

    /**
     * Closes the panel and returns the focus to the toolbar button.
     *
     * @param {boolean} restoreFocus Whether to focus the toolbar button
     */
    close(restoreFocus = true) {
        this.panel.hidden = true;
        this.toggleButton.setAttribute('aria-expanded', 'false');
        if (restoreFocus) {
            this.toggleButton.focus();
        }
    }

    /**
     * Toggles the panel.
     */
    toggle() {
        if (this.isOpen()) {
            this.close();
        } else {
            this.open();
        }
    }

    /**
     * Marks the section that contains the given page.
     *
     * @param {number} pageNum Current page
     */
    setCurrentPage(pageNum) {
        let current = null;
        this.links.forEach((link) => {
            const page = parseInt(link.dataset.page, 10);
            if (page <= pageNum && (!current || page >= parseInt(current.dataset.page, 10))) {
                current = link;
            }
        });
        this.links.forEach((link) => {
            const active = link === current;
            link.classList.toggle('is-current', active);
            if (active) {
                link.setAttribute('aria-current', 'location');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    }
}

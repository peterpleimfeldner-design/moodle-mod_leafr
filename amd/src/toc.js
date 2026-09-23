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
 * Content of the "Contents" sidebar tab, built from the bookmarks (outline) of the PDF.
 * Showing and hiding the tab itself is handled by mod_leafr/sidebar.
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
     * @param {HTMLElement} options.panel The tab panel the outline is rendered into
     * @param {Array} options.entries Outline entries {title, page, children}
     * @param {Function} options.onNavigate Called with the page number of a chosen entry
     */
    constructor(options) {
        this.panel = options.panel;
        this.onNavigate = options.onNavigate;
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
                button.addEventListener('click', () => this.onNavigate(item.page));
                li.appendChild(button);
                this.links.push(button);
                if (item.children && item.children.length) {
                    li.appendChild(build(item.children));
                }
                list.appendChild(li);
            });
            return list;
        };
        this.panel.innerHTML = '';
        this.panel.appendChild(build(entries));
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

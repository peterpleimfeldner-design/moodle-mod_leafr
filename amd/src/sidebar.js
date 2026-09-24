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
 * The reader's sidebar: a tabbed panel for thumbnails, the table of contents and search.
 *
 * @module     mod_leafr/sidebar
 * @copyright  2026 Peter Pleimfeldner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Viewports narrower than this show the sidebar as a full-width overlay. */
const NARROW_QUERY = '(max-width: 767.98px)';

export default class Sidebar {

    /**
     * Constructor.
     *
     * @param {Object} options
     * @param {HTMLElement} options.root The sidebar element
     * @param {HTMLElement} options.toggle The toolbar button that opens and closes the sidebar
     * @param {Array<{name: string, button: HTMLElement, panel: HTMLElement}>} options.tabs Tabs, in order
     */
    constructor(options) {
        this.root = options.root;
        this.toggleButton = options.toggle;
        this.tabs = options.tabs;
        this.activateCallbacks = new Map();
        this.activatedOnce = new Set();
        this.activeName = this.tabs.length ? this.tabs[0].name : null;
        this.bind();
    }

    /**
     * Registers a callback that runs once, the first time a tab is activated. Used to defer
     * expensive work (rendering thumbnails, indexing the document for search) until needed.
     *
     * @param {string} name Tab name
     * @param {Function} callback
     */
    onFirstActivate(name, callback) {
        this.activateCallbacks.set(name, callback);
    }

    /**
     * Wires up the tab buttons.
     */
    bind() {
        this.tabs.forEach((tab) => {
            tab.button.addEventListener('click', () => {
                if (!tab.button.disabled) {
                    this.activate(tab.name);
                }
            });
            tab.button.addEventListener('keydown', (event) => this.handleTabKey(event));
        });
    }

    /**
     * Moves the tab focus and selection with the arrow keys (the standard ARIA tabs pattern).
     *
     * @param {KeyboardEvent} event Key event
     */
    handleTabKey(event) {
        const enabled = this.tabs.filter((tab) => !tab.button.disabled);
        const index = enabled.findIndex((tab) => tab.button === event.target);
        if (index === -1) {
            return;
        }
        const moves = {ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1};
        let target = null;
        if (moves[event.key]) {
            target = enabled[(index + moves[event.key] + enabled.length) % enabled.length];
        } else if (event.key === 'Home') {
            target = enabled[0];
        } else if (event.key === 'End') {
            target = enabled[enabled.length - 1];
        }
        if (target) {
            event.preventDefault();
            this.activate(target.name);
            target.button.focus();
        }
    }

    /**
     * Activates a tab: shows its panel, hides the others, and runs its first-activation callback.
     *
     * @param {string} name Tab name
     */
    activate(name) {
        const tab = this.tabs.find((candidate) => candidate.name === name);
        if (!tab || tab.button.disabled) {
            return;
        }
        this.activeName = name;
        this.tabs.forEach((candidate) => {
            const active = candidate === tab;
            candidate.button.setAttribute('aria-selected', active ? 'true' : 'false');
            candidate.button.tabIndex = active ? 0 : -1;
            candidate.panel.hidden = !active;
        });
        if (!this.activatedOnce.has(name)) {
            this.activatedOnce.add(name);
            const callback = this.activateCallbacks.get(name);
            if (callback) {
                callback();
            }
        }
    }

    /**
     * Whether the sidebar is open.
     *
     * @returns {boolean}
     */
    isOpen() {
        return !this.root.hidden;
    }

    /**
     * Opens the sidebar and moves the focus into it.
     *
     * @param {string} name Tab to show, defaults to the last active one
     */
    open(name) {
        this.root.hidden = false;
        this.toggleButton.setAttribute('aria-expanded', 'true');
        this.activate(name || this.activeName);
        const active = this.tabs.find((tab) => tab.name === this.activeName);
        if (active) {
            active.button.focus();
        }
    }

    /**
     * Closes the sidebar and returns the focus to the toolbar button.
     *
     * @param {boolean} restoreFocus Whether to focus the toolbar button
     */
    close(restoreFocus = true) {
        this.root.hidden = true;
        this.toggleButton.setAttribute('aria-expanded', 'false');
        if (restoreFocus) {
            this.toggleButton.focus();
        }
    }

    /**
     * Closes the sidebar automatically after a navigation, but only on narrow screens where it
     * overlays the document instead of sitting next to it.
     */
    closeOnNarrowScreen() {
        if (window.matchMedia(NARROW_QUERY).matches) {
            this.close(false);
        }
    }

    /**
     * Opens the sidebar with the given tab, or closes it if that tab is already showing.
     *
     * @param {string} name Tab name
     */
    toggle(name) {
        if (this.isOpen() && this.activeName === name) {
            this.close();
        } else {
            this.open(name);
        }
    }
}

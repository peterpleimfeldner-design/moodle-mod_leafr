// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Bookmarks UI module for mod_leafr (Pro feature).
 * Handles create, read, update, delete of named bookmarks.
 *
 * @module     mod_leafr/bookmarks
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function () {

    'use strict';

    /** @type {Object} Configuration */
    let cfg = {};

    /** @type {Function} Navigation callback */
    let onNavigateCallback = null;

    /** @type {Array} Current bookmarks */
    let bookmarks = [];

    /**
     * Initialize the bookmarks module.
     *
     * @param {Object} options
     * @param {number} options.cmid Course module ID
     * @param {Object} options.strings Language strings
     * @param {Function} options.onNavigate Navigation callback(pageNum)
     */
    async function init(options) {
        cfg = options;
        onNavigateCallback = options.onNavigate;

        // Load existing bookmarks.
        bookmarks = await loadBookmarks();
        renderBookmarkList();

        // Wire up "add bookmark" button.
        const addBtn = document.getElementById('leafr-bookmark-add');
        if (addBtn) {
            addBtn.addEventListener('click', () => {
                const currentPage = parseInt(document.getElementById('leafr-page-input')?.value || '1', 10);
                showBookmarkModal(currentPage);
            });
        }
    }

    const MAX_BOOKMARKS_FREE = 1;

    /**
     * Load bookmarks from Moodle user preferences.
     *
     * @return {Promise<Array>}
     */
    async function loadBookmarks() {
        try {
            const raw = M.util.get_user_preference('leafr_bookmarks_' + cfg.cmid, '[]');
            return JSON.parse(raw);
        } catch (e) {
            return [];
        }
    }

    /**
     * Render the bookmark list in the panel.
     */
    function renderBookmarkList() {
        const list = document.getElementById('leafr-bookmark-list');
        if (!list) {
            return;
        }

        list.innerHTML = '';

        if (bookmarks.length === 0) {
            list.innerHTML = '<li class="leafr-bookmark-empty">' + (cfg.strings.bookmark_empty || 'Noch keine Lesezeichen gesetzt.') + '</li>';
            return;
        }

        bookmarks.forEach((bm) => {
            const li = document.createElement('li');
            li.className = 'leafr-bookmark-item';
            li.dataset.id = bm.id;

            const pageBtn = document.createElement('button');
            pageBtn.className = 'leafr-bookmark-page';
            pageBtn.textContent = 'S.' + bm.pageno;
            pageBtn.setAttribute('aria-label', 'Zu Seite ' + bm.pageno + ' navigieren');
            pageBtn.addEventListener('click', () => {
                if (onNavigateCallback) {
                    onNavigateCallback(bm.pageno);
                }
            });

            const labelEl = document.createElement('span');
            labelEl.className = 'leafr-bookmark-label';
            labelEl.textContent = bm.label;
            labelEl.setAttribute('title', bm.note || '');

            const deleteBtn = document.createElement('button');
            deleteBtn.className = 'leafr-bookmark-delete';
            deleteBtn.setAttribute('aria-label', 'Lesezeichen löschen: ' + bm.label);
            deleteBtn.innerHTML = '&times;';
            deleteBtn.addEventListener('click', () => deleteBookmark(bm.id));

            li.appendChild(pageBtn);
            li.appendChild(labelEl);
            li.appendChild(deleteBtn);
            list.appendChild(li);
        });
    }

    /**
     * Show the modal to create a new bookmark.
     *
     * @param {number} pageNum Current page number
     */
    function showBookmarkModal(pageNum) {
        const modal = document.getElementById('leafr-bookmark-modal');
        if (!modal) {
            return;
        }

        const pageInput = modal.querySelector('#leafr-bm-page');
        const labelInput = modal.querySelector('#leafr-bm-label');
        const noteInput = modal.querySelector('#leafr-bm-note');
        const saveBtn = modal.querySelector('#leafr-bm-save');
        const cancelBtn = modal.querySelector('#leafr-bm-cancel');

        if (pageInput) { pageInput.value = pageNum; }
        if (labelInput) { labelInput.value = ''; }
        if (noteInput) { noteInput.value = ''; }

        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        if (labelInput) { labelInput.focus(); }

        const save = async () => {
            const label = labelInput?.value?.trim();
            if (!label) {
                if (labelInput) { labelInput.focus(); }
                return;
            }
            await createBookmark(parseInt(pageInput?.value || pageNum, 10), label, noteInput?.value || '');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        };

        const cancel = () => {
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
        };

        if (saveBtn) { saveBtn.onclick = save; }
        if (cancelBtn) { cancelBtn.onclick = cancel; }

        modal.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                cancel();
            }
        });
    }

    /**
     * Create a new bookmark via Moodle user preferences (Free Tier: Max 1).
     *
     * @param {number} pageNum Page number
     * @param {string} label Label
     * @param {string} note Optional note
     */
    async function createBookmark(pageNum, label, note) {
        if (bookmarks.length >= MAX_BOOKMARKS_FREE) {
            showSnackbar('Nur 1 Lesezeichen im Free-Tier. Upgrade auf Pro für mehr.');
            return;
        }

        const newId = Date.now();
        bookmarks.unshift({
            id: newId,
            pageno: pageNum,
            label: label || ('Seite ' + pageNum),
            note: note || '',
            timecreated: Math.floor(Date.now() / 1000),
            timemodified: Math.floor(Date.now() / 1000),
        });

        M.util.set_user_preference('leafr_bookmarks_' + cfg.cmid, JSON.stringify(bookmarks));

        renderBookmarkList();
        showSnackbar(cfg.strings.bookmark_saved || 'Lesezeichen gespeichert.');
    }

    /**
     * Delete a bookmark from preferences with undo functionality.
     *
     * @param {number} bookmarkId Bookmark ID
     */
    async function deleteBookmark(bookmarkId) {
        const bm = bookmarks.find((b) => b.id === bookmarkId);
        if (!bm) {
            return;
        }

        // Remove from local list and save to preference
        bookmarks = bookmarks.filter((b) => b.id !== bookmarkId);
        M.util.set_user_preference('leafr_bookmarks_' + cfg.cmid, JSON.stringify(bookmarks));
        renderBookmarkList();

        // Show undo snackbar for 5 seconds.
        showSnackbar(
            (cfg.strings.bookmark_deleted || 'Lesezeichen gelöscht.') + ' ',
            'Rückgängig',
            () => {
                bookmarks.unshift(bm);
                bookmarks.sort((a, b) => b.timemodified - a.timemodified);
                M.util.set_user_preference('leafr_bookmarks_' + cfg.cmid, JSON.stringify(bookmarks));
                renderBookmarkList();
            },
            5000
        );
    }

    /**
     * Show a snackbar notification.
     *
     * @param {string} message Main message
     * @param {string|null} actionLabel Action button label
     * @param {Function|null} actionHandler Action click handler
     * @param {number} duration Duration in ms
     */
    function showSnackbar(message, actionLabel = null, actionHandler = null, duration = 3000) {
        let snackbar = document.getElementById('leafr-snackbar');
        if (!snackbar) {
            snackbar = document.createElement('div');
            snackbar.id = 'leafr-snackbar';
            snackbar.className = 'leafr-snackbar';
            snackbar.setAttribute('role', 'status');
            snackbar.setAttribute('aria-live', 'polite');
            document.body.appendChild(snackbar);
        }

        snackbar.innerHTML = '';
        const msgEl = document.createElement('span');
        msgEl.textContent = message;
        snackbar.appendChild(msgEl);

        if (actionLabel && actionHandler) {
            const btn = document.createElement('button');
            btn.className = 'leafr-snackbar-action';
            btn.textContent = actionLabel;
            btn.addEventListener('click', () => {
                actionHandler();
                snackbar.classList.remove('is-visible');
            });
            snackbar.appendChild(btn);
        }

        snackbar.classList.add('is-visible');

        setTimeout(() => {
            snackbar.classList.remove('is-visible');
        }, duration);
    }

    /**
     * Call a Moodle Web Service.
     *
     * @param {string} methodname
     * @param {Object} args
     * @return {Promise<Object|null>}
     */
    async function callWebService(methodname, args) {
        try {
            const response = await fetch(M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify([{ methodname, args }]),
            });
            const data = await response.json();
            if (data && data[0] && !data[0].error) {
                return data[0].data;
            }
        } catch (e) {
            // Silent fail.
        }
        return null;
    }

    return { init };
});

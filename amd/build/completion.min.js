// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Completion tracking module for mod_leafr.
 * Tracks seen pages and fires completion events via Moodle Web Services.
 *
 * @module     mod_leafr/completion
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function () {

    'use strict';

    /** @type {Set<number>} Set of seen page numbers */
    const seenPages = new Set();

    /** @type {boolean} Whether completion has been fired */
    let completionFired = false;

    /** @type {Object} Configuration */
    let cfg = {};

    /** @type {number|null} Debounce timer for position save */
    let saveTimer = null;

    /** @type {number|null} Debounce timer for completion check */
    let completionTimer = null;

    /**
     * Initialize completion tracking.
     *
     * @param {Object} options
     * @param {number} options.cmid Course module ID
     * @param {number} options.totalPages Total number of pages
     * @param {Object} options.config Completion config from PHP
     * @param {string} options.wwwroot Moodle site root
     * @param {boolean} [options.simpleView] Whether in simple view mode
     */
    function init(options) {
        cfg = options;

        if (options.simpleView) {
            setupIntersectionObserver();
        }
    }

    /**
     * Mark a page as seen and check completion.
     *
     * @param {number} pageNum Page number (1-based)
     */
    function trackPage(pageNum) {
        if (pageNum < 1 || pageNum > cfg.totalPages) {
            return;
        }

        seenPages.add(pageNum);

        // Debounced completion check.
        clearTimeout(completionTimer);
        completionTimer = setTimeout(() => {
            checkCompletion();
        }, 500);
    }

    /**
     * Save the current reading position via user preferences.
     *
     * @param {number} cmid Course module ID
     * @param {number} pageNum Current page number
     */
    function savePosition(cmid, pageNum) {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            if (typeof M !== 'undefined' && M.util && M.util.set_user_preference) {
                M.util.set_user_preference('leafr_pos_' + cmid, pageNum);
            }
        }, 1000);
    }

    /**
     * Check if completion criteria are met and fire if so.
     */
    function checkCompletion() {
        if (completionFired || cfg.config.completionType === 0) {
            return;
        }

        let shouldComplete = false;
        const config = cfg.config;
        const total = cfg.totalPages;

        switch (config.completionType) {
            case 1:
                // Last page.
                shouldComplete = seenPages.has(total);
                break;
            case 2:
                // Percentage.
                shouldComplete = (seenPages.size / total * 100) >= config.completionPercent;
                break;
            case 3:
                // Specific page.
                shouldComplete = seenPages.has(config.completionPage);
                break;
        }

        if (shouldComplete) {
            completionFired = true;
            sendCompletionEvent([...seenPages]);
        }
    }

    /**
     * Send the completion event via Moodle Web Services.
     *
     * @param {number[]} seenPagesArr Array of seen page numbers
     */
    async function sendCompletionEvent(seenPagesArr) {
        const result = await callWebService('mod_leafr_page_viewed', {
            cmid: cfg.cmid,
            seen_pages: seenPagesArr,
        });

        if (result && result.completed) {
            showCompletionFeedback();
        }
    }

    /**
     * Show accessible completion feedback.
     */
    function showCompletionFeedback() {
        // ARIA live region announcement.
        const liveRegion = document.getElementById('leafr-aria-live');
        if (liveRegion) {
            liveRegion.textContent = 'Aktivität als abgeschlossen markiert.';
        }

        // Visual feedback.
        const completionEl = document.getElementById('leafr-completion-notice');
        if (completionEl) {
            completionEl.style.display = 'flex';
            completionEl.setAttribute('aria-hidden', 'false');
            setTimeout(() => {
                completionEl.style.opacity = '0';
                setTimeout(() => {
                    completionEl.style.display = 'none';
                }, 500);
            }, 3000);
        }
    }

    /**
     * Setup IntersectionObserver for simple view page tracking.
     */
    function setupIntersectionObserver() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && entry.intersectionRatio >= 0.5) {
                    const pageEl = entry.target;
                    const pageNum = parseInt(pageEl.dataset.pageNum || pageEl.id.replace('leafr-page-', ''), 10);
                    if (!isNaN(pageNum)) {
                        trackPage(pageNum);
                    }
                }
            });
        }, {
            threshold: 0.5,
        });

        // Observe all simple-view pages.
        document.querySelectorAll('.leafr-simple-page').forEach((el) => {
            observer.observe(el);
        });
    }

    /**
     * Call a Moodle Web Service via AJAX.
     *
     * @param {string} methodname The web service function name
     * @param {Object} args The arguments
     * @return {Promise<Object|null>} The response or null on error
     */
    async function callWebService(methodname, args) {
        try {
            const response = await fetch(M.cfg.wwwroot + '/lib/ajax/service.php?sesskey=' + M.cfg.sesskey, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify([{ methodname, args }]),
            });

            if (!response.ok) {
                return null;
            }

            const data = await response.json();
            if (data && data[0] && !data[0].error) {
                return data[0].data;
            }
        } catch (e) {
            // Silent fail — completion will be re-checked on next page.
        }
        return null;
    }

    return {
        init,
        trackPage,
        savePosition,
    };
});

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Toolbar UI module for mod_leafr.
 * Manages the bottom navigation bar with all controls.
 *
 * @module     mod_leafr/toolbar
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function () {

    'use strict';

    /** @type {number} Total pages */
    let total = 0;

    /** @type {number} Current page */
    let current = 1;

    /** @type {Function} Page change callback */
    let onPageChange = null;

    /** @type {boolean} Mobile layout active */
    const isMobile = window.innerWidth < 768;

    /** Inactive timer for mobile auto-hide */
    let inactiveTimer = null;

    /**
     * Initialize the toolbar.
     *
     * @param {Object} options
     * @param {number} options.totalPages
     * @param {number} options.startPage
     * @param {boolean} options.downloadAllowed
     * @param {string} options.fileurl
     * @param {Object} options.strings
     * @param {boolean} options.simpleView
     * @param {Function} options.onPageChange
     * @param {Function|null} options.onFullscreen
     * @param {Function|null} options.onToggleSimple
     * @param {Function|null} options.onToggleToc
     */
    function init(options) {
        total = options.totalPages;
        current = options.startPage || 1;
        onPageChange = options.onPageChange;

        const toolbar = document.getElementById('leafr-toolbar');
        if (!toolbar) {
            return;
        }

        // Wire up buttons.
        wireButton('leafr-btn-first', () => options.onPageChange(1));
        wireButton('leafr-btn-prev', () => options.onPageChange(current - (options.simpleView ? 1 : 2)));
        wireButton('leafr-btn-next', () => options.onPageChange(current + (options.simpleView ? 1 : 2)));
        wireButton('leafr-btn-last', () => options.onPageChange(total));

        if (options.onFullscreen) {
            wireButton('leafr-btn-fullscreen', options.onFullscreen);
        } else {
            hideButton('leafr-btn-fullscreen');
        }

        if (!options.downloadAllowed) {
            hideButton('leafr-btn-download');
        } else {
            const dlBtn = document.getElementById('leafr-btn-download');
            if (dlBtn) {
                dlBtn.href = options.fileurl + '?forcedownload=1';
                dlBtn.setAttribute('download', '');
            }
        }

        if (options.onToggleToc) {
            wireButton('leafr-btn-toc', options.onToggleToc);
        }

        if (options.onToggleSimple) {
            wireButton('leafr-btn-simple', options.onToggleSimple);
        }

        if (options.onZoomIn) {
            wireButton('leafr-btn-zoom-in', options.onZoomIn);
            wireButton('leafr-btn-zoom-out', options.onZoomOut);
            wireButton('leafr-btn-zoom-reset', options.onZoomReset);
        }

        // Page number input.
        const pageInput = document.getElementById('leafr-page-input');
        if (pageInput) {
            pageInput.setAttribute('aria-label', options.strings.pageof || 'Zur Seite');
            pageInput.addEventListener('change', (e) => {
                const val = parseInt(e.target.value, 10);
                if (!isNaN(val) && val >= 1 && val <= total) {
                    options.onPageChange(val);
                } else {
                    e.target.value = current;
                }
            });
            pageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    e.target.blur();
                }
            });
        }

        // Progress bar click/drag.
        const progressBar = document.getElementById('leafr-progress-bar');
        const progressTrack = document.getElementById('leafr-progress-track');
        if (progressTrack) {
            progressTrack.addEventListener('click', (e) => {
                const rect = progressTrack.getBoundingClientRect();
                const ratio = (e.clientX - rect.left) / rect.width;
                const targetPage = Math.max(1, Math.min(total, Math.round(ratio * total)));
                options.onPageChange(targetPage);
            });

            // Keyboard navigation for progress bar (WCAG 2.5.7).
            progressTrack.setAttribute('role', 'slider');
            progressTrack.setAttribute('aria-valuemin', '1');
            progressTrack.setAttribute('aria-valuemax', String(total));
            progressTrack.setAttribute('aria-valuenow', String(current));
            progressTrack.setAttribute('tabindex', '0');
            progressTrack.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowRight') {
                    options.onPageChange(current + 1);
                } else if (e.key === 'ArrowLeft') {
                    options.onPageChange(current - 1);
                }
            });
        }

        // Hide on mobile after 3s inactivity.
        if (isMobile) {
            setupMobileAutoHide(toolbar);
        }

        // Initial update.
        updatePage(current);

        // Fullscreen change event.
        document.addEventListener('fullscreenchange', () => {
            const isFS = !!document.fullscreenElement;
            const btn = document.getElementById('leafr-btn-fullscreen');
            if (btn) {
                btn.setAttribute('aria-pressed', isFS ? 'true' : 'false');
                const outIcon = btn.querySelector('.leafr-icon-arrows-out');
                const inIcon = btn.querySelector('.leafr-icon-arrows-in');
                if (outIcon && inIcon) {
                    outIcon.style.display = isFS ? 'none' : '';
                    inIcon.style.display = isFS ? '' : 'none';
                }
            }
        });
    }

    /**
     * Update the toolbar to reflect the current page.
     *
     * @param {number} pageNum Current page number
     */
    function updatePage(pageNum) {
        current = pageNum;

        const pageInput = document.getElementById('leafr-page-input');
        const totalEl = document.getElementById('leafr-total-pages');
        const progressFill = document.getElementById('leafr-progress-fill');
        const progressTrack = document.getElementById('leafr-progress-track');

        if (pageInput) {
            if (document.activeElement !== pageInput) {
                pageInput.value = pageNum;
            }
            pageInput.setAttribute('aria-label', 'Seite ' + pageNum + ' von ' + total);
        }
        if (totalEl) {
            totalEl.textContent = total;
        }

        const pct = total > 0 ? (pageNum / total) * 100 : 0;
        if (progressFill) {
            progressFill.style.width = pct.toFixed(1) + '%';
        }
        if (progressTrack) {
            progressTrack.setAttribute('aria-valuenow', String(pageNum));
        }

        // Enable/disable first/prev/next/last buttons.
        setDisabled('leafr-btn-first', pageNum <= 1);
        setDisabled('leafr-btn-prev', pageNum <= 1);
        setDisabled('leafr-btn-next', pageNum >= total);
        setDisabled('leafr-btn-last', pageNum >= total);

        // Update next button aria-label for accessibility.
        const nextBtn = document.getElementById('leafr-btn-next');
        if (nextBtn && pageNum < total) {
            nextBtn.setAttribute('aria-label', 'Nächste Seite (Seite ' + (pageNum + 1) + ' von ' + total + ')');
        }
    }

    /**
     * Wire a click event to a button element.
     *
     * @param {string} id Element ID
     * @param {Function} handler Click handler
     */
    function wireButton(id, handler) {
        const btn = document.getElementById(id);
        if (btn && handler) {
            btn.addEventListener('click', handler);
        }
    }

    /**
     * Hide a button element.
     *
     * @param {string} id Element ID
     */
    function hideButton(id) {
        const btn = document.getElementById(id);
        if (btn) {
            btn.style.display = 'none';
            btn.setAttribute('aria-hidden', 'true');
        }
    }

    /**
     * Set disabled state on a button.
     *
     * @param {string} id Element ID
     * @param {boolean} disabled Whether to disable
     */
    function setDisabled(id, disabled) {
        const btn = document.getElementById(id);
        if (btn) {
            btn.disabled = disabled;
            btn.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        }
    }

    /**
     * Setup mobile auto-hide behavior (hide toolbar after 3s inactivity).
     *
     * @param {HTMLElement} toolbar The toolbar element
     */
    function setupMobileAutoHide(toolbar) {
        function showToolbar() {
            toolbar.classList.add('is-visible');
            clearTimeout(inactiveTimer);
            inactiveTimer = setTimeout(() => {
                toolbar.classList.remove('is-visible');
            }, 3000);
        }

        // Show on touch.
        document.addEventListener('touchstart', showToolbar, { passive: true });
        document.addEventListener('touchmove', showToolbar, { passive: true });

        // Initially show.
        showToolbar();
    }

    return {
        init,
        updatePage,
    };
});

<?php
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
 * Behat steps for mod_leafr: checks on the rendered PDF pages, JavaScript errors, full screen and drawers.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Mink\Exception\ExpectationException;

/**
 * Behat steps for mod_leafr.
 *
 * @package   mod_leafr
 * @category  test
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_leafr extends behat_base {
    /**
     * Starts recording uncaught JavaScript errors, rejected promises nobody handled and console.error()
     * calls of the current page, for the step "no JavaScript errors should have been recorded".
     *
     * @Given /^I start recording JavaScript errors$/
     */
    public function i_start_recording_javascript_errors(): void {
        $this->require_javascript();
        $this->execute_script(<<<'JS'
            (function() {
                if (window.leafrBehatErrors) {
                    return;
                }
                window.leafrBehatErrors = [];
                window.addEventListener('error', function(event) {
                    window.leafrBehatErrors.push('error: ' + event.message + ' (' + event.filename + ':' + event.lineno + ')');
                });
                window.addEventListener('unhandledrejection', function(event) {
                    var reason = event.reason;
                    window.leafrBehatErrors.push('unhandled rejection: ' + (reason && reason.stack ? reason.stack : reason));
                });
                var original = console.error;
                console.error = function() {
                    window.leafrBehatErrors.push('console.error: ' + Array.prototype.join.call(arguments, ' '));
                    return original.apply(console, arguments);
                };
            })();
            JS);
    }

    /**
     * Fails if JavaScript errors were recorded since "I start recording JavaScript errors".
     *
     * @Then /^no JavaScript errors should have been recorded$/
     */
    public function no_javascript_errors_should_have_been_recorded(): void {
        $errors = $this->evaluate_script('return window.leafrBehatErrors || null;');
        if ($errors === null) {
            throw new ExpectationException(
                'JavaScript errors were not being recorded (the page was reloaded?)',
                $this->getSession()
            );
        }
        if (!empty($errors)) {
            throw new ExpectationException(
                "JavaScript errors were recorded:\n" . implode("\n", $errors),
                $this->getSession()
            );
        }
    }

    /**
     * Waits until the canvas of a page in the reader shows something other than an empty page.
     *
     * A part is "whole page", "left half" or "right half". The check passes when at least the given
     * share of the part's pixels is clearly darker than paper (luminance below 200 of 255) after the
     * canvas has been scaled to 200 pixels width; an empty page has 0%. Which canvas is checked:
     * "page" means the page in the book or the simple view, "thumbnail" the thumbnail in the sidebar.
     *
     * @Then /^the Leafr (page|thumbnail) "(\d+)" should be at least "([\d.]+)"% dark in the (whole page|left half|right half)$/
     * @param string $kind "page" or "thumbnail"
     * @param string $page 1-based page number
     * @param string $percent Minimum share of dark pixels in percent
     * @param string $part "whole page", "left half" or "right half"
     */
    public function the_leafr_canvas_should_show_dark_pixels(string $kind, string $page, string $percent, string $part): void {
        $this->require_javascript();
        $selector = $kind === 'thumbnail'
            ? '.leafr-thumb[data-page="' . (int)$page . '"] canvas'
            : '[data-region="view"] [data-page="' . (int)$page . '"] canvas.leafr-page-canvas';
        $from = $part === 'right half' ? 0.5 : 0;
        $to = $part === 'left half' ? 0.5 : 1;
        $script = 'return (function(selector, from, to) {
            var canvas = document.querySelector(selector);
            if (!canvas || !canvas.width || !canvas.height) {
                return -1;
            }
            var width = 200;
            var height = Math.max(1, Math.round(width * canvas.height / canvas.width));
            var small = document.createElement("canvas");
            small.width = width;
            small.height = height;
            var context = small.getContext("2d");
            context.fillStyle = "#fff";
            context.fillRect(0, 0, width, height);
            context.drawImage(canvas, 0, 0, width, height);
            var x0 = Math.floor(width * from);
            var x1 = Math.ceil(width * to);
            var data = context.getImageData(x0, 0, x1 - x0, height).data;
            var dark = 0;
            for (var i = 0; i < data.length; i += 4) {
                if (0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2] < 200) {
                    dark++;
                }
            }
            return 100 * dark / (data.length / 4);
        })(' . json_encode($selector) . ', ' . $from . ', ' . $to . ');';

        $last = null;
        try {
            $this->spin(function () use ($script, $percent, &$last) {
                $last = (float)$this->evaluate_script($script);
                return $last >= (float)$percent;
            }, false, self::get_extended_timeout());
        } catch (Exception $e) {
            $found = $last === null || $last < 0 ? 'no rendered canvas' : sprintf('%.2f%% dark pixels', $last);
            throw new ExpectationException(
                "Leafr {$kind} {$page}: expected at least {$percent}% dark pixels in the {$part}, found {$found}",
                $this->getSession()
            );
        }
    }

    /**
     * Reinitialises the reader of the current page as if the browser lacked a feature PDF.js needs.
     *
     * Promise.withResolvers is removed only while the reader starts and restored right after, so the
     * rest of the page keeps working. The reader then shows its "browser too old" message.
     *
     * @When /^I restart the Leafr reader in a browser that is too old for PDF\.js$/
     */
    public function i_restart_the_leafr_reader_in_a_browser_that_is_too_old(): void {
        $this->require_javascript();
        $this->execute_script(<<<'JS'
            (function() {
                var root = document.querySelector('.leafr-reader[data-initialised]');
                require(['mod_leafr/reader'], function(reader) {
                    var original = Promise.withResolvers;
                    delete root.dataset.initialised;
                    Promise.withResolvers = undefined;
                    try {
                        reader.init('#' + root.id);
                    } finally {
                        Promise.withResolvers = original;
                    }
                });
            })();
            JS);
        $shown = 'return !document.querySelector(\'.leafr-reader [data-region="error"]\').hidden;';
        $exception = new ExpectationException('The reader did not show an error message', $this->getSession());
        $this->spin(function () use ($shown) {
            return $this->evaluate_script($shown);
        }, false, self::get_extended_timeout(), $exception);
        $this->wait_for_pending_js();
    }

    /**
     * Opens Moodle's course index drawer (Boost based themes) if it is closed.
     *
     * @Given /^I open the course index drawer if it is closed$/
     */
    public function i_open_the_course_index_drawer_if_it_is_closed(): void {
        $this->require_javascript();
        if (!$this->is_course_index_open()) {
            $this->execute_script(
                'document.querySelector(\'[data-toggler="drawers"][data-action="toggle"]\'
                    + \'[data-target="theme_boost-drawers-courseindex"]\').click();'
            );
        }
        $this->spin(function () {
            return $this->is_course_index_open();
        }, false, self::get_extended_timeout(), new ExpectationException('The course index did not open', $this->getSession()));
        $this->wait_for_pending_js();
    }

    /**
     * Checks whether Moodle's course index drawer is open or closed.
     *
     * @Then /^the course index drawer should be (open|closed)$/
     * @param string $state "open" or "closed"
     */
    public function the_course_index_drawer_should_be(string $state): void {
        $this->require_javascript();
        $this->wait_for_pending_js();
        if ($this->is_course_index_open() !== ($state === 'open')) {
            throw new ExpectationException("The course index drawer is not {$state}", $this->getSession());
        }
    }

    /**
     * Whether Moodle's course index drawer is shown.
     *
     * @return bool
     */
    protected function is_course_index_open(): bool {
        return (bool)$this->evaluate_script(
            'return document.getElementById("theme_boost-drawers-courseindex").classList.contains("show");'
        );
    }

    /**
     * Checks whether the Leafr reader is shown in full screen mode.
     *
     * @Then /^the Leafr reader should (be|not be) in full screen mode$/
     * @param string $be "be" or "not be"
     */
    public function the_leafr_reader_should_be_in_full_screen_mode(string $be): void {
        $this->require_javascript();
        if ($be === 'be') {
            $script = 'return document.fullscreenElement !== null
                && document.querySelector(".leafr-reader").classList.contains("is-fullscreen");';
        } else {
            $script = 'return document.fullscreenElement === null
                && !document.querySelector(".leafr-reader").classList.contains("is-fullscreen")
                && !document.documentElement.classList.contains("leafr-fullscreen");';
        }
        $expected = true;
        $exception = new ExpectationException(
            $be === 'be' ? 'The reader is not in full screen mode' : 'The reader is still in full screen mode',
            $this->getSession()
        );
        $this->spin(function () use ($script, $expected) {
            return (bool)$this->evaluate_script($script) === $expected;
        }, false, self::get_extended_timeout(), $exception);
        if ($be === 'be') {
            // Entering full screen resizes the window, which is when Moodle's drawers set up their
            // backdrop. A headless browser may not resize, so the resize event is sent here.
            $this->execute_script('window.dispatchEvent(new Event("resize"));');
            $this->wait_for_pending_js();
        }
    }

    /**
     * Checks that the page is shown with the given theme, so a scenario for another theme cannot
     * silently run with Boost when that theme is missing.
     *
     * @Then /^the page should use the "(?P<theme_string>[^"]*)" theme$/
     * @param string $theme Theme name, e.g. "boost_union"
     */
    public function the_page_should_use_the_theme(string $theme): void {
        $this->require_javascript();
        $actual = $this->evaluate_script('return M.cfg.theme;');
        if ($actual !== $theme) {
            throw new ExpectationException("The page uses the theme \"{$actual}\", not \"{$theme}\"", $this->getSession());
        }
    }

    /**
     * Checks that a click in the middle of every visible button of the reader reaches that button,
     * i.e. that no element of the theme or of Moodle lies on top of it (relevant in full screen
     * mode, where the reader covers the page).
     *
     * @Then /^every visible control of the Leafr reader should be clickable$/
     */
    public function every_visible_control_of_the_leafr_reader_should_be_clickable(): void {
        $this->require_javascript();
        $blocked = $this->evaluate_script('return (function() {
            var result = [];
            document.querySelectorAll(".leafr-reader [data-action]").forEach(function(button) {
                var rect = button.getBoundingClientRect();
                if (!rect.width || !rect.height || button.closest("[hidden]")) {
                    return;
                }
                var hit = document.elementFromPoint(rect.left + rect.width / 2, rect.top + rect.height / 2);
                if (hit !== button && !button.contains(hit)) {
                    result.push(button.dataset.action + " (covered by " +
                        (hit ? hit.tagName.toLowerCase() + (hit.id ? "#" + hit.id : "") + "." + hit.className : "nothing") + ")");
                }
            });
            return result;
        })();');
        if ($blocked) {
            throw new ExpectationException('Controls that cannot be clicked: ' . implode(', ', $blocked), $this->getSession());
        }
    }
}

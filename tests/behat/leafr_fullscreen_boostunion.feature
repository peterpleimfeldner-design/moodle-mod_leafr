@mod @mod_leafr @leafr_themecheck
Feature: Full screen mode of the flipbook with the theme Boost Union
  In order to read comfortably in full screen mode
  As a student
  I need full screen mode to work with Boost Union as it does with Boost
  # Runs only in the CI job "Theme check", which installs Boost Union (GitHub issue #16:
  # Moodle 5.3 with Boost Union, toolbar and menu reacted hardly at all in full screen mode).

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | course | name     |
      | leafr    | C1     | Handbook |
    And the following config values are set as admin:
      | theme | boost_union |

  @javascript
  Scenario: Toolbar, view menu, sidebar and help react to clicks in full screen mode
    Given I change window size to "large"
    And I am on the "Handbook" "leafr activity" page logged in as "student1"
    And the page should use the "boost_union" theme
    And I start recording JavaScript errors
    When I click on ".leafr-reader [data-action='view-menu']" "css_element"
    And I click on ".leafr-reader [data-action='fullscreen']" "css_element"
    Then the Leafr reader should be in full screen mode
    # The view menu is still open: all its items and the toolbar must be reachable.
    And every visible control of the Leafr reader should be clickable
    And I click on ".leafr-reader [data-action='spread-single']" "css_element"
    And the "aria-checked" attribute of ".leafr-reader [data-action='spread-single']" "css_element" should contain "true"
    And I click on ".leafr-reader [data-action='spread-auto']" "css_element"
    And the "aria-checked" attribute of ".leafr-reader [data-action='spread-auto']" "css_element" should contain "true"
    And I click on ".leafr-reader [data-action='zoom-in']" "css_element"
    And I should see "125%" in the ".leafr-reader [data-region='zoom-label']" "css_element"
    And I click on ".leafr-reader [data-action='zoom-out']" "css_element"
    And I should see "100%" in the ".leafr-reader [data-region='zoom-label']" "css_element"
    And I click on ".leafr-reader [data-action='fit-width']" "css_element"
    And the "aria-checked" attribute of ".leafr-reader [data-action='fit-width']" "css_element" should contain "true"
    And I click on ".leafr-reader [data-action='fit-page']" "css_element"
    And the "aria-checked" attribute of ".leafr-reader [data-action='fit-page']" "css_element" should contain "true"
    And I click on ".leafr-reader [data-action='simpleview']" "css_element"
    And the "aria-checked" attribute of ".leafr-reader [data-action='simpleview']" "css_element" should contain "true"
    And I click on ".leafr-reader [data-action='simpleview']" "css_element"
    And the "aria-checked" attribute of ".leafr-reader [data-action='simpleview']" "css_element" should contain "false"
    And I click on ".leafr-reader [data-action='view-menu']" "css_element"
    And ".leafr-reader [data-region='view-menu']" "css_element" should not be visible
    And every visible control of the Leafr reader should be clickable
    # Toolbar.
    And I click on ".leafr-reader [data-action='next']" "css_element"
    And the field "Go to page" matches value "3"
    And I click on ".leafr-reader [data-action='prev']" "css_element"
    And the field "Go to page" matches value "1"
    And I click on ".leafr-reader [data-action='last']" "css_element"
    And the field "Go to page" does not match value "1"
    And I click on ".leafr-reader [data-action='first']" "css_element"
    And the field "Go to page" matches value "1"
    And I click on ".leafr-reader [data-action='bookmark']" "css_element"
    And the "aria-pressed" attribute of ".leafr-reader [data-action='bookmark']" "css_element" should contain "true"
    And I click on ".leafr-reader [data-action='bookmark']" "css_element"
    And the "aria-pressed" attribute of ".leafr-reader [data-action='bookmark']" "css_element" should contain "false"
    And I click on ".leafr-reader [data-action='sidebar']" "css_element"
    And the "aria-expanded" attribute of ".leafr-reader [data-action='sidebar']" "css_element" should contain "true"
    And every visible control of the Leafr reader should be clickable
    And I click on ".leafr-reader [data-action='sidebar']" "css_element"
    And the "aria-expanded" attribute of ".leafr-reader [data-action='sidebar']" "css_element" should contain "false"
    And I click on ".leafr-reader [data-action='help']" "css_element"
    And ".leafr-reader [data-region='help']" "css_element" should be visible
    And I click on ".leafr-reader [data-action='help-close']" "css_element"
    And ".leafr-reader [data-region='help']" "css_element" should not be visible
    And the Leafr reader should be in full screen mode
    When I click on ".leafr-reader [data-action='view-menu']" "css_element"
    And I click on ".leafr-reader [data-action='fullscreen']" "css_element"
    Then the Leafr reader should not be in full screen mode
    And no JavaScript errors should have been recorded

  @javascript
  Scenario: The course index stays open after full screen mode with Boost Union
    # Moodle closed the course index on every click into the book once the reader itself had been
    # in full screen mode (GitHub issue #16), also after leaving full screen.
    Given I change window size to "large"
    And I am on the "Handbook" "leafr activity" page logged in as "student1"
    And the page should use the "boost_union" theme
    And I open the course index drawer if it is closed
    And I start recording JavaScript errors
    When I press "View"
    And I press "Full screen"
    Then the Leafr reader should be in full screen mode
    And I press "View"
    And I click on ".leafr-stage" "css_element"
    And I press "Next page"
    And the field "Go to page" matches value "3"
    When I press "View"
    And I press "Exit full screen"
    Then the Leafr reader should not be in full screen mode
    And I press "View"
    And the course index drawer should be open
    And I click on ".leafr-stage" "css_element"
    And I press "Next page"
    And the field "Go to page" matches value "5"
    And the course index drawer should be open
    And no JavaScript errors should have been recorded

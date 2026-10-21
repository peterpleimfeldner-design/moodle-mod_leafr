@mod @mod_leafr
Feature: Render PDF pages with the bundled PDF.js library
  In order to read course material in any current browser
  As a student
  I need the flipbook to render the pages of a PDF document without errors

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
      | activity | course | name     | downloadallowed |
      | leafr    | C1     | Handbook | 1               |

  @javascript
  Scenario: Pages are rendered in every view of the reader without JavaScript errors
    Given I am on the "Handbook" "leafr activity" page logged in as "student1"
    And I start recording JavaScript errors
    Then I should see "of 12" in the ".leafr-toolbar" "css_element"
    And the Leafr page "1" should be at least "0.2"% dark in the whole page
    When I press "Next page"
    Then the field "Go to page" matches value "3"
    And the Leafr page "3" should be at least "0.2"% dark in the whole page
    When I press "View"
    And I click on "[data-action='zoom-in']" "css_element"
    Then the Leafr page "3" should be at least "0.2"% dark in the whole page
    When I press "Sidebar"
    And I click on "[data-tab='thumbs']" "css_element"
    Then the Leafr thumbnail "1" should be at least "0.2"% dark in the whole page
    And I click on ".leafr-thumb[data-page='7']" "css_element"
    And the field "Go to page" matches value "7"
    And the Leafr page "7" should be at least "0.2"% dark in the whole page
    When I click on "[data-tab='toc']" "css_element"
    And I click on "Chapter 3: Summary" "button"
    Then the field "Go to page" matches value "9"
    When I click on "[data-tab='search']" "css_element"
    And I set the field "Search text" to "Chapter 3"
    And I press the enter key
    Then I should see "1 of 4" in the ".leafr-search-status" "css_element"
    And the field "Go to page" matches value "9"
    And the Leafr page "9" should be at least "0.2"% dark in the whole page
    When I press "View"
    And I press "Simple view"
    Then the Leafr page "9" should be at least "0.2"% dark in the whole page
    And no JavaScript errors should have been recorded

  @javascript
  Scenario: JPEG 2000 and JBIG2 images are decoded and shown
    # The images are decoded by the WebAssembly decoders in vendor/pdfjs/wasm; without them the
    # page stays white. The left half of the page holds a JPEG 2000 image, the right half a JBIG2 image.
    Given the following "activities" exist:
      | activity | course | name   | pdffixture |
      | leafr    | C1     | Images | images.pdf |
    And I am on the "Images" "leafr activity" page logged in as "student1"
    And I start recording JavaScript errors
    Then I should see "of 1" in the ".leafr-toolbar" "css_element"
    And the Leafr page "1" should be at least "10"% dark in the left half
    And the Leafr page "1" should be at least "10"% dark in the right half
    And no JavaScript errors should have been recorded

  @javascript
  Scenario: A browser that is too old for the PDF library gets a clear message
    Given I am on the "Handbook" "leafr activity" page logged in as "student1"
    When I restart the Leafr reader in a browser that is too old for PDF.js
    Then I should see "Your browser is too old to display this document." in the "[data-region='error']" "css_element"
    And "Download PDF" "link" should exist in the "[data-region='error']" "css_element"
    And "Reload page" "button" should not be visible
    And I should not see "The document could not be loaded."

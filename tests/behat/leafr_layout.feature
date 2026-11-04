@mod @mod_leafr
Feature: Page layout of the flipbook for documents with different page formats
  In order to read textbooks with a wide cover comfortably
  As a student
  I need the flipbook to offer two-page spreads when the content pages allow it

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
      | activity | course | name     | pdffixture         |
      | leafr    | C1     | Textbook | landscapecover.pdf |

  @javascript
  Scenario: A PDF with a landscape first page offers double pages
    # landscapecover.pdf: page 1 is a wide landscape cover, pages 2-6 are portrait A4.
    Given I change window size to "large"
    And I am on the "Textbook" "leafr activity" page logged in as "student1"
    And I start recording JavaScript errors
    Then I should see "of 6" in the ".leafr-toolbar" "css_element"
    And the Leafr page "1" should be at least "10"% dark in the whole page
    When I press "View"
    Then the "Double page" "button" should be enabled
    And I press "Double page"
    # Spreads 1-2, 3-4, 5-6: "Next page" moves by two pages (by one in the single page layout).
    And I press "Next page"
    Then the field "Go to page" matches value "3"
    And the Leafr page "4" should be at least "0.2"% dark in the whole page
    And I press "Next page"
    And the field "Go to page" matches value "5"
    And no JavaScript errors should have been recorded

  @javascript
  Scenario: Automatic layout shows a PDF with a landscape cover as spreads on a wide screen
    Given I change window size to "large"
    And I am on the "Textbook" "leafr activity" page logged in as "student1"
    Then I should see "of 6" in the ".leafr-toolbar" "css_element"
    When I press "Next page"
    Then the field "Go to page" matches value "3"

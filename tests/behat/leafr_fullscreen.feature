@mod @mod_leafr
Feature: Full screen mode of the flipbook
  In order to read with as much room as possible
  As a student
  I need a full screen mode that leaves the rest of the Moodle page as it was

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

  @javascript
  Scenario: The course index stays open after full screen mode
    # Moodle closed the course index on every click into the book once the reader itself had been
    # in full screen mode (GitHub issue #16), also after leaving full screen.
    Given I change window size to "large"
    And I am on the "Handbook" "leafr activity" page logged in as "student1"
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

@mod @mod_leafr
Feature: Read a PDF document in a Leafr flipbook
  In order to study course material
  As a student
  I need to open a PDF document as a flipbook and leaf through it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name     | intro             | downloadallowed |
      | leafr    | C1     | Handbook | Read the handbook | 1               |

  Scenario: A student opens the flipbook
    When I am on the "Handbook" "leafr activity" page logged in as "student1"
    Then I should see "Handbook"
    And ".leafr-toolbar" "css_element" should exist
    And "Download PDF" "link" should exist
    And "Sidebar" "button" should exist

  Scenario: The download button is hidden when downloading is not allowed
    Given the following "activities" exist:
      | activity | course | name      | downloadallowed | showtoc |
      | leafr    | C1     | Protected | 0               | 0       |
    When I am on the "Protected" "leafr activity" page logged in as "student1"
    Then "Download PDF" "link" should not exist
    And "Contents" "button" should not exist

  @javascript
  Scenario: The document is rendered and the student can leaf through it
    When I am on the "Handbook" "leafr activity" page logged in as "student1"
    Then I should see "of 12" in the ".leafr-toolbar" "css_element"
    And the field "Go to page" matches value "1"
    And I press "Next page"
    And the field "Go to page" matches value "3"
    And I set the field "Go to page" to "9"
    And I press the enter key
    And the field "Go to page" matches value "9"
    And I press "Sidebar"
    And I press "Contents"
    And I should see "Chapter 3: Summary"
    And I should see "of 12 pages read" in the ".leafr-toolbar" "css_element"

  @javascript
  Scenario: A student searches the document
    When I am on the "Handbook" "leafr activity" page logged in as "student1"
    And I press "Sidebar"
    # Not "I press 'Search'": Moodle's own site navigation already has a "Search" button,
    # so the sidebar's search tab needs an unambiguous selector.
    And I click on "[data-tab='search']" "css_element"
    And I set the field "Search text" to "Chapter 3"
    And I press the enter key
    And I wait "2" seconds
    Then I should see "1 of 4" in the ".leafr-search-status" "css_element"
    And I should see "Chapter 3" in the ".leafr-search-results" "css_element"
    And the field "Go to page" matches value "9"
    And I click on "[data-action='search-next']" "css_element"
    And the field "Go to page" matches value "10"

  @javascript
  Scenario: A returning student sees a notice instead of a silent jump
    Given I am on the "Handbook" "leafr activity" page logged in as "student1"
    And I press "Next page"
    And the field "Go to page" matches value "3"
    And I wait "2" seconds
    When I reload the page
    Then I should see "Continue on page 3"
    And I press "Start from the beginning"
    And the field "Go to page" matches value "1"

  @javascript @_file_upload
  Scenario: A teacher creates a flipbook
    Given I am on the "Course 1" course page logged in as "teacher1"
    And I turn editing mode on
    When I add a "Leafr flipbook" to section "1" using the activity chooser
    And I set the field "Name" to "Guide"
    And I upload "mod/leafr/tests/fixtures/sample.pdf" file to "PDF file" filemanager
    And I press "Save and display"
    Then I should see "of 12" in the ".leafr-toolbar" "css_element"

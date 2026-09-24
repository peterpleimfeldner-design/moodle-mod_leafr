@mod @mod_leafr @leafrtool_report
Feature: See each student's own reading progress in the overview
  In order to know who still needs to read the material
  As a teacher
  I need an overview that shows every student's own progress, not a mix-up between students

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
      | student2 | Student   | Two      | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | enablecompletion |
      | Course 1 | C1        | 1                |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the following "activities" exist:
      | activity | course | name     | intro             | completion | completiontype | completionpercent |
      | leafr    | C1     | Progress | Read the material | 2          | 2              | 50                |

  @javascript
  Scenario: Two students with different progress each see their own state in the overview
    # Student One reads well past the 50% mark (three "Next page" presses on the 12-page
    # document, shown two pages at a time, covers pages 1-8).
    Given I am on the "Progress" "leafr activity" page logged in as "student1"
    And I press "Next page"
    And I press "Next page"
    And I press "Next page"
    # tracker.js batches seen-page updates and only sends them 1500ms after the last page turn
    # (see amd/src/tracker.js SEND_DELAY) - comfortably under the old, shorter page-turn
    # animation, but the animation was deliberately slowed down since, so
    # this wait needs enough headroom for flush() to fire AND its AJAX call to complete before
    # navigating away as a different user, not just for the last page turn's own animation.
    And I wait "4" seconds
    # Student Two only ever looks at the first two pages, well under the 50% mark.
    When I am on the "Progress" "leafr activity" page logged in as "student2"
    And I wait "2" seconds
    And I am on the "Course 1" course page logged in as "teacher1"
    And I follow "Progress"
    And I navigate to "Overview" in current page administration
    Then I should see "Student One"
    And I should see "Student Two"
    # "Completed" is shown as an icon (not the word "Yes") when true, and as the word "No" when
    # false - see leafrtool_report\local\report::get_rows()/report.php.
    And I should not see "No" in the "Student One" "table_row"
    And I should see "No" in the "Student Two" "table_row"

  @javascript @accessibility
  Scenario: The overview meets accessibility standards
    Given I am on the "Progress" "leafr activity" page logged in as "student1"
    And I press "Next page"
    And I wait "2" seconds
    When I am on the "Course 1" course page logged in as "teacher1"
    And I follow "Progress"
    And I navigate to "Overview" in current page administration
    Then the page should meet accessibility standards

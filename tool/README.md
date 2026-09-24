# Leafr tools (`leafrtool` subplugins)

This folder holds subplugins of type `leafrtool` that extend the Leafr flipbook without changing
its core. Two tools ship with Leafr:

- `leafrtool_confirm` – read confirmation: an optional completion rule that asks students to
  confirm that they have read the document (reference implementation for the hooks below).
- `leafrtool_report` – overview for teachers: completion, required pages read and the read
  confirmation date per participant, with group filter and CSV export.

Each subplugin lives in its own folder `tool/<name>/` with its own `version.php`
(`$plugin->component = 'leafrtool_<name>'`) and may provide the following functions in its
`lib.php`. All of them are optional; the core calls them via `component_callback()` and treats a
missing result as "nothing to contribute".

## Activity settings form

```php
function leafrtool_<name>_extend_settings_form(MoodleQuickForm $mform, ?stdClass $instance): void
function leafrtool_<name>_get_form_data(int $leafrid): array
function leafrtool_<name>_save_settings(stdClass $data, int $leafrid): void
```

`extend_settings_form()` is called at the end of `mod_form.php` (before the standard course
module elements) and adds the tool's own form elements. `get_form_data($leafrid)` returns the
current values (unsuffixed element name => value) for `data_preprocessing()`; `$leafrid` is `0`
for a new activity, in which case sensible defaults should be returned. `save_settings($data,
$leafrid)` stores the tool's own fields from `$data` in its own table and is called from both
`leafr_add_instance()` and `leafr_update_instance()`.

## Automatic completion rules

```php
function leafrtool_<name>_completion_rules(): array
function leafrtool_<name>_completion_rule_enabled(stdClass $leafr): bool
function leafrtool_<name>_completion_state(stdClass $leafr, int $userid): bool
function leafrtool_<name>_completion_rule_elements(MoodleQuickForm $mform, string $formname, string $suffix): void
```

`completion_rules()` returns additional completion rules (rule name => language string key for the
checkbox label). For each rule the language string `completiondetail:<rulename>` is expected as
well (the description students see on the activity page). The core renders a checkbox for every
rule in the completion conditions; `completion_rule_elements()` may add extra fields right after it
(for example a text field that is only shown while the checkbox is ticked, using
`$mform->hideIf($field, $formname, 'notchecked')`). `completion_rule_enabled()` tells whether the
rule is switched on for an activity, `completion_state()` whether a user has fulfilled it.

## Navigation

```php
function leafrtool_<name>_extend_navigation(navigation_node $node, cm_info $cm, context_module $context): void
```

Called while the activity navigation is built (the links next to "Settings" and under "More").
A tool can link its own page this way, for example an overview for teachers (`leafrtool_report`).
The tool checks the capabilities itself (only call `$node->add(...)` if `has_capability(...)` is
true).

## Area below the reader

```php
function leafrtool_<name>_render_reader(cm_info $cm, context_module $context, stdClass $leafr): ?string
```

Called by `view.php` after the reader. Returns ready HTML (usually rendered from the tool's own
Mustache template via `$OUTPUT->render_from_template()`) that is inserted below the reader, or
`null` if the tool has nothing to contribute for this activity. The tool may load its own AMD
module here with `$PAGE->requires->js_call_amd()`.

When reading is complete, the core reader dispatches the DOM event `leafr:reading-complete` on
`document` (detail: `{cmid}`), so a tool's JavaScript can react without the core knowing about it.
Reading counts as complete once the page based completion rule is fulfilled or, if the activity
has no such rule, once the last page has been reached.

## Course reset

```php
function leafrtool_<name>_reset_course_form_definition(MoodleQuickForm $mform): void
function leafrtool_<name>_reset_course_form_defaults(): array
function leafrtool_<name>_reset_userdata(stdClass $data): array
```

`reset_course_form_definition()` adds a checkbox to the course reset form (without a heading of
its own; it appears in the Leafr section). `reset_userdata()` receives the complete form data
(including `courseid`) and returns status messages in the format expected from a Moodle module's
`reset_userdata()`.

## Deleting an activity

```php
function leafrtool_<name>_delete_instance(int $leafrid): void
```

Called by `leafr_delete_instance()` for **every installed** tool (including disabled ones, so no
orphaned data is left behind) before the `leafr` record itself is deleted.

## Privacy, backup and restore

These work through Moodle's standard subplugin mechanisms as soon as a tool provides the usual
classes (`classes/privacy/provider.php`,
`backup/moodle2/backup_leafrtool_<name>_subplugin.class.php` and
`backup/moodle2/restore_leafrtool_<name>_subplugin.class.php`, each with
`define_leafr_subplugin_structure()` and processing via `$this->get_namefor()` /
`get_pathfor()`). The core does not need to be changed for this (see `db/subplugins.json` and the
`add_subplugin_structure('leafrtool', ...)` calls in `backup/moodle2/`).

## Web services

A tool with its own actions (for example "submit read confirmation") registers them in its own
`db/services.php` with function names `leafrtool_<name>_<action>` and a class under
`leafrtool_<name>\external\...`, exactly like a regular plugin.

# Leafr-Werkzeuge (`leafrtool`)

Dieser Ordner nimmt Unter-Plugins vom Typ `leafrtool` auf, z. B. `leafrtool_highlight`,
`leafrtool_office`, `leafrtool_confirm`, `leafrtool_report` (siehe `ROADMAP.md`, Pakete F–I).

Jedes Unter-Plugin liegt in einem eigenen Ordner `tool/<name>/` mit eigener `version.php`
(`$plugin->component = 'leafrtool_<name>'`) und kann die folgenden, vom Kern aufgerufenen
Funktionen in seiner `lib.php` bereitstellen. Alle Funktionen sind optional; der Kern ruft sie
über `component_callback()` auf und behandelt ein fehlendes Ergebnis als „nichts beizutragen“.

## Einstellungsformular

```php
function leafrtool_<name>_extend_settings_form(MoodleQuickForm $mform, ?stdClass $instance): void
```

Wird am Ende von `mod_form.php` aufgerufen (vor den Standard-Kursmodul-Elementen). Fügt eigene
Formularelemente hinzu, z. B. eine Ein/Aus-Einstellung. Das Unter-Plugin ist selbst für Speichern
und Validieren seiner Felder zuständig (`leafr_add_instance()`/`leafr_update_instance()` in
`lib.php` reichen `$data` unverändert an `\mod_leafr\local\tool_manager::save_settings()` weiter,
das wiederum `leafrtool_<name>_save_settings($data, $instance)` aufruft, falls vorhanden).

## Automatische Abschlussregeln

```php
function leafrtool_<name>_completion_rules(): array
```

Gibt eine Liste zusätzlicher Abschlussregeln zurück (Formularname => Beschreibung als
Sprachstring-Schlüssel). Ob die Regel erfüllt ist, prüft der Kern über:

```php
function leafrtool_<name>_completion_rule_enabled(stdClass $leafr): bool
function leafrtool_<name>_completion_state(stdClass $leafr, int $userid): bool
```

## Werkzeugleiste und Seitenleiste

Werden mit Paket B/E ergänzt, sobald die neue Seitenleiste und Werkzeugleiste stehen. Der Kern
liefert dann `cm_info` und `context` an `leafrtool_<name>_toolbar_buttons()` bzw.
`leafrtool_<name>_sidebar_tabs()`; jedes Unter-Plugin registriert dabei sein eigenes AMD-Modul.

## Datenschutz, Backup, Zurücksetzen

Laufen automatisch über die Moodle-Subplugin-Mechanismen, sobald ein Unter-Plugin die üblichen
Klassen bereitstellt (`classes/privacy/provider.php`, `backup/moodle2/backup_leafrtool_<name>_subplugin.class.php`,
`backup/moodle2/restore_leafrtool_<name>_subplugin.class.php`, `lib.php`-Funktion
`leafrtool_<name>_reset_userdata($data)`). Der Kern muss dafür nicht angepasst werden
(siehe `db/subplugins.json` und die `add_subplugin_structure()`-Aufrufe in `backup/moodle2/`).

# Leafr-Werkzeuge (`leafrtool`)

Dieser Ordner nimmt Unter-Plugins vom Typ `leafrtool` auf, z. B. `leafrtool_confirm` (Paket F),
`leafrtool_report`, `leafrtool_highlight`, `leafrtool_office` (siehe `ROADMAP.md`, Pakete F–I).

Jedes Unter-Plugin liegt in einem eigenen Ordner `tool/<name>/` mit eigener `version.php`
(`$plugin->component = 'leafrtool_<name>'`) und kann die folgenden, vom Kern aufgerufenen
Funktionen in seiner `lib.php` bereitstellen. Alle Funktionen sind optional; der Kern ruft sie
über `component_callback()` auf und behandelt ein fehlendes Ergebnis als „nichts beizutragen“.
Referenzimplementierung: `leafrtool_confirm`.

## Einstellungsformular

```php
function leafrtool_<name>_extend_settings_form(MoodleQuickForm $mform, ?stdClass $instance): void
function leafrtool_<name>_get_form_data(int $leafrid): array
function leafrtool_<name>_save_settings(stdClass $data, int $leafrid): void
```

`extend_settings_form()` wird am Ende von `mod_form.php` aufgerufen (vor den
Standard-Kursmodul-Elementen) und fügt eigene Formularelemente hinzu, z. B. eine Ein/Aus-Einstellung
mit eigenem Bereich. `get_form_data($leafrid)` liefert die aktuellen Werte (unsuffixierte
Feldnamen => Wert) für `data_preprocessing()`; `$leafrid` ist `0` bei einer neuen Aktivität, in dem
Fall sinnvolle Vorgabewerte liefern. `save_settings($data, $leafrid)` speichert die eigenen Felder
aus `$data` in die eigene Tabelle – wird sowohl von `leafr_add_instance()` als auch
`leafr_update_instance()` aufgerufen.

## Automatische Abschlussregeln

```php
function leafrtool_<name>_completion_rules(): array
function leafrtool_<name>_completion_rule_enabled(stdClass $leafr): bool
function leafrtool_<name>_completion_state(stdClass $leafr, int $userid): bool
function leafrtool_<name>_completion_rule_elements(MoodleQuickForm $mform, string $formname, string $suffix): void
```

`completion_rules()` gibt eine Liste zusätzlicher Abschlussregeln zurück (Regelname =>
Sprachstring-Schlüssel für die Checkbox-Beschriftung im Formular). Für jede Regel wird außerdem der
Sprachstring `completiondetail:<regelname>` erwartet (Beschreibung, die Teilnehmer/innen auf der
Aktivitätsseite sehen). Der Kern rendert für jede Regel automatisch eine eigene Checkbox in den
Abschlussbedingungen; `completion_rule_elements()` darf direkt danach eigene Zusatzfelder einfügen
(z. B. ein Textfeld, das nur bei aktivierter Checkbox sichtbar ist über `$mform->hideIf($feld,
$formname, 'notchecked')`). Ob die Regel für eine Aktivität aktiv ist, prüft
`completion_rule_enabled()`; ob sie für eine Person erfüllt ist, `completion_state()`.

## Navigation

```php
function leafrtool_<name>_extend_navigation(navigation_node $node, cm_info $cm, context_module $context): void
```

Wird beim Aufbau der Aktivitäts-Navigation aufgerufen (die Links unter „Mehr“ neben
„Einstellungen“). Damit kann ein Werkzeug eine eigene Seite verlinken, z. B. eine
Übersicht/Bericht für Lehrende (`leafrtool_report`). Rechteprüfung übernimmt das Werkzeug selbst
(`$node->add(...)` nur, wenn `has_capability(...)` zutrifft).

## Werkzeugleiste, Seitenleiste und der Bereich unter dem Reader

```php
function leafrtool_<name>_render_reader(cm_info $cm, context_module $context, stdClass $leafr): ?string
```

Wird von `view.php` nach dem Reader aufgerufen. Gibt fertiges HTML zurück (eigenes Mustache-Template
über `$OUTPUT->render_from_template()`), das unter dem Reader eingefügt wird, oder `null`, wenn das
Werkzeug für diese Aktivität nichts beizutragen hat. Innerhalb dieser Funktion kann das Unter-Plugin
mit `$PAGE->requires->js_call_amd()` sein eigenes AMD-Modul laden. Der Kern-Reader löst beim
Erreichen der Leseanforderung ein DOM-Ereignis `leafr:reading-complete` auf `document` aus (Detail:
`{cmid}`), auf das Unter-Plugin-JavaScript reagieren kann, ohne dass der Kern sie kennen muss.

Eigene Werkzeugleisten-Knöpfe oder Seitenleisten-Reiter (z. B. für Textmarker) folgen demselben
Muster, sobald ein Werkzeug das braucht – noch nicht generalisiert, da `leafrtool_confirm` das nicht
benötigt.

## Das Hauptdokument selbst (PDF vs. andere Formate)

```php
function leafrtool_<name>_get_accepted_file_types(): array
function leafrtool_<name>_handle_content_saved(int $leafrid, context_module $context): void
function leafrtool_<name>_get_converted_file(int $leafrid, context_module $context): ?array
function leafrtool_<name>_get_download_file(int $leafrid, context_module $context, stored_file $default): ?stored_file
```

Der Kern speichert die hochgeladene Datei immer im eigenen Dateibereich `mod_leafr`/`content`
(Itemid 0) und akzeptiert dort standardmäßig nur `.pdf`. `get_accepted_file_types()` liefert
zusätzliche Dateiendungen (z. B. `.docx`), die das Hauptformular zusätzlich erlaubt – siehe
`leafrtool_office`, das hier nur Endungen zurückgibt, die der auf der Website eingerichtete
Dokumentkonverter tatsächlich in PDF umwandeln kann (leer, wenn kein Konverter eingerichtet ist –
dann bleibt es bei „nur PDF“, ganz ohne Sonderfall im Kern).

`handle_content_saved()` wird von `leafr_save_pdf()` aufgerufen, **nur wenn sich die gespeicherte
Datei geändert hat**. Ein Werkzeug prüft hier selbst, ob es sich zuständig fühlt (z. B. weil die
Datei kein PDF ist), und kümmert sich um alles Weitere (z. B. eine Hintergrund-Aufgabe zum
Umwandeln einreihen).

Ist die im Kern gespeicherte Datei kein PDF, ruft `view.php` **vor** dem Laden des Readers
`get_converted_file()` auf. Gibt ein Werkzeug ein Array zurück (`['status' => 'pending'|'ready'|
'failed', 'file' => stored_file|null]`), zeigt der Kern je nach Status den Reader (mit der
zurückgegebenen `file`, die *nicht* im `mod_leafr`-Dateibereich liegen muss – sie wird über ihre
eigene `component`/`filearea`/`itemid` verlinkt, das Werkzeug liefert sie dann über einen eigenen
Eintrag in `leafr_pluginfile()` selbst aus, siehe `leafrtool_office` und den dortigen Umbau von
`lib.php`) oder einen Hinweistext „wird vorbereitet“/„fehlgeschlagen“. Gibt kein installiertes
Werkzeug ein Ergebnis zurück, zeigt der Kern den generischen „kein PDF hochgeladen“-Hinweis.

`get_download_file()` wird nur aufgerufen, wenn Herunterladen überhaupt erlaubt ist, und lässt ein
Werkzeug die angebotene Datei ersetzen (z. B. Original statt PDF, je nach eigener Einstellung).

## Kurs-Zurücksetzen

```php
function leafrtool_<name>_reset_course_form_definition(MoodleQuickForm $mform): void
function leafrtool_<name>_reset_course_form_defaults(): array
function leafrtool_<name>_reset_userdata(stdClass $data): array
```

`reset_course_form_definition()` fügt eine eigene Checkbox zum Kurs-Zurücksetzen-Formular hinzu
(ohne eigene Überschrift – erscheint im „Leafr-Flipbooks“-Bereich). `reset_userdata()` bekommt die
gesamten Formulardaten (inkl. `courseid`) und liefert Statusmeldungen im von `reset_userdata()`
eines Moodle-Moduls erwarteten Format zurück.

## Löschen einer Aktivität

```php
function leafrtool_<name>_delete_instance(int $leafrid): void
```

Wird von `leafr_delete_instance()` für **jedes installierte** Werkzeug aufgerufen (auch
deaktivierte, damit keine verwaisten Daten zurückbleiben), bevor der `leafr`-Datensatz selbst
gelöscht wird.

## Datenschutz, Sicherung/Wiederherstellung

Laufen automatisch über die Moodle-Subplugin-Mechanismen, sobald ein Unter-Plugin die üblichen
Klassen bereitstellt (`classes/privacy/provider.php`,
`backup/moodle2/backup_leafrtool_<name>_subplugin.class.php`,
`backup/moodle2/restore_leafrtool_<name>_subplugin.class.php`, jeweils mit der Methode
`define_leafr_subplugin_structure()`/Verarbeitung über `$this->get_namefor()`/`get_pathfor()`). Der
Kern muss dafür nicht angepasst werden (siehe `db/subplugins.json` und die
`add_subplugin_structure('leafrtool', ...)`-Aufrufe in `backup/moodle2/`).

## Webservices

Ein Werkzeug mit eigenen Aktionen (z. B. „Lesebestätigung abgeben“) registriert seine eigene
`db/services.php` mit Funktionsnamen `leafrtool_<name>_<aktion>` und einer Klasse unter
`leafrtool_<name>\external\...`, genau wie ein normales Plugin.

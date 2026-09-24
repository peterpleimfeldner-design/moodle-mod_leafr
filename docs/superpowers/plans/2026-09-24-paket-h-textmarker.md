# Paket H – Textmarker (`leafrtool_highlight`) Implementation Plan

> **Status 24.09.2026: ZURÜCKGESTELLT.** Nach Besprechung mit Peter nicht umgesetzt (Aufwand/Risiko vs.
> Nutzen ohne geplante Pro-Version, siehe `ROADMAP.md` Paket H). Dieser Plan bleibt als Referenz erhalten,
> falls das Paket später (z. B. für eine mögliche künftige Pro-Version) wieder aufgegriffen wird.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Nutzer/innen können Text im PDF markieren (4 Farben, optionale Notiz), sehen ihre Markierungen
beim erneuten Öffnen wieder, verwalten sie in einer Liste "Meine Markierungen" im bestehenden
Lesezeichen-Reiter, und können sie zusammen mit dem zitierten Text und der Seitenzahl drucken/exportieren.

**Architecture:** Neues Sub-Plugin `leafrtool_highlight` nach dem Muster von `leafrtool_confirm`
(Paket F). Eine Markierung = eine Seite + eine Liste prozentual positionierter Rechtecke + zitierter
Text + Farbe + optionale Notiz (Speicherformat siehe Task 2). Die Textauswahl selbst läuft komplett im
Sub-Plugin-JavaScript, das dafür `mod_leafr/pdf`s bereits vorhandene `getTextItems()` wiederverwendet
(keine Kernänderung nötig). Zwei kleine, generische Erweiterungen im Kern-Reader (Task 1) geben jedem
zukünftigen Werkzeug Zugriff auf das PDF-Dokument und auf "ein Tab wurde zum ersten Mal geöffnet" –
ohne dass der Kern etwas über Textmarker weiß, im Sinne der bestehenden Hook-Philosophie
(`tool/README.md`).

**Tech Stack:** PHP (Moodle-Subplugin `leafrtool`), JavaScript/AMD (ES-Module, Grunt-Build), PDF.js
(`mod_leafr/pdf`, insb. `getTextItems()`), Mustache, PHPUnit, Behat.

**Abweichung von der Standard-Granularität dieser Skill-Vorlage:** Bei einem Paket dieser Größe
(13 neue/geänderte Dateien im Kern und Sub-Plugin) werden reine Kopiermuster-Aufgaben (z. B. Backup/
Restore, Privacy-Provider), die 1:1 dem bestehenden `leafrtool_confirm`-Vorbild folgen, als ein Task mit
vollständigem Code zusammengefasst statt in einzelne 2-Minuten-Schritte zerlegt – wie auch die
bisherigen Pakete A–G laut `CHANGELOG.md` als ein bis zwei zusammenhängende Commits geliefert wurden,
nicht als viele Mikro-Commits. Jeder Task liefert trotzdem vollständigen, direkt einsetzbaren Code, keine
Platzhalter.

---

## Vorab zu bestätigen (bitte vor Task 1 lesen)

Zwei kleine, aber echte Kernänderungen sind nötig, weil `tool/README.md` selbst sagt: „Eigene
Werkzeugleisten-Knöpfe oder Seitenleisten-Reiter … noch nicht generalisiert.“ Für Paket H wird das jetzt
nachgeholt, generisch (nicht Textmarker-spezifisch), damit spätere Werkzeuge denselben Weg nutzen können:

1. **Neues DOM-Ereignis `leafr:reader-ready`** (`amd/src/reader.js`): wird einmal ausgelöst, sobald das
   PDF geladen und die erste Ansicht aufgebaut ist. Detail: `{cmid, pdfDoc, total}`. Gibt
   Sub-Plugin-JavaScript Zugriff auf das PDF-Dokument, ohne dass der Kern-Reader etwas über Textmarker
   wissen muss – analog zum bereits vorhandenen `leafr:reading-complete`.
2. **Neues DOM-Ereignis `leafr:tab-activated`** (`amd/src/sidebar.js`): wird beim ersten Öffnen jedes
   Seitenleisten-Reiters auf dessen Panel-Element ausgelöst (Detail: `{tab}`, blubbert zum `document`).
   Lässt ein Sub-Plugin erkennen, wann z. B. der Lesezeichen-Reiter zum ersten Mal sichtbar wird, um dann
   erst (sparsam) seine eigene "Meine Markierungen"-Liste zu laden.
3. **Neuer PHP-Hook `render_bookmarks_tab()`** (`tool/README.md` + `tool_manager.php`): wie
   `render_reader()`, aber das HTML landet als leerer, vom Sub-Plugin selbst befüllter Container **im
   Lesezeichen-Reiter-Panel** statt unter dem Reader.

Diese drei Änderungen sind minimal, rückwärtskompatibel (kein bestehendes Sub-Plugin nutzt sie, also
nichts bricht) und werden in `tool/README.md` dokumentiert wie alle anderen Hooks. Falls Peter das anders
haben möchte, bitte vor Task 1 Bescheid geben – sonst wird direkt losgelegt.

---

## Datenmodell (für alle Tasks verbindlich)

Tabelle `leafrtool_highlight` (eine Zeile = eine Markierung):

| Feld | Typ | Bedeutung |
|---|---|---|
| `id` | int | PK |
| `leafrid` | int | FK auf `leafr.id` |
| `userid` | int | FK auf `user.id` |
| `pageno` | int | Seite (1-basiert), auf der die Markierung liegt |
| `colour` | char(10) | eine von `yellow`, `green`, `pink`, `blue` |
| `quotetext` | text | markierter Text (Klartext, für Anzeige/Export) |
| `rects` | text | JSON-Array `[{"left":0,"top":0,"width":0,"height":0}, …]`, Werte in Prozent der Seite |
| `note` | text, nullable | optionale Notiz, max. 500 Zeichen (wie Lesezeichen) |
| `timecreated` | int | |
| `timemodified` | int | |

Eine Markierung bezieht sich immer auf **eine** Seite (Roadmap: „Speicherung als Seite + Textbereich“).
Reicht eine Browser-Selektion über mehrere Seiten, wird sie im Frontend auf die Startseite begrenzt und
der Nutzer bekommt einen Hinweis (Task 5). Mehrere `rects` pro Zeile der Selektion sind normal (eine
mehrzeilige Markierung erzeugt mehrere Rechtecke aus `Range.getClientRects()`).

---

### Task 1: Kern-Erweiterungen (zwei Events + ein Hook)

**Files:**
- Modify: `amd/src/reader.js`
- Modify: `amd/src/sidebar.js`
- Modify: `classes/local/tool_manager.php`
- Modify: `tool/README.md`
- Modify: `view.php` (oder die Stelle, die den Mustache-Kontext für `reader.mustache` zusammenbaut –
  wird beim Öffnen von `view.php` verifiziert, da der genaue Dateiname/die Zeile in der Exploration
  nicht abschließend geprüft wurde)
- Modify: `templates/reader.mustache`

- [ ] **Schritt 1: `leafr:reader-ready` in `reader.js` auslösen**

  In `init()`, direkt nach `await this.showView();` (siehe `amd/src/reader.js:198`), ergänzen:

  ```js
  await this.showView();
  document.dispatchEvent(new CustomEvent('leafr:reader-ready', {
      detail: {cmid: this.cmid, pdfDoc: this.pdfDoc, total: this.total},
  }));
  this.setLoading(false);
  ```

- [ ] **Schritt 2: `leafr:tab-activated` in `sidebar.js` auslösen**

  In der `Sidebar`-Klasse, an der Stelle, an der ein Tab zum ersten Mal aktiviert wird (dort, wo die
  `onFirstActivate`-Callbacks ausgeführt werden), unmittelbar vor dem Callback-Aufruf ergänzen:

  ```js
  panel.dispatchEvent(new CustomEvent('leafr:tab-activated', {bubbles: true, detail: {tab: name}}));
  ```

  (`panel` = das Panel-Element des gerade aktivierten Tabs, `name` = Tab-Name wie in
  `this.tabs`/`onFirstActivate(name, …)`. Die genaue Variable prüft der Bearbeiter beim Öffnen der
  Datei – Ziel ist: das Ereignis feuert **bevor** der registrierte `onFirstActivate`-Callback läuft, und
  **nur beim ersten Mal**, wie der Callback selbst auch nur einmal läuft.)

- [ ] **Schritt 3: Hook `render_bookmarks_tab()` in `tool_manager.php` ergänzen**

  Nach der bestehenden Methode `render_reader()` (siehe `classes/local/tool_manager.php:219-228`)
  einfügen:

  ```php
  /**
   * Collects the HTML every enabled tool wants to add inside the "Bookmarks" sidebar tab, below the
   * built-in bookmark list (e.g. a "My highlights" section).
   *
   * @param \cm_info $cm Course module
   * @param \context_module $context Module context
   * @param stdClass $leafr Leafr instance record
   * @return string Rendered HTML, empty if no enabled tool contributes anything
   */
  public static function render_bookmarks_tab(\cm_info $cm, \context_module $context, stdClass $leafr): string {
      $html = '';
      foreach (self::get_enabled_tools() as $component) {
          $piece = component_callback($component, 'render_bookmarks_tab', [$cm, $context, $leafr]);
          if ($piece) {
              $html .= $piece;
          }
      }
      return $html;
  }
  ```

- [ ] **Schritt 4: Hook in `view.php` aufrufen und ins Template geben**

  In `view.php` suchen, wo `tool_manager::render_reader($cm, $context, $leafr)` bereits aufgerufen und
  in den Mustache-Kontext geschrieben wird (Variable vermutlich `toolsreaderhtml` o. ä.). Direkt daneben
  ergänzen:

  ```php
  $templatecontext->toolsbookmarkstab = \mod_leafr\local\tool_manager::render_bookmarks_tab($cm, $context, $leafr);
  ```

- [ ] **Schritt 5: Container im Lesezeichen-Panel ergänzen**

  In `templates/reader.mustache`, im Panel `[data-panel="bookmarks"]`, ans Ende (nach der Liste, die
  `BookmarkList` befüllt) ergänzen:

  ```mustache
  {{{toolsbookmarkstab}}}
  ```

- [ ] **Schritt 6: `tool/README.md` dokumentieren**

  Abschnitt „Werkzeugleiste, Seitenleiste und der Bereich unter dem Reader“ erweitern:

  ```markdown
  ```php
  function leafrtool_<name>_render_bookmarks_tab(cm_info $cm, context_module $context, stdClass $leafr): ?string
  ```

  Wird von `view.php` aufgerufen. Gibt fertiges HTML zurück, das **innerhalb des Lesezeichen-Reiters**
  unterhalb der eingebauten Lesezeichenliste eingefügt wird (Container, den das Werkzeug selbst über
  eigenes JavaScript befüllt), oder `null`. Zwei DOM-Ereignisse helfen dabei, ohne dass der Kern etwas
  über das Werkzeug wissen muss:

  - `document` löst `leafr:reader-ready` aus, sobald das PDF geladen ist (Detail: `{cmid, pdfDoc,
    total}`), z. B. um selbst mit PDF.js zu arbeiten (siehe `leafrtool_highlight`).
  - Das jeweilige Sidebar-Panel-Element löst `leafr:tab-activated` aus (Detail: `{tab}`, blubbert),
    sobald sein Reiter zum ersten Mal geöffnet wird – nützlich, um eigene Inhalte erst bei Bedarf zu
    laden.
  ```

- [ ] **Schritt 7: Lokal prüfen, dass nichts kaputtgeht**

  Moodle-Cache leeren (`purge_caches.php`), Testkurs öffnen, Reader lädt fehlerfrei, Lesezeichen-Reiter
  funktioniert weiter wie vorher (noch kein Sub-Plugin nutzt die neuen Hooks). Browser-Konsole: keine
  Fehler.

- [ ] **Schritt 8: Commit**

  ```bash
  git add amd/src/reader.js amd/src/sidebar.js classes/local/tool_manager.php tool/README.md view.php templates/reader.mustache
  git commit -m "core: add reader-ready/tab-activated events and render_bookmarks_tab hook for subplugins"
  ```

---

### Task 2: Sub-Plugin-Grundgerüst

**Files:**
- Create: `tool/highlight/version.php`
- Create: `tool/highlight/db/install.xml`
- Create: `tool/highlight/lib.php`
- Create: `tool/highlight/lang/de/leafrtool_highlight.php`
- Create: `tool/highlight/lang/en/leafrtool_highlight.php`

- [ ] **Schritt 1: `version.php`**

  ```php
  <?php
  defined('MOODLE_INTERNAL') || die();

  $plugin->component = 'leafrtool_highlight';
  $plugin->version   = 2026092400;
  $plugin->requires  = 2024100700; // Moodle 4.5 LTS.
  $plugin->maturity  = MATURITY_BETA;
  $plugin->release   = '1.2.0';
  $plugin->dependencies = ['mod_leafr' => 2026092400];
  ```

- [ ] **Schritt 2: `db/install.xml`** (eine Tabelle, siehe Datenmodell oben)

  ```xml
  <?xml version="1.0" encoding="UTF-8" ?>
  <XMLDB PATH="mod/leafr/tool/highlight/db" VERSION="20260924" COMMENT="leafrtool_highlight">
    <TABLES>
      <TABLE NAME="leafrtool_highlight" COMMENT="Private text highlights per user and activity">
        <FIELDS>
          <FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
          <FIELD NAME="leafrid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
          <FIELD NAME="userid" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
          <FIELD NAME="pageno" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
          <FIELD NAME="colour" TYPE="char" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
          <FIELD NAME="quotetext" TYPE="text" NOTNULL="true" SEQUENCE="false"/>
          <FIELD NAME="rects" TYPE="text" NOTNULL="true" SEQUENCE="false"/>
          <FIELD NAME="note" TYPE="text" NOTNULL="false" SEQUENCE="false"/>
          <FIELD NAME="timecreated" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
          <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="false"/>
        </FIELDS>
        <KEYS>
          <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
          <KEY NAME="fk_leafrid" TYPE="foreign" FIELDS="leafrid" REFTABLE="leafr" REFFIELDS="id"/>
          <KEY NAME="fk_userid" TYPE="foreign" FIELDS="userid" REFTABLE="user" REFFIELDS="id"/>
        </KEYS>
        <INDEXES>
          <INDEX NAME="leafrid-userid-pageno" UNIQUE="false" FIELDS="leafrid, userid, pageno"/>
        </INDEXES>
      </TABLE>
    </TABLES>
  </XMLDB>
  ```

- [ ] **Schritt 3: `lib.php` (Grundgerüst, `render_reader()` lädt nur das AMD-Modul)**

  ```php
  <?php
  defined('MOODLE_INTERNAL') || die();

  function leafrtool_highlight_render_reader(cm_info $cm, context_module $context, stdClass $leafr): ?string {
      global $USER;
      $PAGE->requires->js_call_amd('leafrtool_highlight/highlight', 'init', [[
          'cmid' => $cm->id,
          'colours' => ['yellow', 'green', 'pink', 'blue'],
          'maxnotelength' => 500,
      ]]);
      return null;
  }

  function leafrtool_highlight_render_bookmarks_tab(cm_info $cm, context_module $context, stdClass $leafr): ?string {
      global $OUTPUT;
      return $OUTPUT->render_from_template('leafrtool_highlight/list_container', ['cmid' => $cm->id]);
  }

  function leafrtool_highlight_delete_instance(int $leafrid): void {
      \leafrtool_highlight\local\highlight::delete_for_instance($leafrid);
  }

  function leafrtool_highlight_reset_course_form_definition(MoodleQuickForm $mform): void {
      $mform->addElement('checkbox', 'reset_leafr_highlights', get_string('resethighlights', 'leafrtool_highlight'));
  }

  function leafrtool_highlight_reset_course_form_defaults(): array {
      return ['reset_leafr_highlights' => 1];
  }

  function leafrtool_highlight_reset_userdata(stdClass $data): array {
      if (empty($data->reset_leafr_highlights)) {
          return [];
      }
      $leafrs = get_all_instances_in_course('leafr', get_course($data->courseid));
      $count = 0;
      foreach ($leafrs as $leafr) {
          $count += \leafrtool_highlight\local\highlight::delete_for_instance($leafr->id);
      }
      return [[
          'component' => get_string('pluginname', 'leafrtool_highlight'),
          'item' => get_string('resethighlights', 'leafrtool_highlight'),
          'error' => false,
      ]];
  }
  ```

  (`leafrtool_highlight_render_reader()` fügt kein HTML unter dem Reader ein, lädt aber sein
  AMD-Modul – wie `render_reader()` mit `null`-Rückgabe laut `tool/README.md` erlaubt ist. Der eigentliche
  Container fürs Ergebnis liegt im Lesezeichen-Reiter, siehe `render_bookmarks_tab()`.)

  `classes/local/highlight.php::delete_for_instance()` (Task 3) muss dafür die Anzahl gelöschter Zeilen
  zurückgeben (`int`, nicht `void` – Abweichung vom `confirm`-Vorbild, weil `reset_userdata()` eine
  Zusammenfassung braucht).

- [ ] **Schritt 4: Sprachdateien** (`lang/de/leafrtool_highlight.php`, Auszug – `en` analog auf Englisch)

  ```php
  <?php
  defined('MOODLE_INTERNAL') || die();

  $string['pluginname'] = 'Textmarker';
  $string['colour_yellow'] = 'Gelb';
  $string['colour_green'] = 'Grün';
  $string['colour_pink'] = 'Pink';
  $string['colour_blue'] = 'Blau';
  $string['highlighttext'] = 'Markieren';
  $string['removehighlight'] = 'Markierung entfernen';
  $string['addnote'] = 'Notiz hinzufügen';
  $string['mynotes'] = 'Meine Markierungen';
  $string['nohighlights'] = 'Noch keine Markierungen.';
  $string['crosspagewarning'] = 'Bitte nur Text auf einer Seite markieren.';
  $string['noselectabletext'] = 'Dieses Dokument enthält keinen markierbaren Text.';
  $string['resethighlights'] = 'Textmarkierungen löschen';
  $string['privacy:metadata:leafrtool_highlight'] = 'Private Textmarkierungen einer Person in einer Leafr-Aktivität.';
  $string['privacy:metadata:leafrtool_highlight:pageno'] = 'Die Seite, auf der markiert wurde.';
  $string['privacy:metadata:leafrtool_highlight:quotetext'] = 'Der markierte Text.';
  $string['privacy:metadata:leafrtool_highlight:note'] = 'Die eigene Notiz zur Markierung.';
  $string['privacy:metadata:leafrtool_highlight:timecreated'] = 'Wann die Markierung angelegt wurde.';
  ```

- [ ] **Schritt 5: Commit**

  ```bash
  git add tool/highlight/version.php tool/highlight/db/install.xml tool/highlight/lib.php tool/highlight/lang
  git commit -m "leafrtool_highlight: scaffold subplugin (schema, version, lib hooks, language strings)"
  ```

---

### Task 3: Datenzugriffsklasse + PHPUnit-Tests

**Files:**
- Create: `tool/highlight/classes/local/highlight.php`
- Test: `tool/highlight/tests/local/highlight_test.php`

- [ ] **Schritt 1: `classes/local/highlight.php`**

  ```php
  <?php
  namespace leafrtool_highlight\local;

  defined('MOODLE_INTERNAL') || die();

  class highlight {
      const COLOURS = ['yellow', 'green', 'pink', 'blue'];
      const MAX_NOTE_LENGTH = 500;

      public static function get_for_user(int $leafrid, int $userid): array {
          global $DB;
          $records = $DB->get_records('leafrtool_highlight', ['leafrid' => $leafrid, 'userid' => $userid],
              'pageno ASC, timecreated ASC');
          return array_map([self::class, 'cast_record'], $records);
      }

      public static function create(int $leafrid, int $userid, int $pageno, string $colour, string $quotetext,
              array $rects, ?string $note): stdClass {
          global $DB;
          if (!in_array($colour, self::COLOURS, true)) {
              throw new \invalid_parameter_exception('Unknown highlight colour: ' . $colour);
          }
          $note = self::clean_note($note);
          $now = time();
          $record = (object)[
              'leafrid' => $leafrid,
              'userid' => $userid,
              'pageno' => $pageno,
              'colour' => $colour,
              'quotetext' => $quotetext,
              'rects' => json_encode(array_values($rects)),
              'note' => $note,
              'timecreated' => $now,
              'timemodified' => $now,
          ];
          $record->id = $DB->insert_record('leafrtool_highlight', $record);
          return self::cast_record($record);
      }

      public static function update_note(int $id, int $userid, ?string $note): void {
          global $DB;
          $existing = $DB->get_record('leafrtool_highlight', ['id' => $id, 'userid' => $userid], '*', MUST_EXIST);
          $DB->update_record('leafrtool_highlight', (object)[
              'id' => $existing->id,
              'note' => self::clean_note($note),
              'timemodified' => time(),
          ]);
      }

      public static function delete(int $id, int $userid): void {
          global $DB;
          if ($DB->record_exists('leafrtool_highlight', ['id' => $id, 'userid' => $userid])) {
              $DB->delete_records('leafrtool_highlight', ['id' => $id, 'userid' => $userid]);
          }
      }

      public static function delete_for_instance(int $leafrid): int {
          global $DB;
          $count = $DB->count_records('leafrtool_highlight', ['leafrid' => $leafrid]);
          $DB->delete_records('leafrtool_highlight', ['leafrid' => $leafrid]);
          return $count;
      }

      private static function clean_note(?string $note): ?string {
          if ($note === null || trim($note) === '') {
              return null;
          }
          return \core_text::substr(trim($note), 0, self::MAX_NOTE_LENGTH);
      }

      private static function cast_record(\stdClass $record): \stdClass {
          $record->id = (int)$record->id;
          $record->leafrid = (int)$record->leafrid;
          $record->userid = (int)$record->userid;
          $record->pageno = (int)$record->pageno;
          $record->rects = json_decode($record->rects, true) ?: [];
          $record->timecreated = (int)$record->timecreated;
          $record->timemodified = (int)$record->timemodified;
          return $record;
      }
  }
  ```

- [ ] **Schritt 2: `tests/local/highlight_test.php`**

  ```php
  <?php
  namespace leafrtool_highlight\local;

  defined('MOODLE_INTERNAL') || die();

  /**
   * @covers \leafrtool_highlight\local\highlight
   */
  final class highlight_test extends \advanced_testcase {

      public function test_create_and_get_for_user(): void {
          $this->resetAfterTest();
          $rects = [['left' => 10.0, 'top' => 20.0, 'width' => 30.0, 'height' => 4.0]];
          $created = highlight::create(1, 2, 5, 'yellow', 'Hallo Welt', $rects, 'Wichtig');

          $this->assertSame('yellow', $created->colour);
          $this->assertSame($rects, $created->rects);

          $list = highlight::get_for_user(1, 2);
          $this->assertCount(1, $list);
          $this->assertSame('Hallo Welt', $list[0]->quotetext);
      }

      public function test_create_rejects_unknown_colour(): void {
          $this->resetAfterTest();
          $this->expectException(\invalid_parameter_exception::class);
          highlight::create(1, 2, 5, 'purple', 'x', [], null);
      }

      public function test_note_is_trimmed_to_max_length(): void {
          $this->resetAfterTest();
          $long = str_repeat('a', 600);
          $created = highlight::create(1, 2, 1, 'green', 'x', [], $long);
          $this->assertSame(500, \core_text::strlen($created->note));
      }

      public function test_update_note_only_for_owner(): void {
          $this->resetAfterTest();
          $created = highlight::create(1, 2, 1, 'green', 'x', [], null);
          highlight::update_note($created->id, 2, 'Neue Notiz');
          $list = highlight::get_for_user(1, 2);
          $this->assertSame('Neue Notiz', $list[0]->note);

          $this->expectException(\dml_missing_record_exception::class);
          highlight::update_note($created->id, 999, 'Fremd');
      }

      public function test_delete_only_for_owner(): void {
          $this->resetAfterTest();
          $created = highlight::create(1, 2, 1, 'green', 'x', [], null);
          highlight::delete($created->id, 999); // Fremder Nutzer: still, kein Fehler, nichts gelöscht.
          $this->assertCount(1, highlight::get_for_user(1, 2));
          highlight::delete($created->id, 2);
          $this->assertCount(0, highlight::get_for_user(1, 2));
      }

      public function test_delete_for_instance_isolates_other_instances(): void {
          $this->resetAfterTest();
          highlight::create(1, 2, 1, 'green', 'a', [], null);
          highlight::create(2, 2, 1, 'green', 'b', [], null);
          $removed = highlight::delete_for_instance(1);
          $this->assertSame(1, $removed);
          $this->assertCount(0, highlight::get_for_user(1, 2));
          $this->assertCount(1, highlight::get_for_user(2, 2));
      }
  }
  ```

- [ ] **Schritt 3: Tests ausführen**

  ```bash
  vendor/bin/phpunit --filter leafrtool_highlight tool/highlight/tests/local/highlight_test.php
  ```

  Erwartung: 6 Tests, alle grün.

- [ ] **Schritt 4: Commit**

  ```bash
  git add tool/highlight/classes/local/highlight.php tool/highlight/tests/local/highlight_test.php
  git commit -m "leafrtool_highlight: add data access class with PHPUnit coverage"
  ```

---

### Task 4: Webservices

**Files:**
- Create: `tool/highlight/db/services.php`
- Create: `tool/highlight/classes/external/highlight_create.php`
- Create: `tool/highlight/classes/external/highlight_update_note.php`
- Create: `tool/highlight/classes/external/highlight_delete.php`
- Create: `tool/highlight/classes/external/highlight_list.php`

Alle vier Klassen folgen exakt dem Muster von `tool/confirm/classes/external/confirm_set.php`:
`validate_parameters()` → `get_course_and_cm_from_cmid($cmid, 'leafr')` →
`context_module::instance()` → `self::validate_context($context)` →
`require_capability('mod/leafr:view', $context)` → Gastprüfung (`require_login($course, false, $cm);
if (isguestuser()) { throw new \moodle_exception(...); }`) → Aufruf der `local\highlight`-Methode mit
`$USER->id`.

- [ ] **Schritt 1: `classes/external/highlight_create.php`**

  ```php
  <?php
  namespace leafrtool_highlight\external;

  use core_external\external_api;
  use core_external\external_function_parameters;
  use core_external\external_multiple_structure;
  use core_external\external_single_structure;
  use core_external\external_value;

  defined('MOODLE_INTERNAL') || die();

  class highlight_create extends external_api {
      public static function execute_parameters(): external_function_parameters {
          return new external_function_parameters([
              'cmid' => new external_value(PARAM_INT, 'Course module id'),
              'pageno' => new external_value(PARAM_INT, 'Page number'),
              'colour' => new external_value(PARAM_ALPHA, 'Highlight colour'),
              'quotetext' => new external_value(PARAM_RAW, 'Quoted text'),
              'rects' => new external_value(PARAM_RAW, 'JSON-encoded array of {left,top,width,height} in percent'),
              'note' => new external_value(PARAM_RAW, 'Optional note', VALUE_DEFAULT, ''),
          ]);
      }

      public static function execute(int $cmid, int $pageno, string $colour, string $quotetext, string $rects,
              string $note = ''): array {
          global $USER;
          $params = self::validate_parameters(self::execute_parameters(),
              compact('cmid', 'pageno', 'colour', 'quotetext', 'rects', 'note'));

          [$course, $cm] = get_course_and_cm_from_cmid($params['cmid'], 'leafr');
          $context = \context_module::instance($cm->id);
          self::validate_context($context);
          require_capability('mod/leafr:view', $context);
          require_login($course, false, $cm);
          if (isguestuser()) {
              throw new \moodle_exception('noguest');
          }

          $decodedrects = json_decode($params['rects'], true);
          if (!is_array($decodedrects)) {
              throw new \invalid_parameter_exception('rects must be a JSON array');
          }

          $created = \leafrtool_highlight\local\highlight::create(
              $cm->instance, $USER->id, $params['pageno'], $params['colour'], $params['quotetext'],
              $decodedrects, $params['note'] === '' ? null : $params['note']
          );

          return ['id' => $created->id, 'timecreated' => $created->timecreated];
      }

      public static function execute_returns(): external_single_structure {
          return new external_single_structure([
              'id' => new external_value(PARAM_INT, 'Id of the new highlight'),
              'timecreated' => new external_value(PARAM_INT, 'Creation timestamp'),
          ]);
      }
  }
  ```

- [ ] **Schritt 2: `classes/external/highlight_update_note.php`, `highlight_delete.php`**

  Gleiches Skelett, Parameter `id` (PARAM_INT) statt `pageno`/`colour`/`quotetext`/`rects`;
  `update_note` zusätzlich `note`. Beide rufen `\leafrtool_highlight\local\highlight::update_note($id,
  $USER->id, $note)` bzw. `::delete($id, $USER->id)` auf und geben `['success' => true]` zurück
  (`external_single_structure` mit `'success' => new external_value(PARAM_BOOL, ...)`).

- [ ] **Schritt 3: `classes/external/highlight_list.php`**

  Parameter nur `cmid`. Ruft `\leafrtool_highlight\local\highlight::get_for_user($cm->instance,
  $USER->id)` auf. `execute_returns()`:

  ```php
  public static function execute_returns(): external_multiple_structure {
      return new external_multiple_structure(new external_single_structure([
          'id' => new external_value(PARAM_INT, 'Id'),
          'pageno' => new external_value(PARAM_INT, 'Page number'),
          'colour' => new external_value(PARAM_ALPHA, 'Colour'),
          'quotetext' => new external_value(PARAM_RAW, 'Quoted text'),
          'rects' => new external_value(PARAM_RAW, 'JSON-encoded rects'),
          'note' => new external_value(PARAM_RAW, 'Note', VALUE_DEFAULT, ''),
          'timecreated' => new external_value(PARAM_INT, 'Created'),
      ]));
  }
  ```

  In `execute()` jedes Element mit `'rects' => json_encode($item->rects), 'note' => $item->note ?? ''`
  aufbauen (der Webservice serialisiert `rects` zurück zu JSON, damit der Rückgabetyp einfach bleibt;
  das JS decodiert es wieder).

- [ ] **Schritt 4: `db/services.php`**

  ```php
  <?php
  defined('MOODLE_INTERNAL') || die();

  $functions = [
      'leafrtool_highlight_create' => [
          'classname' => 'leafrtool_highlight\external\highlight_create',
          'description' => 'Creates a new text highlight',
          'type' => 'write',
          'ajax' => true,
          'capabilities' => 'mod/leafr:view',
          'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
      ],
      'leafrtool_highlight_update_note' => [
          'classname' => 'leafrtool_highlight\external\highlight_update_note',
          'description' => 'Updates a highlight\'s note',
          'type' => 'write',
          'ajax' => true,
          'capabilities' => 'mod/leafr:view',
          'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
      ],
      'leafrtool_highlight_delete' => [
          'classname' => 'leafrtool_highlight\external\highlight_delete',
          'description' => 'Deletes a highlight',
          'type' => 'write',
          'ajax' => true,
          'capabilities' => 'mod/leafr:view',
          'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
      ],
      'leafrtool_highlight_list' => [
          'classname' => 'leafrtool_highlight\external\highlight_list',
          'description' => 'Lists the current user\'s highlights for an activity',
          'type' => 'read',
          'ajax' => true,
          'capabilities' => 'mod/leafr:view',
          'services' => [MOODLE_OFFICIAL_MOBILE_SERVICE],
      ],
  ];
  ```

- [ ] **Schritt 5: Lokal prüfen**

  Nach `php admin/cli/upgrade.php --non-interactive` die vier Funktionen in „Website-Administration →
  Plugins → Externe Dienste“ prüfen (sollten automatisch registriert sein), testweise per
  `webservice/rest/server.php` oder direkt über die spätere JS-Anbindung (Task 5/6) aufrufen.

- [ ] **Schritt 6: Commit**

  ```bash
  git add tool/highlight/db/services.php tool/highlight/classes/external
  git commit -m "leafrtool_highlight: add create/update/delete/list webservices"
  ```

---

### Task 5: Text-Layer + Auswahl-UI (AMD)

**Files:**
- Create: `tool/highlight/amd/src/highlight.js`
- Create: `tool/highlight/styles.css`

Kernidee: `leafr:reader-ready` liefert `pdfDoc`. Bei `mouseup`/`selectionchange` mit nicht-leerer
`window.getSelection()` innerhalb eines `[data-page]`-Containers wird die native Browser-Selektion
ausgewertet (kein eigener Textlayer nötig, weil die Seite bereits eine unsichtbare, selektierbare
Textebene hat – **Korrektur zur ursprünglichen Roadmap-Formulierung "PDF.js-Textebene über der Seite"**:
diese wird als Teil dieses Tasks direkt im Sub-Plugin gebaut, nicht im Kern, siehe Schritt 1, weil sie
nur für Paket H gebraucht wird und der Kern dafür nicht angefasst werden muss).

- [ ] **Schritt 1: Unsichtbare, selektierbare Textebene je Seite aufbauen**

  ```js
  import {getTextItems} from 'mod_leafr/pdf';

  const buildTextLayer = async(pageEl, pdfDoc, pageno) => {
      if (pageEl.querySelector('.leafrtool-highlight-textlayer')) {
          return; // Already built.
      }
      let items;
      try {
          items = await getTextItems(pdfDoc, pageno);
      } catch (error) {
          return; // Scan without text: nothing to select.
      }
      if (!items.length) {
          return;
      }
      const pageSize = pageEl.querySelector('canvas.leafr-page-canvas');
      if (!pageSize) {
          return;
      }
      const width = pageSize.width || pageSize.getBoundingClientRect().width;
      const height = pageSize.height || pageSize.getBoundingClientRect().height;

      const layer = document.createElement('div');
      layer.className = 'leafrtool-highlight-textlayer';
      items.forEach((item) => {
          const span = document.createElement('span');
          span.textContent = item.str;
          span.style.left = (item.left / width * 100) + '%';
          span.style.top = (item.top / height * 100) + '%';
          span.style.width = (item.width / width * 100) + '%';
          span.style.height = (item.height / height * 100) + '%';
          span.style.fontSize = (item.height / height * pageEl.offsetHeight) + 'px';
          layer.appendChild(span);
      });
      pageEl.appendChild(layer);
  };
  ```

  CSS (`styles.css`):

  ```css
  .leafrtool-highlight-textlayer {
      position: absolute;
      inset: 0;
      line-height: 1;
      pointer-events: none;
  }
  .leafrtool-highlight-textlayer span {
      position: absolute;
      color: transparent;
      white-space: pre;
      transform-origin: 0 0;
      user-select: text;
      cursor: text;
      pointer-events: all;
  }
  ```

  (`.leafr-page` bzw. `.leafr-scroll-page` haben laut Exploration bereits `position: relative` – ohne
  das würde schon der bestehende Such-Treffer-Overlay `leafr-search-highlight` nicht funktionieren. Der
  Bearbeiter prüft das beim Implementieren kurz in `styles.css` des Kerns und ergänzt es dort nur, falls
  es doch fehlt.)

- [ ] **Schritt 2: Textebene lazy pro sichtbarer Seite aufbauen**

  Da Rendering pro Seite passiert (Canvas erscheint/verschwindet beim Blättern/Scrollen), die Textebene
  bei jedem `leafr:reading-complete`-unabhängigen Mechanismus neu aufbauen: einfachster robuster Weg ist
  ein `MutationObserver` auf dem `viewHost` (Selektor wie beim Kern: `[data-region="leafr-view"]` bzw.
  äquivalent – Bearbeiter prüft den tatsächlichen Selektor beim Einbauen), der bei neu hinzugefügten
  `[data-page]`-Knoten `buildTextLayer()` aufruft, plus ein initialer Durchlauf über bereits vorhandene
  Seiten nach `leafr:reader-ready`.

- [ ] **Schritt 3: Selektion auswerten und Farbwahl-Popup zeigen**

  ```js
  let currentSelection = null;

  document.addEventListener('mouseup', (event) => {
      const selection = window.getSelection();
      if (!selection || selection.isCollapsed || !selection.toString().trim()) {
          hidePopup();
          return;
      }
      const range = selection.getRangeAt(0);
      const startPage = range.startContainer.parentElement?.closest('[data-page]');
      const endPage = range.endContainer.parentElement?.closest('[data-page]');
      if (!startPage || !startPage.closest('.leafrtool-highlight-textlayer')) {
          return; // Selection outside a text layer (e.g. UI chrome): ignore.
      }
      if (startPage !== endPage) {
          showToast(strings.crosspagewarning);
          hidePopup();
          return;
      }
      const pageno = parseInt(startPage.dataset.page, 10);
      const pageRect = startPage.getBoundingClientRect();
      const rects = [...range.getClientRects()].map((rect) => ({
          left: (rect.left - pageRect.left) / pageRect.width * 100,
          top: (rect.top - pageRect.top) / pageRect.height * 100,
          width: rect.width / pageRect.width * 100,
          height: rect.height / pageRect.height * 100,
      }));
      currentSelection = {pageno, quotetext: selection.toString(), rects};
      showPopup(event.clientX, event.clientY);
  });
  ```

  `showPopup()` rendert 4 Farbknöpfe (`data-colour="yellow|green|pink|blue"`) nahe der
  Mausposition/`range.getBoundingClientRect()`. Klick auf eine Farbe ruft
  `Ajax.call([{methodname: 'leafrtool_highlight_create', args: {cmid, pageno: currentSelection.pageno,
  colour, quotetext: currentSelection.quotetext, rects: JSON.stringify(currentSelection.rects), note:
  ''}}])[0]` auf, zeichnet danach die persistente Markierung (Task 6) und löscht die
  Browser-Selektion (`selection.removeAllRanges()`).

- [ ] **Schritt 4: Hinweis bei Scans ohne Text**

  Wenn `getTextItems()` für alle Seiten leer bleibt (kein Text im Dokument), keinen aktiven Aufwand
  betreiben: einfach keine Textebene aufbauen, keine Markierung möglich. Optionaler Hinweis
  (`noselectabletext`) erscheint nur, falls der Nutzer die "Meine Markierungen"-Liste öffnet und noch
  keine Textebene existiert (Task 7 prüft das dort, kein zusätzlicher UI-Code hier nötig).

- [ ] **Schritt 5: Lokal im Browser testen**

  Testdokument mit Text (`tests/fixtures/sample.pdf`) öffnen, Text markieren, Farbe wählen, Netzwerk-Tab
  prüft `leafrtool_highlight_create`-Aufruf mit plausiblen `rects`. Scan-Testdokument (Bild ohne Text)
  öffnen: keine Textebene, keine Fehler in der Konsole.

- [ ] **Schritt 6: Commit**

  ```bash
  git add tool/highlight/amd/src/highlight.js tool/highlight/styles.css
  git commit -m "leafrtool_highlight: build selectable text layer and colour-picker selection UI"
  ```

---

### Task 6: Persistente Overlays beim Laden anzeigen + Entfernen per Klick

**Files:**
- Modify: `tool/highlight/amd/src/highlight.js`

- [ ] **Schritt 1: Bestehende Markierungen laden**

  Nach `leafr:reader-ready`: `Ajax.call([{methodname: 'leafrtool_highlight_list', args: {cmid}}])[0]`,
  Ergebnis in `this.highlights` (Array, `rects` mit `JSON.parse()` zurückgewandelt) merken.

- [ ] **Schritt 2: Overlay je Markierung zeichnen, sobald ihre Seite im DOM erscheint**

  Im selben `MutationObserver`-Callback aus Task 5 Schritt 2: für jede Markierung mit
  `highlight.pageno === pageno` ein `<div class="leafrtool-highlight-mark" data-colour="…"
  data-highlight-id="…">` je Rechteck anlegen, prozentual positioniert wie die Textebene (gleiches
  Koordinatensystem, siehe Task 5 Schritt 3). CSS: `background: var(--leafrtool-highlight-yellow);
  opacity: .35; pointer-events: all; cursor: pointer; position: absolute;` (4 CSS-Variablen für die 4
  Farben, kontraststark und farbfehlsichtig-verträglich gewählt, z. B. `#ffd54a`, `#8bd17c`, `#f4a6c6`,
  `#7fb8e6` – Bearbeiter prüft beim Umsetzen kurz den Kontrast der reinen Flächenfarbe, nicht als
  Textfarbe verwendet, daher kein WCAG-Textkontrast nötig, aber deutlich unterscheidbar auch bei
  Rot-Grün-Schwäche).

- [ ] **Schritt 3: Klick auf ein Overlay öffnet Mini-Menü**

  Klick auf `.leafrtool-highlight-mark` zeigt ein kleines Menü („Notiz hinzufügen/ändern“, „Entfernen“)
  in der Nähe des Klicks. „Entfernen“ ruft `leafrtool_highlight_delete` auf und entfernt alle
  `.leafrtool-highlight-mark`-Elemente mit demselben `data-highlight-id` aus dem DOM sowie den Eintrag
  aus `this.highlights` und – falls die Liste aus Task 7 gerade offen ist – aus deren Anzeige.

- [ ] **Schritt 4: Lokal testen**

  Seite mit Markierung verlassen und zurückkehren (bzw. Reader neu laden): Markierung erscheint wieder
  exakt an derselben Textstelle, auch nach Zoom-Änderung (da prozentual positioniert). Entfernen
  funktioniert, Reload zeigt sie danach nicht mehr.

- [ ] **Schritt 5: Commit**

  ```bash
  git add tool/highlight/amd/src/highlight.js
  git commit -m "leafrtool_highlight: render persisted highlights and support removal"
  ```

---

### Task 7: "Meine Markierungen"-Liste im Lesezeichen-Reiter

**Files:**
- Create: `tool/highlight/templates/list_container.mustache`
- Modify: `tool/highlight/amd/src/highlight.js`

- [ ] **Schritt 1: Leerer Container (serverseitig gerendert)**

  `templates/list_container.mustache`:

  ```mustache
  <section data-region="leafrtool-highlight-list" data-cmid="{{cmid}}">
      <h3>{{#str}}mynotes, leafrtool_highlight{{/str}}</h3>
      <ul data-region="items"></ul>
      <p data-region="empty">{{#str}}nohighlights, leafrtool_highlight{{/str}}</p>
  </section>
  ```

- [ ] **Schritt 2: Liste lazy befüllen, sobald der Lesezeichen-Reiter zum ersten Mal offen ist**

  ```js
  document.addEventListener('leafr:tab-activated', (event) => {
      if (event.detail.tab !== 'bookmarks') {
          return;
      }
      renderHighlightList();
  }, {once: true});
  ```

  (`{once: true}` reicht, weil `leafr:tab-activated` laut Task 1 selbst nur beim ersten Öffnen jedes
  Tabs feuert.)

  `renderHighlightList()` nutzt `this.highlights` (bereits aus Task 6 geladen) und rendert pro Eintrag
  ein `<li>` mit: Seitenlink (`data-action="goto"`, Klick → bestehendes `Reader`-Navigationsmuster über
  ein eigenes `leafr:goto-page`-artiges Vorgehen – da der Kern keine öffentliche `goTo()`-API nach außen
  gibt, reicht ein einfacher Ankerlink `#` mit `data-page` und ein `document.dispatchEvent(new
  CustomEvent('leafr:request-goto', {detail: {page}}))`; **dafür ist ein dritter, kleiner Kern-Hook
  nötig** – siehe Ergänzung in Task 1 Schritt 1: `reader.js` registriert zusätzlich
  `document.addEventListener('leafr:request-goto', (e) => this.goTo(e.detail.page))` in `bindEvents()`),
  farbigem Punkt (`data-colour`), zitiertem Text (gekürzt auf ~120 Zeichen mit „…“), editierbarem
  Notizfeld (gleiches Debounce/Zeichenzähler-Muster wie `BookmarkList.buildItem()`), und
  „Entfernen“-Knopf (ruft dieselbe Lösch-Logik wie Task 6 Schritt 3 auf).

- [ ] **Schritt 3: Hinweistext bei Dokumenten ohne markierbaren Text**

  Falls nach `leafr:reader-ready` feststeht, dass keine einzige Seite Text lieferte (aus Task 5 Schritt
  4 bekannt), `data-region="empty"` stattdessen mit `noselectabletext` statt `nohighlights` befüllen.

- [ ] **Schritt 4: Lokal testen**

  Mehrere Markierungen mit und ohne Notiz anlegen, Lesezeichen-Reiter öffnen: Liste zeigt alle, Klick auf
  Seitenzahl springt hin, Notiz bearbeiten und Reload zeigt die neue Notiz, Entfernen aus der Liste
  entfernt auch das Overlay auf der Seite.

- [ ] **Schritt 5: Commit**

  ```bash
  git add tool/highlight/templates/list_container.mustache tool/highlight/amd/src/highlight.js
  git commit -m "leafrtool_highlight: add 'my highlights' list inside the bookmarks sidebar tab"
  ```

  (Ergänzend, falls in Task 1 Schritt 1 der `leafr:request-goto`-Listener vergessen wurde: an dieser
  Stelle in `amd/src/reader.js#bindEvents()` nachtragen und in den Task-1-Commit-Nachtrag oder einen
  eigenen kleinen Commit packen – wichtig ist, dass er vor diesem Task getestet ist.)

---

### Task 8: Datenschutz (Privacy Provider)

**Files:**
- Create: `tool/highlight/classes/privacy/provider.php`

1:1 nach dem Muster von `tool/confirm/classes/privacy/provider.php`, aber für eine reine
Nutzerdaten-Tabelle (kein Settings-Teil, da Paket H keine Aktivitäts-Einstellungen hat):

- [ ] **Schritt 1: Provider schreiben**

  ```php
  <?php
  namespace leafrtool_highlight\privacy;

  use core_privacy\local\metadata\collection;
  use core_privacy\local\request\approved_contextlist;
  use core_privacy\local\request\approved_userlist;
  use core_privacy\local\request\contextlist;
  use core_privacy\local\request\userlist;
  use core_privacy\local\request\writer;

  defined('MOODLE_INTERNAL') || die();

  class provider implements
      \core_privacy\local\metadata\provider,
      \core_privacy\local\request\core_userlist_provider,
      \core_privacy\local\request\plugin\provider {

      public static function get_metadata(collection $collection): collection {
          $collection->add_database_table('leafrtool_highlight', [
              'pageno' => 'privacy:metadata:leafrtool_highlight:pageno',
              'quotetext' => 'privacy:metadata:leafrtool_highlight:quotetext',
              'note' => 'privacy:metadata:leafrtool_highlight:note',
              'timecreated' => 'privacy:metadata:leafrtool_highlight:timecreated',
          ], 'privacy:metadata:leafrtool_highlight');
          return $collection;
      }

      public static function get_contexts_for_userid(int $userid): contextlist {
          $sql = "SELECT ctx.id
                    FROM {leafrtool_highlight} h
                    JOIN {leafr} l ON l.id = h.leafrid
                    JOIN {course_modules} cm ON cm.instance = l.id
                    JOIN {modules} m ON m.id = cm.module AND m.name = 'leafr'
                    JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                   WHERE h.userid = :userid";
          $contextlist = new contextlist();
          $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);
          return $contextlist;
      }

      public static function get_users_in_context(userlist $userlist): void {
          $context = $userlist->get_context();
          if (!$context instanceof \context_module) {
              return;
          }
          $sql = "SELECT h.userid
                    FROM {leafrtool_highlight} h
                    JOIN {leafr} l ON l.id = h.leafrid
                    JOIN {course_modules} cm ON cm.instance = l.id
                   WHERE cm.id = :cmid";
          $userlist->add_from_sql('userid', $sql, ['cmid' => $context->instanceid]);
      }

      public static function export_user_data(approved_contextlist $contextlist): void {
          global $DB;
          foreach ($contextlist->get_contexts() as $context) {
              if (!$context instanceof \context_module) {
                  continue;
              }
              $leafrid = self::get_instance_id($context);
              $records = $DB->get_records('leafrtool_highlight', ['leafrid' => $leafrid, 'userid' => $contextlist->get_user()->id]);
              if ($records) {
                  writer::with_context($context)->export_data(
                      [get_string('pluginname', 'leafrtool_highlight')],
                      (object)['highlights' => array_values($records)]
                  );
              }
          }
      }

      public static function delete_data_for_all_users_in_context(\context $context): void {
          if (!$context instanceof \context_module) {
              return;
          }
          global $DB;
          $DB->delete_records('leafrtool_highlight', ['leafrid' => self::get_instance_id($context)]);
      }

      public static function delete_data_for_user(approved_contextlist $contextlist): void {
          global $DB;
          foreach ($contextlist->get_contexts() as $context) {
              if (!$context instanceof \context_module) {
                  continue;
              }
              $DB->delete_records('leafrtool_highlight', [
                  'leafrid' => self::get_instance_id($context),
                  'userid' => $contextlist->get_user()->id,
              ]);
          }
      }

      public static function delete_data_for_users(approved_userlist $userlist): void {
          global $DB;
          $context = $userlist->get_context();
          if (!$context instanceof \context_module) {
              return;
          }
          $leafrid = self::get_instance_id($context);
          foreach ($userlist->get_userids() as $userid) {
              $DB->delete_records('leafrtool_highlight', ['leafrid' => $leafrid, 'userid' => $userid]);
          }
      }

      private static function get_instance_id(\context $context): int {
          $cm = get_coursemodule_from_id('leafr', $context->instanceid, 0, false, MUST_EXIST);
          return (int)$cm->instance;
      }
  }
  ```

- [ ] **Schritt 2: Lokal prüfen**

  „Website-Administration → Nutzer/innen → Datenschutz und Richtlinien → Datenanfragen“: Test-Export für
  eine Person mit Markierungen anstoßen, prüfen dass `leafrtool_highlight`-Daten im Export erscheinen;
  Test-Löschung prüft, dass die Zeilen danach weg sind.

- [ ] **Schritt 3: Commit**

  ```bash
  git add tool/highlight/classes/privacy/provider.php
  git commit -m "leafrtool_highlight: implement privacy provider (export/delete)"
  ```

---

### Task 9: Backup/Restore

**Files:**
- Create: `tool/highlight/backup/moodle2/backup_leafrtool_highlight_subplugin.class.php`
- Create: `tool/highlight/backup/moodle2/restore_leafrtool_highlight_subplugin.class.php`

1:1 nach dem Muster von `tool/confirm`, aber nur ein Element (`highlights`/`highlight`), immer nur
gesichert wenn `$userinfo` (Nutzerdaten) aktiv ist, da eine Markierung ohne Nutzerbezug bedeutungslos
wäre (anders als `leafrtool_confirm_settings`, die auch ohne Nutzerdaten gesichert werden):

- [ ] **Schritt 1: Backup-Klasse**

  ```php
  <?php
  defined('MOODLE_INTERNAL') || die();

  class backup_leafrtool_highlight_subplugin extends backup_subplugin {
      protected function define_leafr_subplugin_structure() {
          $subplugin = $this->get_subplugin_element();
          $subpluginwrapper = new backup_nested_element($this->get_recommended_name());
          $subplugin->add_child($subpluginwrapper);

          $highlights = new backup_nested_element('highlights');
          $highlight = new backup_nested_element('highlight', ['id'], [
              'userid', 'pageno', 'colour', 'quotetext', 'rects', 'note', 'timecreated', 'timemodified',
          ]);
          $subpluginwrapper->add_child($highlights);
          $highlights->add_child($highlight);

          if ($this->get_setting_value('userinfo')) {
              $highlight->set_source_table('leafrtool_highlight', ['leafrid' => backup::VAR_PARENTID]);
              $highlight->annotate_ids('user', 'userid');
          }

          return $subplugin;
      }
  }
  ```

- [ ] **Schritt 2: Restore-Klasse**

  ```php
  <?php
  defined('MOODLE_INTERNAL') || die();

  class restore_leafrtool_highlight_subplugin extends restore_subplugin {
      protected function define_leafr_subplugin_structure() {
          return [
              new restore_path_element(
                  'leafrtool_highlight_highlight',
                  $this->get_pathfor('/highlights/highlight')
              ),
          ];
      }

      public function process_leafrtool_highlight_highlight($data): void {
          global $DB;
          $data = (object)$data;
          $data->leafrid = $this->get_new_parentid('leafr');
          $data->userid = $this->get_mappingid('user', $data->userid);
          if ($data->userid) {
              $DB->insert_record('leafrtool_highlight', $data);
          }
      }
  }
  ```

- [ ] **Schritt 3: Lokal prüfen**

  „Aktivität duplizieren“ mit einer Aktivität, die Markierungen der eingeloggten Testperson hat: prüfen
  ob Backup mit Nutzerdaten die Markierungen mitnimmt (dupliziert wird i. d. R. ohne Nutzerdaten – dann
  bewusst leer, wie bei Lesezeichen/Bestätigungen erwartet), und ob ein vollständiger Kurs-Export +
  -Import mit „Nutzerdaten einschließen“ sie korrekt überträgt (per CLI-Skript wie in
  `Moodle-Plugin-Wissen\lessons-learned.md` beschrieben gegenprüfen).

- [ ] **Schritt 4: Commit**

  ```bash
  git add tool/highlight/backup
  git commit -m "leafrtool_highlight: add backup/restore support"
  ```

---

### Task 10: Export/Druck erweitern

**Files:**
- Create: `tool/highlight/print.php`
- Create: `tool/highlight/templates/print.mustache`
- Modify: `tool/highlight/templates/list_container.mustache` (Link zur Druckansicht)

- [ ] **Schritt 1: `print.php`** (Skelett wie Kern-`print.php`, eine Ebene tiefer)

  ```php
  <?php
  require_once(__DIR__ . '/../../../../config.php');

  $id = required_param('id', PARAM_INT);
  [$course, $cm] = get_course_and_cm_from_cmid($id, 'leafr');
  $context = context_module::instance($cm->id);
  require_login($course, false, $cm);
  require_capability('mod/leafr:view', $context);

  $PAGE->set_url('/mod/leafr/tool/highlight/print.php', ['id' => $id]);
  $PAGE->set_context($context);
  $PAGE->set_pagelayout('popup');
  $PAGE->set_title(get_string('mynotes', 'leafrtool_highlight'));

  $leafr = $DB->get_record('leafr', ['id' => $cm->instance], '*', MUST_EXIST);
  $highlights = [];
  if (!isguestuser()) {
      $highlights = \leafrtool_highlight\local\highlight::get_for_user($leafr->id, $USER->id);
  }

  echo $OUTPUT->header();
  echo $OUTPUT->render_from_template('leafrtool_highlight/print', [
      'activityname' => format_string($leafr->name),
      'username' => fullname($USER),
      'exportdate' => userdate(time(), get_string('strftimedatetime', 'langconfig')),
      'empty' => empty($highlights),
      'highlights' => array_map(fn($h) => [
          'pagelabel' => get_string('page') . ' ' . $h->pageno,
          'quotetext' => $h->quotetext,
          'colour' => $h->colour,
          'note' => $h->note,
      ], $highlights),
  ]);
  echo $OUTPUT->footer();
  ```

- [ ] **Schritt 2: `templates/print.mustache`**

  Analog zum Kern-`print.mustache`: Kopfzeile mit Aktivitätsname/Nutzername/Exportdatum, dann pro
  Markierung ein Block mit Seitenzahl (`pagelabel`), Farbe als kleiner farbiger Balken, zitiertem Text
  in Anführungszeichen, Notiz darunter falls vorhanden. `{{^highlights}}` mit `nohighlights`-Text als
  Leerfall.

- [ ] **Schritt 3: Link aus der Liste**

  In `templates/list_container.mustache` einen Link `<a href="{{{printurl}}}"
  target="_blank">{{#str}}print, moodle{{/str}}</a>` ergänzen; `printurl` wird in
  `leafrtool_highlight_render_bookmarks_tab()` (Task 2) als
  `(new moodle_url('/mod/leafr/tool/highlight/print.php', ['id' => $cm->id]))->out(false)` an das
  Template übergeben.

- [ ] **Schritt 4: Lokal testen**

  Mehrere Markierungen mit/ohne Notiz anlegen, Druckansicht öffnen: alle erscheinen mit korrekter Seite,
  Text und Notiz; „Drucken/Als PDF speichern“ über den Browser-Dialog sieht sauber aus (kein
  abgeschnittener Text, siehe CSS-Erfahrung aus Paket C).

- [ ] **Schritt 5: Commit**

  ```bash
  git add tool/highlight/print.php tool/highlight/templates/print.mustache tool/highlight/templates/list_container.mustache
  git commit -m "leafrtool_highlight: add print/export view with quoted text and page reference"
  ```

---

### Task 11: Grunt-Build, Behat, CHANGELOG, ROADMAP

**Files:**
- Modify: `CHANGELOG.md`
- Modify: `ROADMAP.md`
- Modify: `tests/behat/leafr_basic.feature`

- [ ] **Schritt 1: AMD-Build**

  ```bash
  PATH=/c/Users/peter/AppData/Local/nvm/v22.11.0:$PATH node ../../node_modules/grunt/bin/grunt amd
  ```

  (im deployten Ordner `C:\moodle\server\moodle\mod\leafr`, danach `amd/build/*` zurück nach
  `leafr_github/tool/highlight/amd/build/` kopieren, wie in `CLAUDE.md` beschrieben.)

- [ ] **Schritt 2: Behat-Szenario ergänzen** (in `tests/behat/leafr_basic.feature`, neues `Scenario:`)

  ```gherkin
  @javascript
  Scenario: A student highlights text and finds it again in the bookmarks tab
    Given I am on the "Leafr Testkurs" course page logged in as student1
    And I follow "Leafr Testflipbook"
    And I select the text "Kapitel" in the ".leafrtool-highlight-textlayer" "css_element"
    And I click on "[data-colour='yellow']" "css_element"
    And I click on "[data-tab='bookmarks']" "css_element"
    Then I should see "Kapitel" in the "[data-region='leafrtool-highlight-list']" "css_element"
  ```

  (Die genaue Selektions-Step-Definition für „Text markieren“ existiert in Moodle-Behat nicht
  standardmäßig – Bearbeiter prüft, ob ein eigener JS-Snippet-Step nötig ist, z. B. über
  `I execute the javascript` mit `window.getSelection()`-Manipulation, wie es für ähnliche
  Text-Selektions-Tests in anderen Moodle-Plugins üblich ist.)

- [ ] **Schritt 3: Lokal alle Tests laufen lassen**

  ```bash
  vendor/bin/phpunit tool/highlight/tests
  vendor/bin/behat --tags=@mod_leafr
  ```

- [ ] **Schritt 4: `CHANGELOG.md` ergänzen**

  Neuer Abschnitt unter der aktuellen unveröffentlichten Version mit Stichpunkten analog zu den
  Einträgen der Pakete C–G (kurz, sachlich, was wirklich gebaut wurde – keine Behauptungen über nicht
  vorhandene Funktionen, laut `CLAUDE.md`-Arbeitsregel).

- [ ] **Schritt 5: `ROADMAP.md` abhaken**

  Alle fünf Punkte unter „Paket H“ auf `[x]` setzen, kurzen „Fertig …“-Absatz mit Datum und den während
  der Umsetzung getroffenen echten Entscheidungen ergänzen (wie bei den Paketen A–G), „Nächster Schritt“
  am Dateianfang auf Paket I setzen.

- [ ] **Schritt 6: Commit, Push, CI prüfen**

  ```bash
  git add -A
  git commit -m "leafrtool_highlight: finish Paket H (build, tests, changelog, roadmap)"
  git push origin release/1.2.0
  ```

  ```bash
  "/c/Program Files/GitHub CLI/gh.exe" run list --branch release/1.2.0
  ```

  Bei Rot: `gh run view <id> --log-failed`, Fehler beheben, `Moodle-Plugin-Wissen\lessons-learned.md`
  bei neuen generischen Fehlerklassen ergänzen (siehe `CI-Selbstreview-Wunsch`-Notiz).

---

## Selbstüberprüfung des Plans

- **Spec-Abdeckung** (ROADMAP.md, Paket H): Textebene ✅ (Task 5), Markieren+Farbe+Notiz ✅ (Task 5/6),
  „Meine Markierungen“ im Lesezeichen-Reiter ✅ (Task 7), Datenschutz/Backup/Zurücksetzen ✅ (Task 8/9,
  Reset in Task 2), Scans ausgegraut/Hinweis ✅ (Task 5 Schritt 4, Task 7 Schritt 3), Export/Druck mit
  Text+Notiz+Seitenverweis ✅ (Task 10).
- **Offene Architekturentscheidung, die Peters Bestätigung braucht:** die drei Kernänderungen aus Task 1
  (siehe „Vorab zu bestätigen“ oben) – bitte vor Ausführung von Task 1 kurz Rückmeldung geben.
- **Bekannte Unschärfe:** die genauen Selektor-/Variablennamen in `sidebar.js` und `reader.js`
  (`panel`/`name` in Task 1 Schritt 2, `viewHost`-Selektor in Task 5 Schritt 2) werden beim Öffnen der
  jeweiligen Datei im ausführenden Schritt verifiziert, da die Explorations-Zusammenfassung nicht jede
  Zeile zitiert hat – kein Blocker für die Planung, aber für den ausführenden Agenten wichtig zu wissen.

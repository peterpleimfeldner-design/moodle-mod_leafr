# Leafr – Changelog

## 1.2.0 (in Arbeit, Branch `release/1.2.0`)

### Paket C – Lesezeichen
- Neue Tabelle `leafr_bookmarks` (Seite, optionale Notiz bis 500 Zeichen, pro Person privat), neue
  Webservices `mod_leafr_bookmark_set`, `mod_leafr_bookmark_delete`, `mod_leafr_bookmark_list`
  (alle mit Kontext- und Rechteprüfung, nur eigene Daten).
- Neuer Reiter **Lesezeichen** in der Seitenleiste (zwischen Inhalt und Suche): Miniatur, Seitenzahl
  und editierbare Notiz je Lesezeichen, Notiz wird automatisch gespeichert.
- Neuer Knopf in der Werkzeugleiste und Taste **B**: setzt oder entfernt das Lesezeichen der
  aktuellen Seite. Eine kleine Fähnchen-Markierung erscheint an der oberen Seitenkante, sowohl in
  der Buch- als auch in der einfachen Ansicht.
- Datenschutz (Export/Löschen), Kurs-Zurücksetzen (eigene Einstellung „Lesezeichen aller Personen
  löschen“) und Sicherung/Wiederherstellung (inkl. Aktivität duplizieren) berücksichtigen die
  neue Tabelle.

### Paket B – Seitenleiste und Wiederfinden
- Neue Seitenleiste (ersetzt das bisherige Inhaltsverzeichnis-Panel) mit den Reitern **Miniaturen**,
  **Inhalt** und **Suche** (ARIA-Tabs, mit Pfeiltasten bedienbar). Der Reiter „Lesezeichen“ folgt in
  Paket C, sobald es die Lesezeichen-Datenstruktur gibt.
- **Miniaturansichten**: werden erst gerendert, wenn sie in den sichtbaren Bereich scrollen, weit
  entfernte werden wieder freigegeben. Gelesene Seiten zeigen ein Häkchen, die aktuelle Seite ist
  hervorgehoben, Klick springt hin.
- **Inhalt**: wie bisher aus der PDF-Gliederung, jetzt als Reiter statt eigenes Panel.
- **Volltextsuche** über `PDF.js getTextContent()`: Trefferliste mit Seitenzahl und Textausschnitt,
  Treffer werden auf der Seite selbst hervorgehoben (positionsgenaue Markierung, unabhängig von Zoom
  und Blätteranimation), „nächster/vorheriger Treffer“. Bei Scans ohne Text erscheint ein Hinweis.
- Auf dem Handy ist die Seitenleiste jetzt eine Überlagerung über die volle Breite (vorher 18rem) und
  schließt sich automatisch nach einer Navigation.
- Zwei Fehler dabei gefunden und behoben: `getTextItems()` verdoppelte die Skalierung von Breite/Höhe
  der Textelemente (PDF.js liefert sie bereits in Seiteneinheiten); die „Nächster/Vorheriger Treffer“-
  Knöpfe der Suche verwendeten dieselben `data-action`-Werte wie die Werkzeugleiste und lösten dadurch
  zusätzlich eine echte Seitenwende aus. Außerdem `FlipbookView.goTo()` vereinfacht (immer
  `turnToPage()`), da die animierte `flip()`-Kurzstrecke bei einzelnen Seiten unzuverlässig war.
- Reader-Höhe berücksichtigt jetzt alle fixierten/klebenden Leisten am oberen Rand (nicht nur die
  erste gefundene), damit die eigene Werkzeugleiste bei Themes mit mehreren Navigationsleisten
  sichtbar bleibt. Die Messung läuft entprellt bei jeder Fenstergrößenänderung.
- Neuer Weiterlesen-Hinweis: Wird die zuletzt gelesene Seite statt der Startseite geöffnet,
  erscheint eine kurze, per Tastatur erreichbare Meldung „Weiter bei Seite X“ mit dem Knopf
  „Von vorne beginnen“ (verschwindet nach 8 Sekunden von selbst).
- Der Fortschrittsbalken ist jetzt ein barrierefreier Slider (ARIA, Tastatur mit Pfeiltasten/
  Pos1/Ende, anklickbar) statt einer rein dekorativen Anzeige.
- Neue Anzeige „X von Y Seiten gelesen“ neben dem Fortschrittsbalken.
- Veralteten `get_strings()`-Fallback (nur bis Moodle 4.2 nötig) aus `reader.js` entfernt.

### Paket 0 – Umbau für die Zukunft
- Mindestversion auf Moodle 4.5 LTS angehoben (`requires` = 2024100700, `supported` = [405, 502]).
  CI-Matrix läuft jetzt auf Moodle 4.5, 5.0, 5.1, 5.2 (Moodle 4.2 entfernt).
- Veralteten Formular-Fallback für `get_suffix()` (nur bis Moodle 4.2 nötig) entfernt.
- Neuer Subplugin-Typ `leafrtool` (`db/subplugins.json`, Ordner `tool/`, Klasse
  `\mod_leafr\plugininfo\leafrtool`) für zukünftige Zusatzfunktionen (Textmarker, Word/PowerPoint,
  Lesebestätigung, Lehrenden-Bericht). Kern bietet dafür `\mod_leafr\local\tool_manager` (Einstellungs-
  formular erweitern, zusätzliche Abschlussregeln) sowie `add_subplugin_structure()` in Backup/Restore.
  Datenschutz läuft automatisch über die Moodle-Subplugin-Mechanismen.
- Sieben neue Testdokumente unter `tests/fixtures/`: 160-seitiges Skript mit 12 Kapiteln (Gliederung),
  PDF ohne Gliederung, Scan ohne Text, Querformat-Folien, gemischte Seitengrößen, Word mit
  Überschriften, PowerPoint.

## 1.1.0 – 2026-09-22 (Überarbeitung nach Gesamt-Review)

Vollständige Überarbeitung auf Basis eines Reviews des gesamten Codes. Getestet im lokalen
Moodle 5.0 (Upgrade von 1.0.7, Lesen im Browser, Abschluss, Formular), dazu neue PHPUnit-
und Behat-Tests sowie GitHub-Actions-CI.

### Kritische Fehler behoben
- **Webservices funktionierten ab Moodle 4.2 gar nicht:** Die Klassen nutzten die alten globalen
  Namen `external_api` usw., die nur nach `require lib/externallib.php` existieren. Umgestellt auf
  `core_external\*`.
- **Fortschritt konnte nie gespeichert werden:** `page_viewed` rief `leafr_update_progress()` aus
  `locallib.php` auf, die im AJAX-Aufruf nie geladen war. Fortschritt liegt jetzt in der neuen
  Tabelle `leafr_progress` (Klasse `mod_leafr\local\progress`), `locallib.php` entfällt.
- **Leseposition und Einfache Ansicht wurden nie gespeichert:** Nicht registrierte Nutzer-
  Präferenzen werden von Moodle verworfen. Leseposition läuft jetzt über `mod_leafr_page_viewed`,
  die Ansicht über die registrierte Präferenz `mod_leafr_simpleview` (gilt für alle Flipbooks).
- **Abschlussregel war nie aktiv:** `customcompletionrules` fehlte in
  `leafr_get_coursemodule_info()`, und `completiontype` blieb auch bei ausgeschalteter Regel 1.
  Formular speichert jetzt korrekt, inkl. Suffix-Unterstützung für Moodle 4.3+.
- **Backup/Restore stürzte ab** (inkompatible Methodensignatur `encode_content_links()`), außerdem
  fehlten `totalpages`, `showtoc` und die Beschreibungsdateien im Backup.
- **`leafr_add_instance()`** rief `get_coursemodule_from_id()` mit der Instanz-ID auf und setzte
  „angesehen“ für die Lehrperson.
- **Sicherheit:** PDF.js läuft mit `isEvalSupported: false` (Schutz vor CVE-2024-4367).
- **`vendor/` fehlte im Repository** (stand in `.gitignore`), ein Klon von GitHub war nicht lauffähig.
  Jetzt enthalten, Herkunft per `thirdpartylibs.xml` und Lizenzdateien dokumentiert.

### Reader (JavaScript komplett neu, ES-Module mit Moodle-Grunt gebaut)
- Keine doppelten Event-Listener mehr beim Wechsel der Ansicht; kein doppeltes Blättern auf dem Handy.
- Seiten werden nur um die aktuelle Seite herum gerendert und weiter entfernte wieder freigegeben –
  auch lange PDFs bringen den Browser nicht mehr zum Absturz (vorher: alle Seiten in 2,5-facher Größe).
- Schärfe passt sich Bildschirm (devicePixelRatio) und Zoom an.
- Zoom 50–300 % in beiden Ansichten, im Buch mit Ziehen zum Verschieben.
- Einfache Ansicht: Text jeder Seite für Screenreader, Seiten als Gruppen statt Landmarks.
- Inhaltsverzeichnis mit Unterkapiteln und Markierung des aktuellen Kapitels; Knopf bleibt
  deaktiviert, wenn das PDF keine Lesezeichen hat.
- Tastenkürzel nur, wenn der Reader den Fokus hat (WCAG 2.1.4), Hilfe-Dialog mit `?`.
- Alle Texte über Sprachdateien (vorher ca. 40 fest eingebaute deutsche Texte).
- Abschluss-Hinweis nur, wenn die Regel während des Lesens erfüllt wird.

### Entfernt
- Lesezeichen-Funktion (Tabelle `leafr_bookmarks`, drei Webservices, JS-Modul) – laut Absprache.
- Unbenutzte Capability `mod/leafr:viewreport`, Webservices `save_position`/`get_position`,
  `renderer.php`, `db/events.php`, `db/tasks.php`, unbenutzte Icons.

### Neu
- Kurs-Zurücksetzen löscht auf Wunsch den Lesefortschritt.
- Upgrade übernimmt vorhandenen Fortschritt aus den alten Präferenzen.
- PHPUnit-Tests (Fortschritt, Abschluss, Webservice, Datenschutz, Duplizieren/Backup), Behat-Tests,
  GitHub-Actions-CI (Moodle 4.2, 4.5, 5.0, 5.1).
- Mindestversion korrigiert: Moodle 4.2 (der Wert `2023042400` war schon immer 4.2, nicht 4.1).

---

## 1.0.7 – 2026-03-10
### Kritische Bugfixes
- **Fehlende Variablendeklarationen in reader.js**: `currentPage` und `flipbookInstance`
  waren im AMD-Modul nie mit `let` deklariert. In strict mode (`'use strict'`) führte das
  beim ersten Zugriff zu einem `ReferenceError`, der das gesamte Flipbook zum Absturz
  brachte – der Fehler wurde vom try-catch in `initFlipbook()` still geschluckt.
  Fix: Beide Variablen mit Initialwerten als `let currentPage = 1` und
  `let flipbookInstance = null` deklariert.
  Betroffene Dateien: `amd/src/reader.js`, `amd/build/reader.min.js`.

- **`func_get_arg()` in `page_viewed::execute()`**: Der Parameter `total_pages` wurde per
  `func_get_arg(2)` aus dem Call-Stack geholt statt als regulärer PHP-Parameter übergeben.
  Das ist undefiniertes Verhalten bei AJAX-Aufrufen über Moodles External API Framework.
  Fix: Methoden-Signatur erweitert: `execute(int $cmid, array $seenpages, int $totalpages = 0)`.
  Betroffene Dateien: `classes/external/page_viewed.php`.

- **`total_pages` nicht im AJAX-Call mitgesendet**: `completion.js` schickte `total_pages`
  nie an den Web Service. Folge: `totalpages` in der DB blieb immer 0, prozentuale und
  seitenbasierte Abschluss-Bedingungen funktionierten nicht.
  Fix: `total_pages: cfg.totalPages` in `sendCompletionEvent()` ergänzt.
  Betroffene Dateien: `amd/src/completion.js`, `amd/build/completion.min.js`.

### Bugfixes
- **IntersectionObserver Threshold**: In der Simple View wurden Seiten als „gelesen" markiert,
  sobald sie minimal sichtbar waren (`isIntersecting = true`), unabhängig vom konfigurierten
  50%-Threshold. Fix: Bedingung auf `entry.isIntersecting && entry.intersectionRatio >= 0.5`
  erweitert – konsistent mit dem IntersectionObserver in `completion.js`.
  Betroffene Dateien: `amd/src/reader.js`, `amd/build/reader.min.js`.

- **Keydown-Listener-Akkumulation im Bookmark-Modal**: Bei jedem Öffnen des Bookmark-Modals
  wurde ein neuer `keydown`-Listener auf dem Modal-Element registriert. Nach 5× Öffnen feuerte
  Escape 5× gleichzeitig.
  Fix: Listener-Referenz in `modalKeydownHandler` gespeichert und vor jedem neuen Hinzufügen
  der alte Listener entfernt (`removeEventListener`).
  Betroffene Dateien: `amd/src/bookmarks.js`, `amd/build/bookmarks.min.js`.

- **ResizeObserver Memory Leak**: Der im `flipbook.js` erzeugte `ResizeObserver` wurde in einer
  lokalen Variable gespeichert und nie getrennt (`disconnect()`). Bei wiederholtem
  Simple↔Flipbook-Wechsel akkumulierten Beobachter im Speicher.
  Fix: Observer in Modul-Scope-Variable `resizeObserver` gespeichert; bei Neuinitialisierung
  wird der vorherige Observer getrennt.
  Betroffene Dateien: `amd/src/flipbook.js`, `amd/build/flipbook.min.js`.

- **Touch-Koordinaten ohne Null-Check**: `e.touches[0].clientX` wurde ohne vorherige
  Längenprüfung aufgerufen. Wenn `e.touches` leer ist, folgte ein TypeError.
  Fix: Guard `if (e.touches.length > 0)` hinzugefügt.
  Betroffene Dateien: `amd/src/flipbook.js`, `amd/build/flipbook.min.js`.

- **Canvas-Kontext-Check in renderSimplePage()**: Fehlte ein Null-Check für
  `canvas.getContext('2d')`. Bei detachtem DOM-Element wäre `ctx` null und
  `page.render()` hätte einen TypeError geworfen.
  Fix: `if (!ctx) throw new Error(...)` vor `page.render()`.
  Betroffene Dateien: `amd/src/reader.js`, `amd/build/reader.min.js`.

### Code-Qualität
- Debug-`console.log()`-Aufrufe aus `reader.js`, `bookmarks.js` und `toolbar.js` entfernt.

---

## 1.0.3 – 2026-03-05
### Bugfixes
- **`invalidresponse`-Fehler auf Moodle-Dashboard**: `leafr_supports()` war als `?bool`
  deklariert. PHP coercierte den String `MOD_PURPOSE_CONTENT` zu `true` (boolean), was
  Moodles External API als `purpose`-Feld mit `PARAM_ALPHA`-Typ erwartete → Exception.
  Fix: Return-Type auf `mixed` geändert.
  Betroffene Dateien: `lib.php`.

- **PDF lädt nicht / ewiger Ladekreis**: PDF.js 3.x registriert sich als named AMD
  module unter `"pdfjs-dist/build/pdf"`. Der RequireJS-Pfad war aber als
  `"mod_leafr/vendor-pdfjs"` konfiguriert → AMD-Dependency nie aufgelöst → Spinner
  drehte dauerhaft. Fix: Pfad-Key und `define()`-Referenz auf `"pdfjs-dist/build/pdf"`
  korrigiert.
  Betroffene Dateien: `view.php`, `amd/src/pdfloader.js`, `amd/build/pdfloader.min.js`.

- **`totalpages` fehlt in der Datenbank**: Das Feld `totalpages` fehlte im
  Installationsschema (`install.xml`), wodurch die Abschluss-Bedingungen auf
  Basis der Seitenzahl nicht mehr funktionierten (`totalpages` war immer 0).
  Fix: Feld in `install.xml` hinzugefügt, Upgrade-Schritt in `upgrade.php`
  implementiert und Speichern in der Web Service Funktion `page_viewed`
  sichergestellt.
  Betroffene Dateien: `db/install.xml`, `db/upgrade.php`,
  `classes/external/page_viewed.php`, `version.php`.

### Verbesserungen
- **Landscape/Portrait-Erkennung**: Querformat-PDFs (`width > height`) werden jetzt
  automatisch in Einzelseitenansicht dargestellt. Hochformat-PDFs zeigen weiterhin den
  Zwei-Seiten-Spread (Buchansicht). Auf mobilen Geräten immer Einzelseite.
  Betroffene Dateien: `amd/src/flipbook.js`, `amd/build/flipbook.min.js`.

---

## 1.0.2 – 2026-03-04
### Bugfixes
- **PDF.js / StPageFlip laden nicht (AMD-Konflikt)**: Beide Vendor-Bibliotheken
  (PDF.js 3.11.174 und StPageFlip 2.0.7) sind UMD-Module und haben Moodles
  `window.define` (RequireJS) erkannt und sich als AMD-Module registriert, anstatt
  `window.pdfjsLib` bzw. `window.St` zu setzen. Fix: Bibliotheken werden jetzt per
  `require.config({ paths: { ... } })` als benannte RequireJS-Pfade registriert und
  in `pdfloader.js` / `flipbook.js` als AMD-Abhängigkeiten geladen.
  Betroffene Dateien: `view.php`, `amd/src/pdfloader.js`, `amd/src/flipbook.js`.

### Verbesserungen
- **Abschlusseinstellungen konsolidiert**: Die eigene Sektion „Abschluss-Einstellungen"
  im Aktivitätsformular wurde entfernt. Alle Abschlussoptionen (Art des Abschlusses,
  Prozentschwelle, spezifische Seite) befinden sich jetzt vollständig in Moodles
  nativer Sektion „Abschlussbedingungen" unter dem Punkt „Bedingungen hinzufügen".
  Betroffene Dateien: `mod_form.php`.

---

## 1.0.0 – 2026-03-04
### Initial Release
- Vollständige Implementierung des mod_leafr Moodle Activity Plugins
- PDF-Rendering via PDF.js 3.11.174 (lokal)
- Blätterfunktion via StPageFlip 2.0.7 (lokal)
- Leseposition wird über Moodle User Preferences gespeichert
- Weiterlesen-Toast (800 ms Delay, 8 s Auto-Dismiss)
- Tastatur- und Touch-Navigation
- Einfache Ansicht (Simple View) für `prefers-reduced-motion`
- Inhaltsverzeichnis (TOC) aus PDF-Outline
- Abschluss-Tracking: letzte Seite / Prozentsatz / spezifische Seite
- Custom Completion API (Moodle 4.1+)
- DSGVO-konform (Privacy API vollständig implementiert)
- WCAG 2.2 AA (ARIA Live Regions, Fokus-Styles)
- Backup/Restore vollständig
- Web Services / AJAX für Position, Fortschritt, Lesezeichen
- Pro-Feature-Hook (`leafr_pro_is_available()`)
- Sprachen: Deutsch + Englisch

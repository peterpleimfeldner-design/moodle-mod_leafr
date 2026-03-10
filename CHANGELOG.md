# Leafr – Changelog

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

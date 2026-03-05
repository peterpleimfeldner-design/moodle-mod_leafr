# Leafr – Changelog

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

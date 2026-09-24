# Leafr – Changelog

## 1.2.0 (in Arbeit, Branch `release/1.2.0`)

### UX/UI-Feinschliff (Review vom 24.09.2026)
Vollständiges Review aller Seiten im Browser, dokumentiert in `docs/ux-review_2026-09/`.
- **Handy/Tablet:** Die Werkzeugleiste richtet sich jetzt nach der tatsächlichen Breite des Readers
  statt nach der Bildschirmbreite. Vorher waren auf dem Handy „Ansicht“, „Lesezeichen“ und
  „Download“ abgeschnitten und nicht erreichbar. Bei schmalem Reader: kleinere Knöpfe, „von N“
  ausgeblendet, Download im Ansicht-Menü, Seitenlayout-Auswahl (dort wirkungslos) ausgeblendet.
- Doppelseite: kein innerer Scrollbalken mehr durch die Lesezeichen-Leiste über dem Buch; das Buch
  rechnet deren Platz ein und behält beim Blättern seine Größe.
- Fortschritt („16 von 16 Seiten gelesen · 6 von 6 Pflichtseiten gelesen“) in einer Zeile – mehr
  Platz für das Buch.
- Ansicht-Menü: breiter, Auswahlgruppen als Segment-Schalter, Häkchen bei „Einfache Ansicht“ und
  „Vollbild“; „Doppelseite“ ragte vorher über den Rand.
- Suche: Pfeil für „vorheriger Treffer“ zeigte nach unten (behoben), Hinweis im leeren Zustand,
  Textauszüge auf drei Zeilen begrenzt; Seitenleiste ändert beim Suchen nicht mehr ihre Breite.
- Miniaturen: bei Doppelseiten sind beide sichtbaren Seiten markiert; Legende für Pflichtseiten-Punkt
  und Gelesen-Haken.
- Lesezeichen: „Gespeichert“-Rückmeldung nach dem automatischen Speichern einer Notiz; Drucken als
  Knopf mit Symbol; „Lesezeichen entfernen“ als dezenter Knopf; gesetzte Lesezeichen in der Leiste
  über dem Buch deutlich hervorgehoben; Leer-Hinweis erklärt, wie man ein Lesezeichen setzt.
- „Weiter bei Seite …“-Meldung bricht auf schmalen Bildschirmen nicht mehr Wort für Wort um.
- **Neues Logo**: aufgeschlagenes Buch, dessen rechte Seite gerade umgeblättert wird („to leaf
  through“ = durchblättern) – ersetzt das Blatt-Logo, das auf der Kursseite wie ein Wassertropfen
  wirkte. Farbig (`pix/icon.svg`) und einfarbig (`pix/monologo.svg`, die umblätternde Seite durch
  eine transparente Fuge abgesetzt, damit es in jeder Theme-Farbe lesbar bleibt).
- **Kapitel-Formular**: Titel und Startseite eines Kapitels stehen in einer Zeile („Kapitel 1:
  [Titel] [Seite]“); ein Hinweistext über der Liste ersetzt das Hilfe-Symbol in jeder Zeile.
- **Dunkelmodus**: Der Reader wird nur noch dunkel, wenn auch die Moodle-Seite dunkel ist (dunkles
  Theme), nicht mehr allein wegen der Systemeinstellung – ein dunkler Reader in einer weißen
  Moodle-Seite wirkte wie ein Fremdkörper.
- **Vollbild-Tipp**: Beim ersten Öffnen (einmal pro Browser) weist ein kurzer Hinweis auf das
  Vollbild hin, mit Knopf zum direkten Wechseln.
- Das Buch wird neu berechnet, sobald sein Platz schrumpft (vorher erst ab 8 px Unterschied), und
  die Fortschrittszeile reserviert ihren Platz von Anfang an – beides verhinderte in manchen Fällen
  einen kleinen Scrollbalken.
- Bericht „Übersicht“: ohne Gruppenfilter heißt es jetzt „In diesem Kurs sind noch keine
  Teilnehmer/innen eingeschrieben“ statt „… entsprechen dem aktuellen Filter“; Datenschutzhinweis
  als ruhiger Text statt zweitem blauen Hinweiskasten.

### Paket H3 – Spanisches Sprachpaket
- Neue Übersetzung `lang/es/leafr.php` sowie `tool/confirm/lang/es/` und `tool/report/lang/es/`
  (zusammen rund 160 Strings). Erster Entwurf, sollte vor der Veröffentlichung von einer
  spanischsprachigen Person gegengelesen werden.

### Paket H2 – Seitenblättern verbessern
- Blätter-Animation (StPageFlip) neu abgestimmt: längere, ruhigere Animation, mehr Schattentiefe
  für optisches Gewicht, höhere Auslöseschwelle für Wisch-Gesten, und Umblättern per Klick/Ziehen
  nur noch über die Seitenecken statt von jeder Stelle der Seite aus.

### Paket H – Textmarker
- **Zurückgestellt**, nicht umgesetzt. Siehe `ROADMAP.md` für die Begründung; der bereits erstellte
  Umsetzungsplan bleibt als Referenz erhalten.

### Paket G – Datensparsamer Bericht für Lehrende (`leafrtool_report`)
- Neue Seite **„Übersicht“** (eigener Reiter neben „Einstellungen“, sichtbar nur mit der neuen
  Capability `mod/leafr:viewreport`, standardmäßig Lehrende/Trainer/innen und Manager).
- Pro Person nur: Name, „abgeschlossen ja/nein“, „Pflichtseiten gelesen“ (ja/nein oder als
  Prozentsatz, je nach Abschlussregel) und, falls die Lesebestätigung (`leafrtool_confirm`)
  eingerichtet ist, das Bestätigungsdatum.
- Filter nach Gruppe (bei Kursen mit Gruppenmodus), CSV-Export.
- Bewusst **nicht** erfasst oder gezeigt: Lesezeiten, einzelne Seitenaufrufe pro Person,
  Lesezeichen, Notizen, Markierungen. Hinweistext auf der Seite erklärt das.
- Der Kern (`tool_manager`) wurde um eine generische Navigationserweiterung ergänzt
  (`extend_navigation`), über die jedes `leafrtool`-Unter-Plugin eigene Links in die
  „Mehr“-Navigation der Aktivität einträgt.
- CSV-Export gegen Formel-Injection abgesichert (ein Name, der mit `=`, `+`, `-`, `@` oder Tab
  beginnt, würde sonst beim Öffnen in Excel/Calc als Formel ausgeführt).

### Qualitätssicherung (CI)
- Statische Analyse mit **PHPStan** (Level 5, `micaherne/phpstan-moodle`) als eigener CI-Schritt,
  läuft gegen jede getestete Moodle-Version.
- Neuer PHPUnit-Test mit zehn simulierten Studierenden und unterschiedlichem Lesefortschritt
  bestätigt, dass die Übersicht (Paket G) jede Person einzeln und korrekt sortiert zeigt.
- Neue Behat-Szenarien: zwei Studierende mit unterschiedlichem Fortschritt, Lehrperson prüft in
  der Übersicht, dass die Werte nicht zwischen den Personen vermischt werden; außerdem
  automatische Barrierefreiheitsprüfung (axe-core, `the page should meet accessibility
  standards`) für das Flipbook und die Übersicht.

### Paket F – Lesebestätigung (`leafrtool_confirm`)
- Erstes echtes Unter-Plugin vom Typ `leafrtool`: **Lesebestätigung** als eigenständiges,
  optionales Werkzeug. Neue Checkbox „Lesebestätigung erforderlich“ bei den Abschlussbedingungen,
  mit eigenem Textfeld für die Bestätigungsformulierung (Standard: „Ich habe die Inhalte gelesen
  und verstanden.“).
- Sobald der Pflichtbereich bzw. die letzte Seite gelesen ist, erscheint unter dem Reader eine
  ruhige Karte mit Checkbox und Knopf „Bestätigen“; die Bestätigung wird mit Zeitpunkt gespeichert
  und beim nächsten Besuch angezeigt.
- Neue, mit den Seitenregeln kombinierbare Abschlussregel „Lesebestätigung abgegeben“.
- Eigene Datenbanktabellen, eigener Webservice, eigenes Backup/Restore/Datenschutz-Modul für das
  Unter-Plugin; „Aktivität duplizieren“ übernimmt die Einstellung, nicht die Bestätigungen der
  Teilnehmer/innen. Eigene Option beim Kurs-Zurücksetzen.
- Der Kern (`mod_leafr\local\tool_manager`) wurde dafür allgemein erweitert, damit künftige
  Unter-Plugins (Pakete G–I) auf demselben Weg eigene Abschlussregeln, Formularfelder und einen
  eigenen Bereich unter dem Reader beisteuern können, ohne den Kern anzufassen (siehe
  `tool/README.md`).

### Paket E – Einzel-/Doppelseite und Buchoptik
- Neues **Ansicht-Menü** in der Werkzeugleiste (ersetzt die bisherigen Einzelknöpfe für Zoom,
  Einfache Ansicht und Vollbild): **Seitenlayout** (Automatisch/Einzelseite/Doppelseite, als
  Nutzerpräferenz gespeichert), **Einfache Ansicht**, Zoom, **An Seite anpassen**/**An Breite
  anpassen** (auf dem Handy automatisch „An Breite"), Vollbild. Menü schließt per Klick außerhalb
  oder Escape.
- **Buchoptik**: dezenter Schatten unter dem Buch, angedeutete Seitenkanten.
- **Werkzeugleiste neu geordnet**: Seitenleiste links, Navigation mittig, Ansicht-Menü,
  Lesezeichen, Download und Hilfe rechts. Seitenleiste, Ansicht und Lesezeichen zeigen ab
  Tablet-Breite eine sichtbare Beschriftung neben dem Symbol, darunter nur Tooltip.
- **Neues Logo** (endgültige Fassung nach dem UX-Review, siehe dort).
- **Farben**: Primärfarbe wird von der Moodle-Theme-Variable `--bs-primary` übernommen, wenn deren
  Kontrast gegen Weiß WCAG AA (4,5:1) erreicht, sonst bleibt die feste Leafr-Petrol-Farbe
  `#0E6A62`. Gilt nur im hellen Modus; der dunkle Modus (Systemeinstellung) behält seine eigene,
  geprüfte Akzentfarbe unabhängig vom Theme.

### Paket D – Kapitel und Pflicht-Lernstoff
- Neuer Formularbereich **Kapitel**: beliebig viele Zeilen mit Titel und Startseite, plus
  Einstellung „Immer die manuelle Liste verwenden“. Wird für den Reiter „Inhalt“ genutzt, wenn das
  PDF keine eigene Gliederung hat (oder immer, wenn die Einstellung aktiv ist).
- Neue Abschlussregel **„Bestimmte Seiten/Kapitel lesen“** (Typ 4): Seitenbereiche als Text
  („1-5, 8, 12-20“, mit Eingabeprüfung) und/oder Auswahl bereits gespeicherter Kapitel, die beim
  Speichern zu den Pflichtseiten hinzugefügt werden.
- Pflichtbereiche sind im Reader sichtbar: zweite Fortschrittsanzeige „X von Y Pflichtseiten
  gelesen“, ein Punkt an den betroffenen Miniaturen und ein „Pflicht“-Abzeichen bei den
  entsprechenden Kapiteln im Inhaltsreiter.
- Hilfetext bei „Kapiteltitel“ erklärt, wie PDFs mit Gliederung erzeugt werden (Word:
  Überschrift-Formatvorlagen; PowerPoint-Export: „Textmarken erstellen mit: Überschriften“).
- Neue Klasse `mod_leafr\local\chapters` (Kapitelliste aus dem Formular bauen/speichern, Auswahl in
  Seitenzahlen umrechnen), Upgrade-Schritt für die neuen Felder `completionpages`,
  `manualchapters`, `usemanualchapters`.

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
- **Nachbesserung (Peters Rückmeldung):** In der Doppelseiten-Ansicht ließ sich nur die linke der
  beiden sichtbaren Seiten mit einem Lesezeichen versehen. Erste Lösung war ein klickbarer Bereich
  an der Seitenecke – das überschnitt sich aber mit der Umblätter-Erkennung von StPageFlip (Klick/
  Drag/Hover-Kurve genau in derselben Ecke) und sah während der Umblätter-Animation kaputt aus.
  Stattdessen gibt es jetzt eine schmale Leiste oberhalb des Buchs mit einer Schaltfläche je
  sichtbarer Seite (nur bei einer echten Doppelseite eingeblendet); die Eselsohr-Markierung an der
  Seitenecke bleibt als rein optische Anzeige ohne eigenen Klick-Bereich erhalten. Werkzeugleisten-
  Knopf und Taste B bleiben für die einfache Ansicht und die Tastaturbedienung zuständig.
- **Nachbesserung:** Das Notizfeld zeigt jetzt einen Zeichenzähler („120 von 500 Zeichen“, der ab
  90 % farblich warnt) und wächst beim Tippen bis zu einer Maximalhöhe mit.
- **Neu: Druckansicht** (`print.php`, erreichbar über „Drucken oder als PDF speichern“ im
  Lesezeichen-Reiter): einfache, eigenständige Seite mit allen Lesezeichen (Seitenzahl + Notiz),
  druckt sauber über die Browser-Druckfunktion bzw. „Als PDF speichern“. Die Zuordnung zu
  Textstellen/Absätzen ist bewusst Paket H (Textmarker) vorbehalten, das dafür die PDF-Textebene
  mitbringt.

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

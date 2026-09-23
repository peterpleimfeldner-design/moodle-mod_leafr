<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German strings for mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['bookmark'] = 'Lesezeichen';
$string['bookmark_empty'] = 'Noch keine Lesezeichen.';
$string['bookmark_note_chars'] = '{$a->used} von {$a->max} Zeichen';
$string['bookmark_note_label'] = 'Notiz zu';
$string['bookmark_note_placeholder'] = 'Notiz hinzufügen …';
$string['bookmark_page'] = 'Lesezeichen für Seite {$a}';
$string['bookmark_print'] = 'Drucken oder als PDF speichern';
$string['bookmark_remove'] = 'Lesezeichen entfernen';
$string['close'] = 'Schließen';
$string['completion_done'] = 'Gut gemacht! Sie haben genug gelesen, um die Aktivität abzuschließen.';
$string['completion_lastpage'] = 'Die letzte Seite muss angesehen werden';
$string['completion_percent'] = 'Ein Prozentsatz aller Seiten muss angesehen werden';
$string['completion_specificpage'] = 'Eine bestimmte Seite muss angesehen werden';
$string['completiondetail:lastpage'] = 'Letzte Seite ansehen';
$string['completiondetail:page'] = 'Seite {$a} ansehen';
$string['completiondetail:percent'] = '{$a} % der Seiten ansehen';
$string['completionpage'] = 'Seitenzahl';
$string['completionpageseen'] = 'Seitenaufrufe erforderlich';
$string['completionpageseen_desc'] = 'Teilnehmer/innen müssen Seiten des Dokuments ansehen';
$string['completionpercent'] = 'Prozentsatz der Seiten';
$string['completiontype'] = 'Anzusehende Seiten';
$string['continuenotice'] = 'Weiter bei Seite {$a}';
$string['displaysettings'] = 'Darstellung';
$string['document'] = 'Dokument';
$string['download'] = 'PDF herunterladen';
$string['downloadallowed'] = 'Herunterladen erlauben';
$string['downloadallowed_help'] = 'Wenn aktiviert, können Teilnehmer/innen mit der Berechtigung „PDF herunterladen“ die ursprüngliche PDF-Datei herunterladen.';
$string['error_invalidpage'] = 'Bitte geben Sie eine Seitenzahl von 1 oder höher ein.';
$string['error_invalidpercent'] = 'Bitte geben Sie einen Prozentsatz zwischen 1 und 100 ein.';
$string['errordocument'] = 'Das Dokument konnte nicht geladen werden.';
$string['eventpageviewed'] = 'Seite angesehen';
$string['firstpage'] = 'Erste Seite';
$string['fullscreen_enter'] = 'Vollbild';
$string['fullscreen_exit'] = 'Vollbild beenden';
$string['gotopage'] = 'Gehe zu Seite';
$string['help'] = 'Tastaturkürzel';
$string['help_bookmark'] = 'Lesezeichen der aktuellen Seite setzen oder entfernen';
$string['help_close'] = 'Dialog, Inhaltsverzeichnis oder Vollbild schließen';
$string['help_firstlast'] = 'Erste oder letzte Seite';
$string['help_fullscreen'] = 'Vollbild ein oder aus';
$string['help_help'] = 'Diese Hilfe anzeigen';
$string['help_intro'] = 'Die Tastaturkürzel funktionieren, wenn der Reader den Fokus hat.';
$string['help_nextprev'] = 'Nächste oder vorherige Seite';
$string['help_toc'] = 'Inhaltsverzeichnis ein oder aus';
$string['help_zoom'] = 'Vergrößern oder verkleinern';
$string['initialpage'] = 'Startseite';
$string['initialpage_help'] = 'Die Seite, die beim ersten Öffnen angezeigt wird. Danach öffnet sich das Dokument an der zuletzt gelesenen Seite.';
$string['lastpage'] = 'Letzte Seite';
$string['leafr:addinstance'] = 'Neues Leafr-Flipbook hinzufügen';
$string['leafr:download'] = 'PDF herunterladen';
$string['leafr:view'] = 'Leafr-Flipbook ansehen';
$string['leafrname'] = 'Name';
$string['loading'] = 'Dokument wird geladen …';
$string['matchofmatches'] = '{$a->index} von {$a->total}';
$string['modulename'] = 'Leafr-Flipbook';
$string['modulename_help'] = 'Das Leafr-Flipbook zeigt ein PDF-Dokument als Buch, in dem Teilnehmer/innen direkt im Kurs blättern können.

Beim nächsten Öffnen geht es an der zuletzt gelesenen Seite weiter. Die Aktivität kann automatisch abgeschlossen werden, wenn die letzte Seite, ein bestimmter Prozentsatz der Seiten oder eine bestimmte Seite angesehen wurde.

Für Barrierefreiheit gibt es eine einfache, scrollbare Ansicht ohne Blätteranimation.';
$string['modulenameplural'] = 'Leafr-Flipbooks';
$string['nextpage'] = 'Nächste Seite';
$string['nonewmodules'] = 'In diesem Kurs gibt es keine Leafr-Flipbooks.';
$string['nopdfuploaded'] = 'Zu dieser Aktivität wurde noch keine PDF-Datei hinzugefügt.';
$string['pagelabel'] = 'Seite {$a}';
$string['pageofpages'] = 'Seite {$a->page} von {$a->total}';
$string['pageprogress'] = 'Lesefortschritt';
$string['pagesofpages'] = 'Seiten {$a->first} und {$a->last} von {$a->total}';
$string['pdffile'] = 'PDF-Datei';
$string['pdffile_help'] = 'Das PDF-Dokument, das als Flipbook angezeigt wird. Die maximale Dateigröße hängt von den Einstellungen der Website ab.';
$string['pluginadministration'] = 'Leafr-Flipbook-Administration';
$string['pluginname'] = 'Leafr-Flipbook';
$string['previouspage'] = 'Vorherige Seite';
$string['privacy:bookmarkssubcontext'] = 'Lesezeichen';
$string['privacy:metadata:leafr_bookmarks'] = 'Die Lesezeichen einer Person in einem Leafr-Flipbook.';
$string['privacy:metadata:leafr_bookmarks:note'] = 'Die zum Lesezeichen hinzugefügte Notiz.';
$string['privacy:metadata:leafr_bookmarks:pageno'] = 'Die mit einem Lesezeichen versehene Seite.';
$string['privacy:metadata:leafr_bookmarks:timecreated'] = 'Der Zeitpunkt, zu dem das Lesezeichen erstellt wurde.';
$string['privacy:metadata:leafr_bookmarks:timemodified'] = 'Der Zeitpunkt der letzten Aktualisierung des Lesezeichens.';
$string['privacy:metadata:leafr_bookmarks:userid'] = 'Die ID der Person.';
$string['privacy:metadata:leafr_progress'] = 'Der Lesefortschritt einer Person in einem Leafr-Flipbook.';
$string['privacy:metadata:leafr_progress:lastpage'] = 'Die zuletzt gelesene Seite.';
$string['privacy:metadata:leafr_progress:seenpages'] = 'Die angesehenen Seiten.';
$string['privacy:metadata:leafr_progress:timemodified'] = 'Der Zeitpunkt der letzten Aktualisierung.';
$string['privacy:metadata:leafr_progress:userid'] = 'Die ID der Person.';
$string['privacy:metadata:preference:simpleview'] = 'Ob die Person die einfache Ansicht ohne Blätteranimation bevorzugt.';
$string['progresssummary'] = '{$a->seen} von {$a->total} Seiten gelesen';
$string['readerlabel'] = 'Flipbook: {$a}';
$string['reload'] = 'Seite neu laden';
$string['resetbookmarks'] = 'Lesezeichen aller Personen löschen';
$string['resetprogress'] = 'Lesefortschritt aller Personen löschen';
$string['restartreading'] = 'Von vorne beginnen';
$string['search_indexing'] = 'Suche wird vorbereitet …';
$string['search_label'] = 'Suchbegriff';
$string['search_next'] = 'Nächster Treffer';
$string['search_noresults'] = 'Keine Treffer gefunden.';
$string['search_noresults_scan'] = 'Dieses Dokument enthält keinen durchsuchbaren Text.';
$string['search_placeholder'] = 'Im Dokument suchen';
$string['search_prev'] = 'Vorheriger Treffer';
$string['showtoc'] = 'Inhaltsverzeichnis anzeigen';
$string['showtoc_help'] = 'Zeigt die Lesezeichen (Gliederung) des PDFs als Inhaltsverzeichnis. Das PDF muss Lesezeichen enthalten, die die meisten Programme beim PDF-Export erzeugen können.';
$string['sidebar'] = 'Seitenleiste';
$string['simpleview'] = 'Einfache Ansicht';
$string['tab_bookmarks'] = 'Lesezeichen';
$string['tab_contents'] = 'Inhalt';
$string['tab_search'] = 'Suche';
$string['tab_thumbnails'] = 'Miniaturen';
$string['toc_empty'] = 'Dieses PDF enthält kein Inhaltsverzeichnis.';
$string['toolbar'] = 'Reader-Steuerung';
$string['totalpages'] = 'von {$a}';
$string['zoomin'] = 'Vergrößern';
$string['zoomlevel'] = 'Zoom: {$a} %';
$string['zoomout'] = 'Verkleinern';
$string['zoomreset'] = 'Zoom zurücksetzen';

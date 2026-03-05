<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Deutsche Sprachstrings für mod_leafr
 *
 * @package    mod_leafr
 * @copyright  2026 Leafr
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// ── Plugin-Metadaten ──────────────────────────────────────────────────────────
$string['modulename']           = 'Leafr Flipbook';
$string['modulenameplural']     = 'Leafr Flipbooks';
$string['modulename_help']      = 'Die Leafr-Flipbook-Aktivität ermöglicht die Präsentation von PDF-Dokumenten als interaktives Blätterbuch direkt in Moodle.';
$string['pluginadministration'] = 'Leafr-Verwaltung';
$string['pluginname']           = 'Leafr Flipbook';

// ── Formularfelder ────────────────────────────────────────────────────────────
$string['leafrname']            = 'Aktivitätsname';
$string['pdffile']              = 'PDF-Datei';
$string['pdffile_help']         = 'Laden Sie ein PDF-Dokument hoch. Es wird als interaktives Flipbook angezeigt. Die maximale Dateigröße hängt von Ihrer Moodle-Konfiguration ab.';
$string['displaysettings']      = 'Anzeigeeinstellungen';
$string['showtoc']              = 'Inhaltsverzeichnis anzeigen';
$string['downloadallowed']      = 'PDF-Download erlauben';
$string['downloadallowed_help'] = 'Wenn aktiviert, können Studierende die ursprüngliche PDF-Datei herunterladen.';
$string['initialpage']          = 'Startseite';
$string['completionsettings']   = 'Abschluss-Einstellungen';
$string['completiontype']       = 'Abschlussbedingung';
$string['completionpercent']    = 'Erforderlicher Prozentsatz der Seiten (%)';
$string['completionpage']       = 'Erforderliche Seitenzahl';

// ── Abschlusstypen ────────────────────────────────────────────────────────────
$string['completion_none']              = 'Kein automatischer Abschluss';
$string['completion_lastpage']          = 'Studierende müssen die letzte Seite erreichen';
$string['completion_percent']           = 'Studierende müssen einen Prozentsatz der Seiten sehen';
$string['completion_specificpage']      = 'Studierende müssen eine bestimmte Seite erreichen';
$string['completionpageseen']           = 'Seitenaufrufe erforderlich';
$string['completionpageseen_desc']      = 'Studierende müssen die erforderlichen Seiten aufrufen';
$string['completion_percent_desc']      = 'Studierende müssen {$a}% der Seiten gesehen haben';
$string['completion_specificpage_desc'] = 'Studierende müssen Seite {$a} erreichen';

// ── UI-Strings ────────────────────────────────────────────────────────────────
$string['loading']              = 'Dokument wird geladen...';
$string['errordocument']        = 'Das Dokument konnte nicht geladen werden.';
$string['nopdfuploaded']        = 'Es wurde noch keine PDF-Datei hochgeladen.';
$string['pageof']               = 'Seite {page} von {total}';
$string['toc_title']            = 'Inhaltsverzeichnis';
$string['toc_empty']            = 'Kein Inhaltsverzeichnis verfügbar.';
$string['simple_view']          = 'Einfache Ansicht (barrierefrei)';
$string['pro_teaser']           = 'Upgraden Sie auf Leafr Pro, um Lesezeichen, Analysen und mehr freizuschalten.';

// ── Weiterlesen-Toast ─────────────────────────────────────────────────────────
$string['resume_toast']         = 'Sie waren zuletzt auf Seite {page}.';
$string['resume_continue']      = 'Weiterlesen';
$string['resume_restart']       = 'Von vorne beginnen';

// ── Abschluss-Feedback ────────────────────────────────────────────────────────
$string['completion_done']      = 'Aktivität als abgeschlossen markiert!';

// ── Lesezeichen-Strings ───────────────────────────────────────────────────────
$string['bookmark_add']         = 'Lesezeichen hinzufügen';
$string['bookmark_saved']       = 'Lesezeichen gespeichert.';
$string['bookmark_deleted']     = 'Lesezeichen gelöscht.';
$string['bookmark_empty']       = 'Noch keine Lesezeichen gesetzt.';
$string['bookmark_limit']       = 'Maximal 20 Lesezeichen pro Aktivität erreicht.';
$string['bookmarknotfound']     = 'Lesezeichen nicht gefunden.';
$string['requirespro']          = 'Diese Funktion erfordert Leafr Pro.';

// ── Fehlermeldungen ───────────────────────────────────────────────────────────
$string['error_invalidpercent'] = 'Der Prozentsatz muss zwischen 1 und 100 liegen.';
$string['error_invalidpage']    = 'Die Seitenzahl muss mindestens 1 sein.';

// ── Ereignisse ────────────────────────────────────────────────────────────────
$string['event_course_module_viewed'] = 'Leafr-Flipbook aufgerufen';
$string['event_page_viewed']          = 'Flipbook-Seite aufgerufen';

// ── Datenschutz-API ───────────────────────────────────────────────────────────
$string['privacy:metadata:preference:readingpos']             = 'Die aktuelle Leseposition (Seitenzahl) für diese Leafr-Aktivität.';
$string['privacy:metadata:preference:progress']               = 'Welche Seiten der Benutzer in dieser Leafr-Aktivität gesehen hat.';
$string['privacy:metadata:preference:simpleview']             = 'Ob der Benutzer die einfache (barrierefreie) Ansicht für diese Aktivität aktiviert hat.';
$string['privacy:metadata:leafr_bookmarks']                   = 'Benannte Lesezeichen, die vom Benutzer in einer Leafr-Aktivität erstellt wurden.';
$string['privacy:metadata:leafr_bookmarks:userid']            = 'Die ID des Benutzers, der das Lesezeichen erstellt hat.';
$string['privacy:metadata:leafr_bookmarks:pageno']            = 'Die Seitenzahl, auf die das Lesezeichen verweist.';
$string['privacy:metadata:leafr_bookmarks:label']             = 'Die benutzerdefinierte Bezeichnung des Lesezeichens.';
$string['privacy:metadata:leafr_bookmarks:note']              = 'Eine optionale Notiz zum Lesezeichen.';
$string['privacy:metadata:leafr_bookmarks:timecreated']       = 'Zeitstempel der Erstellung des Lesezeichens.';
$string['privacy:metadata:leafr_bookmarks:timemodified']      = 'Zeitstempel der letzten Änderung des Lesezeichens.';

// ── Sonstiges ─────────────────────────────────────────────────────────────────
$string['nonewmodules']         = 'Es gibt keine Leafr-Flipbook-Aktivitäten in diesem Kurs.';

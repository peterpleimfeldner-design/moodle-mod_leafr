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
 * English strings for leafrtool_office.
 *
 * @package   leafrtool_office
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['conversionerror_noconverter'] = 'No document converter is configured on this site.';
$string['conversionerror_noresult'] = 'The document converter did not return a file.';
$string['conversionerror_timeout'] = 'The document converter did not finish in time.';
$string['downloadoriginal'] = 'Offer original file for download';
$string['downloadoriginal_help'] = 'If enabled, students downloading the document (see "Allow download" above) get the original Word/PowerPoint/ODF file instead of the converted PDF.';
$string['layoutnotice'] = 'Complex Word/PowerPoint layouts (unusual fonts, unsupported effects, unusual page sizes) may look slightly different once converted to PDF. Check the result after uploading.';
$string['noconverternotice'] = 'Word, PowerPoint and OpenDocument files can only be used once the site administrator configures a document converter (Site administration -> Plugins -> Document converters). Until then, only PDF files can be uploaded here.';
$string['pluginname'] = 'Word and PowerPoint documents';
$string['privacy:metadata'] = 'This tool only stores the status of a background PDF conversion and an activity-level display setting, neither tied to an individual user.';

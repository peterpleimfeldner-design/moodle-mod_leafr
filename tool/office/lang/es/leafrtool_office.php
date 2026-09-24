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
 * Spanish strings for leafrtool_office.
 *
 * @package   leafrtool_office
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['conversionerror_noconverter'] = 'Este sitio no tiene configurado ningún conversor de documentos.';
$string['conversionerror_noresult'] = 'El conversor de documentos no devolvió ningún archivo.';
$string['conversionerror_timeout'] = 'El conversor de documentos no terminó a tiempo.';
$string['downloadoriginal'] = 'Ofrecer el archivo original para descargar';
$string['downloadoriginal_help'] = 'Si se activa, al descargar el documento (ver «Permitir descarga» arriba) el alumnado recibe el archivo original de Word/PowerPoint/ODF en lugar del PDF convertido.';
$string['layoutnotice'] = 'Los diseños complejos de Word/PowerPoint (fuentes poco habituales, efectos no compatibles, tamaños de página inusuales) pueden verse ligeramente distintos tras convertirse a PDF. Comprueba el resultado después de subir el archivo.';
$string['noconverternotice'] = 'Los archivos de Word, PowerPoint y OpenDocument solo se pueden usar cuando la administración del sitio configura un conversor de documentos (Administración del sitio -> Complementos -> Conversores de documentos). Hasta entonces, aquí solo se pueden subir archivos PDF.';
$string['pluginname'] = 'Documentos de Word y PowerPoint';
$string['privacy:metadata'] = 'Esta herramienta solo almacena el estado de una conversión a PDF en segundo plano y un ajuste de visualización a nivel de actividad, ninguno vinculado a una persona concreta.';

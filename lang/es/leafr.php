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
 * Spanish strings for mod_leafr.
 *
 * @package   mod_leafr
 * @copyright 2026 Peter Pleimfeldner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addchapters'] = 'Añadir capítulos';
$string['bookmark'] = 'Marcador';
$string['bookmark_empty'] = 'Todavía no hay marcadores. Añada uno con el botón de marcador de la barra de herramientas o con la tecla B.';
$string['bookmark_note_chars'] = '{$a->used} de {$a->max} caracteres';
$string['bookmark_note_label'] = 'Nota para';
$string['bookmark_note_placeholder'] = 'Añadir una nota…';
$string['bookmark_note_saved'] = 'Guardado';
$string['bookmark_page'] = 'Marcar la página {$a}';
$string['bookmark_print'] = 'Imprimir o guardar como PDF';
$string['bookmark_remove'] = 'Quitar marcador';
$string['chapterno'] = 'Capítulo {no}';
$string['chapterpage'] = 'Página de inicio';
$string['chaptersheader'] = 'Capítulos';
$string['chaptersintro'] = 'Una fila por capítulo: título y página de inicio. Se usa en la pestaña «Contenido» cuando el PDF no tiene un índice propio, o siempre si más arriba está activada la opción «Usar siempre la lista manual de capítulos». Consejo: los PDF exportados desde Word o PowerPoint suelen incluir un índice automáticamente si los títulos usan estilos de título y, al exportar, se activa la opción de crear marcadores a partir de los títulos; en ese caso, por lo general no hace falta esta lista manual.';
$string['chaptertitle'] = 'Título del capítulo';
$string['close'] = 'Cerrar';
$string['completion_done'] = '¡Muy bien! Ha leído lo suficiente de este documento para completar la actividad.';
$string['completion_lastpage'] = 'Debe verse la última página';
$string['completion_percent'] = 'Debe verse un porcentaje de todas las páginas';
$string['completion_specificpage'] = 'Debe verse una página específica';
$string['completion_specificrange'] = 'Deben verse páginas o capítulos específicos';
$string['completionchapters'] = 'O seleccionar los capítulos obligatorios';
$string['completionchapters_help'] = 'Al seleccionar capítulos aquí, sus páginas se añaden a «Páginas obligatorias» (más arriba) al guardar la configuración. Solo se muestran los capítulos ya guardados en la sección «Capítulos».';
$string['completiondetail:lastpage'] = 'Ver la última página';
$string['completiondetail:page'] = 'Ver la página {$a}';
$string['completiondetail:percent'] = 'Ver el {$a}% de las páginas';
$string['completiondetail:range'] = 'Ver las páginas {$a}';
$string['completionpage'] = 'Número de página';
$string['completionpages'] = 'Páginas obligatorias';
$string['completionpages_help'] = 'Números o rangos de página que deben verse todos, p. ej. «1-5, 8, 12-20». También puede seleccionar capítulos completos más abajo en lugar de escribir números de página.';
$string['completionpageseen'] = 'Requerir visualización de páginas';
$string['completionpageseen_desc'] = 'Los estudiantes deben ver páginas del documento';
$string['completionpercent'] = 'Porcentaje de páginas';
$string['completiontype'] = 'Páginas que deben verse';
$string['continuenotice'] = 'Continuar en la página {$a}';
$string['displaysettings'] = 'Visualización';
$string['document'] = 'Documento';
$string['download'] = 'Descargar PDF';
$string['downloadallowed'] = 'Permitir descarga';
$string['downloadallowed_help'] = 'Si está activado, los estudiantes con la capacidad «Descargar el PDF» pueden descargar el archivo PDF original.';
$string['error_invalidpage'] = 'Introduzca un número de página igual o mayor que 1.';
$string['error_invalidpagerange'] = 'Introduzca números o rangos de página válidos, p. ej. «1-5, 8, 12-20», o seleccione al menos un capítulo.';
$string['error_invalidpercent'] = 'Introduzca un porcentaje entre 1 y 100.';
$string['errordocument'] = 'No se pudo cargar el documento.';
$string['eventpageviewed'] = 'Página vista';
$string['firstpage'] = 'Primera página';
$string['fit_page'] = 'Ajustar a la página';
$string['fit_width'] = 'Ajustar al ancho';
$string['fitmode'] = 'Ajuste de zoom';
$string['fullscreen_enter'] = 'Pantalla completa';
$string['fullscreen_exit'] = 'Salir de pantalla completa';
$string['fullscreentip'] = 'Consejo: en pantalla completa tiene más espacio para leer (tecla F).';
$string['gotopage'] = 'Ir a la página';
$string['help'] = 'Atajos de teclado';
$string['help_bookmark'] = 'Añadir o quitar el marcador de la página actual';
$string['help_close'] = 'Cerrar un cuadro de diálogo o la barra lateral, o salir de la pantalla completa';
$string['help_firstlast'] = 'Primera o última página';
$string['help_fullscreen'] = 'Activar o desactivar la pantalla completa';
$string['help_help'] = 'Mostrar esta ayuda';
$string['help_intro'] = 'Los atajos de teclado funcionan en cuanto haya hecho clic en el documento.';
$string['help_nextprev'] = 'Página siguiente o anterior';
$string['help_toc'] = 'Mostrar u ocultar el índice';
$string['help_zoom'] = 'Acercar o alejar el zoom';
$string['initialpage'] = 'Página inicial';
$string['initialpage_help'] = 'La página que se muestra la primera vez que se abre el documento. Después, el documento se abre en la última página leída.';
$string['lastpage'] = 'Última página';
$string['leafr:addinstance'] = 'Añadir un nuevo flipbook Leafr';
$string['leafr:download'] = 'Descargar el PDF';
$string['leafr:view'] = 'Ver el flipbook Leafr';
$string['leafr:viewreport'] = 'Ver el informe de lectura de Leafr';
$string['leafrname'] = 'Nombre';
$string['loading'] = 'Cargando documento…';
$string['matchofmatches'] = '{$a->index} de {$a->total}';
$string['modulename'] = 'Flipbook Leafr';
$string['modulename_help'] = 'El flipbook Leafr muestra un documento PDF como un libro que los estudiantes hojean directamente en el curso, con una animación realista de paso de página.

Los estudiantes continúan donde lo dejaron la última vez, pueden buscar en el texto, saltar a los capítulos mediante el índice o las miniaturas de las páginas y añadir marcadores con notas personales.

La actividad puede completarse automáticamente al ver la última página, un porcentaje de las páginas, una página específica o determinadas páginas y capítulos; además, se puede requerir una confirmación de lectura. Para mejorar la accesibilidad, hay disponible una vista simple y desplazable, sin animación de paso de página.';
$string['modulenameplural'] = 'Flipbooks Leafr';
$string['nextpage'] = 'Página siguiente';
$string['nonewmodules'] = 'No hay flipbooks Leafr en este curso.';
$string['nopdfuploaded'] = 'Todavía no se ha añadido ningún archivo PDF a esta actividad.';
$string['pagelabel'] = 'Página {$a}';
$string['pageofpages'] = 'Página {$a->page} de {$a->total}';
$string['pageprogress'] = 'Progreso de lectura';
$string['pagesofpages'] = 'Páginas {$a->first} y {$a->last} de {$a->total}';
$string['pdffile'] = 'Archivo PDF';
$string['pdffile_help'] = 'El documento PDF que se muestra como flipbook. El tamaño máximo del archivo depende de la configuración del sitio.';
$string['pluginadministration'] = 'Administración del flipbook Leafr';
$string['pluginname'] = 'Flipbook Leafr';
$string['previouspage'] = 'Página anterior';
$string['privacy:bookmarkssubcontext'] = 'Marcadores';
$string['privacy:metadata:leafr_bookmarks'] = 'Los marcadores de un usuario en un flipbook Leafr.';
$string['privacy:metadata:leafr_bookmarks:note'] = 'La nota añadida al marcador.';
$string['privacy:metadata:leafr_bookmarks:pageno'] = 'La página marcada.';
$string['privacy:metadata:leafr_bookmarks:timecreated'] = 'La fecha en que se creó el marcador.';
$string['privacy:metadata:leafr_bookmarks:timemodified'] = 'La fecha de la última actualización del marcador.';
$string['privacy:metadata:leafr_bookmarks:userid'] = 'El identificador del usuario.';
$string['privacy:metadata:leafr_progress'] = 'El progreso de lectura de un usuario en un flipbook Leafr.';
$string['privacy:metadata:leafr_progress:lastpage'] = 'La última página leída por el usuario.';
$string['privacy:metadata:leafr_progress:seenpages'] = 'Las páginas que el usuario ha visto.';
$string['privacy:metadata:leafr_progress:timemodified'] = 'La fecha de la última actualización del progreso.';
$string['privacy:metadata:leafr_progress:userid'] = 'El identificador del usuario.';
$string['privacy:metadata:preference:simpleview'] = 'Si el usuario prefiere la vista simple sin animación de paso de página.';
$string['privacy:metadata:preference:spreadmode'] = 'Si el usuario prefiere página única, doble página o cambio automático en la vista de libro.';
$string['progresssummary'] = '{$a->seen} de {$a->total} páginas leídas';
$string['readerlabel'] = 'Flipbook: {$a}';
$string['reload'] = 'Recargar página';
$string['required_badge'] = 'Obligatorio';
$string['requiredsummary'] = '{$a->seen} de {$a->total} páginas obligatorias leídas';
$string['resetbookmarks'] = 'Eliminar los marcadores de todos los usuarios';
$string['resetprogress'] = 'Eliminar el progreso de lectura de todos los usuarios';
$string['restartreading'] = 'Empezar desde el principio';
$string['search_hint'] = 'Escriba una palabra y pulse Intro.';
$string['search_indexing'] = 'Preparando la búsqueda…';
$string['search_label'] = 'Buscar texto';
$string['search_next'] = 'Siguiente coincidencia';
$string['search_noresults'] = 'No se encontraron coincidencias.';
$string['search_noresults_scan'] = 'Este documento no contiene texto que se pueda buscar.';
$string['search_placeholder'] = 'Buscar en el documento';
$string['search_prev'] = 'Coincidencia anterior';
$string['showtoc'] = 'Mostrar índice';
$string['showtoc_help'] = 'Muestra el índice guardado en el propio PDF (la estructura de capítulos, que los programas de PDF suelen llamar «marcadores») en la pestaña «Contenido» de la barra lateral. Si el PDF no tiene índice, se usan los capítulos introducidos en la sección «Capítulos».';
$string['sidebar'] = 'Barra lateral';
$string['simpleview'] = 'Vista simple';
$string['spread_auto'] = 'Automático';
$string['spread_double'] = 'Doble página';
$string['spread_single'] = 'Página única';
$string['spreadmode'] = 'Diseño de página';
$string['subplugintype_leafrtool'] = 'Herramienta de Leafr';
$string['subplugintype_leafrtool_plural'] = 'Herramientas de Leafr';
$string['tab_bookmarks'] = 'Marcadores';
$string['tab_contents'] = 'Contenido';
$string['tab_search'] = 'Buscar';
$string['tab_thumbnails'] = 'Miniaturas';
$string['thumbs_legend_required'] = 'Página obligatoria';
$string['thumbs_legend_seen'] = 'Leída';
$string['toc_empty'] = 'Este PDF no contiene ningún índice.';
$string['toolbar'] = 'Controles del visor';
$string['totalpages'] = 'de {$a}';
$string['usemanualchapters'] = 'Usar siempre la lista manual de capítulos';
$string['usemanualchapters_help'] = 'De forma predeterminada, se usa el índice automático del PDF en la pestaña «Contenido» si existe, y la lista manual de arriba solo se usa como alternativa cuando el PDF no tiene ninguno. Active esta opción para usar siempre la lista manual en su lugar.';
$string['viewmenu'] = 'Vista';
$string['zoomheading'] = 'Zoom';
$string['zoomin'] = 'Acercar';
$string['zoomlevel'] = 'Zoom: {$a}%';
$string['zoomout'] = 'Alejar';
$string['zoomreset'] = 'Restablecer zoom';

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
$string['bookmark_empty'] = 'Todavía no hay marcadores.';
$string['bookmark_note_chars'] = '{$a->used} de {$a->max} caracteres';
$string['bookmark_note_label'] = 'Nota para';
$string['bookmark_note_placeholder'] = 'Añadir una nota…';
$string['bookmark_page'] = 'Marcar la página {$a}';
$string['bookmark_print'] = 'Imprimir o guardar como PDF';
$string['bookmark_remove'] = 'Quitar marcador';
$string['chapterpage'] = 'Página de inicio';
$string['chaptersheader'] = 'Capítulos';
$string['chaptertitle'] = 'Título del capítulo';
$string['chaptertitle_help'] = 'Una fila por capítulo: título y página de inicio. Se usa en la pestaña «Contenido» cuando el PDF no tiene un índice propio, o siempre si más abajo está activado «Usar siempre la lista manual de capítulos».

Consejo: los PDF exportados desde Word o PowerPoint obtienen un índice automáticamente si usaste estilos de título (Word) o «Crear vínculos usando: Títulos» (opciones de exportación de PowerPoint); en ese caso, normalmente no hace falta esta lista manual.';
$string['close'] = 'Cerrar';
$string['completion_done'] = '¡Muy bien! Has leído suficiente de este documento para completar la actividad.';
$string['completion_lastpage'] = 'Debe verse la última página';
$string['completion_percent'] = 'Debe verse un porcentaje de todas las páginas';
$string['completion_specificpage'] = 'Debe verse una página específica';
$string['completion_specificrange'] = 'Deben verse páginas o capítulos específicos';
$string['completionchapters'] = 'O seleccionar los capítulos obligatorios';
$string['completionchapters_help'] = 'Al seleccionar capítulos aquí, sus páginas se añaden a «Páginas obligatorias» más abajo al guardar la configuración. Solo se muestran los capítulos ya guardados en la sección «Capítulos» de arriba.';
$string['completiondetail:lastpage'] = 'Ver la última página';
$string['completiondetail:page'] = 'Ver la página {$a}';
$string['completiondetail:percent'] = 'Ver el {$a}% de las páginas';
$string['completiondetail:range'] = 'Ver las páginas {$a}';
$string['completionpage'] = 'Número de página';
$string['completionpages'] = 'Páginas obligatorias';
$string['completionpages_help'] = 'Números o rangos de página que deben verse todos, p. ej. «1-5, 8, 12-20». También puedes seleccionar capítulos completos más abajo en lugar de escribir números de página.';
$string['completionpageseen'] = 'Exigir visualización de páginas';
$string['completionpageseen_desc'] = 'El alumnado debe ver páginas del documento';
$string['completionpercent'] = 'Porcentaje de páginas';
$string['completiontype'] = 'Páginas a ver';
$string['continuenotice'] = 'Continuar en la página {$a}';
$string['conversionfailed'] = 'No se pudo convertir este documento a PDF. Ponte en contacto con tu profesor/a.';
$string['conversionpending'] = 'Este documento se está preparando para su visualización. Esta página se actualizará automáticamente en unos segundos.';
$string['displaysettings'] = 'Visualización';
$string['document'] = 'Documento';
$string['download'] = 'Descargar PDF';
$string['downloadallowed'] = 'Permitir descarga';
$string['downloadallowed_help'] = 'Si está activado, el alumnado con el permiso «Descargar el PDF» puede descargar el archivo PDF original.';
$string['error_invalidpage'] = 'Introduce un número de página igual o mayor que 1.';
$string['error_invalidpagerange'] = 'Introduce números o rangos de página válidos, p. ej. «1-5, 8, 12-20», o selecciona al menos un capítulo.';
$string['error_invalidpercent'] = 'Introduce un porcentaje entre 1 y 100.';
$string['errordocument'] = 'No se pudo cargar el documento.';
$string['eventpageviewed'] = 'Página vista';
$string['firstpage'] = 'Primera página';
$string['fit_page'] = 'Ajustar a la página';
$string['fit_width'] = 'Ajustar al ancho';
$string['fitmode'] = 'Ajuste de zoom';
$string['fullscreen_enter'] = 'Pantalla completa';
$string['fullscreen_exit'] = 'Salir de pantalla completa';
$string['gotopage'] = 'Ir a la página';
$string['help'] = 'Atajos de teclado';
$string['help_bookmark'] = 'Marcar o quitar el marcador de la página actual';
$string['help_close'] = 'Cerrar el cuadro de diálogo, el índice o la pantalla completa';
$string['help_firstlast'] = 'Primera o última página';
$string['help_fullscreen'] = 'Activar o desactivar la pantalla completa';
$string['help_help'] = 'Mostrar esta ayuda';
$string['help_intro'] = 'Los atajos funcionan cuando el lector tiene el foco.';
$string['help_nextprev'] = 'Página siguiente o anterior';
$string['help_toc'] = 'Activar o desactivar el índice';
$string['help_zoom'] = 'Acercar o alejar el zoom';
$string['initialpage'] = 'Página inicial';
$string['initialpage_help'] = 'La página que se muestra cuando un/a estudiante abre el documento por primera vez. Después, el documento se abre en la última página leída.';
$string['lastpage'] = 'Última página';
$string['leafr:addinstance'] = 'Añadir un nuevo libro Leafr';
$string['leafr:download'] = 'Descargar el PDF';
$string['leafr:view'] = 'Ver el libro Leafr';
$string['leafr:viewreport'] = 'Ver el informe de lectura de Leafr';
$string['leafrname'] = 'Nombre';
$string['loading'] = 'Cargando documento…';
$string['matchofmatches'] = '{$a->index} de {$a->total}';
$string['modulename'] = 'Libro Leafr';
$string['modulename_help'] = 'El libro Leafr muestra un documento PDF como un libro que el alumnado puede hojear directamente en el curso.

El alumnado continúa leyendo donde lo dejó la última vez. La actividad puede completarse automáticamente al ver la última página, un determinado porcentaje de las páginas o una página específica.

Para la accesibilidad hay disponible una vista simple y desplazable, sin animación de paso de página.';
$string['modulenameplural'] = 'Libros Leafr';
$string['nextpage'] = 'Página siguiente';
$string['nonewmodules'] = 'No hay libros Leafr en este curso.';
$string['nopdfuploaded'] = 'Todavía no se ha añadido ningún archivo PDF a esta actividad.';
$string['pagelabel'] = 'Página {$a}';
$string['pageofpages'] = 'Página {$a->page} de {$a->total}';
$string['pageprogress'] = 'Progreso de lectura';
$string['pagesofpages'] = 'Páginas {$a->first} y {$a->last} de {$a->total}';
$string['pdffile'] = 'Archivo PDF';
$string['pdffile_help'] = 'El documento PDF que se muestra como libro. El tamaño máximo del archivo depende de la configuración del sitio.';
$string['pluginadministration'] = 'Administración del libro Leafr';
$string['pluginname'] = 'Libro Leafr';
$string['previouspage'] = 'Página anterior';
$string['privacy:bookmarkssubcontext'] = 'Marcadores';
$string['privacy:metadata:leafr_bookmarks'] = 'Los marcadores de un usuario en un libro Leafr.';
$string['privacy:metadata:leafr_bookmarks:note'] = 'La nota añadida al marcador.';
$string['privacy:metadata:leafr_bookmarks:pageno'] = 'La página marcada.';
$string['privacy:metadata:leafr_bookmarks:timecreated'] = 'La fecha en que se creó el marcador.';
$string['privacy:metadata:leafr_bookmarks:timemodified'] = 'La fecha de la última actualización del marcador.';
$string['privacy:metadata:leafr_bookmarks:userid'] = 'El identificador del usuario.';
$string['privacy:metadata:leafr_progress'] = 'El progreso de lectura de un usuario en un libro Leafr.';
$string['privacy:metadata:leafr_progress:lastpage'] = 'La última página leída por el usuario.';
$string['privacy:metadata:leafr_progress:seenpages'] = 'Las páginas que el usuario ha visto.';
$string['privacy:metadata:leafr_progress:timemodified'] = 'La fecha de la última actualización del progreso.';
$string['privacy:metadata:leafr_progress:userid'] = 'El identificador del usuario.';
$string['privacy:metadata:preference:simpleview'] = 'Si el usuario prefiere la vista simple sin animación de paso de página.';
$string['privacy:metadata:preference:spreadmode'] = 'Si el usuario prefiere página única, página doble o cambio automático en la vista de libro.';
$string['progresssummary'] = '{$a->seen} de {$a->total} páginas leídas';
$string['readerlabel'] = 'Libro: {$a}';
$string['reload'] = 'Recargar página';
$string['required_badge'] = 'Obligatoria';
$string['requiredsummary'] = '{$a->seen} de {$a->total} páginas obligatorias leídas';
$string['resetbookmarks'] = 'Eliminar los marcadores de todos los usuarios';
$string['resetprogress'] = 'Eliminar el progreso de lectura de todos los usuarios';
$string['restartreading'] = 'Empezar desde el principio';
$string['search_indexing'] = 'Preparando la búsqueda…';
$string['search_label'] = 'Buscar texto';
$string['search_next'] = 'Siguiente coincidencia';
$string['search_noresults'] = 'No se encontraron coincidencias.';
$string['search_noresults_scan'] = 'Este documento no contiene texto que se pueda buscar.';
$string['search_placeholder'] = 'Buscar en el documento';
$string['search_prev'] = 'Coincidencia anterior';
$string['showtoc'] = 'Mostrar índice';
$string['showtoc_help'] = 'Muestra los marcadores (esquema) del PDF como índice. El PDF debe contener marcadores, que la mayoría de programas pueden crear al exportar a PDF.';
$string['sidebar'] = 'Barra lateral';
$string['simpleview'] = 'Vista simple';
$string['spread_auto'] = 'Automático';
$string['spread_double'] = 'Página doble';
$string['spread_single'] = 'Página única';
$string['spreadmode'] = 'Diseño de página';
$string['tab_bookmarks'] = 'Marcadores';
$string['tab_contents'] = 'Contenido';
$string['tab_search'] = 'Buscar';
$string['tab_thumbnails'] = 'Miniaturas';
$string['toc_empty'] = 'Este PDF no contiene un índice.';
$string['toolbar'] = 'Controles del lector';
$string['totalpages'] = 'de {$a}';
$string['usemanualchapters'] = 'Usar siempre la lista manual de capítulos';
$string['usemanualchapters_help'] = 'De forma predeterminada, se usa el índice automático del PDF en la pestaña «Contenido» si existe, y la lista manual de arriba solo se usa como alternativa cuando el PDF no tiene ninguno. Activa esta opción para usar siempre la lista manual en su lugar.';
$string['viewmenu'] = 'Vista';
$string['zoomheading'] = 'Zoom';
$string['zoomin'] = 'Acercar';
$string['zoomlevel'] = 'Zoom: {$a}%';
$string['zoomout'] = 'Alejar';
$string['zoomreset'] = 'Restablecer zoom';

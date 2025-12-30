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
 * TODO describe file index
 *
 * @package    local_lessonbank
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\output\html_writer;
use local_lessonbank\form\search_form;

require('../../config.php');

require_login();

$url = new moodle_url('/local/lessonbank/index.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('lessonbank', 'local_lessonbank'));
$PAGE->set_title(get_string('lessonbank', 'local_lessonbank'));

$searchform = new search_form(attributes: ['id' => 'local_lessonbank_filters']);

$PAGE->requires->js_call_amd('local_lessonbank/searchform', 'registerFilter');

$lessonbankcontrolsdata = [
    'lessonbankitemcount' => get_string('foundlessons', 'local_lessonbank', 0),
    'paginationoptions' => [10, 25, 50, 100]
];

echo $OUTPUT->header();

echo html_writer::start_div('container');

echo $searchform->render();

echo $OUTPUT->render_from_template('local_lessonbank/lessonbankcontrols', $lessonbankcontrolsdata);

echo html_writer::div(
    $OUTPUT->render_from_template(
        'core/overlay_loading',
        ['visible' => true]
    ),
    'position-relative',
    ['data-region' => 'cards-container']
);

echo html_writer::end_div();

echo $OUTPUT->footer();

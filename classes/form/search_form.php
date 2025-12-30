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

namespace local_lessonbank\form;

use local_lessonbank\external\fetch_langlevels;
use mod_minilesson\utils;
use moodleform;

require_once($CFG->libdir . '/formslib.php');

/**
 * Class search_form
 *
 * @package    local_lessonbank
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_form extends moodleform {

    const PERPAGE = 10;

    protected function definition() {
        $mform = $this->_form;

        $languages = get_string_manager()->get_list_of_languages();
        $languages = ['' => get_string('choose')] + utils::get_lang_options();

        $mform->addElement('html', '<div class="lessonbank_search_form d-flex flex-wrap">');
        $mform->addElement('html', '<div class="d-flex mb-3">');

        $mform->addElement('select', 'language', get_string('language'), $languages);
        $mform->setType('language', PARAM_INT);

        $mform->addElement('text', 'search', '', ['placeholder' => get_string('keywords', 'local_lessonbank'), 'size' => 50]);
        $mform->setType('search', PARAM_RAW);

        $mform->addElement('submit', 'submit', get_string('search'));

        $mform->addElement('html', '<div class="d-flex align-items-center ml-2 mr-2">');

        $mform->addElement('html', '<a class="btn text-primary" href="#advancesearch" data-toggle="collapse" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="advancesearch">' .
                get_string('showadvanced', 'local_lessonbank') . '</a>');

        $mform->addElement('html', '</div>');

        $mform->addElement('html', '</div>');

        $fieldoptions = fetch_langlevels::execute();
        $fieldoptions = array_column($fieldoptions, 'text', 'value');
        $mform->addElement('html', '<div class="collapse w-100" id="advancesearch">');
        $mform->addElement('autocomplete', 'level', get_string('level', 'local_lessonbank'), $fieldoptions, 'multiple');
        $mform->setType('level', PARAM_INT);
        $mform->addElement('html', '</div>');

        $mform->addElement('html', '</div>');

        $mform->addElement('hidden', 'page');
        $mform->setType('page', PARAM_INT);
        $mform->setDefault('page', 1);

        $mform->addElement('hidden', 'perpage');
        $mform->setType('perpage', PARAM_INT);
        $mform->setDefault('perpage', self::PERPAGE);
    }

}

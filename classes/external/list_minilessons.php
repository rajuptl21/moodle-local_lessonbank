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

namespace local_lessonbank\external;

use core_customfield\data_controller;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_api;
use core_external\external_single_structure;
use core_external\external_value;
use core_reportbuilder\local\report\column;
use local_modcustomfields\customfield\mod_handler;
use mod_minilesson\utils;
use moodle_url;
use stdClass;

/**
 * Implementation of web service local_lessonbank_list_minilessons
 *
 * @package    local_lessonbank
 * @copyright  2025 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class list_minilessons extends external_api
{

    /**
     * @var array
     */
    const CUSTOMFIELDS = [
        0 => 'description',
        1 => 'version',
        2 => 'posterimage',
        3 => 'languagelevel',
        4 => 'skills',
        5 => 'keywords',
        6 => 'keyvocabulary'
    ];

    /**
     * Describes the parameters for local_lessonbank_list_minilessons
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters
    {
        return new external_function_parameters([
            'language' => new external_value(PARAM_RAW, 'Language', VALUE_DEFAULT),
            'level' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'level', VALUE_DEFAULT),
                'Level',
                VALUE_DEFAULT,
                []
            ),
            'keywords' => new external_value(PARAM_RAW, 'Key words', VALUE_DEFAULT),
        ]);
    }

    /**
     * Implementation of web service local_lessonbank_list_minilessons
     *
     * @param string $language
     * @param string $level
     * @param string $keywords
     */
    public static function execute($language, $level, $keywords)
    {
        global $DB;
        // Parameter validation.
        [
            'language' => $language,
            'level' => $levels,
            'keywords' => $keywords,
        ] = self::validate_parameters(
                    self::execute_parameters(),
                    [
                        'language' => $language,
                        'level' => $level,
                        'keywords' => $keywords,
                    ]
                );

        $params['moduleid'] = $DB->get_field('modules', 'id', ['name' => 'minilesson']);
        $params['ctxlevel'] = CONTEXT_MODULE;

        $fields = "cm.id AS cmid, m.id, m.name,m.ttslanguage AS language,m.nativelang,ctx.id AS contextid, mi.itemtypes";
        $from = "{minilesson} m JOIN {course_modules} cm ON cm.instance = m.id";
        $from .= " JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :ctxlevel";
        $from .= " LEFT JOIN (
            SELECT minilesson AS minilessonid,GROUP_CONCAT(DISTINCT type) AS itemtypes
            FROM {minilesson_rsquestions} GROUP BY minilesson
        ) mi ON mi.minilessonid = m.id";
        $where = "cm.module = :moduleid AND cm.visible = 1";

        $modcustomfieldhandler = mod_handler::create();
        $allfieldshorts = self::CUSTOMFIELDS;
        $allfields = array_fill_keys($allfieldshorts, null);
        $alllangs = utils::get_lang_options();

        foreach ($modcustomfieldhandler->get_categories_with_fields() as $categorycontoller) {
            if ($categorycontoller->get('name') === get_string('lessonbankcatname', 'local_lessonbank')) {
                foreach ($categorycontoller->get_fields() as $field) {
                    $i = $field->get('id');
                    $fieldshortname = $field->get('shortname');
                    if (in_array($fieldshortname, $allfieldshorts)) {
                        $customdatatablealias = "cd{$i}";
                        $allfields[$fieldshortname] = $field;
                        $from .= " LEFT JOIN {customfield_data} {$customdatatablealias}
                            ON {$customdatatablealias}.fieldid = {$field->get('id')}
                            AND {$customdatatablealias}.instanceid = cm.id";
                        $datacontroller = data_controller::create(0, null, $field);
                        $datafield = $datacontroller->datafield();

                        $dbfield = $customdatasql = "{$customdatatablealias}.{$datafield}";

                        // Numeric column (non-text) should coalesce with default, for aggregation.
                        $columntype = match ($field->get('type')) {
                            'checkbox' => column::TYPE_BOOLEAN,
                            'date' => column::TYPE_TIMESTAMP,
                            'select' => column::TYPE_TEXT,
                            'intvalue' => column::TYPE_INTEGER,
                            'decvalue' => column::TYPE_FLOAT,
                            'value' => column::TYPE_LONGTEXT,
                            default => column::TYPE_TEXT
                        };
                        if (!in_array($columntype, [column::TYPE_TEXT, column::TYPE_LONGTEXT])) {

                            // See MDL-78783 regarding no bound parameters, and SQL Server limitations of GROUP BY.
                            $customdatasql = "
                                CASE WHEN cm.id IS NOT NULL
                                    THEN COALESCE({$customdatasql}, " . (float) $datacontroller->get_default_value() . ")
                                    ELSE NULL
                                END";
                        }

                        // Select enough fields to re-create and format each custom field instance value.
                        $fields .= ", {$customdatasql} AS {$fieldshortname}{$datafield}, {$customdatatablealias}.id AS {$fieldshortname}id, {$customdatatablealias}.contextid AS {$fieldshortname}contextid";
                        if ($datafield === 'value') {
                            // We will take the format into account when displaying the individual values.
                            $fields .= ", {$customdatatablealias}.valueformat AS {$fieldshortname}valueformat, {$customdatatablealias}.valuetrust AS {$fieldshortname}valuetrust";
                        }

                        // Filters
                        if ($fieldshortname === $allfieldshorts[3]) {
                            $levelvalues = [];
                            if (!empty($levels) && $field->get('type') === 'select') {
                                $fieldoptions = $field->get_options();
                                foreach ($levels as $level) {
                                    if (!is_numeric($level) && in_array($level, $fieldoptions)) {
                                        $levelvalues[] = array_search($level, $fieldoptions);
                                    } else {
                                        $levelvalues[] = $level;
                                    }
                                }
                            }
                            if (!empty($levelvalues)) {
                                $where .= " AND (";
                                $levelors = [];
                                foreach ($levelvalues as $key => $level) {
                                    $levelors[] = "{$dbfield} = :langlevel{$key}";
                                    $params["langlevel{$key}"] = $level;
                                }
                                $where .= join(' OR ', $levelors);
                                $where .= ")";
                            }
                        }

                        if ($fieldshortname === $allfieldshorts[5]) {
                            if (!empty($keywords)) {
                                $keywords = array_filter(explode(' ', $keywords), 'trim');
                            }
                            $ors = [];
                            foreach ($keywords as $j => $keyword) {
                                $ors[] = $DB->sql_like($dbfield, ':keyword' . $j, false);
                                $params['keyword' . $j] = "%{$keyword}%";
                            }
                            $j = isset($j) ? $j + 1: 0;
                            $ors[] = $DB->sql_like('m.name', ':keyword' . $j, false);
                            $params['keyword' . $j] = "%{$keyword}%";
                            if (!empty($ors)) {
                                $where .= " AND (" . join(' OR ', $ors) . ")";
                            }
                        }
                    }
                }
            }
        }

        if (!empty($language)) {
            $where .= " AND {$DB->sql_like('m.ttslanguage', ':langcode')} ";
            $params['langcode'] = trim($language);
        }
        $sql = "SELECT {$fields} FROM {$from} WHERE {$where}";
        $records = $DB->get_records_sql($sql, $params);
        foreach ($records as $record) {

            if (array_key_exists($record->language, $alllangs)) {
                $record->language = $alllangs[$record->language];
            }

            foreach ($allfields as $fieldshort => $fieldobj) {
                $row = new stdClass();
                foreach ($record as $key => $value) {
                    if (str_starts_with($key, $fieldshort)) {
                        $row->{substr($key, strlen($fieldshort))} = $value;
                        unset($record->$key);
                    }
                }
                if (!empty($row->id)) {
                    $datacontroller = data_controller::create(0, $row, $fieldobj);
                    $record->{$fieldshort} = $datacontroller->export_value();
                }
                if (!empty($record->{$fieldshort}) && $fieldshort === $allfieldshorts[2]) {
                    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $record->{$fieldshort}, $m)) {
                        $record->{$fieldshort} = $m[1];
                    }
                }
            }
            $record->viewurl = new moodle_url('/mod/minilesson/view.php', ['id' => $record->cmid]);
            $record->viewurl = $record->viewurl->out(false);
            if (!empty($record->nativelang)) {
                $record->nativelanguage = $record->nativelang;
            }

            $record->shortdesc = shorten_text(
                $record->description, 150, false,
                '<button type="button" data-action="showtext">' . get_string('more'). '</button>'
            );

            if ($record->itemtypes) {
                $record->itemtypes = array_map(
                    fn($itemtype) => (object) [
                        'text' => get_string($itemtype, 'mod_minilesson')
                    ],
                    explode(',', $record->itemtypes)
                );
                sort($record->itemtypes);
                end($record->itemtypes)->islast = true;
            } else {
                unset($record->itemtypes);
            }
        }

        // Ensure that we always return something array like and that will satisfy our external structure.
        $returnrecords = [];
        if ($records && !empty($records)) {
            foreach ($records as $record) {
                $allfieldsexist = true;
                foreach ($allfieldshorts as $key => $customfield) {
                    if (!isset($record->{$customfield}) || empty($record->{$customfield})) {
                        $allfieldsexist = false;
                        break;
                    }
                }
                if ($allfieldsexist) {
                    $returnrecords[] = $record;
                }
            }
        }
        return $returnrecords;
    }

    /**
     * Describe the return structure for local_lessonbank_list_minilessons
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure
    {
        $contentstructure = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'id of minilesson'),
            'name' => new external_value(PARAM_TEXT, 'name of minilesson'),
            'language' => new external_value(PARAM_TEXT, 'language of minilesson'),
            'nativelanguage' => new external_value(PARAM_TEXT, 'native language of minilesson', VALUE_OPTIONAL),
            'description' => new external_value(PARAM_TEXT, 'description of minilesson'),
            'shortdesc' => new external_value(PARAM_RAW, 'description of minilesson'),
            'version' => new external_value(PARAM_TEXT, 'version information of minilesson'),
            'posterimage' => new external_value(PARAM_URL, 'poster url of minilesson'),
            'languagelevel' => new external_value(PARAM_TEXT, 'language level of minilesson'),
            'skills' => new external_value(PARAM_TEXT, 'skills information of minilesson'),
            'keywords' => new external_value(PARAM_TEXT, 'keywords of minilesson'),
            'keyvocabulary' => new external_value(PARAM_TEXT, 'keyvocabulary of minilesson'),
            'viewurl' => new external_value(PARAM_URL, 'preview url of minilesson'),
            'itemtypes' => new external_multiple_structure(
                new external_single_structure([
                    'text' => new external_value(PARAM_TEXT, 'item type'),
                    'islast' => new external_value(PARAM_BOOL, 'last item or not', VALUE_DEFAULT, false),
                ]),
                'all items used in minilesson',
                VALUE_OPTIONAL
            ),
        ]);
        return new external_multiple_structure($contentstructure);
    }
}

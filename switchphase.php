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
 * Change the current phase of the concordance.
 *
 * @package    mod_concordance
 * @copyright  2020 Marie-Eve Levesque <marie-eve.levesque.8@umontreal.ca>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');

$cmid = required_param('cmid', PARAM_INT);            // Course module.
$phase = required_param('phase', PARAM_INT);           // The code of the new phase.

require_sesskey();

$cm = get_coursemodule_from_id('concordance', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$concordance = $DB->get_record('concordance', ['id' => $cm->instance], '*', MUST_EXIST);
$concordancepersistent = new \mod_concordance\concordance($concordance->id);

$PAGE->set_url($concordancepersistent->switchphase_url($phase), ['cmid' => $cmid, 'phase' => $phase]);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/concordance:view', $context);

$concordancepersistent->set('activephase', $phase);

$params = [
    'context' => $context,
    'objectid' => $concordancepersistent->get('id'),
];
$event = \mod_concordance\event\concordance_updated::create($params);
$event->add_record_snapshot('concordance', $concordance);
$event->trigger();

$concordancepersistent->save();

redirect($concordancepersistent->view_url());

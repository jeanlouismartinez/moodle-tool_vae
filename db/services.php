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
 * External functions of the plugin.
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * One function, called by the module tool_vae/autosave through core/ajax (ticket 5 of MMR-520). It is in no service: it is
 * not offered to web service clients, only to the pages of the plugin, with the session of the person writing.
 *
 * CAPABILITIES. tool/vae:draftown is the right draft_manager checks, in the context of the writer, before anything is written;
 * the field only declares it (the core does not check it: execute() does, through draft_manager, as every other door does).
 *
 * @package    tool_vae
 * @category   webservice
 * @copyright  2026 Jean-Louis Martinez <jeanlouis@martinez.pro>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'tool_vae_save_draft' => [
        'classname' => \tool_vae\external\save_draft::class,
        'description' => 'Save the draft in progress of a text being written by the vaeiste.',
        'type' => 'write',
        'capabilities' => 'tool/vae:draftown',
        'ajax' => true,
        'loginrequired' => true,
    ],
];

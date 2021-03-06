<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 2 of the License, or
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
 * Popup message processor - stores the message to be shown using the message popup
 *
 * @copyright Luis Rodrigues
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v2 or later
 * @package message
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php'); //included from messagelib (how to fix?)
require_once($CFG->dirroot.'/message/output/lib.php');

class message_output_popup extends message_output{

    /**
     * Process the popup message.
     * The popup doesn't send data only saves in the database for later use,
     * the popup_interface.php takes the message from the message table into
     * the message_read.
     * @param object $eventdata the event data submitted by the message sender plus $eventdata->savedmessageid
     * @return true if ok, false if error
     */
    public function send_message($eventdata) {
        global $DB;

        //hold onto the popup processor id because /admin/cron.php sends a lot of messages at once
        static $processorid = null;

        //prevent users from getting popup notifications of messages to themselves (happens with forum notifications)
        if ($eventdata->userfrom->id!=$eventdata->userto->id) {
            if (empty($processorid)) {
                $processor = $DB->get_record('message_processors', array('name'=>'popup'));
                $processorid = $processor->id;
            }
            $procmessage = new stdClass();
            $procmessage->unreadmessageid = $eventdata->savedmessageid;
            $procmessage->processorid     = $processorid;

            //save this message for later delivery
            $DB->insert_record('message_working', $procmessage);
        }

        return true;
    }

    function config_form($preferences) {
        return null;
    }

    public function process_form($form, &$preferences) {
        return true;
    }
    public function load_data(&$preferences, $userid) {
        global $USER;
        return true;
    }
}
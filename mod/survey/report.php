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
 * This file is responsible for producing the survey reports
 *
 * @package   mod-survey
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once("../../config.php");
    require_once("lib.php");

// Check that all the parameters have been provided.

    $id      = required_param('id', PARAM_INT);           // Course Module ID
    $action  = optional_param('action', '', PARAM_ALPHA); // What to look at
    $qid     = optional_param('qid', 0, PARAM_RAW);       // Question IDs comma-separated list
    $student = optional_param('student', 0, PARAM_INT);   // Student ID
    $notes   = optional_param('notes', '', PARAM_RAW);    // Save teachers notes

    $qids = explode(',', $qid);
    $qids = clean_param_array($qids, PARAM_INT);
    $qid = implode (',', $qids);

    if (! $cm = get_coursemodule_from_id('survey', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf');
    }

    $url = new moodle_url('/mod/survey/report.php', array('id'=>$id));
    if ($action !== '') {
        $url->param('action', $action);
    }
    if ($qid !== 0) {
        $url->param('qid', $qid);
    }
    if ($student !== 0) {
        $url->param('student', $student);
    }
    if ($notes !== '') {
        $url->param('notes', $notes);
    }
    $PAGE->set_url($url);

    require_login($course->id, false, $cm);

    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

    require_capability('mod/survey:readresponses', $context);

    if (! $survey = $DB->get_record("survey", array("id"=>$cm->instance))) {
        print_error('invalidsurveyid', 'survey');
    }

    if (! $template = $DB->get_record("survey", array("id"=>$survey->template))) {
        print_error('invalidtmptid', 'survey');
    }

    $showscales = ($template->name != 'ciqname');


    $strreport = get_string("report", "survey");
    $strsurvey = get_string("modulename", "survey");
    $strsurveys = get_string("modulenameplural", "survey");
    $strsummary = get_string("summary", "survey");
    $strscales = get_string("scales", "survey");
    $strquestion = get_string("question", "survey");
    $strquestions = get_string("questions", "survey");
    $strdownload = get_string("download", "survey");
    $strallscales = get_string("allscales", "survey");
    $strallquestions = get_string("allquestions", "survey");
    $strselectedquestions = get_string("selectedquestions", "survey");
    $strseemoredetail = get_string("seemoredetail", "survey");
    $strnotes = get_string("notes", "survey");

    add_to_log($course->id, "survey", "view report", "report.php?id=$cm->id", "$survey->id", $cm->id);

    switch ($action) {
        case 'download':
            $PAGE->navbar->add(get_string('downloadresults', 'survey'));
            break;
        case 'summary':
        case 'scales':
        case 'questions':
            $PAGE->navbar->add($strreport);
            $PAGE->navbar->add(${'str'.$action});
            break;
        case 'students':
            $PAGE->navbar->add($strreport);
            $PAGE->navbar->add(get_string('participants'));
            break;
        case '':
            $PAGE->navbar->add($strreport);
            $PAGE->navbar->add($strsummary);
            break;
        default:
            $PAGE->navbar->add($strreport);
            break;
    }

    $PAGE->set_title("$course->shortname: ".format_string($survey->name));
    $PAGE->set_heading($course->fullname);
    echo $OUTPUT->header();

/// Check to see if groups are being used in this survey
    if ($groupmode = groups_get_activity_groupmode($cm)) {   // Groups are being used
        $menuaction = $action == "student" ? "students" : $action;
        $currentgroup = groups_get_activity_group($cm, true);
        groups_print_activity_menu($cm, $CFG->wwwroot . "/mod/survey/report.php?id=$cm->id&amp;action=$menuaction&amp;qid=$qid");
    } else {
        $currentgroup = 0;
    }

    if ($currentgroup) {
        $users = get_users_by_capability($context, 'mod/survey:participate', '', '', '', '', $currentgroup, null, false);
    } else if (!empty($cm->groupingid)) {
        $groups = groups_get_all_groups($courseid, 0, $cm->groupingid);
        $groups = array_keys($groups);
        $users = get_users_by_capability($context, 'mod/survey:participate', '', '', '', '', $groups, null, false);
    } else {
        $users = get_users_by_capability($context, 'mod/survey:participate', '', '', '', '', '', null, false);
        $group = false;
    }

    $groupingid = $cm->groupingid;

    echo $OUTPUT->box_start("generalbox boxaligncenter");
    if ($showscales) {
        echo "<a href=\"report.php?action=summary&amp;id=$id\">$strsummary</a>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=scales&amp;id=$id\">$strscales</a>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=questions&amp;id=$id\">$strquestions</a>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=students&amp;id=$id\">".get_string('participants')."</a>";
        if (has_capability('mod/survey:download', $context)) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;id=$id\">$strdownload</a>";
        }
        if (empty($action)) {
            $action = "summary";
        }
    } else {
        echo "<a href=\"report.php?action=questions&amp;id=$id\">$strquestions</a>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=students&amp;id=$id\">".get_string('participants')."</a>";
        if (has_capability('mod/survey:download', $context)) {
            echo "&nbsp;&nbsp;&nbsp;&nbsp;<a href=\"report.php?action=download&amp;id=$id\">$strdownload</a>";
        }
        if (empty($action)) {
            $action = "questions";
        }
    }
    echo $OUTPUT->box_end();

    echo $OUTPUT->spacer(array('height'=>30, 'width'=>30, 'br'=>true)); // should be done with CSS instead


/// Print the menu across the top

    $virtualscales = false;

    switch ($action) {

      case "summary":
        echo $OUTPUT->heading($strsummary);

        if (survey_count_responses($survey->id, $currentgroup, $groupingid)) {
            echo "<div class='reportsummary'><a href=\"report.php?action=scales&amp;id=$id\">";
            survey_print_graph("id=$id&amp;group=$currentgroup&amp;type=overall.png");
            echo "</a></div>";
        } else {
            echo $OUTPUT->notification(get_string("nobodyyet","survey"));
        }
        break;

      case "scales":
        echo $OUTPUT->heading($strscales);

        if (! $results = survey_get_responses($survey->id, $currentgroup, $groupingid) ) {
            echo $OUTPUT->notification(get_string("nobodyyet","survey"));

        } else {

            $questions = $DB->get_records_list("survey_questions", "id", explode(',', $survey->questions));
            $questionorder = explode(",", $survey->questions);

            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];
                if ($question->type < 0) {  // We have some virtual scales.  Just show them.
                    $virtualscales = true;
                    break;
                }
            }

            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];
                if ($question->multi) {
                    if (!empty($virtualscales) && $question->type > 0) {  // Don't show non-virtual scales if virtual
                        continue;
                    }
                    echo "<p class=\"centerpara\"><a title=\"$strseemoredetail\" href=\"report.php?action=questions&amp;id=$id&amp;qid=$question->multi\">";
                    survey_print_graph("id=$id&amp;qid=$question->id&amp;group=$currentgroup&amp;type=multiquestion.png");
                    echo "</a></p><br />";
                }
            }
        }

        break;

      case "questions":

        if ($qid) {     // just get one multi-question
            $questions = $DB->get_records_select("survey_questions", "id in ($qid)");
            $questionorder = explode(",", $qid);

            if ($scale = $DB->get_records("survey_questions", array("multi"=>$qid))) {
                $scale = array_pop($scale);
                echo $OUTPUT->heading("$scale->text - $strselectedquestions");
            } else {
                echo $OUTPUT->heading($strselectedquestions);
            }

        } else {        // get all top-level questions
            $questions = $DB->get_records_list("survey_questions", "id", explode(',',$survey->questions));
            $questionorder = explode(",", $survey->questions);

            echo $OUTPUT->heading($strallquestions);
        }

        if (! $results = survey_get_responses($survey->id, $currentgroup, $groupingid) ) {
            echo $OUTPUT->notification(get_string("nobodyyet","survey"));

        } else {

            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];
                if ($question->type < 0) {  // We have some virtual scales.  DON'T show them.
                    $virtualscales = true;
                    break;
                }
            }

            foreach ($questionorder as $key => $val) {
                $question = $questions[$val];

                if ($question->type < 0) {  // We have some virtual scales.  DON'T show them.
                    continue;
                }
                $question->text = get_string($question->text, "survey");

                if ($question->multi) {
                    echo "<h3>$question->text:</h3>";

                    $subquestions = $DB->get_records_list("survey_questions", "id", explode(',', $question->multi));
                    $subquestionorder = explode(",", $question->multi);
                    foreach ($subquestionorder as $key => $val) {
                        $subquestion = $subquestions[$val];
                        if ($subquestion->type > 0) {
                            echo "<p class=\"centerpara\">";
                            echo "<a title=\"$strseemoredetail\" href=\"report.php?action=question&amp;id=$id&amp;qid=$subquestion->id\">";
                            survey_print_graph("id=$id&amp;qid=$subquestion->id&amp;group=$currentgroup&amp;type=question.png");
                            echo "</a></p>";
                        }
                    }
                } else if ($question->type > 0 ) {
                    echo "<p class=\"centerpara\">";
                    echo "<a title=\"$strseemoredetail\" href=\"report.php?action=question&amp;id=$id&amp;qid=$question->id\">";
                    survey_print_graph("id=$id&amp;qid=$question->id&amp;group=$currentgroup&amp;type=question.png");
                    echo "</a></p>";

                } else {
                    $table = new html_table();
                    $table->head = array($question->text);
                    $table->align = array ("left");

                    $contents = '<table cellpadding="15" width="100%">';

                    if ($aaa = survey_get_user_answers($survey->id, $question->id, $currentgroup, "sa.time ASC")) {
                        foreach ($aaa as $a) {
                            $contents .= "<tr>";
                            $contents .= '<td class="fullnamecell">'.fullname($a).'</td>';
                            $contents .= '<td valign="top">'.$a->answer1.'</td>';
                            $contents .= "</tr>";
                        }
                    }
                    $contents .= "</table>";

                    $table->data[] = array($contents);

                    echo html_writer::table($table);

                    echo $OUTPUT->spacer(array('height'=>30)); // should be done with CSS instead
                }
            }
        }

        break;

      case "question":
        if (!$question = $DB->get_record("survey_questions", array("id"=>$qid))) {
            print_error('cannotfindquestion', 'survey');
        }
        $question->text = get_string($question->text, "survey");

        $answers =  explode(",", get_string($question->options, "survey"));

        echo $OUTPUT->heading("$strquestion: $question->text");


        $strname = get_string("name", "survey");
        $strtime = get_string("time", "survey");
        $stractual = get_string("actual", "survey");
        $strpreferred = get_string("preferred", "survey");
        $strdateformat = get_string("strftimedatetime");

        $table = new html_table();
        $table->head = array("", $strname, $strtime, $stractual, $strpreferred);
        $table->align = array ("left", "left", "left", "left", "right");
        $table->size = array (35, "", "", "", "");

        if ($aaa = survey_get_user_answers($survey->id, $question->id, $currentgroup)) {
            foreach ($aaa as $a) {
                if ($a->answer1) {
                    $answer1 =  "$a->answer1 - ".$answers[$a->answer1 - 1];
                } else {
                    $answer1 =  "&nbsp;";
                }
                if ($a->answer2) {
                    $answer2 = "$a->answer2 - ".$answers[$a->answer2 - 1];
                } else {
                    $answer2 = "&nbsp;";
                }
                $table->data[] = array(
                       $OUTPUT->user_picture($a, array('courseid'=>$course->id)),
                       "<a href=\"report.php?id=$id&amp;action=student&amp;student=$a->userid\">".fullname($a)."</a>",
                       userdate($a->time),
                       $answer1, $answer2);

            }
        }

        echo html_writer::table($table);

        break;

      case "students":

         echo $OUTPUT->heading(get_string("analysisof", "survey", get_string('participants')));

         if (! $results = survey_get_responses($survey->id, $currentgroup, $groupingid) ) {
             echo $OUTPUT->notification(get_string("nobodyyet","survey"));
         } else {
             survey_print_all_responses($cm->id, $results, $course->id);
         }

        break;

      case "student":
         if (!$user = $DB->get_record("user", array("id"=>$student))) {
             print_error('invaliduserid');
         }

         echo $OUTPUT->heading(get_string("analysisof", "survey", fullname($user)));

         if ($notes != '' and confirm_sesskey()) {
             if (survey_get_analysis($survey->id, $user->id)) {
                 if (! survey_update_analysis($survey->id, $user->id, $notes)) {
                     echo $OUTPUT->notification("An error occurred while saving your notes.  Sorry.");
                 } else {
                     echo $OUTPUT->notification(get_string("savednotes", "survey"));
                 }
             } else {
                 if (! survey_add_analysis($survey->id, $user->id, $notes)) {
                     echo $OUTPUT->notification("An error occurred while saving your notes.  Sorry.");
                 } else {
                     echo $OUTPUT->notification(get_string("savednotes", "survey"));
                 }
             }
         }

         echo "<p <p class=\"centerpara\">";
         echo $OUTPUT->user_picture($user, array('courseid'=>$course->id));
         echo "</p>";

         $questions = $DB->get_records_list("survey_questions", "id", explode(',', $survey->questions));
         $questionorder = explode(",", $survey->questions);

         if ($showscales) {
             // Print overall summary
             echo "<p <p class=\"centerpara\">>";
             survey_print_graph("id=$id&amp;sid=$student&amp;type=student.png");
             echo "</p>";

             // Print scales

             foreach ($questionorder as $key => $val) {
                 $question = $questions[$val];
                 if ($question->type < 0) {  // We have some virtual scales.  Just show them.
                     $virtualscales = true;
                     break;
                 }
             }

             foreach ($questionorder as $key => $val) {
                 $question = $questions[$val];
                 if ($question->multi) {
                     if ($virtualscales && $question->type > 0) {  // Don't show non-virtual scales if virtual
                         continue;
                     }
                     echo "<p class=\"centerpara\">";
                     echo "<a title=\"$strseemoredetail\" href=\"report.php?action=questions&amp;id=$id&amp;qid=$question->multi\">";
                     survey_print_graph("id=$id&amp;qid=$question->id&amp;sid=$student&amp;type=studentmultiquestion.png");
                     echo "</a></p><br />";
                 }
             }
         }

         // Print non-scale questions

         foreach ($questionorder as $key => $val) {
             $question = $questions[$val];
             if ($question->type == 0 or $question->type == 1) {
                 if ($answer = survey_get_user_answer($survey->id, $question->id, $user->id)) {
                    $table = new html_table();
                     $table->head = array(get_string($question->text, "survey"));
                     $table->align = array ("left");
                     $table->data[] = array(s($answer->answer1)); // no html here, just plain text
                     echo html_writer::table($table);
                     echo $OUTPUT->spacer(array('height'=>30));
                 }
             }
         }

         if ($rs = survey_get_analysis($survey->id, $user->id)) {
            $notes = $rs->notes;
         } else {
            $notes = "";
         }
         echo "<hr noshade=\"noshade\" size=\"1\" />";
         echo "<div class='studentreport'>";
         echo "<form action=\"report.php\" method=\"post\">";
         echo "<h3>$strnotes:</h3>";
         echo "<blockquote>";
         echo "<textarea name=\"notes\" rows=\"10\" cols=\"60\">";
         p($notes);
         echo "</textarea><br />";
         echo "<input type=\"hidden\" name=\"action\" value=\"student\" />";
         echo "<input type=\"hidden\" name=\"sesskey\" value=\"".sesskey()."\" />";
         echo "<input type=\"hidden\" name=\"student\" value=\"$student\" />";
         echo "<input type=\"hidden\" name=\"id\" value=\"$cm->id\" />";
         echo "<input type=\"submit\" value=\"".get_string("savechanges")."\" />";
         echo "</blockquote>";
         echo "</form>";
         echo "</div>";


         break;

      case "download":
        echo $OUTPUT->heading($strdownload);

        require_capability('mod/survey:download', $context);

        echo '<p class="centerpara">'.get_string("downloadinfo", "survey").'</p>';

        echo $OUTPUT->container_start('reportbuttons');
        $options = array();
        $options["id"] = "$cm->id";
        $options["group"] = $currentgroup;

        $options["type"] = "ods";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadods"));

        $options["type"] = "xls";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadexcel"));

        $options["type"] = "txt";
        echo $OUTPUT->single_button(new moodle_url("download.php", $options), get_string("downloadtext"));
        echo $OUTPUT->container_end();

        break;

    }
    echo $OUTPUT->footer();


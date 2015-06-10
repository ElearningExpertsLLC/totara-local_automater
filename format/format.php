<?php

/**
 * Automater course aiken format
 * 
 * @copyright Copyright 2015 eLearningExperts
 * @license   http://www.gnu.org/licenses/gpl-3.0.txt GNU Public License 3.0
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Aiken format - a simple format for creating multiple choice questions (with
 * only one correct choice, and no feedback).
 */
class automater_qformat_aiken extends qformat_aiken {

    /**
     * @var int total question upload to question bank
     */
    public $totalquestions = 0;

    /** @return bool whether this plugin provides import functionality. */
    public function provide_import() {
        return true;
    }

    /**
     * Process the file
     * This method should not normally be overidden
     * 
     * @param object $category
     * @return bool success
     */
    public function importprocess($category) {
        global $USER, $CFG, $DB, $OUTPUT;

        // reset the timer in case file upload was slow
        core_php_time_limit::raise();

        // STAGE 1: Parse the file
        if (!$lines = $this->readdata($this->filename)) {
            return false;
        }

        if (!$questions = $this->readquestions($lines)) {   // Extract all the questions
            return false;
        }

        // STAGE 2: Write data to database
        // check for errors before we continue
        if ($this->stoponerror and ( $this->importerrors > 0)) {
            return true;
        }

        // get list of valid answer grades
        $gradeoptionsfull = question_bank::fraction_options_full();

        // check answer grades are valid
        // (now need to do this here because of 'stop on error': MDL-10689)
        $gradeerrors = 0;
        $goodquestions = array();
        foreach ($questions as $question) {
            if (!empty($question->fraction) and ( is_array($question->fraction))) {
                $fractions = $question->fraction;
                $invalidfractions = array();
                foreach ($fractions as $key => $fraction) {
                    $newfraction = match_grade_options($gradeoptionsfull, $fraction, $this->matchgrades);
                    if ($newfraction === false) {
                        $invalidfractions[] = $fraction;
                    } else {
                        $fractions[$key] = $newfraction;
                    }
                }
                if ($invalidfractions) {
                    ++$gradeerrors;
                    continue;
                } else {
                    $question->fraction = $fractions;
                }
            }
            $goodquestions[] = $question;
        }
        $questions = $goodquestions;

        // check for errors before we continue
        if ($this->stoponerror && $gradeerrors > 0) {
            return false;
        }

        // count number of questions processed
        $count = 0;

        foreach ($questions as $question) {   // Process and store each question
            $transaction = $DB->start_delegated_transaction();

            // reset the php timeout
            core_php_time_limit::raise();

            // check for category modifiers
            if ($question->qtype == 'category') {
                if ($this->catfromfile) {
                    // find/create category object
                    $catpath = $question->category;
                    $newcategory = $this->create_category_path($catpath);
                    if (!empty($newcategory)) {
                        $this->category = $newcategory;
                    }
                }
                $transaction->allow_commit();
                continue;
            }
            $question->context = $this->importcontext;

            $count++;

            $question->category = $this->category->id;
            $question->stamp = make_unique_id_code();  // Set the unique code (not to be changed)

            $question->createdby = $USER->id;
            $question->timecreated = time();
            $question->modifiedby = $USER->id;
            $question->timemodified = time();
            $fileoptions = array(
                'subdirs' => true,
                'maxfiles' => -1,
                'maxbytes' => 0,
            );

            $question->id = $DB->insert_record('question', $question);

            if (isset($question->questiontextitemid)) {
                $question->questiontext = file_save_draft_area_files($question->questiontextitemid, $this->importcontext->id, 'question', 'questiontext', $question->id, $fileoptions, $question->questiontext);
            } else if (isset($question->questiontextfiles)) {
                foreach ($question->questiontextfiles as $file) {
                    question_bank::get_qtype($question->qtype)->import_file(
                            $this->importcontext, 'question', 'questiontext', $question->id, $file);
                }
            }
            if (isset($question->generalfeedbackitemid)) {
                $question->generalfeedback = file_save_draft_area_files($question->generalfeedbackitemid, $this->importcontext->id, 'question', 'generalfeedback', $question->id, $fileoptions, $question->generalfeedback);
            } else if (isset($question->generalfeedbackfiles)) {
                foreach ($question->generalfeedbackfiles as $file) {
                    question_bank::get_qtype($question->qtype)->import_file(
                            $this->importcontext, 'question', 'generalfeedback', $question->id, $file);
                }
            }
            $DB->update_record('question', $question);

            $this->questionids[] = $question->id;

            // Now to save all the answers and type-specific options

            $result = question_bank::get_qtype($question->qtype)->save_question_options($question);

            if (!empty($CFG->usetags) && isset($question->tags)) {
                require_once($CFG->dirroot . '/tag/lib.php');
                tag_set('question', $question->id, $question->tags, 'core_question', $question->context->id);
            }

            if (!empty($result->error)) {
                echo $OUTPUT->notification($result->error);
                // Can't use $transaction->rollback(); since it requires an exception,
                // and I don't want to rewrite this code to change the error handling now.
                $DB->force_transaction_rollback();
                return false;
            }

            $transaction->allow_commit();

            // Give the question a unique version stamp determined by question_hash()
            $DB->set_field('question', 'version', question_hash($question), array('id' => $question->id));
        }
        $this->setTotalQuestions($count);

        return true;
    }

    /**
     * Set number of questions uploaded
     * 
     * @param type $count
     */
    public function setTotalQuestions($count) {
        $this->totalquestions = $count;
    }

    /**
     * Provide number of questions uploaded
     * 
     * @return type
     */
    public function getTotalQuestions() {
        return $this->totalquestions;
    }

}

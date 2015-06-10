<?php

/**
 * Automater course
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

namespace local_automater;

defined('MOODLE_INTERNAL') || die();

/**
 * This is the course automater restore user interface class * 
 */
class autorestore_ui implements \file_progress {

    protected $contextid;
    protected $category;
    protected $fullname = null;
    protected $shortname = null;
    protected $externalurl = null;
    protected $mform = null;
    protected $format = null;
    protected $courseid = null;
    protected $filename = null;
    protected $filepath = null;

    /**
     * @var string Content hash of archive file to restore (if specified by hash)
     */
    protected $contenthash = null;

    /**
     * @var string Pathname hash of stored_file object to restore
     */
    protected $pathnamehash = null;
    protected $details;

    /**
     * @var bool True if we have started reporting progress
     */
    protected $startedprogress = false;

    /**
     * @var \core\progress\base Optional progress reporter
     */
    private $progressreporter;

    /**
     * Automater course restore constructor
     * 
     * @param \moodleform $mform
     * @param object $data
     * @param object $file
     */
    public function __construct(\moodleform $mform, $data, $file) {
        $this->contextid = $data->contextid;
        $this->fullname = $data->fullname;
        $this->shortname = $data->shortname;
        $this->category = $data->category;
        $this->externalurl = $data->externalurl;
        $this->format = $data->format;
        $this->mform = $mform;

        // Identify file object by its pathname hash.
        $this->pathnamehash = $file->get_pathnamehash();

        // The file content hash is also passed for security; users
        // cannot guess the content hash (unless they know the file contents),
        // so this guarantees that either the system generated this link or
        // else the user has access to the restore archive anyhow.
        $this->contenthash = $file->get_contenthash();
    }

    /**
     * Implementation for backup file_progress and restore process     
     * 
     * @global object $DB
     * @global object $USER
     * @return int course id
     * @throws restore_ui_exception
     */
    public function process() {

        global $DB, $USER;
        
        $transaction = $DB->start_delegated_transaction();
        try {
            
            $fs = get_file_storage();
            $storedfile = $fs->get_file_by_hash($this->pathnamehash);
            if (!$storedfile || $storedfile->get_contenthash() !== $this->contenthash) {
                throw new \restore_ui_exception('invalidrestorefile');
            }
            $this->extract_file_to_dir($storedfile);

            // Create the course with default course restoring name
            list($fullname, $shortname) = \restore_dbops::calculate_course_names(0, get_string('restoringcourse', 'backup'), get_string('restoringcourseshortname', 'backup'));
            $this->courseid = \restore_dbops::create_new_course($fullname, $shortname, $this->category);

            echo \html_writer::start_div('', array('id' => 'executionprogress'));
            // Restore process controller 
            $rc = new \restore_controller($this->get_filepath(), $this->courseid, \backup::INTERACTIVE_NO, \backup::MODE_GENERAL, $USER->id, \backup::TARGET_NEW_COURSE);

            // process course settings only for activities
            $this->process_tasks($rc);

            $rc->get_logger()->set_next(new \output_indented_logger(\backup::LOG_INFO, false, true));
            $rc->execute_precheck();
            $rc->set_progress(new \core\progress\display());
            // executes the restore plan
            $rc->execute_plan();
            echo \html_writer::end_div();
            echo \html_writer::script('document.getElementById("executionprogress").style.display = "none";');

            // Modify the url, quiz and label activities after course restore
            $this->automater_course_process($this->courseid);
        } catch (Exception $e) {
            $this->cleanup();
            throw $e;
        }
        $transaction->allow_commit();

        return $this->courseid;
    }

    /**
     * Set the settings for only restore course activites and 
     * disable all the other setings like(user, blocaks etc.)
     * 
     * @param \restore_controller $rc
     * @return int
     */
    protected function process_tasks(\restore_controller $rc) {
        //Get the restore plan and tasks
        $plan = $rc->get_plan();
        $tasks = $plan->get_tasks();

        $settingsdata = new \stdClass();

        // Set the settings object of course according to all the backup course tasks
        foreach ($tasks as &$task) {
            // Get all settings into a var so we can iterate by reference
            $settings = $task->get_settings();
            foreach ($settings as &$setting) {
                $name = $setting->get_ui_name();
                $settingsdata->$name = $setting->get_value();
            }
        }

        // Overwrite the settings object of backup course elements and 
        // disable all settings other than activites            
        $settingsdata->setting_root_users = 0;
        $settingsdata->setting_root_enrol_migratetomanual = 0;
        $settingsdata->setting_root_role_assignments = 0;
        $settingsdata->setting_root_activities = 1;
        $settingsdata->setting_root_blocks = 0;
        $settingsdata->setting_root_filters = 0;
        $settingsdata->setting_root_comments = 0;
        $settingsdata->setting_root_badges = 0;
        $settingsdata->setting_root_calendarevents = 0;
        $settingsdata->setting_root_userscompletion = 0;
        $settingsdata->setting_root_logs = 0;
        $settingsdata->setting_root_grade_histories = 0;
        $settingsdata->setting_course_course_fullname = $this->fullname;
        $settingsdata->setting_course_course_shortname = $this->shortname;
        $settingsdata->setting_course_course_startdate = time();
        $settingsdata->setting_course_keep_roles_and_enrolments = 0;
        $settingsdata->setting_course_keep_groups_and_groupings = 0;
        $settingsdata->setting_course_overwrite_conf = 1;

        // Change the restore task according to the required settings object
        $changes = 0;
        foreach ($tasks as &$task) {
            // Get all settings into a var so we can iterate by reference
            $settings = $task->get_settings();

            foreach ($settings as &$setting) {
                $name = $setting->get_ui_name();
                if (isset($settingsdata->$name) && $settingsdata->$name != $setting->get_value()) {
                    $setting->set_value($settingsdata->$name);
                    $changes++;
                } else if (!isset($settingsdata->$name) && $setting->get_ui_type() == \backup_setting::UI_HTML_CHECKBOX && $setting->get_value()) {
                    $setting->set_value(0);
                    $changes++;
                }
            }
        }

        // Enforces dependencies on all settings. Call before save
        foreach ($tasks as &$task) {
            // Store as a var so we can iterate by reference
            $settings = $task->get_settings();
            foreach ($settings as &$setting) {
                // Get all dependencies for iteration by reference
                $dependencies = $setting->get_dependencies();
                foreach ($dependencies as &$dependency) {
                    // Enforce each dependency
                    if ($dependency->enforce()) {
                        $changes++;
                    }
                }
            }
        }

        // Return the number of changes the user made
        return $changes;
    }

    /**
     * Modify the course activities 
     * 
     * @global object $DB
     * @param int $courseid
     * @return boolean
     */
    protected function automater_course_process($courseid) {
        global $DB;

        $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

        if ($course->format != 'topics') {
            $DB->set_field('course', 'format', 'topics', array('id' => $courseid));
        }

        $section = \get_fast_modinfo($course)->get_instances();
        $initialdata = convert_to_array($section);

        $this->automater_check_availability($initialdata);
        $this->modfiy_url_activity($initialdata['url']);
        $this->modfiy_quiz_activity($initialdata['quiz'], $course);
        $this->modfiy_label_activity($initialdata['label'], $initialdata['quiz']);
        rebuild_course_cache($course->id, true);

        return true;
    }

    /**
     * Check the url, quiz and label activties are present in first section or not 
     * 
     * @param array $data
     * @return boolean
     * @throws moodle_exception
     */
    protected function automater_check_availability($data) {
        $othernum = 0;
        $quiznum = 0;
        $urlnum = 0;
        $labelnum = 0;
        foreach ($data as $mod) {
            foreach ($mod as $moddata) {
                if ($moddata['sectionnum'] == 1) {
                    switch ($moddata['modname']) {
                        case 'quiz':
                            $quiznum++;
                            break;
                        case 'url':
                            $urlnum++;
                            break;
                        case 'label':
                            $labelnum++;
                            break;
                        default :
                            $othernum++;
                    }
                }
            }
        }

        if ($quiznum != 1 || $urlnum != 1 || $labelnum != 2 || $othernum > 0) {
            $this->cleanup();
            throw new \moodle_exception('invalidbackupcourse', 'local_automater');
        } else {
            return true;
        }
    }

    /**
     * Update the url activity name and url
     * 
     * @global object $DB
     * @param array $data
     * @return boolean
     */
    protected function modfiy_url_activity($data) {
        global $DB;

        $url = new \stdClass();
        $url->id = key($data);
        $url->name = $this->fullname;
        $url->display = RESOURCELIB_DISPLAY_NEW;
        $url->externalurl = \url_fix_submitted_url($this->externalurl);
        $url->timemodified = time();

        $cmdata = new \stdClass();
        $cmdata->id = $data[$url->id]['id'];
        $cmdata->completion = 0;
        // update course url module 
        $DB->update_record('course_modules', $cmdata);
        // update url activtiy
        $DB->update_record('url', $url);

        return true;
    }

    /**
     * Update the url activity name and grade info. Delete question from question for 
     * current course and activty categories
     * 
     * @global object $DB
     * @param array $data
     * @param object $course
     * @return boolean
     */
    protected function modfiy_quiz_activity($data, $course) {
        global $DB;

        $quiz = new \stdClass();
        $quiz->id = key($data);

        // Delete all quiz activity questions 
        $quizquesions = $DB->get_records('quiz_slots', array('quizid' => $quiz->id), null, 'questionid');
        $DB->delete_records('quiz_slots', array('quizid' => $quiz->id));

        // Delete all questions from question bank for quiz activity category related to current module
        $modcontext = \context_module::instance($data[$quiz->id]['id']);
        if ($categoriesmods = $DB->get_records('question_categories', array('contextid' => $modcontext->id), 'parent', 'id, parent, name, contextid')) {
            //Sort categories following their tree (parent-child) relationships
            //this will make the feedback more readable
            $categoriesmods = \sort_categories_by_tree($categoriesmods);

            foreach ($categoriesmods as $category) {

                //Delete it completely (questions itself)
                //deleting questions
                if ($questions = $DB->get_records('question', array('category' => $category->id), '', 'id,qtype')) {
                    foreach ($questions as $question) {
                        \question_delete_question($question->id);
                    }
                    $DB->delete_records("question", array("category" => $category->id));
                }
            }
        }

        // Delete all questions from question bank for quiz activity category related to current module
        $coursecontext = \context_course::instance($course->id);
        $categoriescourse = $DB->get_records('question_categories', array('contextid' => $coursecontext->id), 'parent', 'id, parent, name, contextid');
        if ($categoriescourse) {
            //Sort categories following their tree (parent-child) relationships
            //this will make the feedback more readable
            $categoriescourse = \sort_categories_by_tree($categoriescourse);

            foreach ($categoriescourse as $category) {

                //Delete it completely (questions itself)
                //deleting questions
                if ($questions = $DB->get_records('question', array('category' => $category->id), '', 'id,qtype')) {
                    foreach ($questions as $question) {
                        \question_delete_question($question->id);
                    }
                    $DB->delete_records("question", array("category" => $category->id));
                }
            }
        }

        // Delete the extra question records like random questions
        foreach ($quizquesions as $quizq) {
            if ($DB->record_exists('question', array('id' => $quizq->questionid))) {
                \question_delete_question($quizq->questionid);
            }
        }

        $quizmodule = $DB->get_record('quiz', array('id' => $data[$quiz->id]['instance']));
        $quizmodule->instance = $quizmodule->id;
        $quizmodule->cmid = $data[$quiz->id]['id'];
        $qcount = $this->upload_quizfile($course, $quizmodule);
        \quiz_set_grade($qcount, $quizmodule);

        $quizgradeitem = \grade_item::fetch(array('itemtype' => 'mod', 'itemmodule' => $data[$quiz->id]['modname'], 'iteminstance' => $data[$quiz->id]['instance'],
                    'courseid' => $data[$quiz->id]['course'], 'itemnumber' => 0));
        $grade_item = new \grade_item(array('id' => $quizgradeitem->id));
        \grade_object::set_properties($grade_item, array('gradepass' => $qcount));
        $grade_item->outcomeid = null;
        $grade_item->update();

        $quiz->name = get_string('quizname', 'local_automater', $this->fullname);
        $quiz->timemodified = time();

        $cmdata = new \stdClass();
        $cmdata->id = $data[$quiz->id]['id'];
        $cmdata->completion = 2;
        $cmdata->completiongradeitemnumber = 0;
        // update course module
        $DB->update_record('course_modules', $cmdata);
        $DB->update_record('quiz', $quiz);

        $this->course_completion_criteria($cmdata->id, $course->id);

        return true;
    }

    /**
     * Upload and add the questions to the question bank and the current activity
     * 
     * @global object $CFG
     * @global object $DB
     * @param object $course
     * @param object $quiz
     * @return int
     * @throws \moodle_exception
     */
    public function upload_quizfile($course, $quiz) {
        global $CFG, $DB;

        // work out if this is an uploaded file
        // or one from the filesarea.
        $realfilename = $this->mform->get_new_filename('newfile');

        $importfile = "{$CFG->tempdir}/questionimport/{$realfilename}";
        make_temp_directory('questionimport');
        if (!$result = $this->mform->save_file('newfile', $importfile, true)) {
            $this->cleanup();
            throw new \moodle_exception('uploadproblem');
        }
        $formatfile = $CFG->dirroot . '/question/format/' . $this->format . '/format.php';
        $newformatfile = $CFG->dirroot . '/local/automater/format/format.php';

        if (!is_readable($formatfile)) {
            $this->cleanup();
            throw new \moodle_exception('formatnotfound', 'question', '', $this->format);
        }

        $thiscontext = \context_course::instance($course->id);
        $contexts = new \question_edit_contexts($thiscontext);
        $defaultcategory = \question_make_default_categories($contexts->all());
        if (!$category = $DB->get_record("question_categories", array('id' => $defaultcategory->id))) {
            $this->cleanup();
            \print_error('nocategory', 'question');
        }

        $categorycontext = \context::instance_by_id($category->contextid);
        $category->context = $categorycontext;

        require_once($formatfile);
        require_once($newformatfile);

        $classname = 'automater_qformat_' . $this->format;
        $qformat = new $classname();

        // load data into class
        $qformat->setCategory($category);
        $qformat->setContexts($contexts->having_one_edit_tab_cap('import'));
        $qformat->setCourse($course);
        $qformat->setFilename($importfile);
        $qformat->setRealfilename($realfilename);
        $qformat->setMatchgrades('error');
        $qformat->setCatfromfile(1);
        $qformat->setContextfromfile(1);
        $qformat->setStoponerror(1);

        // Do anything before that we need to
        if (!$qformat->importpreprocess()) {
            print_error('cannotimport', '', '');
        }

        // Process the uploaded file
        if (!$qformat->importprocess($category)) {
            print_error('cannotimport', '', '');
        }

        // In case anything needs to be done after
        if (!$qformat->importpostprocess()) {
            print_error('cannotimport', '', '');
        }

        foreach ($qformat->questionids as $value) { // Parse input for question ids.
            \quiz_require_question_use($value);
            \quiz_add_quiz_question($value, $quiz);
        }
        
        $counts = $qformat->getTotalQuestions();

        return $counts;
    }

    /**
     * Update course completion criteria
     * 
     * @global object $CFG
     * @param int $cmid
     * @param int $courseid
     * @return boolean
     */
    protected function course_completion_criteria($cmid, $courseid) {
        global $CFG;

        $completion = new \stdClass();
        $completion->criteria_activity_value[$cmid] = 1;
        $completion->id = $courseid;
        $completion->overall_aggregation = 1;
        $completion->activity_aggregation = 1;

        require_once($CFG->dirroot . '/completion/criteria/completion_criteria_activity.php');

        $class = 'completion_criteria_activity';
        $criterion = new $class();
        $criterion->update_config($completion);

        $aggdata = array(
            'course' => $completion->id,
            'criteriatype' => null
        );
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod($completion->overall_aggregation);
        $aggregation->save();

        // Handle activity aggregation.
        if (empty($completion->activity_aggregation)) {
            $completion->activity_aggregation = 0;
        }

        $aggdata['criteriatype'] = COMPLETION_CRITERIA_TYPE_ACTIVITY;
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod($completion->activity_aggregation);
        $aggregation->save();

        // Handle course aggregation.
        if (empty($completion->course_aggregation)) {
            $completion->course_aggregation = 0;
        }

        $aggdata['criteriatype'] = COMPLETION_CRITERIA_TYPE_COURSE;
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod($completion->course_aggregation);
        $aggregation->save();

        // Handle role aggregation.
        if (empty($completion->role_aggregation)) {
            $completion->role_aggregation = 0;
        }

        $aggdata['criteriatype'] = COMPLETION_CRITERIA_TYPE_ROLE;
        $aggregation = new \completion_aggregation($aggdata);
        $aggregation->setMethod($completion->role_aggregation);
        $aggregation->save();

        return true;
    }

    /**
     * Update the restored label activities access settings
     * 
     * @global object $DB
     * @param array $data
     * @param array $quizdata
     * @return boolean
     */
    protected function modfiy_label_activity($data, $quizdata) {
        global $DB;

        $urllabel = current($data);
        $urlcm = new \stdClass();
        $urlcm->id = $urllabel['id'];
        $urlcm->completion = 0;
        // update course url label module 
        $DB->update_record('course_modules', $urlcm);

        $quizlabel = next($data);
        $accessstructure = array('type' => 'completion',
            'cm' => (int) $quizdata[key($quizdata)]['id'],
            'e' => COMPLETION_COMPLETE_PASS);
        $availability = '{"op":"&","c":[' . json_encode($accessstructure) . '],"showc":[false]}';

        $quizcm = new \stdClass();
        $quizcm->id = $quizlabel['id'];
        $quizcm->completion = 0;
        $quizcm->availability = $availability;
        // update course quiz label module
        $DB->update_record('course_modules', $quizcm);

        return true;
    }

    /**
     * Extracts the file.
     *
     * @param string|stored_file $source Archive file to extract
     */
    protected function extract_file_to_dir($source) {
        global $CFG, $USER;

        $this->filepath = \restore_controller::get_tempdir_name($this->contextid, $USER->id);

        $fb = get_file_packer('application/vnd.moodle.backup');
        $result = $fb->extract_to_pathname($source, $CFG->tempdir . '/backup/' . $this->filepath . '/', null, $this);

        // If any progress happened, end it.
        if ($this->startedprogress) {
            $this->get_progress_reporter()->end_progress();
        }

        return $result;
    }
    
    /**
     * Delete course which is created by restore process
     */
    public function cleanup() {
        $courseid = $this->get_course_id();
        \delete_course($courseid, false);
    }

    /**
     * Implementation for file_progress interface to display unzip progress.
     *
     * @param int $progress Current progress
     * @param int $max Max value
     */
    public function progress($progress = self::INDETERMINATE, $max = self::INDETERMINATE) {
        $reporter = $this->get_progress_reporter();

        // Start tracking progress if necessary.
        if (!$this->startedprogress) {
            $reporter->start_progress('extract_file_to_dir', ($max == \file_progress::INDETERMINATE) ? \core\progress\base::INDETERMINATE : $max);
            $this->startedprogress = true;
        }

        // Pass progress through to whatever handles it.
        $reporter->progress(
                ($progress == \file_progress::INDETERMINATE) ? \core\progress\base::INDETERMINATE : $progress);
    }

    /**
     * Gets the progress reporter object in use for restore UI stage.
     *
     * @return \core\progress\null
     */
    public function get_progress_reporter() {
        if (!$this->progressreporter) {
            $this->progressreporter = new \core\progress\null();
        }
        return $this->progressreporter;
    }

    /**
     * Gets the restore course file path
     * 
     * @return type
     */
    public function get_filepath() {
        return $this->filepath;
    }

    /**
     * Gets current course id
     * 
     * @return type
     */
    public function get_course_id() {
        return $this->courseid;
    }

    /**
     * Gets the current course category
     * 
     * @return type
     */
    public function get_category_id() {
        return $this->category;
    }

}

<?php
    global $CFG;

    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->libdir.'/formslib.php');
    require_once($CFG->libdir.'/filelib.php');
    require_once($CFG->dirroot.'/mod/quiz/lib.php');
    require_once(__DIR__ . '/quizHelper.php');
    
    /*Form definition*/
    class xmluploadingform extends moodleform {
        protected $messages = array();

        public function definition() {
            global $PAGE;
            $mform = $this->_form;

            // Add xml upload field
            $mform->addElement(
                'filepicker', 
                'xml_file',  
                "Upload xml with quizes definitions",
                null,
                array('maxbytes' => '100000','accepted_types' => '.xml')
            );

            $mform->addRule('xml_file', 'This field is required', 'required', null, 'client');

            $mform->addElement('hidden', 'courseid', 'course id');
            $mform->setType('courseid', PARAM_INT);
            $mform->setDefault('courseid', $PAGE->course->id);

            $mform->addElement('hidden', 'contextid', 'context id');
            $mform->setType('contextid', PARAM_INT);
            $mform->setDefault('contextid', $PAGE->context->id);

            $this->add_action_buttons(false, "Create");
        }

        function displayErrors($form) {
            $errors = $this->validation($form->get_data(), array());

            $errorsHtml = '';
            if (!empty($errors)) {
                $errorsHtml .= html_writer::div('XML file submition errors', 'alert alert-error');
                $errorsHtml .= html_writer::start_tag('ul');
                foreach ($errors as $fieldname => $errormessage) {
                    $errorsHtml .= html_writer::tag('li', $errormessage);
                }
                $errorsHtml .= html_writer::end_tag('ul');
            }

            return $errorsHtml;
        }

        // Parse XML
        function parse($xml) {
            $quizes = array();
    
            foreach ($xml->children() as $quizElement)
            {
                $quiz = new stdClass();
                $quiz->title = (string)$quizElement->title[0];
                
                // Get exercises
                $quiz->exercises = new stdClass();
                $quiz->exercises->questionCategory = (string)$quizElement->exercises->questionCategory[0];
                
                $quiz->exercises->withSubname = array();
                foreach($quizElement->exercises->withSubname as $exercise) 
                {
                    $withSubname = new stdClass();
                    $withSubname->count = $exercise->count;
                    $withSubname->subname = $exercise->subname;
                    array_push($quiz->exercises->withSubname, $withSubname);
                }
                
                $quiz->exercises->withTag = array();
                foreach($quizElement->exercises->withTag as $exercise) {
                    $withTag = new stdClass();
                    $withTag->count = $exercise->count;
                    $withTag->tag = $exercise->tag;
                    array_push($quiz->exercises->withTag, $withTag);
                }

                // Get users
                $quiz->usersSeparator = (string)$quizElement->quizUsers['separator'];
                $quiz->quizUsers = array();
                foreach($quizElement->quizUsers->quizUser as $quizUser)
                {
                    $user = new stdClass();
                    $user->filter = (string)$quizUser->searchField[0];
                    $user->condition = (string)$quizUser->condition[0];
                    $user->name = (string)$quizUser->name[0];
                    array_push($quiz->quizUsers, $user);
                }

                // Get settings
                $settings = $quizElement->settings;
                $quiz->shuffleanswers = ((string)$settings->shuffle[0] == "true") ? 1 : 0;
                $quiz->attempts = ((string)$settings->attempts[0] == "unlimited") ? 0 : (string)$settings->attempts[0];
                $quiz->grade = (string)$settings->passgrade[0];
                array_push($quizes, $quiz);
            }
    
            return $quizes;
        }
    }
?>
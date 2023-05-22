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

                // Get variants
                $quiz->variants = array();
                foreach ($quizElement->quizVariant as $variant) {
                    // Get users
                    $newVariant = new stdClass();
                    $newVariant->usersSeparator = (string)$variant['usersSeparator'];
                    $newVariant->users = array();
                    foreach($variant->user as $user)
                    {
                        $newUser = new stdClass();
                        $newUser->filter = (string)$user->searchField[0];
                        $newUser->condition = (string)$user->condition[0];
                        $newUser->name = (string)$user->name[0];
                        array_push($newVariant->users, $newUser);
                    }

                    array_push($quiz->variants, $newVariant);
                }

                $quiz->questionCategory = (string)$quizElement->exercises->questionCategory[0];
                
                // Get exercises
                $quiz->exercises = array();
                foreach($quizElement->exercises->withSubname as $exercise) {
                    $newExercise = new stdClass();
                    $newExercise->isWithSubname = true;
                    $newExercise->count = (int)$exercise->count;
                    $newExercise->subname = (string)$exercise->subname[0];
                    array_push($quiz->exercises, $newExercise);
                }

                foreach($quizElement->exercises->withTag as $exercise) 
                {
                    $newExercise = new stdClass();
                    $newExercise->count = $exercise->count;
                    $newExercise->isWithSubname = false;
                    $newExercise->count = (int)$exercise->count;
                    $newExercise->tag = (string)$exercise->tag[0];
                    array_push($quiz->exercises, $newExercise);
                }

                // Get settings
                $settings = $quizElement->settings;
                $quiz->shufflequestions = ((string)$settings->shuffle[0] == "true") ? 1 : 0;
                $quiz->attempts = ((string)$settings->attempts[0] == "unlimited") ? 0 : (string)$settings->attempts[0];
                $quiz->grade = (string)$settings->passgrade[0];
                array_push($quizes, $quiz);
            }
    
            return $quizes;
        }
    }
?>
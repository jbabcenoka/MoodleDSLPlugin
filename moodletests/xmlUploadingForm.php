<?php
    global $CFG;

    require_once(__DIR__ . '/../../config.php');
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->libdir.'/formslib.php');
    require_once($CFG->libdir.'/filelib.php');
    require_once($CFG->dirroot.'/mod/quiz/lib.php');
    
    /*Form definition*/
    class xml_uploading_form extends moodleform {
        protected $messages = array();

        //Adding elements to form
        public function definition() {
            global $CFG;
            global $USER;
           
            $mform = $this->_form;
            $courses = enrol_get_users_courses($USER->id, true);

            foreach ($courses as $course) {
                $coursesOptions[$course->id] = $course->fullname;
            }

            $mform->addElement('select', 'courseid', 'Select a course', $coursesOptions);
            $mform->addRule('courseid', 'This field is required', 'required', null, 'client');

            // Add xml element to form.
            $mform->addElement(
                'filepicker', 
                'xml_file',  
                "Upload xml with quizes definitions",
                array('maxbytes' => '100000','accepted_types' => '.xml'),
                array('showremovebutton' => true)
            );

            // Set type of element
            $mform->setType('xml_file', PARAM_FILE);
            
            // Set file uploading to moodle form
            $mform->addRule('xml_file', 'This field is required', 'required', null, 'client');

            $this->add_action_buttons(false, 'Upload file and create quizes');
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
    }
?>
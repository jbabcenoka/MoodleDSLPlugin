<?php

use core\output\notification;

    require_once(__DIR__ . '/xmluploadingform.php');
    require_once('../../config.php');
    require_once('lib.php');
    
    global $DB, $PAGE, $CFG;

    $courseId = required_param('courseid', PARAM_INT);
    if (!$course = $DB->get_record('course', array('id'=>$courseId))) {
        throw new \moodle_exception('invalidcourseid');
    }

    $contextId = required_param('contextid', PARAM_INT);
    list($context, $course, $cm) = get_context_info_array($contextId);
    require_login($course, false, $cm);

    $PAGE->set_context($context);
    $PAGE->set_url('/local/moodletests/view.php', array(
        'courseid' => $courseId,
        'contextid' => $contextId
    ));
    $PAGE->set_heading($course->fullname);

    //Create new instance of the new form class
    $form = new xmluploadingform();

    $redirect = $CFG->wwwroot . '/local/moodletests/view.php?courseid=' . $courseId . '&contextid=' . $contextId;
    
    //Process form
    if ($data = $form->get_data()) {
        
        $xmlfile = $form->get_file_content('xml_file');
        $xml = simplexml_load_string($xmlfile);

        //Try to parse XML file
        try
        {
            $quizes = $form->parse($xml);
            if (empty($quizes)) {
                $msg = 'XML file does not contain any quizes. Try to generate XML file again.';
                redirect($redirect, $msg, null, notification::NOTIFY_ERROR);
            }
        }
        catch (Exception $e)
        {
            $msg = 'Error occured during XML parsing. Try to generate XML again.';
            redirect($redirect, $msg, null, notification::NOTIFY_ERROR);
        }
        
        // Try to create quizes
        try
        {
            create_quizes($quizes, $data->courseid, $context, $DB);
        }
        catch (Exception $e)
        {
            $msg = !empty($e->getMessage()) 
                ? $e->getMessage() 
                : 'Error occured during quizes creation. Try to generate XML again.';
            redirect($redirect, $msg, null, notification::NOTIFY_ERROR);
        }
        
        $msg = 'Quizes created successfully.';
        redirect($redirect, $msg, null, notification::NOTIFY_SUCCESS);
    }
    
    //Form display
    echo $OUTPUT->header();
    echo $OUTPUT->heading('XML uploading form');
    $form->displayErrors($form);
    $form->display();
    echo $OUTPUT->footer();
?>
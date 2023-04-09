<?php
    require_once(__DIR__ . '/xmlUploadingForm.php');
    require_once(__DIR__ . '/xmlParsingHelper.php');

    global $DB;

    //Create new instance of the new form class
    $form = new xml_uploading_form();
    
    //Form display
    echo $OUTPUT->header();
    echo $OUTPUT->heading('XML uploading form');
    
    //Process form
    if ($data = $form->get_data()) {
        echo html_writer::div('XML file submitted', 'alert alert-success');
        
        $xmlfile = $form->get_file_content('xml_file');
        $xml = simplexml_load_string($xmlfile);
        $courseId = $data->courseid;
        
        $parsedQuizes = parseXML($xml);
        if (!empty($parsedQuizes)) {
            createQuizes($parsedQuizes, $courseId, $DB);
        }
        
        print_r("Quizes creation done");
    }
    else 
    {
        $form->displayErrors($form);
    }

    $form->display();
    echo $OUTPUT->footer();
?>
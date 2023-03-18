<?php
    require_once(__DIR__ . '/xmlUploadingForm.php');

    //Create new instance of the new form class
    $form = new xml_uploading_form();

    // Form display
    echo $OUTPUT->header();
    echo $OUTPUT->heading('XML uploading form');

    //Process form
    if ($data = $form->get_data() && $form->isValid()) { 
        echo html_writer::div('XML file submitted', 'alert alert-success');
    }
    else {
        $form->displayErrors();
    }

    $form->display();
    echo $OUTPUT->footer();
?>
<?php
    require_once(__DIR__ . '/../../config.php');
    require_login();

    $courseid = required_param('courseid', PARAM_INT);

    if (!$course = $DB->get_record('course', array('id'=> $id))) {
        echo 'Course ID is incorrect';
    }

    $courseid = required_param('courseid', PARAM_INT);

    $PAGE->set_context(context_course::instance($courseid));

    $PAGE->set_title('Quiz plugin');
    $PAGE->set_heading('Plugin for quiz creation');

    echo $OUTPUT->header();

    echo "<h2>Welcome to new plugin!</h2>";
    echo "<p>This plugin allows you to upload a file and crete new quizes.</p>";
    echo "<p><a href='upload.php?courseid=".$courseid.">Click here to upload a file</a></p>";

    echo $OUTPUT->footer();

?>
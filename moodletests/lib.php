<?php
    function local_moodletests_extend_navigation_course($navigation, $course, $context) {
        if (has_capability('moodle/course:update', $context)) {
            $navigation->add(
                'Quizes plugin',
                new moodle_url('/local/moodletests/view.php', array('courseid'=>$course->id, 'contextid'=>$context->id)),
                navigation_node::TYPE_SETTING,
                null,
                null, 
                new pix_icon('i/settings' , '')
            );
        }
    }
?>
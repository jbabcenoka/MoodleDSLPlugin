<?php

require '../config.php';

function get_all_questions() {
    global $DB;
    $questions = $DB->get_records('question');
    return $questions;
    /*
    foreach ($questions as $question) {
        print_object($question);
    }*/
}


$questions = get_all_questions();
foreach ($questions as $question) { echo "Exercise: " . $question->name . "<br>"; }

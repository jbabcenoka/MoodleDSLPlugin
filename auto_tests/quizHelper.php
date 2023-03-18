<?php
    /*
        Creates new quiz
    */
    function createQuiz($name) {
        $quiz = new stdClass();
        $quiz->name = (string)$name;

        return $quiz;
    }

    /*
        Findes exercises that has specific substring in name
    */
    function findExerciseBySubname($subname, $count, $courseId, $DB) {
        $category = $DB->get_record('question_categories', array('id' => 4));
        $questions = get_questions_category($category, true);

        $questions = $DB->get_record('question_categories', array('questioncategoryid' => 4));
        $res_questions = array();
        $res_count = 0; 

        foreach ($questions as $questionId) {
            if ($res_count <= $count)
                return $res_questions;

            $retrieved_question = question_bank::load_question_data($questionId);

            //add questions with similar subname
            if (strpos($subname, $retrieved_question->name) != false) {
                array_push($res_questions, $retrieved_question);
                $res_count++;
            }
        }

        return $res_questions;
    }
    
    /*
        Adds exercise to quiz
    */
    function addExerciseToQuiz($exerciseId, $quizId, $DB) {
        $exercise = new stdClass();
        $exercise->quiz = $quizId;
        $exercise->question = $exerciseId;
        $DB->insert_record('quiz_question_instances', $exercise);
    }

    /*
        Save quiz to database
    */
    function addQuizToDB($quiz, $DB) {
        return $DB->insert_record('quiz', $quiz);
    }

?>
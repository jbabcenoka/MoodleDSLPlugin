<?php
    require_once($CFG->dirroot.'/lib/questionlib.php');
    require_once($CFG->dirroot.'/course/lib.php');

    function createCourseModule($courseId, $moduleId, $sectionId, $DB) {
        $cm = new stdClass();
        $cm->name = 'New exams module';
        $cm->course = $courseId;
        $cm->module = $moduleId;
        $cm->section = $sectionId;
        $cm->idnumber = null;
        $cm->added = time();
        $cm->id	= $DB->insert_record('course_modules', $cm);
        course_add_cm_to_section($courseId, $cm->id, $sectionId);
        return $cm;
    }

    function addQuizToModule($quiz, $coursemoduleid) {
        $$quiz->coursemodule = $coursemoduleid;
        $quiz->quizpassword = "";
        $quiz->intro = "";
        $quiz->timeopen = 0;
        $quiz->timeclose = 0;
        $quiz->timelimit = 0;
        $quiz->introformat = 1; //HTML tags
        $quiz->grade = 10;
        $quiz->questiondecimalpoints = -1;
        $quiz->decimalpoints = 2;
        $quiz->questionsperpage = 1;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quizId = quiz_add_instance($quiz);

        return $quizId;
    }

    function createQuizes($parsedQuizes, $courseId, $DB) {
        //get quiz module
        $module = $DB->get_record("modules", array("name" => "quiz"), '*', MUST_EXIST);
        if (!$module) {
            return null;
        }

        //get course first section. if doesn`t exist - create new 
        $section = $DB->get_record("course_sections", array("course" => $courseId, "section" => 0),"*", IGNORE_MULTIPLE);
        if (!$section) {
            $section = course_create_section($courseId, 0);
        }

        foreach($parsedQuizes as $quiz) {
            $courseQuestionBankQuestions = getCourseQuestions($courseId, $DB);
            
            $questionIds = getQuestionsBySubname($courseQuestionBankQuestions, "version", 3);

            //create course module and quiz to database
            $cm = createCourseModule($courseId, $module->id, $section->id, $DB);
            $quizId = addQuizToModule($quiz, $cm->id);

            $newQuiz = $DB->get_record('quiz', array('id' => $quizId));

            addQuestionsToQuiz($questionIds, $newQuiz);
            quiz_update_sumgrades($newQuiz);
        }
    }

    function getCourseContextId($courseid) {
        $context = context_course::instance($courseid);
        return $context->id;
    }

    function getCourseQuestions($courseid, $DB) {
        $contextId = getCourseContextId($courseid);
        $questionCategories = $DB->get_record_sql(
            'SELECT TOP 200 id FROM {question_categories} WHERE contextid = ? AND parent > 0',
            [
                $contextId,
                '> 0',
            ]
        );
        return question_bank::get_finder()->get_questions_from_categories((array)$questionCategories, "");
    }

    // Findes exercises that has specific substring in name 
    function getQuestionsBySubname($questionsIds, $subname, $count) {
        $res_questions = array();
        $res_count = 0; 

        foreach ($questionsIds as $questionId) {
            if ($res_count >= $count)
                return $res_questions;

            $retrieved_question = question_bank::load_question_data($questionId);

            //add questions with similar subname
            //if (strpos($subname, $retrieved_question->name) != false) {
                array_push($res_questions, $retrieved_question->id);
                $res_count++;
            //}
        }

        return $res_questions;
    }
    
    function addQuestionsToQuiz($questionsIds, $quizId) {
        foreach ($questionsIds as $questionId) {
            quiz_add_quiz_question($questionId, $quizId);
        };
    }
?>
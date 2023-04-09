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

    function createQuiz($quiz, $courseId, $moduleId, $sectionId, $title, $DB) {
        $cm = createCourseModule($courseId, $moduleId, $sectionId, $DB);
        $quiz->name = $title; 
        $quiz->coursemodule = $cm->id;
        $quiz->course = $courseId;
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
        
        $contextId = getCourseContextId($courseId);
        $questionCategories = $DB->get_record_sql(
            'SELECT id FROM {question_categories} WHERE contextid = ? AND parent > 0', [ $contextId ]
        );
        
        //$questionBankQuestions = getCourseQuestions($contextId, $DB);

        // get quizes with exercises
        foreach($parsedQuizes as $parsedQuiz) {
            $quizId = createQuiz($parsedQuiz, $courseId, $module->id, $section->id, $parsedQuiz->title, $DB);
            $newQuiz = $DB->get_record('quiz', array('id' => $quizId));

            $questions = array();
            
            // Get exercises with subnames
            foreach ($parsedQuiz->exercisesWithSubname as $exerciseWithSubname) {
                $questionIds = get_questions_from_categories_and_subname(
                    (array)$questionCategories, 
                    $exerciseWithSubname->count->__toString(), 
                    $exerciseWithSubname->subname->__toString());
                $questions = array_merge($questions, $questionIds);
            }

            // Get exercises with tags
            foreach ($parsedQuiz->exercisesWithTag as $exerciseWithTag) {
                $questionIds = get_questions_from_categories_and_tag(
                    (array)$questionCategories, 
                    $exerciseWithTag->count->__toString(), 
                    $exerciseWithTag->tag->__toString());
                $questions = array_merge($questions, $questionIds);
            }

            addQuestionsToQuiz($questions, $newQuiz);
            quiz_update_sumgrades($newQuiz);
        }
    }

    function getCourseContextId($courseid) {
        $context = context_course::instance($courseid);
        return $context->id;
    }

    function getCourseQuestions($contextId, $DB) {
        $questionCategories = $DB->get_record_sql(
            'SELECT TOP 200 id FROM {question_categories} WHERE contextid = ? AND parent > 0',
            [
                $contextId
            ]
        );

        // Get questions using question categories
        $questionIds = (array)question_bank::get_finder()->get_questions_from_categories((array)$questionCategories, "");
        $questions = array();

        // Load questions data
        foreach($questionIds as $questionId) {
            array_push($questions, question_bank::load_question_data($questionId));
        };

        return $questions;
    }

    function get_questions_from_categories_and_subname($categoryids, $count, $subname) {
        global $DB;

        list($qcsql, $qcparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'qc');
        $qcparams['readystatus'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $qcparams['subname'] = '%'.$subname.'%';

        $sql = "SELECT q.id
                FROM {question} q
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                WHERE qbe.questioncategoryid {$qcsql}
                    AND q.parent = 0
                    AND qv.status = :readystatus
                    AND q.name LIKE :subname";
        $retrieved_questions =  $DB->get_records_sql_menu($sql, $qcparams);

        $qids = array_keys($retrieved_questions);
        shuffle($qids);

        return array_slice($qids, 0, $count);
    }

    function get_questions_from_categories_and_tag($categoryids, $count, $tag) {
        global $DB;

        list($qcsql, $qcparams) = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED, 'qc');
        $qcparams['readystatus'] = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $qcparams['questionitemtype'] = 'question';
        $qcparams['questioncomponent'] = 'core_question';
        $qcparams['tag'] = $tag;
        
        $sql = "SELECT q.id
                FROM {question} q
                JOIN {question_versions} qv ON qv.questionid = q.id
                JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
                WHERE qbe.questioncategoryid {$qcsql}
                    AND q.parent = 0
                    AND qv.status = :readystatus
                    AND q.id IN (SELECT ti.itemid
                                    FROM {tag_instance} ti
                                    WHERE ti.itemtype = :questionitemtype
                                        AND ti.component = :questioncomponent
                                        AND ti.tagid = :tag)";
        $retrieved_questions =  $DB->get_records_sql_menu($sql, $qcparams);
        
        $qids = array_keys($retrieved_questions);
        shuffle($qids);

        return array_slice($qids, 0, $count);
    }

    function getQuestionsWithSubname($questionCategories, $subname) {
        // Get questions using question categories
        $extraconditions = "q.name = :subname";
        $questionIds = (array)question_bank::get_finder()->get_questions_from_categories((array)$questionCategories, $extraconditions);
        $questions = array();

        // Load questions data
        foreach($questionIds as $questionId) {
            array_push($questions, question_bank::load_question_data($questionId));
        };

        return $questions;
    }

    function addParsedQuestions($parsedQuizes, $questionBankQuestions) {
        $questions = array();

        foreach ($parsedQuizes->exercisesWithSubname as $withSubname)
        {
            $retrievedCount = 0;
            foreach ($questionBankQuestions as $question) {
                if ($retrievedCount >= $withSubname->count){
                    break;
                }

                if (!in_array($question->id, $questions, true) 
                    && strpos($question->name, $withSubname->substring) !== false){
                    array_push($res_questions, $question->id);
                } 
                
                $retrievedCount++;
            }
        }
        
        foreach ($parsedQuizes->exercisesWithTag as $withTag)
        {
            $retrievedCount = 0;
            foreach ($questionBankQuestions as $question) {
                if ($retrievedCount >= $withTag->count){
                    break;
                }

                if (!in_array($question->id, $questions, true) 
                    && strpos($question->name, $withTag->tag) !== false){
                    array_push($res_questions, $question->id);
                } 
                
                $retrievedCount++;
            }
        }

        return $questions;
    }
    
    function addQuestionsToQuiz($questionsIds, $quiz) {
        foreach ($questionsIds as $questionId) {
            quiz_add_quiz_question($questionId, $quiz);
        };
    }
?>
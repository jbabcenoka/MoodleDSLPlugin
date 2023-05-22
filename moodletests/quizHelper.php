<?php
    require_once($CFG->dirroot.'/lib/questionlib.php');
    require_once($CFG->dirroot.'/course/lib.php');

    function create_quiz($quiz, $courseId, $moduleId, $sectionId, $title, $DB) {
        $cm = create_course_module($courseId, $moduleId, $sectionId, $DB);
        $quiz->name = $title; 
        $quiz->coursemodule = $cm->id;
        $quiz->course = $courseId;
        $quiz->quizpassword = "";
        $quiz->intro = "";
        $quiz->timeopen = 0;
        $quiz->timeclose = 0;
        $quiz->timelimit = 0;
        $quiz->introformat = 1; //HTML tags
        $quiz->questiondecimalpoints = -1;
        $quiz->decimalpoints = 2;
        $quiz->questionsperpage = 1;
        $quiz->preferredbehaviour = 'deferredfeedback';
        $quizid = quiz_add_instance($quiz);
        
        $quizsection = $DB->get_record('quiz_sections', array('quizid' => $quizid));
        $quizsection->shufflequestions = $quiz->shufflequestions;
        $DB->update_record('quiz_sections', $quizsection);
        return $quizid;
    }
    
    function create_course_module($courseId, $moduleId, $sectionId, $DB) {
        $cm = new stdClass();
        $cm->name = 'New exams module';
        $cm->course = $courseId;
        $cm->module = $moduleId;
        $cm->section = $sectionId;
        $cm->idnumber = null;
        $cm->added = time();
        $cm->visible = 0;
        $cm->id	= $DB->insert_record('course_modules', $cm);
        course_add_cm_to_section($courseId, $cm->id, $sectionId);
        return $cm;
    }

    function create_quizes($quizes, $courseId, $context, $DB) {
        // Get quiz module
        $module = $DB->get_record("modules", array("name" => "quiz"), '*', MUST_EXIST);

        // Get course first topic. If doesn`t exist - create new 
        $section = $DB->get_record("course_sections", array("course" => $courseId, "section" => 0),"*", IGNORE_MULTIPLE);
        if (!$section) {
            $section = course_create_section($courseId, 0);
        }
        
        // Create variants for all quizes
        $questionCategories = get_course_question_categories($context);
        foreach ($quizes as $quiz) {
            // Get quiz question category
            $questionCategory = get_questions_category_by_substring($questionCategories, $quiz->questionCategory);
            if ($questionCategory == null) {
                throw new Exception("Question category not found. Try to change a question category and upload the file again.");
            }

            // Create quiz variants
            $variants = array();
            foreach ($quiz->variants as $quizVariant) {
                // Create new quiz
                $quizId = create_quiz($quiz, $courseId, $module->id, $section->id, $quiz->title, $DB);
                $newQuizVariant = $DB->get_record('quiz', array('id' => $quizId));
                array_push($variants, $newQuizVariant);

                // Add users restrictions
                $restrictions = array();
                foreach($quizVariant->users as $user) {
                    array_push($restrictions, \availability_profile\condition::get_json(
                        false,
                        $user->filter,
                        get_user_field_condition($user->condition),
                        $user->name));
                }
    
                if (!empty($restrictions)){
                    $coursemodule = $DB->get_record('course_modules', array('instance'=>$quizId));
                    $restriction = \core_availability\tree::get_root_json($restrictions, get_users_separator($quizVariant->usersSeparator));
                    $DB->set_field(
                        'course_modules', 
                        'availability', 
                        json_encode($restriction), 
                        ['id' => $coursemodule->id]);
                    rebuild_course_cache($courseId, true);
                }
            }

            // Get exercises
            foreach ($quiz->exercises as $exercise) {
                // Get exercises from category that match query
                $questionIds = get_exercises_from_category($exercise, $questionCategory);
                // Allocate retrieved exercises into variants
                if ($questionIds != null) {
                    $exerciseVariants = AllocateIntoQuizVariants($questionIds, count($quiz->variants), $exercise->count);

                    // Save to variants
                    $variantIterator = 0;
                    foreach ($variants as $variant) {
                        $exercises = $exerciseVariants[$variantIterator];
                        foreach ($exercises as $exercise) {
                            quiz_add_quiz_question($exercise, $variant);
                        }
                        $variantIterator++;
                    }
                }
            }

            // Upgrade sumgrades
            foreach ($variants as $variant) {
                quiz_update_sumgrades($variant);
            }
        }
    }

    function get_exercises_from_category($exercise, $questionCategory) {
        $questionsIds = array();

        if ($exercise->isWithSubname) {
            $questionsIds = get_questions_from_categories_and_subname(
                (array)$questionCategory, 
                $exercise->subname);
        }
        else 
        {
            $questionsIds = get_questions_from_categories_and_tag(
                (array)$questionCategory, 
                $exercise->tag);
        }

        return $questionsIds;
    }

    function get_course_question_categories($context) {
        $contexts = new core_question\local\bank\question_edit_contexts($context);
        $conArray = $contexts->having_cap('moodle/question:add');
        return qbank_managecategories\helper::question_category_options($conArray, true, 0, true, -1, false);
    }
    
    function get_questions_category_by_substring($categories, $substring) {
        $categoryid = null;
        foreach ($categories[0] as $outerKey => $outerValue) {
            foreach ($outerValue as $innerKey => $innerValue) {
                if (strpos($innerValue, $substring) !== false) {
                    $categoryid = $innerKey;
                    break 2;
                }
            }
        }
        
        return $categoryid;
    }

    function get_questions_from_categories_and_subname($categoryids, $subname) {
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
        
        return array_keys($retrieved_questions);        
    }
    
    function get_questions_from_categories_and_tag($categoryids, $tag) {
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
        
        return array_keys($retrieved_questions);
    }

    function AllocateIntoQuizVariants($exercisesIds, $variantCount, $count) {
        $result = array();
        shuffle($exercisesIds);

        $startpos = 0;
        for ($i = 0; $i < $variantCount; $i++) {
            $variantExercises = array_slice($exercisesIds, $startpos, $count);

            if (count($variantExercises) == $count) {
                $startpos = $startpos + $count;
                array_push($result, $variantExercises);
                continue;
            }
            
            while (count($variantExercises) < $count) {
                $exercisesWithoutPart = array_slice($exercisesIds, 0, $startpos);

                $variantExercisesCount = $count - count($variantExercises);
                if (count($exercisesWithoutPart) > 0) {
                    shuffle($exercisesWithoutPart);
                    $exercisesPart = array_slice($exercisesWithoutPart, 0, $variantExercisesCount);
                }
                else {
                    $exercisesPart = array_slice($exercisesIds, 0, $variantExercisesCount);
                }

                foreach ($exercisesPart as $ex) {
                    array_push($variantExercises, $ex);
                }

                shuffle($exercisesIds);
            }
            
            $startpos = 0;
            array_push($result, $variantExercises);
        }

        return $result;
    }


    function get_users_separator($separator) {
        switch ($separator) {
            case "or":
                return '|';
            case "and":
                return '&';
            default:
                throw new Exception("Provided separator doesn`t exist. 
                    Try to change separator value and upload the file again.");
        }
    }

    function get_user_field_condition($field) {
        switch ($field) {
            case "isequalto":
                return \availability_profile\condition::OP_IS_EQUAL_TO;
            case "contains":
                return \availability_profile\condition::OP_CONTAINS;
            case "startswith":
                return \availability_profile\condition::OP_STARTS_WITH;
            default:
                throw new Exception("Provided condition doesn`t exist. 
                    Try to change condition value and upload the file again.");
        }
    }
?>
<?php
    require_once(__DIR__ . '/quizHelper.php');
    
    function parseXML($xml) {
        $parsedQuizes = array();

        foreach ($xml->children() as $quizElement)
        {
            // Get  title
            $quizTitle = (string)$quizElement->title[0];
            
            // Get exercises
            $exercises = $quizElement->exercises;
            $parsedExercises = array();

            foreach($exercises->children() as $withSubnameOrTag) 
            {
                $parsedExercise = new stdClass();
                $parsedExercise->count = $withSubnameOrTag->count;
                
                if ($withSubnameOrTag->getName() == "withSubname") {
                    $parsedExercise->subname = $withSubnameOrTag->subname;
                } else if ($withSubnameOrTag->getName() == "withTag") {
                    $parsedExercise->tag = $withSubnameOrTag->tag;
                } else {
                    throw new Exception;
                }

                array_push($parsedExercises, $parsedExercise);
            }

            $parsedQuiz = new stdClass();
            $parsedQuiz->title = $quizTitle;
            $parsedQuiz->exercises = $parsedExercises;
            array_push($parsedQuizes, $parsedQuiz);
        }

        return $parsedQuizes;
    }
?>
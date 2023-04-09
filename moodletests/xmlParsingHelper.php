<?php
    require_once(__DIR__ . '/quizHelper.php');
    
    function parseXML($xml) {
        $parsedQuizes = array();

        foreach ($xml->children() as $quizElement)
        {
            // Get  title
            $quizTitle = (string)$quizElement->title[0];
            
            // Get exercises
            $exercisesWithSubname = array();
            $exercisesWithTag = array();
            
            $exercises = $quizElement->exercises;
            foreach($exercises->children() as $withSubnameOrTag) 
            {
                if ($withSubnameOrTag->getName() == "withSubname") {
                    $withSubname = new stdClass();
                    $withSubname->count = $withSubnameOrTag->count;
                    $withSubname->subname = $withSubnameOrTag->subname;
                    array_push($exercisesWithSubname, $withSubname);
                } else if ($withSubnameOrTag->getName() == "withTag") {
                    $withSubname = new stdClass();
                    $withSubname->count = $withSubnameOrTag->count;
                    $withSubname->tag = $withSubnameOrTag->tag;
                    array_push($exercisesWithTag, $withSubname);
                } else {
                    throw new Exception;
                }
            }

            $parsedQuiz = new stdClass();
            $parsedQuiz->title = $quizTitle;
            $parsedQuiz->exercisesWithSubname = $exercisesWithSubname;
            $parsedQuiz->exercisesWithTag = $exercisesWithTag;
            array_push($parsedQuizes, $parsedQuiz);
        }

        return $parsedQuizes;
    }
?>
<?php
    //reads XML file
    function loadXml($fileName) {
        $xml = simplexml_load_file($fileName);
        return $xml;
    }
?>
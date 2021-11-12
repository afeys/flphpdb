<?php

require_once(dirname(__FILE__) . '/Exceptions/NoRecordsFoundException.php');
require_once(dirname(__FILE__) . '/Exceptions/RecordAttributeDoesNotExistException.php');
require_once(dirname(__FILE__) . '/Exceptions/RecordNotUniqueException.php');
require_once(dirname(__FILE__) . '/Exceptions/WrongParametersException.php');
require_once(dirname(__FILE__) . '/Connection.php');
require_once(dirname(__FILE__) . '/ConnectionManager.php');
require_once(dirname(__FILE__) . '/QueryBuilder.php');
require_once(dirname(__FILE__) . '/Model.php');
require_once(dirname(__FILE__) . '/Validation.php');

function initialize($modeldirlocations) {
    $modeldirectories = Config::getInstance()->get($modeldirlocations);
    foreach ($modeldirectories as $dir) {
        $root = realpath(isset($dir) ? $dir : '.');
        $files = glob($root . "/*.php");
        foreach ($files as $idx => $filepath) {
            requireFile($filepath);
        }
    }
}

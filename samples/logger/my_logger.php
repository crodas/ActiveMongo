<?php

/**
 *  Extension to teh MongoLogger class, 
 *  this class add information about the 
 *  REMOTE_ADDR, GET and POST
 */
class My_Logger extends MongoLogger
{
    public $user_ip;
    public $request;

    /**
     *  Sample Hook which appends
     *  properties to the document.
     */
    function pre_save($op, &$document)
    {
        if ($op == 'create') {
            $document['user_ip'] = $_SERVER['REMOTE_ADDR'];
            $document['request'] = array("POST" => $_POST, "GET" => $_GET);
        }
    }
}


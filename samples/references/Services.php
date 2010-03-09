<?php

abstract class Service extends ActiveMongo
{
    public $user;

    public $rss;


    function user_filter(&$value, $old_value)
    {
    }

    function pre_save($op, $document)
    {
        if (isset($GLOBALS['debug'])) {
            var_dump(array($op => get_class($this), 'doc' =>  $document));
        }
    }
}

class Twitter extends  Service
{
}

class Blog extends Service
{
}

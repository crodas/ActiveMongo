<?php

abstract class Service extends ActiveMongo
{
    public $user;

    public $rss;


    function user_filter(&$value, $old_value)
    {
    }

    function after_update($document)
    {
        if (isset($GLOBALS['debug'])) {
            var_dump(array(get_class($this) =>  $document));
        }
    }
}

class Twitter extends  Service
{
}

class Blog extends Service
{
}

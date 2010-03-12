<?php

/**
 *  NOTE: The Service class is different from the 
 *  one 'reference', it behaves complete difference, 
 *  ensuring that each subclass is going to be stored
 *  in the 'services' collection.
 */
class Service extends ActiveMongo
{
    public $user;

    public $rss;

    final function getCollectionName()
    {
        return 'services';
    }

    final function before_filter($obj)
    {
        $obj['type'] = get_class($this);
    }

    function after_update($document)
    {
        if (isset($GLOBALS['debug'])) {
            var_dump(array(get_class($this) => $document));
        }
    }

    function user_filter(&$value, $old_value)
    {
    }

}

class Twitter extends  Service
{
}

class Blog extends Service
{
}

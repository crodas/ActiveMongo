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

    final function pre_save($op, &$obj)
    {
        /* Little trick to ensure that each service
         * has its own 'type' value.
         */
        if ($op == 'create') {
            $obj['type'] = get_class($this);
        } else {
            $obj['$set']['type'] = get_class($this);
        }
        if (isset($GLOBALS['debug'])) {
            var_dump(array($op => $obj));
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

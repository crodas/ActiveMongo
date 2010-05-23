<?php

require "../lib/Cache.php";
    

class CacheableModel extends ActiveMongo
{
    public static $cacheable = TRUE;
}

class CacheTest extends PHPUnit_Framework_TestCase
{
    function testCache()
    {
        $c = new CacheableModel;
        $c->foo = 'bar';
        $c->save();
        $id = $c->getID();
        $c->reset();

        $c->where('_id', $id);
        $c->doQuery();
    }

}

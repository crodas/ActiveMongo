<?php

require "../lib/Cache.php";
    

class CacheableModel extends ActiveMongo
{
    public static $cacheable = TRUE;
}

class CacheDriverMem extends CacheDriver
{
    private $mem;

    function set($key, $content, $ttl)
    {
        $this->mem[$key] = $content;
    }

    function delete($key)
    {
        unset($this->mem[$key]);
    }
}

ActiveMongo_Cache::setDriver(new CacheDriverMem);
ActiveMongo_Cache::enable();


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

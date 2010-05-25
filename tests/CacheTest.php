<?php

require "../lib/Cache.php";
    

class CacheableModel extends ActiveMongo
{
    public static $cacheable = TRUE;
}

class CacheDriverMem extends CacheDriver
{
    private $mem;

    function get($key, &$document)
    {
        if (isset($this->mem[$key])) {
            $document = $this->deserialize($this->mem[$key]);
            return TRUE;
        }
        return FALSE;
    }

    function set($key, $document, $ttl)
    {
        $this->mem[$key] = $this->serialize($document);
    }

    function delete(Array $keys)
    {
        foreach ($keys as $key) {
            unset($this->mem[$key]);
        }
    }
}

ActiveMongo_Cache::setDriver(new CacheDriverMem);

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
        $this->assertFalse($c->servedFromCache());

        $d = new CacheableModel;
        $d->where('_id', $id);
        $d->doQuery();

        $this->assertTrue($d->servedFromCache());
        $this->assertEquals($c->foo, $d->foo);
    }

}

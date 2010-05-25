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
    function __construct()
    { 
        try { 
            CacheableModel::drop();
        } Catch (Exception $e) {
        }
    }

    function testCacheSimple()
    {
        $c = new CacheableModel;
        $c->prop = 'bar';
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
        $this->assertEquals($c->prop, $d->prop);

        return $id;
    }

    function testCacheMultiple()
    {
        $var = array('bar','foo','xxx','ccc');
        $c = new CacheableModel;
        foreach ($var as $v) {
            $c->reset();
            $c->var['var_name'] = $v;
            $c->$v = TRUE;
            $c->save();
        }

        $query = new CacheableModel;
        $query->where('var.var_name IN', $var);
        $query->doQuery();
        $this->assertFalse($query->servedFromCache());
        $count1 = $query->count();
        foreach ($query as $result) {
            foreach ($result['var'] as $key => $value) {
                $this->assertTrue(isset($result->$value));
            }
        }

        $query->reset();
        $query->where('var.var_name IN', $var);
        $query->doQuery();
        $this->assertTrue($query->servedFromCache());
        $count2 = $query->count();
        $this->assertEquals($count1, $count2);
        $this->assertEquals($count1, count($var));

        foreach ($query as $result) {
            foreach ($result['var'] as $key => $value) {
                $this->assertTrue(isset($result->$value));
            }
        }
    }

    /**
     *  @depends testCacheSimple
     */
    function testUpdateCache($id)
    {
        $d = new CacheableModel;
        $d->where('_id', $id);
        $d->doQuery();

        $this->assertEquals($d->prop, 'bar');
        $this->assertEquals(1, $d->count());
        $this->assertTrue($d->servedFromCache());
        $d->prop = 'new value';
        $d->save();

        $c = new CacheableModel;
        $c->where('_id', $id);
        $c->doQuery();


        $this->assertTrue($d->servedFromCache());
        $this->assertEquals($c->prop, $d->prop);


    }
}

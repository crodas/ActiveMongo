<?php

require "../lib/plugin/Cache/Cache.php";
    

class CacheableModel extends ActiveMongo
{
    public static $cacheable = TRUE;
}

class CacheDriverMem extends CacheDriver
{
    private $mem;

    function flush()
    {
        $this->mem = array();
    }

    function config($name, $value)
    {
        switch ($name) {
        case 'return':
            return $value;
        case 'true':
            return TRUE;
        case 'false':
            return FALSE;
        }
    }

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


class CacheTest extends PHPUnit_Framework_TestCase
{
    function testInit()
    {
        try { 
            CacheableModel::drop();
        } Catch (ActiveMongo_Exception $e) {
        }
        ActiveMongo_Cache::setDriver(new CacheDriverMem);
    }

    /**
     *  @depends testInit
     */
    function testCacheDriverConfig()
    {
        $this->assertTrue(ActiveMongo_Cache::config('true', NULL));
        $this->assertFalse(ActiveMongo_Cache::config('false', NULL));
        $this->assertEquals(ActiveMongo_Cache::config('return', 'foo'), 'foo');
    }

    /**
     *  @depends testInit
     */
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

        return $var;
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

        return $id;
    }

    /**
     *  @depends testCacheMultiple
     */
    function testUpdateQueryCache($vars)
    {
        /* assert the query is cached */
        $query = new CacheableModel;
        $query->where('var.var_name IN', $vars);
        $query->doQuery();
        $this->assertTrue($query->servedFromCache());
        $this->assertEquals(count($query), count($vars));

        /* add a new element */
        $new = new CacheableModel;
        $new->var['var_name'] = 'xxx';
        $new->xxx = TRUE;
        $new->save();

        /* query to mongodb, without cache */
        $query = new CacheableModel;
        $query->where('var.var_name IN', $vars);
        $query->doQuery(FALSE);
        $this->assertFalse($query->servedFromCache());
        $this->assertEquals(count($query), count($vars)+1);

        /* check if the cache was overrided */
        $query = new CacheableModel;
        $query->where('var.var_name IN', $vars);
        $query->doQuery();
        $this->assertTrue($query->servedFromCache());
        $this->assertEquals(count($query), count($vars)+1);
    }

    /**
     *  @depends testUpdateCache
     */
    function testFetchFromCache($id)
    {
        /* Test if one object is missing in the cache
         * is loaded properly by activemongo cache
         */
        ActiveMongo_Cache::deleteObject($id);
        $this->assertFalse(ActiveMongo_Cache::getObject($id));
        $d = new CacheableModel;
        $d->where('_id', $id);
        $d->doQuery();
        $this->assertTrue($d->servedFromCache());
        $this->assertEquals('new value', $d->prop);
        $this->assertTrue(is_array(ActiveMongo_Cache::getObject($id)));
    }

    function testDrivers()
    {
        $drivers = glob("../lib/plugin/Cache/*.php");
        foreach ($drivers as $drive) {
            if (substr($drive,-9) == 'Cache.php') {
                continue;
            }
            require $drive;
            if (!ActiveMongo_Cache::isDriverActived()) {
                continue;
            }
            ActiveMongo_Cache::flushCache();
            CacheableModel::drop();
            $id   = $this->testCacheSimple();
            $vars = $this->testCacheMultiple();
            $this->testUpdateCache($id);
            $this->testFetchFromCache($id);
            $this->testUpdateQueryCache($vars);
        }
    }
}

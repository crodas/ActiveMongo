<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2010 ActiveMongo                                                  |
  +---------------------------------------------------------------------------------+
  | Redistribution and use in source and binary forms, with or without              |
  | modification, are permitted provided that the following conditions are met:     |
  | 1. Redistributions of source code must retain the above copyright               |
  |    notice, this list of conditions and the following disclaimer.                |
  |                                                                                 |
  | 2. Redistributions in binary form must reproduce the above copyright            |
  |    notice, this list of conditions and the following disclaimer in the          |
  |    documentation and/or other materials provided with the distribution.         |
  |                                                                                 |
  | 3. All advertising materials mentioning features or use of this software        |
  |    must display the following acknowledgement:                                  |
  |    This product includes software developed by César D. Rodas.                  |
  |                                                                                 |
  | 4. Neither the name of the César D. Rodas nor the                               |
  |    names of its contributors may be used to endorse or promote products         |
  |    derived from this software without specific prior written permission.        |
  |                                                                                 |
  | THIS SOFTWARE IS PROVIDED BY CÉSAR D. RODAS ''AS IS'' AND ANY                   |
  | EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED       |
  | WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE          |
  | DISCLAIMED. IN NO EVENT SHALL CÉSAR D. RODAS BE LIABLE FOR ANY                  |
  | DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES      |
  | (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;    |
  | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND     |
  | ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT      |
  | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS   |
  | SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE                     |
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/

/**
 *  Cursor used for cached items
 */
class CacheCursor Extends MongoCursor
{
    protected $var;

    function __construct()
    {
    }

    function reset()
    {
    }

    function current()
    {
    }

    function next()
    {
    }

    function rewind()
    {
    }

    function valid()
    {
    }

    function getNext()
    {
    }
}

abstract class CacheDriver
{
    function serialize($object)
    {
        return bson_encode($object);
    }

    function deserialize($string)
    {
        return bson_decode($string);
    }

    abstract function get($key, &$object);

    abstract function set($key, $object, $ttl);

    abstract function delete($key);
}

final class ActiveMongo_Cache
{
    private static $instance;
    private $enabled;
    private $driver;

    private function __construct()
    {
        ActiveMongo::addEvent('before_query', array($this, 'QueryRead'));
        ActiveMongo::addEvent('after_query',  array($this, 'QuerySave'));
        ActiveMongo::addEvent('after_create', array($this, 'UpdateHook'));
        ActiveMongo::addEvent('after_update', array($this, 'UpdateHook'));
    }

    public static function Init()
    {
        if (self::$instance) {
            return;
        }
        self::$instance = new ActiveMongo_Cache;
    }

    public static function setDriver(CacheDriver $driver)
    {
        self::Init();
        self::$instance->driver = $driver;
    }

    public static function enable()
    {
        self::Init();
        self::$instance->enabled = TRUE;
    }

    public static function disable()
    {
        self::Init();
        self::$instance->enabled = FALSE;
    }

    final protected function hasCache($class)
    {
        if (!$this->driver InstanceOf CacheDriver) {
            return FALSE;
        }
        $enable = isset($class::$cacheable) ? $class::$cacheable : $this->enabled;
        return $enable;
    }

    final protected function getQueryID($query_document)
    {
        /* TODO: Peform some sort of sorting */
        /* to treat queries with same parameters but */
        /* different order equal */

        $id = $this->driver->serialize($query_document);

        return sha1($id);
    }
    
    /**
     *
     */
    function QueryRead($class, $query_document, &$resultset)
    {
        if (!$this->hasCache($class)) {
            return;
        }

        $query_id = $this->getQueryID($query_document);
        
        if (!$this->driver->get($query_id, $query_result)) {
            return; /* Not cached yet */
        }

        if (!is_array($query_result)) {
            /* This is unexpected, always double check */
            return;
        }


        $resultset = new CacheCursor;
        $toquery   = array();
        $result    = array();

        foreach ($query_result as $i=>$id) {
            $doc = NULL;
            if (!$this->driver->get((string)$id, $doc)) {
                $toquery[$i] = $id;
            }
            $result[$i] = $doc;
        }

        if (count($toquery) > 0) {
            $db = new $class;
            $db->where(array_values($toquery));
            foreach ($db as $doc) {
                foreach ($toquery as $i => $id) {
                    if ($id == $doc['_id']) {
                        break;
                    }
                }
                $result[$i] = $doc;
            }
        }

        return;

        throw new ActiveMongo_Results;
    }

    /**
     *
     *
     */
    function QuerySave($class, $query_document, $cursor)
    {
        if (!$this->hasCache($class)) {
            return;
        }

        $query_id = $this->getQueryID($query_document);
        $ids      = array();

        foreach ($cursor as $document) {
            $ids[] = $document['_id'];
            $this->driver->set((string)$document['_id'], $this->driver->serialize($document), 3600);
        }

        $this->driver->set($query_id, $ids, 3600);
    }

    // UpdateHook($class, $document, $obj) {{{
    /** 
     *  Update Hook
     *
     *  Save or Replace an object (document) 
     *  into the cache.
     *
     *  @param string $class    Class name
     *  @param object $document Document sent to mongodb
     *  @param object $obj      ActiveMongo Object
     *
     *  @return NULL
     */
    function UpdateHook($class, $document, $obj)
    {
        if (!$this->hasCache($class)) {
            return;
        }

        if (!isset($obj['_id'])) {
            if (!isset($document['_id'])) {
                return; /* Weird condition */
            }
            $obj['_id'] = $document['_id'];
        }

        $this->driver->set((string)$obj['_id'], $this->driver->serialize($obj), 3600);
    }
    // }}}

}

ActiveMongo_Cache::Init();

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */

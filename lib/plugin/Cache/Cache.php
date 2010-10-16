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

// class CursorCache  {{{
/**
 *  Cursor used for cached items
 *
 *  Hack for ActiveMongo, fake MongoCursor
 *  subclass that iterates in a given array.
 *
 *  This avoid re-write the main iteration 
 *  support at MongoDB, nevertheless this might 
 *  be improved in the future.
 *  
 *  @author César D. Rodas <crodas@php.net>
 *  @license BSD License
 *  @package ActiveMongo
 *  @version 1.0
 *  
 */
final class CacheCursor Extends MongoCursor
{
    protected $var;
    protected $size;
    protected $pos;

    function __construct(Array $array)
    {
        $this->var  = array_values($array);
        $this->size = count($array);
        $this->pos  = 0;
    }

    function reset()
    {
        $this->pos = 0;
    }

    function key()
    {
        return (string)$this->var[$this->pos]['_id'];
    }

    function current()
    {
        if (!$this->valid()) {
            return array();
        }
        return $this->var[$this->pos];
    }

    function next()
    {
        ++$this->pos;
    }

    function valid()
    {
        return isset($this->var[$this->pos]);
    }

    function rewind()
    {
        $this->reset();
        $this->next();
    }

    function getNext()
    {
        $this->rewind();
        return $this->var[$this->pos];
    }

    function count()
    {
        return count($this->var);
    }

}
// }}}

/**
 *  CacheDriver
 *
 *  Cache base class, each driver must inherit 
 *  this class, and must implement each method.
 *
 *  @author César D. Rodas <crodas@php.net>
 *  @license BSD License
 *  @package ActiveMongo
 *  @version 1.0
 */
abstract class CacheDriver
{

    // Serialization {{{
    /**
     *  serialize -- by default with BSON
     *
     *  @param object $object
     *
     *  @return string
     */
    function serialize($object)
    {
        return bson_encode($object);
    }

    /**
     *  deserialize -- by default with BSON
     *
     *  @param string $string
     *
     *  @return object
     */
    function deserialize($string)
    {
        return bson_decode($string);
    }
    // }}}

    // void getMulti (Array $keys, Array &$objects) {{{
    /**
     *  Simple but inneficient implementation of 
     *  the getMulti. It retrieve multiple objects
     *  from the cache that matchs the array of keys.
     *
     *  If the cache supports multiple
     *  get (as memcached does) it should be overrided.
     *
     *
     *  @param array $keys 
     *  @param array &$objects
     *
     */
    function getMulti(Array $keys, Array &$objects)
    {
        foreach ($keys as $key) {
            if ($this->get($key, $objects[$key]) === FALSE) {
                $objects[$key] = FALSE;
            }
        }
    }
    // }}}

    // setMulti(Array $objects, Array $ttl) {{{
    /**
     *  Simple but inneficient implementation of the 
     *  setMulti, it basically push a set of objects
     *  to the cache at once.
     *
     *  If the cache driver support this operation,
     *  this method should be overrided.
     *
     *  @param Array $objects
     *  @param Array $ttl
     *
     *  @retun voie
     */
    function setMulti(Array $objects, Array $ttl)
    {
        foreach ($objects as $id => $value) {
            if (!isset($ttl[$id])) {
                $ttl[$id] = 3600;
            }
            $this->set($id, $value, $ttl[$id]);
        }
    }
    // }}}

    // config($variable, $value) {{{
    /** 
     *  configuration for the driver
     *
     *  @param string $variable
     *  @param mixed   $value
     *
     *  @return NULL
     */
    function config($variable, $value) 
    {
    }
    // }}}

    // isEnabled() {{{
    function isEnabled()
    {
        return TRUE;
    }
    // }}}

    abstract function flush();

    abstract function get($key, &$object);

    abstract function set($key, $document, $ttl);

    abstract function delete(Array $key);

}

/**
 *  CacheDriver
 *
 *  Plug-in which adds cache capabilities to all
 *  ActiveMongo objects. The cache could be enabled
 *  for all objects (by default disabled), or for specified
 *  objects which has the static property cacheable to TRUE.
 *
 *  At query time is also posible to disable the cache, passing
 *  false to doQuery, also this method will override the cache
 *  values if the query can use cache.
 *
 *  @author César D. Rodas <crodas@php.net>
 *  @license BSD License
 *  @package ActiveMongo
 *  @version 1.0
 */
final class ActiveMongo_Cache
{
    private static $instance;
    private $enabled;
    private $driver;
    private $driver_enabled;

    //  __construct() {{{
    /**
     *  Class contructor
     *
     *  This is class is private, so it can be contructed
     *  only using the singleton interfaz.
     *
     *  This method also setup all needed hooks
     *
     *  @return void
     */
    private function __construct()
    {
        ActiveMongo::addEvent('before_query', array($this, 'QueryRead'));
        ActiveMongo::addEvent('after_query',  array($this, 'QuerySave'));
        ActiveMongo::addEvent('after_create', array($this, 'UpdateDocumentHook'));
        ActiveMongo::addEvent('after_update', array($this, 'UpdateDocumentHook'));
    }
    // }}}

    // Init() {{{
    /**
     *  Initialize the Cache system, this is done
     *  automatically.
     *
     *  @return void
     */
    public static function Init()
    {
        if (self::$instance) {
            return;
        }
        self::$instance = new ActiveMongo_Cache;
    }
    // }}}

    // setDriver(CacheDriver $driver) {{{
    /**
     *  Set the CacheDriver object that will be used
     *  to cache object, must be a sub-class of CacheDriver
     *
     *  @param CacheDriver $driver
     *
     *  @return void
     */
    public static function setDriver(CacheDriver $driver)
    {
        self::Init();
        self::$instance->driver         = &$driver;
        self::$instance->driver_enabled = FALSE;

    }
    // }}}

    // enable() {{{
    /**
     *  Enable the cache for all classes, even those
     *  which does not has the state property $cacheable
     *
     *  @return void
     */
    public static function enable()
    {
        self::Init();
        self::$instance->enabled = TRUE;
    }
    // }}}

    // config($name, $value) {{{
    /**
     *  Pass a configuration to the cache driver
     *
     *  @return mixed
     */
    public static function config($name, $value)
    {
        self::Init();
        $self = self::$instance;
        if (!$self->driver) {
            return FALSE;
        }
        return $self->driver->config($name, $value);
    }
    // }}}

    // cacheFailed() {{{
    /**
     *  Tell to ActiveMongo_Cache that the driver cache failed
     *  (it throwed some exception). Currently it is disabled
     *  temporarily, in the future it might have a threshold 
     *  of error and then disable permanently for the request.
     *
     *  @return void
     */
    function cacheFailed()
    {
        /* something went wrong, disable the cache */
        /* temporarily */
        $this->driver_enabled = FALSE;
    }
    // }}}

    // disable() {{{
    /**
     *  Disable the cache for all classes, except those
     *  which has the state property $cacheable =TRUE
     *
     *  @return void
     */
    public static function disable()
    {
        self::Init();
        self::$instance->enabled = FALSE;
    }
    // }}}

    // isDriverActived() {{{
    /**
     *  Check if it has a cache driver and 
     *  if it is valid.
     *
     *  @return bool
     */
    static function isDriverActived()
    {
        self::Init();
        $self = self::$instance;
        if (!$self->driver InstanceOf CacheDriver) {
            return FALSE;
        }
        if (!$self->driver_enabled && !$self->driver->isEnabled()) {
            return FALSE;
        }
        $self->_driver_enabled = TRUE;
        return TRUE;
    }
    // }}}

    // flushCache() {{{
    /**
     *  Delete all the cache content, I can't figureout
     *  how this can be useful, but I'm using for testing :-)
     *  
     */
    static function flushCache()
    {
        self::Init();
        $self = self::$instance;
        if (!$self->driver InstanceOf CacheDriver) {
            return FALSE;
        }
        if (!$self->driver_enabled && !$self->driver->isEnabled()) {
            return FALSE;
        }
        $self->driver->flush();
    }
    // }}}

    // canUseCache($class) {{{
    /**
     *  Return TRUE is the current query
     *  can use a cache.
     *
     *  @param string $class Class name
     *  
     *  @return bool 
     */
    final protected function canUseCache($class)
    {
        if (!$this->driver InstanceOf CacheDriver) {
            return FALSE;
        }
        if (!$this->driver_enabled) {
            $enabled = $this->driver->isEnabled();
            if (!$enabled) {
                return FALSE;
            }
            $this->driver_enabled = TRUE;
        }
        $enable = isset_static_variable($class, 'cacheable') ? get_static_variable($class, 'cacheable') : $this->enabled;
        return $enable;
    }
    // }}}

    // getQueryID(Array $query_docuement) {{{
    /**
     *  Get a ID from a given query, right now it is very
     *  simple, it serialize the query document, it should
     *  be improved to easily delete old queries
     *
     *  @param array $query_document
     *
     *  @return string
     */
    final protected function getQueryID($query_document)
    {
        /* TODO: Peform some sort of sorting */
        /* to treat queries with same parameters but */
        /* different order equal */

        $id = $this->driver->serialize($query_document);

        return sha1($id);
    }
    // }}}

    // deleteObject($id) {{{
    /**
     *  Delete an object from the cache by its $id
     *
     *  @return void
     */
    final static function deleteObject($id)
    {
        self::Init();
        $self = self::$instance;
        $self->driver->delete(array((string)$id));
    }
    // }}}

    // mixed getObject($id) {{{
    /**
     *  Return an object from the cache, if it doesn't
     *  exists it would return FALSE
     *
     *  @param mixed $id
     *  @return mixed $object
     *
     */
    final static function getObject($id)
    {
        self::Init();
        $self = self::$instance;
        if (!$self->driver) {
            return FALSE;
        }
        $object = FALSE;
        $self->driver->get((string)$id, $object);

        return $object;
    }
    // }}}
    
    // QueryRead($class, $query_document, &$resultset, $use_cache=TRUE){{{
    /**
     *  Return the resultset for the current query from the cache if the
     *  cache is enabled, if the current query can be cacheable and if 
     *  it already exists on cache.
     *
     *  @param string $class            Class name
     *  @param array  $query_document   Query sent to mongodb
     *  @param array  &$resultset       The resultset
     *  @param bool   $use_cache        True if cache can be used
     *
     *
     *  @return mixed FALSE or NULL
     */
    function QueryRead($class, $query_document, &$resultset, $use_cache=TRUE)
    {
        if (!$this->canUseCache($class) || !$use_cache) {
            return;
        }
        try {

            $query_id = $this->getQueryID($query_document);
            if ($this->driver->get($query_id, $query_result) === FALSE) {
                return;
            }

            if (!is_array($query_result) || count($query_result) == 0) {
                return;
            }

            $toquery = array();
            $result  = array();

            $cache_ids = array_combine(array_keys($query_result), array_keys($query_result));
     
            $this->driver->getMulti($cache_ids, $result);

            foreach ($result as $id => $doc) {
                if (!is_array($doc)) {
                    $toquery[$id] = $query_result[$id];
                }
            }

            if (count($toquery) > 0) {
                $db = new $class;
                $db->where('_id IN', array_values($toquery));
                $db->doQuery(FALSE);
                $dresult = array();
                foreach ($db as $doc) {
                    $dresult[$doc->key()] = $doc->getArray();
                }
                $this->driver->setMulti($dresult, array());
                $result = array_merge($result, $dresult);
            }


            $resultset = new CacheCursor($result);

        } catch (Exception $e) {
            /* If any goes wrong it shouldn't interupt the current query */
            $this->cacheFailed();
            $resultset = NULL;
        }

        /* Return FALSE to prevent the execution of 
         * any hook similar hook
         */
        return FALSE;
    }
    // }}}

    // QuerySave($class, $query_document, $cursor) {{{
    /**
     *  Save the current resultset into the cache
     *
     *  @param string       $class
     *  @param array        $query_document
     *  @param MongoCursor  $cursor
     *  
     *  @return void
     */
    function QuerySave($class, $query_document, $cursor)
    {
        if (!$this->canUseCache($class)) {
            return;
        }

        $query_id = $this->getQueryID($query_document);
        $ids      = array();
        $ttl      = array();
        $docs     = array();

        try {
            foreach ($cursor as $id => $document) {
                $ids[$id]  = $document['_id'];
                $docs[$id] = $document;
                $ttl[$id]  = 3600;
            }
            $this->driver->setMulti($docs, $ttl);
            $this->driver->set($query_id, $ids, 3600);
        } catch (Exception $e) {
            $this->cacheFailed();
        }

    }
    // }}}

    // UpdateDocumentHook($class, $document, $obj) {{{
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
    function UpdateDocumentHook($class, $document, $obj)
    {
        if (!$this->canUseCache($class)) {
            return;
        }

        if (!isset($obj['_id'])) {
            if (!isset($document['_id'])) {
                return; /* Weird condition */
            }
            $obj['_id'] = $document['_id'];
        }

        try {
            $this->driver->set((string)$obj['_id'], $obj, 3600);
        } catch (Exception $e) {
            $this->cacheFailed();
        }
    }
    // }}}

}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */

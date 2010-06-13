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

// array get_document_vars(stdobj $obj) {{{
/**
 *  Simple hack to avoid get private and protected variables
 *
 *  @param object $obj 
 *  @param bool   $include_id
 *
 *  @return array
 */
function get_document_vars($obj, $include_id=TRUE) 
{
    $document =  get_object_vars($obj);
    if ($include_id && $obj->getID()) {
        $document['_id'] = $obj->getID();
    }
    return $document;
}
// }}}

if (version_compare(PHP_VERSION, '5.3') < 0) {
    require dirname(__FILE__)."/Objects_compat.php";
} else {
    require dirname(__FILE__)."/Objects.php";
}

/**
 *  ActiveMongo
 *
 *  Simple ActiveRecord pattern built on top of MongoDB. This class
 *  aims to provide easy iteration, data validation before update,
 *  and efficient update.
 *
 *  @author César D. Rodas <crodas@php.net>
 *  @license BSD License
 *  @package ActiveMongo
 *  @version 1.0
 *
 */
abstract class ActiveMongo implements Iterator, Countable, ArrayAccess
{

    //{{{ Constants 
    const FIND_AND_MODIFY = 0x001;
    // }}}

    // properties {{{
    /** 
     *  Current databases objects
     *
     *  @type array
     */
    private static $_dbs;
    /**
     *  Current namespace
     *
     *  @type string
     */
    private static $_namespace = NULL;
    /**
     *  Specific namespaces for each class
     *
     *  @type string
     */
    private static $_namespaces = array();
    /**
     *  Current collections objects
     *      
     *  @type array
     */
    private static $_collections;
    /**
     *  Current connection to MongoDB
     *
     *  @type MongoConnection
     */
    private static $_conn;
    /**
     *  Database name
     *
     *  @type string
     */
    private static $_db;
    /**
     *  List of events handlers
     *  
     *  @type array
     */
    static private $_events = array();
    /**
     *  List of global events handlers
     *
     *  @type array
     */
    static private $_super_events = array();
    /**
     *  Host name
     *
     *  @type string
     */
    private static $_host;
    /**
     *  User (Auth)
     *
     *  @type string
     */
    private static $_user;
    
        /**
     *  Password (Auth)
     *
     *  @type string
     */
    private static $_pwd;

    /**
     *  Current document
     *
     *  @type array
     */
    private $_current = array();
    /**
     *  Result cursor
     *
     *  @type MongoCursor
     */
    private $_cursor  = NULL;
    /**
     *  Extended result cursor, used for FindAndModify now
     *
     *  @type int
     */
    private $_cursor_ex  = NULL;
    private $_cursor_ex_value;
    /**
     *  Count the findandmodify result counts  
     *
     *  @tyep array
     */
    private $_findandmodify_cnt = 0;
    /* value to modify */
    private $_findandmodify;

    /* {{{ Silly but useful query abstraction  */
    private $_cached  = FALSE; 
    private $_query   = NULL;
    private $_sort    = NULL;
    private $_limit   = 0;
    private $_skip    = 0;
    private $_properties = NULL;
    /* }}} */

    /**
     *  Current document ID
     *    
     *  @type MongoID
     */
    private $_id;

    /**
     *  Tell if the current object
     *  is cloned or not.
     *
     *  @type bool
     */
    private $_cloned = FALSE;
    // }}}

    // GET CONNECTION CONFIG {{{

    // setNameSpace($namespace='') {{{
    /**
     *  Set a namespace for all connections is it is called
     *  statically from ActiveMongo or for specific classes
     *  if it is called from an instance.
     *
     *  @param string $namespace
     *
     *  @return bool
     */
    final static function setNamespace($namespace='')
    {
        if (preg_match("#^[\-\_a-z0-9]*$#i", $namespace)) {
            /* sort of standard late binding */
            if (isset($this)) {
                $context = get_class($this);
            } else {
                $context = get_called_class();
            }

            if ($context == __CLASS__) {
                self::$_namespace = $namespace;
            } else {
                self::$_namespaces[$context] = $namespace;
            }
            return TRUE;
        }
        return FALSE;
    }
    // }}}

    // collectionName() {{{
    /**
     *  Get CollectionName
     *
     *  Return the collection name (along with its namespace) for
     *  the current object.
     *
     *  Warning: This must not be called statically from outside the 
     *  fundtion.
     *
     *  @return string
     */
    final public function collectionName()
    {
        $parent = __CLASS__;
        /* Need to check if $this is instance of $parent
         * because PHP5.2 fails detecting $this when a non-static
         * method is called statically from another class ($this is 
         * inherited)
         */
        if (isset($this) && $this InstanceOf $parent) {
            $collection = $this->getCollectionName();
            $context    = get_class($this);
        } else {
            /* ugly, it might fail if getCollectionName has some refernce to $this */
            $context    = get_called_class();
            $collection = call_user_func(array($context, 'getCollectionName'));
        }

        if (isset(self::$_namespaces[$context]) && self::$_namespaces[$context]) { 
            $collection = self::$_namespaces[$context].".{$collection}";
        } else if (self::$_namespace) { 
            $collection = self::$_namespace.".{$collection}";
        }

        return $collection;
    }
    // }}}

    // string getCollectionName() {{{
    /**
     *  Get Collection Name, by default the class name,
     *  but you it can be override at the class itself to give
     *  a custom name.
     *
     *  @return string Collection Name
     */
    protected function getCollectionName()
    {
        if (isset($this)) {
            return strtolower(get_class($this));
        } else {
            return strtolower(get_called_class());
        }
    }
    // }}}

    // string getDatabaseName() {{{
    /**
     *  Get Database Name, by default it is used
     *  the db name set by ActiveMong::connect()
     *
     *  @return string DB Name
     */
    protected function getDatabaseName()
    {
        if (is_NULL(self::$_db)) {
            throw new ActiveMongo_Exception("There is no information about the default DB name");
        }
        return self::$_db;
    }
    // }}}

    // void install() {{{
    /**
     *  Install.
     *
     *  This static method iterate over the classes lists,
     *  and execute the setup() method on every ActiveMongo
     *  subclass. You should do this just once.
     *
     */
    final public static function install()
    {
        $classes = array_reverse(get_declared_classes());
        foreach ($classes as $class)
        {
            if ($class == __CLASS__) {
                break;
            }
            if (is_subclass_of($class, __CLASS__)) {
                $obj = new $class;
                $obj->setup();
            }
        }
    }
    // }}}

    // void connection($db, $host) {{{
    /**
     *  Connect
     *
     *  This method setup parameters to connect to a MongoDB
     *  database. The connection is done when it is needed.
     *
     *  @param string $db   Database name
     *  @param string $host Host to connect
     *  @param string $user   User (Auth)
     *  @param string $pwd Password (Auth)   
     *
     *  @return void
     */
    final public static function connect($db, $host='localhost', $user = NULL, $pwd=NULL)
    {
        self::$_host = $host;
        self::$_db   = $db;
        self::$_user = $user;
        self::$_pwd   = $pwd;
    }
    // }}}

    // isConnected() {{{
    /**
     *  Return TRUE is there is any active connection
     *  to MongoDB.
     *
     *  @return bool
     */
    final public static function isConnected()
    {
        return !is_null(self::$_conn) || count(self::$_dbs) > 0 || count(self::$_collections) > 0;
    }
    // }}}

    // disconnect() {{{
    /**
     *  Destroy all connections objects to MongoDB if any.
     *
     *  @return void
     */
    final public static function disconnect()
    {
        self::$_conn        = NULL;
        self::$_dbs         = array();
        self::$_collections = array();
    }
    // }}}

    // MongoConnection _getConnection() {{{
    /**
     *  Get Connection
     *
     *  Get a valid database connection
     *
     *  @return MongoConnection
     */
    final protected function _getConnection()
    {
        if (is_NULL(self::$_conn)) {
            if (is_NULL(self::$_host)) {
                self::$_host = 'localhost';
            }
            self::$_conn = new Mongo(self::$_host);
        }
        if (isset($this)) {
            $dbname = $this->getDatabaseName();
        } else {
            $dbname = self::getDatabaseName();
        }
        if (!isSet(self::$_dbs[$dbname])) {
            self::$_dbs[$dbname] = self::$_conn->selectDB($dbname);
        }
        if ( !is_NULL(self::$_user ) &&   !is_NULL(self::$_pwd )  ) {
            self::$_dbs[$dbname]->authenticate(self::$_user,self::$_pwd);         
        }
        
        
        return self::$_dbs[$dbname];
    }
    // }}}

    // MongoCollection _getCollection() {{{
    /**
     *  Get Collection
     *
     *  Get a collection connection.
     *
     *  @return MongoCollection
     */
    final protected function _getCollection()
    {
        if (isset($this)) {
            $colName = $this->CollectionName();
        } else {
            $colName = self::CollectionName();
        }
        if (!isset(self::$_collections[$colName])) {
            self::$_collections[$colName] = self::_getConnection()->selectCollection($colName);
        }
        return self::$_collections[$colName];
    }
    // }}}

    // }}}

    // GET DOCUMENT TO SAVE OR UPDATE {{{

    // getDocumentVars() {{{
    /**
     *  getDocumentVars
     *
     *
     *
     */
    final protected function getDocumentVars()
    {
        $variables = array();
        foreach ((array)$this->__sleep() as $var) {
            if (!property_exists($this, $var)) {
                continue;
            }
            $variables[$var] = $this->$var;
        }
        return $variables;
    }
    // }}}

    // bool getCurrentSubDocument(array &$document, string $parent_key, array $values, array $past_values) {{{
    /**
     *  Generate Sub-document
     *
     *  This method build the difference between the current sub-document,
     *  and the origin one. If there is no difference, it would do nothing,
     *  otherwise it would build a document containing the differences.
     *
     *  @param array  &$document    Document target
     *  @param string $parent_key   Parent key name
     *  @param array  $values       Current values 
     *  @param array  $past_values  Original values
     *
     *  @return FALSE
     */
    final function getCurrentSubDocument(&$document, $parent_key, Array $values, Array $past_values)
    {
        /**
         *  The current property is a embedded-document,
         *  now we're looking for differences with the 
         *  previous value (because we're on an update).
         *  
         *  It behaves exactly as getCurrentDocument,
         *  but this is simples (it doesn't support
         *  yet filters)
         */
        foreach ($values as $key => $value) {
            $super_key = "{$parent_key}.{$key}";
            if (is_array($value)) {
                /**
                 *  Inner document detected
                 */
                if (!array_key_exists($key, $past_values) || !is_array($past_values[$key])) {
                    /**
                     *  We're lucky, it is a new sub-document,
                     *  we simple add it
                     */
                    $document['$set'][$super_key] = $value;
                } else {
                    /**
                     *  This is a document like this, we need
                     *  to find out the differences to avoid
                     *  network overhead. 
                     */
                    if (!$this->getCurrentSubDocument($document, $super_key, $value, $past_values[$key])) {
                        return FALSE;
                    }
                }
                continue;
            } else if (!array_key_exists($key, $past_values) || $past_values[$key] !== $value) {
                $document['$set'][$super_key] = $value;
            }
        }

        foreach (array_diff(array_keys($past_values), array_keys($values)) as $key) {
            $super_key = "{$parent_key}.{$key}";
            $document['$unset'][$super_key] = 1;
        }

        return TRUE;
    }
    // }}}

    // array getCurrentDocument(bool $update) {{{
    /**
     *    Get Current Document    
     *
     *    Based on this object properties a new document (Array)
     *    is returned. If we're modifying an document, just the modified
     *    properties are included in this document, which uses $set,
     *    $unset, $pushAll and $pullAll.
     *
     *
     *    @param bool $update
     *
     *    @return array
     */
    final protected function getCurrentDocument($update=FALSE, $current=FALSE)
    {
        $document = array();
        $object   = $this->getDocumentVars();

        if (!$current) {
            $current = (array)$this->_current;
        }


        $this->findReferences($object);

        $this->triggerEvent('before_validate', array(&$object, $current));
        $this->triggerEvent('before_validate_'.($update?'update':'creation'), array(&$object, $current));

        foreach ($object as $key => $value) {
            if ($update) {
                if (is_array($value) && isset($current[$key])) {
                    /**
                     *  If the Field to update is an array, it has a different 
                     *  behaviour other than $set and $unset. Fist, we need
                     *  need to check if it is an array or document, because
                     *  they can't be mixed.
                     *
                     */
                    if (!is_array($current[$key])) {
                        /**
                         *  We're lucky, the field wasn't 
                         *  an array previously.
                         */
                        $this->runFilter($key, $value, $current[$key]);
                        $document['$set'][$key] = $value;
                        continue;
                    }

                    if (!$this->getCurrentSubDocument($document, $key, $value, $current[$key])) {
                        throw new Exception("{$key}: Array and documents are not compatible");
                    }
                } else if(!array_key_exists($key, $current) || $value !== $current[$key]) {
                    /**
                     *  It is 'linear' field that has changed, or 
                     *  has been modified.
                     */
                    $past_value = isset($current[$key]) ? $current[$key] : NULL;
                    $this->runFilter($key, $value, $past_value);
                    $document['$set'][$key] = $value;
                }
            } else {
                /**
                 *  It is a document insertation, so we 
                 *  create the document.
                 */
                $this->runFilter($key, $value, NULL);
                $document[$key] = $value;
            }
        }

        /* Updated behaves in a diff. way */
        if ($update) {
            foreach (array_diff(array_keys($this->_current), array_keys($object)) as $property) {
                if ($property == '_id') {
                    continue;
                }
                $document['$unset'][$property] = 1;
            }
        } 

        if (count($document) == 0) {
            return array();
        }

        $this->triggerEvent('after_validate', array(&$document));
        $this->triggerEvent('after_validate_'.($update?'update':'creation'), array(&$object));

        return $document;
    }
    // }}}

    // }}}

    // EVENT HANDLERS {{{

    // addEvent($action, $callback) {{{
    /**
     *  addEvent
     *
     */
    final static function addEvent($action, $callback)
    {
        if (!is_callable($callback)) {
            throw new ActiveMongo_Exception("Invalid callback");
        }

        $class = get_called_class();
        if ($class == __CLASS__) {
            $events = & self::$_super_events;
        } else {
            $events = & self::$_events[$class];
        }
        if (!isset($events[$action])) {
            $events[$action] = array();
        }
        $events[$action][] = $callback;
        return TRUE;
    }
    // }}}

    // triggerEvent(string $event, Array $events_params) {{{
    final function triggerEvent($event, Array $events_params = array(), $context=NULL)
    {
        if (!$context){
            if (!isset($this)) {
                $class = get_called_class();
                $obj   = $class;
            } else {
                $class = get_class($this);
                $obj   = $this;
            }
        } else {
            $class = $context;
            $obj   = $context;
        }
        $events  = & self::$_events[$class][$event];
        $sevents = & self::$_super_events[$event];

        /* Super-Events handler receives the ActiveMongo class name as first param */
        $sevents_params = array_merge(array($class), $events_params);

        foreach (array('events', 'sevents') as $event_type) {
            if (count($$event_type) > 0) {
                $params = "{$event_type}_params";
                foreach ($$event_type as $fnc) {
                    if (call_user_func_array($fnc, $$params) === FALSE) {
                        return;
                    }
                }
            }
        }

        switch ($event) {
        case 'before_create':
        case 'before_update':
        case 'before_validate':
        case 'before_delete':
        case 'before_drop':
        case 'before_query':
        case 'after_create':
        case 'after_update':
        case 'after_validate':
        case 'after_delete':
        case 'after_drop':
        case 'after_query':
            $fnc    = array($obj, $event);
            $params = "events_params";
            if (is_callable($fnc)) {
                call_user_func_array($fnc, $$params);
            }
            break;
        }
    }
    // }}}

     // void runFilter(string $key, mixed &$value, mixed $past_value) {{{
    /**
     *  *Internal Method* 
     *
     *  This method check if the current document property has
     *  a filter method, if so, call it.
     *  
     *  If the filter returns FALSE, throw an Exception.
     *
     *  @return void
     */
    protected function runFilter($key, &$value, $past_value)
    {
        $filter = array($this, "{$key}_filter");
        if (is_callable($filter)) {
            $filter = call_user_func_array($filter, array(&$value, $past_value));
            if ($filter===FALSE) {
                throw new ActiveMongo_FilterException("{$key} filter failed");
            }
            $this->$key = $value;
        }
    }
    // }}}

    // }}}

    // void setCursor(MongoCursor $obj) {{{
    /**
     *  Set Cursor
     *
     *  This method receive a MongoCursor and make
     *  it iterable. 
     *
     *  @param MongoCursor $obj 
     *
     *  @return void
     */
    final protected function setCursor(MongoCursor $obj)
    {
        $this->_cursor = $obj;
        $obj->reset();
        $this->setResult($obj->getNext());
    }
    // }}}

    // void setResult(Array $obj) {{{
    /**
     *  Set Result
     *
     *  This method takes an document and copy it
     *  as properties in this object.
     *
     *  @param Array $obj
     *
     *  @return void
     */
    final protected function setResult($obj)
    {
        /* Unsetting previous results, if any */
        foreach (array_keys(get_document_vars($this, FALSE)) as $key) {
            unset($this->$key);
        }
        $this->_id = NULL;

        /* Add our current resultset as our object's property */
        foreach ((array)$obj as $key => $value) {
            if ($key[0] == '$') {
                continue;
            }
            $this->$key = $value;
        }
        
        /* Save our record */
        $this->_current = $obj;
    }
    // }}}

    // this find([$_id]) {{{
    /**
     *    Simple find.
     *
     *    Really simple find, which uses this object properties
     *    for fast filtering
     *
     *    @return object this
     */
    final function find($_id = NULL)
    {
        $vars = get_document_vars($this);
        $parent_class = __CLASS__;
        foreach ($vars as $key => $value) {
            if (!$value) {
                unset($vars[$key]);
            }
            if ($value InstanceOf $parent_class) {
                $this->getColumnDeference($vars, $key, $value);
                unset($vars[$key]); /* delete old value */
            }
        }
        if ($_id != NULL) {
            if (is_array($_id)) {
                $vars['_id'] = array('$in' => $_id);
            } else {
                $vars['_id'] = $_id;
            }
        }
        $res  = $this->_getCollection()->find($vars);
        $this->setCursor($res);
        return $this;
    }
    // }}}

    // void save(bool $async) {{{
    /**
     *    Save
     *
     *    This method save the current document in MongoDB. If
     *    we're modifying a document, a update is performed, otherwise
     *    the document is inserted.
     *
     *    On updates, special operations such as $set, $pushAll, $pullAll
     *    and $unset in order to perform efficient updates
     *
     *    @param bool $async 
     *
     *    @return void
     */
    final function save($async=TRUE)
    {
        $update   = isset($this->_id) && $this->_id InstanceOf MongoID;
        $conn     = $this->_getCollection();
        $document = $this->getCurrentDocument($update);
        $object   = $this->getDocumentVars();

        if (isset($this->_id)) {
            $object['_id'] = $this->_id;
        }

        if (count($document) == 0) {
            return; /*nothing to do */
        }

         /* PRE-save hook */
        $this->triggerEvent('before_'.($update ? 'update' : 'create'), array(&$document, $object));

        if ($update) {
            $conn->update(array('_id' => $this->_id), $document, array('safe' => $async));
            if (isset($document['$set'])) {
                foreach ($document['$set'] as $key => $value) {
                    if (strpos($key, ".") === FALSE) {
                        $this->_current[$key] = $value;
                        $this->$key = $value;
                    } else {
                        $keys = explode(".", $key);
                        $key  = $keys[0];
                        $arr  = & $this->$key;
                        $arrc = & $this->_current[$key];
                        for ($i=1; $i < count($keys)-1; $i++) {
                            $arr  = &$arr[$keys[$i]];
                            $arrc = &$arrc[$keys[$i]]; 
                        }
                        $arr [ $keys[$i] ] = $value;
                        $arrc[ $keys[$i] ] = $value;
                    }
                }
            }
            if (isset($document['$unset'])) {
                foreach ($document['$unset'] as $key => $value) {
                    if (strpos($key, ".") === FALSE) {
                        unset($this->_current[$key]);
                        unset($this->$key);
                    } else {
                        $keys = explode(".", $key);
                        $key  = $keys[0];
                        $arr  = & $this->$key;
                        $arrc = & $this->_current[$key];
                        for ($i=1; $i < count($keys)-1; $i++) {
                            $arr  = &$arr[$keys[$i]];
                            $arrc = &$arrc[$keys[$i]]; 
                        }
                        unset($arr [ $keys[$i] ]);
                        unset($arrc[ $keys[$i] ]);
                    }
                }
            }
        } else {
            $conn->insert($document, $async);
            $this->setResult($document);
        }

        $this->triggerEvent('after_'.($update ? 'update' : 'create'), array($document, $object));

        return TRUE;
    }
    // }}}

    // bool delete() {{{
    /**
     *  Delete the current document
     *  
     *  @return bool
     */
    final function delete()
    {
        
        $document = array('_id' => $this->_id);
        if ($this->_cursor InstanceOf MongoCursor) {
            $this->triggerEvent('before_delete', array($document));
            $result = $this->_getCollection()->remove($document);
            $this->triggerEvent('after_delete', array($document));
            $this->setResult(array());
            return $result;
        } else {
            $criteria = (array) $this->_query;

            /* remove */
            $this->triggerEvent('before_delete', array($document));
            $this->_getCollection()->remove($criteria);
            $this->triggerEvent('after_delete', array($document));

            /* reset object */
            $this->reset();

            return TRUE;
        }
        return FALSE;
    }
    // }}}

    // Update {{{
    /**
     *  Multiple updates.
     *  
     *  This method perform multiple updates when a given 
     *  criteria matchs (using where).
     *
     *  By default the update is perform safely, but it can be 
     *  changed.
     *
     *  After the operation is done, the criteria is deleted.
     *
     *  @param array $value Values to set
     *  @param bool  $safe  Whether or not peform the operation safely
     *
     *  @return bool
     *
     */
    function update(Array $value, $safe=TRUE)
    {
        $this->_assertNotInQuery();

        $criteria = (array) $this->_query;
        $options  = array('multiple' => TRUE, 'safe' => $safe);

        /* update */
        $col = $this->_getCollection();
        $col->update($criteria, array('$set' => $value), $options);

        /* reset object */
        $this->reset();

        return TRUE;
    }
    // }}}

    // void drop() {{{
    /**
     *  Delete the current colleciton and all its documents
     *  
     *  @return void
     */
    final static function drop()
    {
        $class = get_called_class();
        if ($class == __CLASS__) {
            return FALSE;
        }
        $obj = new $class;
        $obj->triggerEvent('before_drop');
        $result = $obj->_getCollection()->drop();
        $obj->triggerEvent('after_drop');
        if ($result['ok'] != 1) {
            throw new ActiveMongo_Exception($result['errmsg']);
        }
        return TRUE;
        
    }
    // }}}

    // int count() {{{
    /**
     *  Return the number of documents in the actual request. If
     *  we're not in a request, it will return 0.
     *
     *  @return int
     */
    final function count()
    {
        if ($this->valid()) {
            return $this->_cursor->count();
        }
        return 0;
    }
    // }}}

    // void setup() {{{
    /**
     *  This method should contain all the indexes, and shard keys
     *  needed by the current collection. This try to make
     *  installation on development environments easier.
     */
    function setup()
    {
    }
    // }}}

    // batchInsert {{{
    /** 
     *  Perform a batchInsert of objects.
     *
     *  @param array $documents         Arrays of documents to insert
     *  @param bool  $safe              True if a safe will be performed, this means data validation, and wait for MongoDB OK reply
     *  @param bool  $on_error_continue If an error happen while validating an object, if it should continue or not
     *
     *  @return bool
     */
    final public static function batchInsert(Array $documents, $safe=TRUE, $on_error_continue=TRUE)
    {
        $context = get_called_class();

        if (__CLASS__ == $context) {
            throw new ActiveMongo_Exception("Invalid batchInsert usage");
        }

        
        if ($safe) {
            foreach ($documents as $id => $doc) {
                $valid = FALSE;
                if (is_array($doc)) {
                    try {
                        self::triggerEvent('before_create', array(&$doc), $context);
                        self::triggerEvent('before_validate', array(&$doc, $doc), $context);
                        self::triggerEvent('before_validate_creation', array(&$doc, $doc), $context);
                        $documents[$id] = $doc;
                        $valid = TRUE;
                    } catch (Exception $e) {}
                }
                if (!$valid) {
                    if (!$on_error_continue) {
                        throw new ActiveMongo_FilterException("Document $id is invalid");
                    }
                    unset($documents[$id]);
                }
            }
        }

        return self::_getCollection()->batchInsert($documents, array("safe" => $safe));
    }
    // }}}

    // bool addIndex(array $columns, array $options) {{{
    /**
     *  addIndex
     *  
     *  Create an Index in the current collection.
     *
     *  @param array $columns L ist of columns
     *  @param array $options Options
     *
     *  @return bool
     */
    final function addIndex($columns, $options=array())
    {
        $default_options = array(
            'background' => 1,
        );

        if (!is_array($columns)) {
            $columns = array($columns => 1);
        }

        foreach ($columns as $id => $name) {
            if (is_numeric($id)) {
                unset($columns[$id]);
                $columns[$name] = 1;
            }
        }

       foreach ($default_options as $option => $value) {
            if (!isset($options[$option])) {
                $options[$option] = $value;
            }
        }

        $collection = $this->_getCollection();

        return $collection->ensureIndex($columns, $options);
    }
    // }}}

    // Array getIndexes() {{{
    /**
     *  Return an array with all indexes
     *
     *  @return array
     */
    final static function getIndexes()
    {
        return self::_getCollection()->getIndexInfo();
    }
    // }}}

    // string __toString() {{{
    /**
     *  To String
     *
     *  If this object is treated as a string,
     *  it would return its ID.
     *
     *  @return string
     */
    function __toString()
    {
        return (string)$this->getID();
    }
    // }}}

    // array sendCmd(array $cmd) {{{
    /**
     *  This method sends a command to the current
     *  database.
     *
     *  @param array $cmd Current command
     *
     *  @return array
     */
    final protected function sendCmd($cmd)
    {
        return $this->_getConnection()->command($cmd);
    }
    // }}}

    // ITERATOR {{{

    // array getArray() {{{
    /**
     *  Return the current document as an array 
     *  instead of a ActiveMongo object
     *
     *  @return Array
     */
    final function getArray()
    {
        return get_document_vars($this);
    }
    // }}}

    // void reset() {{{
    /**
     *  Reset our Object, delete the current cursor if any, and reset
     *  unsets the values.
     *
     *  @return void
     */
    final function reset()
    {
        if ($this->_cloned) {
            throw new ActiveMongo_Exception("Cloned objects can't be reseted");
        }
        $this->_properties = NULL;
        $this->_cursor     = NULL;
        $this->_cursor_ex  = NULL;
        $this->_query      = NULL;
        $this->_sort       = NULL;
        $this->_limit      = 0;
        $this->_skip       = 0;
        $this->setResult(array());
    }
    // }}}

    // bool valid() {{{
    /**
     *    Valid
     *
     *    Return if we're on an iteration and if it is still valid
     *
     *    @return TRUE
     */
    final function valid()
    {
        $valid = FALSE;
        if (!$this->_cursor_ex) {
            if (!$this->_cursor InstanceOf MongoCursor) {
                 $this->doQuery();
            }
            $valid = $this->_cursor InstanceOf MongoCursor && $this->_cursor->valid();
        } else {
            switch ($this->_cursor_ex) {
            case self::FIND_AND_MODIFY:
                if ($this->_limit > $this->_findandmodify_cnt) {
                    $this->_execFindAndModify();
                    $valid = $this->_cursor_ex_value['ok'] == 1;
                }
                break;
            default:
                throw new ActiveMongo_Exception("Invalid _cursor_ex value");
            }
        }

        return $valid;
    }
    // }}}

    // bool next() {{{
    /**
     *    Move to the next document
     *
     *    @return bool
     */
    final function next()
    {
        if ($this->_cloned) {
            throw new ActiveMongo_Exception("Cloned objects can't iterate");
        }
        if (!$this->_cursor_ex) {
            $result =  $this->_cursor->next();
            $this->current();
            return $result;
        } else {
            switch ($this->_cursor_ex) {
            case self::FIND_AND_MODIFY:
                $this->_cursor_ex_value = NULL;
                break;
            default:
                throw new ActiveMongo_Exception("Invalid _cursor_ex value");
            }
        }
    }
    // }}}

    // this current() {{{
    /**
     *    Return the current object, and load the current document
     *    as this object property
     *
     *    @return object 
     */
    final function current()
    { 
        if (!$this->_cursor_ex) {
            $this->setResult($this->_cursor->current());
        } else {
            switch ($this->_cursor_ex) {
            case self::FIND_AND_MODIFY:
                if (count($this->_cursor_ex_value) == 0) {
                    $this->_execFindAndModify();
                }
                $this->setResult($this->_cursor_ex_value['value']);
                break;
            default:
                throw new ActiveMongo_Exception("Invalid _cursor_ex value");
            }
        }
        return $this;
    }
    // }}}

    // bool rewind() {{{
    /**
     *    Go to the first document
     */
    final function rewind()
    {
        if ($this->_cloned) {
            throw new ActiveMongo_Exception("Cloned objects can't iterate");
        }
        if (!$this->_cursor_ex) {
            /* rely on MongoDB cursor */
            if (!$this->_cursor InstanceOf MongoCursor) {
                $this->doQuery();
            }
            $result = $this->_cursor->rewind();
            $this->current();
            return $result;
        } else {
            switch ($this->_cursor_ex) {
            case self::FIND_AND_MODIFY:
                $this->_findandmodify_cnt = 0;
                break;
            default:
                throw new ActiveMongo_Exception("Invalid _cursor_ex value");
            }
        }
    }
    // }}}
    
    // }}}

    //  ARRAY ACCESS {{{
    final function offsetExists($offset)
    {
        return isset($this->$offset);
    }
    
    final function offsetGet($offset)
    {
        return $this->$offset;
    }

    final function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }

    final function offsetUnset($offset)
    {
        unset($this->$offset);
    }
    // }}}

    // REFERENCES {{{

    // array getReference() {{{
    /**
     *  ActiveMongo extended the Mongo references, adding
     *  the concept of 'dynamic' requests, saving in the database
     *  the current query with its options (sort, limit, etc).
     *
     *  This is useful to associate a document with a given 
     *  request. To undestand this better please see the 'reference'
     *  example.
     *
     *  @return array
     */
    final function getReference($dynamic=FALSE)
    {
        if (!$this->getID() && !$dynamic) {
            return NULL;
        }

        $document = array(
            '$ref'  => $this->CollectionName(), 
            '$id'   => $this->getID(),
            '$db'   => $this->getDatabaseName(),
            'class' => get_class($this),
        );

        if ($dynamic) {
            if (!$this->_cursor InstanceOf MongoCursor && $this->_cursor_ex === NULL) {
                $this->doQuery();
            }

            if (!$this->_cursor InstanceOf MongoCursor) {
                throw new ActiveMongo_Exception("Only MongoDB native cursor could have dynamic references");
            }

            $cursor = $this->_cursor;
            if (!is_callable(array($cursor, "Info"))) {
                throw new Exception("Please upgrade your PECL/Mongo module to use this feature");
            }
            $document['dynamic'] = array();
            $query  = $cursor->Info();
            foreach ($query as $type => $value) {
                $document['dynamic'][$type] = $value;
            }
        }
        return $document;
    }
    // }}}

    // void getDocumentReferences($document, &$refs) {{{
    /**
     *  Get Current References
     *
     *  Inspect the current document trying to get any references,
     *  if any.
     *
     *  @param array $document   Current document
     *  @param array &$refs      References found in the document.
     *  @param array $parent_key Parent key
     *
     *  @return void
     */
    final protected function getDocumentReferences($document, &$refs, $parent_key=NULL)
    {
        foreach ($document as $key => $value) {
           if (is_array($value)) {
               if (MongoDBRef::isRef($value)) {
                   $pkey   = $parent_key;
                   $pkey[] = $key;
                   $refs[] = array('ref' => $value, 'key' => $pkey);
               } else {
                   $parent_key1 = $parent_key;
                   $parent_key1[] = $key;
                   $this->getDocumentReferences($value, $refs, $parent_key1);
               }
           }
        }
    }
    // }}}

    // object _deferencingCreateObject(string $class) {{{
    /**
     *  Called at deferencig time
     *
     *  Check if the given string is a class, and it is a sub class
     *  of ActiveMongo, if it is instance and return the object.
     *
     *  @param string $class
     *
     *  @return object
     */
    private function _deferencingCreateObject($class)
    {
        if (!is_subclass_of($class, __CLASS__)) {
            throw new ActiveMongo_Exception("Fatal Error, imposible to create ActiveMongo object of {$class}");
        }
        return new $class;
    }
    // }}}

    // void _deferencingRestoreProperty(array &$document, array $keys, mixed $req) {{{
    /**
     *  Called at deferencig time
     *
     *  This method iterates $document until it could match $keys path, and 
     *  replace its value by $req.
     *
     *  @param array &$document Document to replace
     *  @param array $keys      Path of property to change
     *  @param mixed $req       Value to replace.
     *
     *  @return void
     */
    private function _deferencingRestoreProperty(&$document, $keys, $req)
    {
        $obj = & $document;

        /* find the $req proper spot */
        foreach ($keys as $key) {
            $obj = & $obj[$key];
        }

        $obj = $req;

        /* Delete reference variable */
        unset($obj);
    }
    // }}}

    // object _deferencingQuery($request) {{{
    /**
     *  Called at deferencig time
     *  
     *  This method takes a dynamic reference and request
     *  it to MongoDB.
     *
     *  @param array $request Dynamic reference
     *
     *  @return this
     */
    private function _deferencingQuery($request)
    {
        $collection = $this->_getCollection();
        $cursor     = $collection->find($request['query'], $request['fields']);
        if ($request['limit'] > 0) {
            $cursor->limit($request['limit']);
        }
        if ($request['skip'] > 0) {
            $cursor->skip($request['skip']);
        }

        $this->setCursor($cursor);

        return $this;
    }
    // }}}

    // void doDeferencing() {{{
    /**
     *  Perform a deferencing in the current document, if there is
     *  any reference.
     *
     *  ActiveMongo will do its best to group references queries as much 
     *  as possible, in order to perform as less request as possible.
     *
     *  ActiveMongo doesn't rely on MongoDB references, but it can support 
     *  it, but it is prefered to use our referencing.
     *
     *  @experimental
     */
    final function doDeferencing($refs=array())
    {
        /* Get current document */
        $document = get_document_vars($this);

        if (count($refs)==0) {
            /* Inspect the whole document */
            $this->getDocumentReferences($document, $refs);
        }

        $db = $this->_getConnection();

        /* Gather information about ActiveMongo Objects
         * that we need to create
         */
        $classes = array();
        foreach ($refs as $ref) {
            if (!isset($ref['ref']['class'])) {

                /* Support MongoDBRef, we do our best to be compatible {{{ */
                /* MongoDB 'normal' reference */

                $obj = MongoDBRef::get($db, $ref['ref']);

                /* Offset the current document to the right spot */
                /* Very inefficient, never use it, instead use ActiveMongo References */

                $this->_deferencingRestoreProperty($document, $ref['key'], $obj);

                /* Dirty hack, override our current document 
                 * property with the value itself, in order to
                 * avoid replace a MongoDB reference by its content
                 */
                $this->_deferencingRestoreProperty($this->_current, $ref['key'], $obj);

                /* }}} */

            } else {

                if (isset($ref['ref']['dynamic'])) {
                    /* ActiveMongo Dynamic Reference */

                    /* Create ActiveMongo object */
                    $req = $this->_deferencingCreateObject($ref['ref']['class']);
                    
                    /* Restore saved query */
                    $req->_deferencingQuery($ref['ref']['dynamic']);
                   
                    $results = array();

                    /* Add the result set */
                    foreach ($req as $result) {
                        $results[]  = clone $result;
                    }

                    /* add  information about the current reference */
                    foreach ($ref['ref'] as $key => $value) {
                        $results[$key] = $value;
                    }

                    $this->_deferencingRestoreProperty($document, $ref['key'], $results);

                } else {
                    /* ActiveMongo Reference FTW! */
                    $classes[$ref['ref']['class']][] = $ref;
                }
            }
        }

        /* {{{ Create needed objects to query MongoDB and replace
         * our references by its objects documents. 
         */
        foreach ($classes as $class => $refs) {
            $req = $this->_deferencingCreateObject($class);

            /* Load list of IDs */
            $ids = array();
            foreach ($refs as $ref) {
                $ids[] = $ref['ref']['$id'];
            }

            /* Search to MongoDB once for all IDs found */
            $req->find($ids);


            /* Replace our references by its objects */
            foreach ($refs as $ref) {
                $id    = $ref['ref']['$id'];
                $place = $ref['key'];
                foreach ($req as $item) {
                    if ($item->getID() == $id) {
                        $this->_deferencingRestoreProperty($document, $place, clone $req);
                    }
                }
                unset($obj);
            }

            /* Release request, remember we
             * safely cloned it,
             */
            unset($req);
        }
        // }}}

        /* Replace the current document by the new deferenced objects */
        foreach ($document as $key => $value) {
            $this->$key = $value;
        }
    }
    // }}}

    // void getColumnDeference(&$document, $propety, ActiveMongo Obj) {{{
    /**
     *  Prepare a "selector" document to search treaing the property
     *  as a reference to the given ActiveMongo object.
     *
     */
    final function getColumnDeference(&$document, $property, ActiveMongo $obj)
    {
        $document["{$property}.\$id"] = $obj->getID();
    }
    // }}}

    // void findReferences(&$document) {{{
    /**
     *  Check if in the current document to insert or update
     *  exists any references to other ActiveMongo Objects.
     *
     *  @return void
     */
    final function findReferences(&$document)
    {
        if (!is_array($document)) {
            return;
        }
        foreach($document as &$value) {
            $parent_class = __CLASS__;
            if (is_array($value)) {
                if (MongoDBRef::isRef($value)) {
                    /*  If the property we're inspecting is a reference,
                     *  we need to remove the values, restoring the valid
                     *  Reference.
                     */
                    $arr = array(
                        '$ref'=>1, '$id'=>1, '$db'=>1, 'class'=>1, 'dynamic'=>1
                    );
                    foreach (array_keys($value) as $key) {
                        if (!isset($arr[$key])) {
                            unset($value[$key]);
                        }
                    }
                } else {
                    $this->findReferences($value);
                }
            } else if ($value InstanceOf $parent_class) {
                $value = $value->getReference();
            }
        }
        /* trick: delete last var. reference */
        unset($value);
    }
    // }}}

    // void __clone() {{{
    /** 
     *  Cloned objects are rarely used, but ActiveMongo
     *  uses it to create different objects per everyrecord,
     *  which is used at deferencing. Therefore cloned object
     *  do not contains the recordset, just the actual document,
     *  so iterations are not allowed.
     *
     */
    final function __clone()
    {
        if (!$this->_current) {
            throw new ActiveMongo_Exception("Empty objects can't be cloned");
        }
        unset($this->_cursor);
        $this->_cloned = TRUE;
    }
    // }}}

    // }}}

    // GET DOCUMENT ID {{{

    // getID() {{{
    /**
     *  Return the current document ID. If there is
     *  no document it would return FALSE.
     *
     *  @return object|FALSE
     */
    final public function getID()
    {
        if ($this->_id instanceof MongoID) {
            return $this->_id;
        }
        return FALSE;
    }
    // }}}
   
    // string key() {{{
    /**
     *    Return the current key
     *
     *    @return string
     */
    final function key()
    {
        return (string)$this->getID();
    }
    // }}}

    // }}}

    // Fancy (and silly) query abstraction {{{

    // _assertNotInQuery() {{{
    /**
     *  Check if we can modify the query or not. We cannot modify
     *  the query if we're iterating over and oldest query, in this case the
     *  object must be reset.
     *
     *  @return void
     */
    final private function _assertNotInQuery()
    {
        if ($this->_cloned || $this->_cursor InstanceOf MongoCursor || $this->_cursor_ex != NULL) {
            throw new ActiveMongo_Exception("You cannot modify the query, please reset the object");
        }
    }
    // }}}

    // bool servedFromCache() {{{
    /**
     *  Return True if the current result
     *  was provided by a before_query hook (aka cache)
     *  or False if it was retrieved from MongoDB
     *
     *  @return bool
     */
    final function servedFromCache()
    {
        return $this->_cached;
    }
    // }}}

    // doQuery() {{{
    /**
     *  Build the current request and send it to MongoDB.
     *
     *  @return this
     */
    final function doQuery($use_cache=TRUE)
    {
        if ($this->_cursor_ex) {
            switch ($this->_cursor_ex) {
            case self::FIND_AND_MODIFY:
                $this->_cursor_ex_value = NULL;
                return;
            default:
                throw new ActiveMongo_Exception("Invalid _cursor_ex value");
            }
        }
        $this->_assertNotInQuery();

        $query = array(
            'collection' => $this->CollectionName(),
            'query'      => (array)$this->_query, 
            'properties' => (array)$this->_properties,
            'sort'       => (array)$this->_sort, 
            'skip'       => $this->_skip,
            'limit'      => $this->_limit
        );

        $this->_cached = FALSE;

        self::triggerEvent('before_query', array(&$query, &$documents, $use_cache));

        if ($documents InstanceOf MongoCursor && $use_cache) {
            $this->_cached = TRUE;
            $this->setCursor($documents);    
            return $this;
        }

        $col = $this->_getCollection();
        if (count($query['properties']) > 0) {
            $cursor = $col->find($query['query'], $query['properties']);
        } else {
            $cursor = $col->find($query['query']);
        }
        if (count($query['sort']) > 0) {
            $cursor->sort($query['sort']);
        }
        if ($query['limit'] > 0) {
            $cursor->limit($query['limit']);
        }
        if ($query['skip'] > 0) {
            $cursor->skip($query['skip']);
        }

        self::triggerEvent('after_query', array($query, $cursor));
        
        /* Our cursor must be sent to ActiveMongo */
        $this->setCursor($cursor);

        return $this;
    }
    // }}}

    // properties($props) {{{
    /**
     *  Select 'properties' or 'columns' to be included in the document,
     *  by default all properties are included.
     *
     *  @param array $props
     *
     *  @return this
     */ 
    final function properties($props)
    {
        $this->_assertNotInQuery();

        if (!is_array($props) && !is_string($props)) {
            return FALSE;
        }

        if (is_string($props)) {
            $props = explode(",", $props);
        }

        foreach ($props as $id => $name) {
            $props[trim($name)] = 1;
            unset($props[$id]);
        }


        /* _id should always be included */
        $props['_id'] = 1;

        $this->_properties = $props;

        return $this;
    }

    final function columns($properties)
    {
       return $this->properties($properties);
    }
    // }}}

    // where($property, $value) {{{
    /**
     *  Where abstraction.
     *
     */
    final function where($property_str, $value=NULL)
    {
        $this->_assertNotInQuery();

        if (is_array($property_str)) {
            if ($value != NULL) {
                throw new ActiveMongo_Exception("Invalid parameters");
            }
            foreach ($property_str as $property => $value) {
                if (is_numeric($property)) {
                    $property = $value;
                    $value    = NULL;
                }
                $this->where($property, $value);
            }
            return $this;
        }

        $column = explode(" ", trim($property_str));
        if (count($column) != 1 && count($column) != 2) {
            throw new ActiveMongo_Exception("Failed while parsing '{$property_str}'");
        } else if (count($column) == 2) {

            $exp_scalar = TRUE;
            switch (strtolower($column[1])) {
            case '>':
            case '$gt':
                $op = '$gt';
                break;

            case '>=':
            case '$gte':
                $op = '$gte';
                break;

            case '<':
            case '$lt':
                $op = '$lt';
                break;

            case '<=':
            case '$lte':
                $op = '$lte';
                break;

            case '==':
            case '$eq':
            case '=':
                if (is_array($value)) {
                    $op = '$all';
                    $exp_scalar = FALSE;
                } else {
                    $op = NULL;
                }
                break;

            case '!=':
            case '<>':
            case '$ne':
                if (is_array($value)) {
                    $op = '$nin';
                    $exp_scalar = FALSE;
                } else {
                    $op = '$ne';
                }
                break;

            case '%':
            case 'mod':
            case '$mod':
                $op         = '$mod';
                $exp_scalar = FALSE;
                break;

            case 'exists':
            case '$exists':
                if ($value === NULL) {
                    $value = 1;
                }
                $op = '$exists';
                break;

            /* regexp  */
            case 'regexp':
            case 'regex':
                $value = new MongoRegex($value);
                $op = NULL;
                break;

            /* arrays */
            case 'in':
            case '$in':
                $exp_scalar = FALSE;
                $op = '$in';
                break;

            case '$nin':
            case 'nin':
                $exp_scalar = FALSE;
                $op = '$nin';
                break;


            /* geo operations */
            case 'near':
            case '$near':
                $op = '$near';
                $exp_scalar = FALSE;
                break;

            default:
                throw new ActiveMongo_Exception("Failed to parse '{$column[1]}'");
            }

            if ($exp_scalar && is_array($value)) {
                throw new ActiveMongo_Exception("Cannot use comparing operations with Array");
            } else if (!$exp_scalar && !is_array($value)) {
                throw new ActiveMongo_Exception("The operation {$column[1]} expected an Array");
            }

            if ($op) {
                $value = array($op => $value);
            }
        } else if (is_array($value)) {
            $value = array('$in' => $value);
        }

        $spot = & $this->_query[$column[0]];
        if (is_array($spot) && is_array($value)) {
            $spot[key($value)] =  current($value);
        } else {
            /* simulate AND among same properties if 
             * multiple values is passed for same property
             */
            if (isset($spot)) {
                if (is_array($spot)) {
                    $spot['$all'][] = $value;
                } else {
                    $spot = array('$all' => array($spot, $value));
                }
            } else {
                $spot =  $value;
            }
        }

        return $this;
    }
    // }}}

    // sort($sort_str) {{{
    /**
     *  Abstract the documents sorting.
     *
     *  @param string $sort_str List of properties to use as sorting
     *
     *  @return this
     */
    final function sort($sort_str)
    {
        $this->_assertNotInQuery();

        $this->_sort = array();
        foreach ((array)explode(",", $sort_str) as $sort_part_str) {
            $sort_part = explode(" ", trim($sort_part_str), 2);
            switch(count($sort_part)) {
            case 1:
                $sort_part[1] = 'ASC';
                break;
            case 2:
                break;
            default:
                throw new ActiveMongo_Exception("Don't know how to parse {$sort_part_str}");
            }

            /* Columns name can't be empty */
            if (!trim($sort_part[0])) {
                throw new ActiveMongo_Exception("Don't know how to parse {$sort_part_str}");
            }

            switch (strtoupper($sort_part[1])) {
            case 'ASC':
                $sort_part[1] = 1;
                break;
            case 'DESC':
                $sort_part[1] = -1;
                break;
            default:
                throw new ActiveMongo_Exception("Invalid sorting direction `{$sort_part[1]}`");
            }
            $this->_sort[ $sort_part[0] ] = $sort_part[1];
        }

        return $this;
    }
    // }}}

    // limit($limit, $skip) {{{
    /**
     *  Abstract the limitation and pagination of documents.
     *
     *  @param int $limit Number of max. documents to retrieve
     *  @param int $skip  Number of documents to skip
     *
     *  @return this
     */
    final function limit($limit=0, $skip=0)
    {
        $this->_assertNotInQuery();

        if ($limit < 0 || $skip < 0) {
            return FALSE;
        }
        $this->_limit = $limit;
        $this->_skip  = $skip;

        return $this;
    }
    // }}}

    // FindAndModify(Array $document) {{{
    /**
     *  findAndModify
     *  
     *
     */
    final function findAndModify($document)
    {
        $this->_assertNotInQuery();

        if (count($document) === 0) {
            throw new ActiveMongo_Exception("Empty \$document is not allowed");
        }

        $this->_cursor_ex     = self::FIND_AND_MODIFY;
        $this->_findandmodify = $document;

        return $this;
    }

    private function _execFindAndModify()
    {
        $query = (array)$this->_query;

        $query = array(
            "findandmodify" => $this->CollectionName(),
            "query" => $query,
            "update" => array('$set' => $this->_findandmodify),
            "new" => TRUE,
        );
        if (isset($this->_sort)) {
            $query["sort"] =  $this->_sort;
        }
        $this->_cursor_ex_value = $this->sendCMD($query);

        $this->_findandmodify_cnt++;
    }
    // }}}

    // }}}

    // __sleep() {{{
    /**
     *  Return a list of properties to serialize, to save
     *  into MongoDB
     *
     *  @return array
     */
    function __sleep()
    {
        return array_keys(get_document_vars($this));
    }
    // }}}

}

require_once dirname(__FILE__)."/Validators.php";
require_once dirname(__FILE__)."/Exceptions.php";

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */

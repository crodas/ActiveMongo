<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2009 The PHP Group                                     |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.0 of the PHP license,       |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_0.txt.                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Authors: Cesar Rodas <crodas@php.net>                                |
  +----------------------------------------------------------------------+
*/


// Class FilterException {{{
/**
 *  FilterException
 *
 *  This is Exception is thrown if any validation
 *  fails when save() is called.
 *
 */
final class FilterException extends Exception 
{
}
// }}}

// array get_object_vars_ex(stdobj $obj) {{{
/**
 *  Simple hack to avoid get private and protected variables
 *
 *  @param obj 
 *
 *  @return array
 */
function get_object_vars_ex($obj) 
{
    return get_object_vars($obj);
}
// }}}

/**
 *  ActiveMongo
 *
 *  Simple ActiveRecord pattern built on top of MongoDB. This class
 *  aims to provide easy iteration, data validation before update,
 *  and efficient update.
 *
 *  @author César D. Rodas <crodas@php.net>
 *  @license PHP License
 *  @package ActiveMongo
 *  @version 1.0
 *
 */
abstract class ActiveMongo implements Iterator 
{

    // properties {{{
    /**
     *    Current collections
     *      
     *    @type array
     */
    private static $_collections;
    /**
     *    Current connection to MongoDB
     *
     *    @type MongoConnection
     */
    private static $_conn;
    /**
     *    Database name
     *
     *    @type string
     */
    private static $_db;
    /**
     *    Host name
     *
     *    @type string
     */
    private static $_host;
    /**
     *    Current document
     *
     *    @type array
     */
    private $_current = array();
    /**
     *    Result cursor
     *
     *    @type MongoCursor
     */
    private $_cursor  = null;
    /**
     *    Current document ID
     *    
     *    @type MongoID
     */
    private $_id;
    // }}}

    // string _getCollectionName() {{{
    /**
     *  Get Collection Name, by default the class name,
     *  but you it can be override at the class itself to give
     *  a custom name.
     *
     *  @return string Colleciton Name
     */
    protected function _getCollectionName()
    {
        return strtolower(get_class($this));
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
            if ($class == 'ActiveMongo') {
                break;
            }
            if (is_subclass_of($class, 'ActiveMongo')) {
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
     *
     *  @return void
     */
    final public static function connect($db, $host='localhost')
    {
        self::$_host = $host;
        self::$_db   = $db;
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
    final protected static function _getConnection()
    {
        if (is_null(self::$_conn)) {
            self::$_conn = new Mongo(self::$_host);
        }
        return self::$_conn->selectDB(self::$_db);
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
        $colName = $this->_getCollectionName();
        return self::_getConnection()->selectCollection($colName);
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
     *  @return false
     */
    function getCurrentSubDocument(&$document, $parent_key, Array $values, Array $past_values)
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
                if (!isset($past_values[$key]) || !is_array($past_values[$key])) {
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
                        return false;
                    }
                }
                continue;
            }
            if (!isset($past_values[$key]) || $past_values[$key] != $value) {
                $document['$set'][$super_key] = $value;
            }
        }

        foreach (array_diff(array_keys($past_values), array_keys($values)) as $key) {
            $super_key = "{$parent_key}.{$key}";
            $document['$unset'][$super_key] = 1;
        }

        return true;
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
    final protected function getCurrentDocument($update=false, $current=false)
    {
        $document = array();
        $object   = get_object_vars_ex($this);

        if (!$current) {
            $current = (array)$this->_current;
        }

        foreach ($object as $key => $value) {
            if (!$value) {
                continue;
            }
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
                        $this->_call_filter($key, $value, $current[$key]);
                        $document['$set'][$key] = $value;
                        continue;
                    }

                    if (!$this->getCurrentSubDocument($document, $key, $value, $current[$key])) {
                        throw new Exception("{$key}: Array and documents are not compatible");
                    }
                } else if(!isset($current[$key]) || $value !== $current[$key]) {
                    /**
                     *  It is 'linear' field that has changed, or 
                     *  has been modified.
                     */
                    $past_value = isset($current[$key]) ? $current[$key] : null;
                    $this->_call_filter($key, $value, $past_value);
                    $document['$set'][$key] = $value;
                }
            } else {
                /**
                 *  It is a document insertation, so we 
                 *  create the document.
                 */
                $this->_call_filter($key, $value, null);
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
        return $document;
    }
    // }}}

    // void _call_filter(string $key, mixed &$value, mixed $past_value) {{{
    /**
     *  *Internal Method* 
     *
     *  This method check if the current document property has
     *  a filter method, if so, call it.
     *  
     *  If the filter returns false, throw an Exception.
     *
     *  @return void
     */
    private function _call_filter($key, &$value, $past_value)
    {
        $filter = array($this, "{$key}_filter");
        if (is_callable($filter)) {
            $filter = call_user_func_array($filter, array(&$value, $past_value));
            if ($filter===false) {
                throw new FilterException("{$key} filter failed");
            }
            $this->$key = $value;
        }
    }
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
        $this->setResult($obj->getNext());
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
        $this->_count = 0;
        $this->_cursor = null;
        $this->setResult(array());
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
        foreach (array_keys((array)$this->_current) as $key) {
            unset($this->$key);
        }

        /* Add our current resultset as our object's property */
        foreach ((array)$obj as $key => $value) {
            $this->$key = $value;
        }
        
        /* Save our record */
        $this->_current = $obj;
    }
    // }}}

    // this find([$_id]) {{{
    /**
     *    Simple find
     *
     *    Really simple find, which uses this object properties
     *    for fast filtering
     *
     *    @return object this
     */
    function find(MongoID $_id = null)
    {
        $vars = $this->getCurrentDocument();
        if ($_id != null) {
            $vars['_id'] = $_id;
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
    final function save($async=true)
    {
        $update = isset($this->_id) && $this->_id InstanceOf MongoID;
        $conn   = $this->_getCollection();
        $obj    = $this->getCurrentDocument($update);
        if (count($obj) == 0) {
            return; /*nothing to do */
        }
        /* PRE-save hook */
        $this->pre_save($update ? 'update' : 'create', $obj);
        if ($update) {
            $conn->update(array('_id' => $this->_id), $obj);
            $conn->save($obj);
            foreach ($obj as $key => $value) {
                $this->_current[$key] = $value;
            }
        } else {
            $conn->insert($obj, $async);
            $this->_id      = $obj['_id'];
            $this->_current = $obj; 
        }
        /* post-save hook */
        if ($update) {
            $this->on_update();
        } else {
            $this->on_save();
        }
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
        if ($this->valid()) {
            return $this->_getCollection()->remove(array('_id' => $this->_id));
        }
        return false;
    }
    // }}}

    // void drop() {{{
    /**
     *  Delete the current colleciton and all its documents
     *  
     *  @return void
     */
    final function drop()
    {
        $this->_getCollection()->drop();
        $this->setResult(array());
        $this->_cursor = null;
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

    // bool valid() {{{
    /**
     *    Valid
     *
     *    Return if we're on an iteration and if it is still valid
     *
     *    @return true
     */
    final function valid()
    {
        return $this->_cursor InstanceOf MongoCursor && $this->_cursor->valid();
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
        return $this->_cursor->next();
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
        $this->setResult($this->_cursor->current());
        return $this;
    }
    // }}}

    // bool rewind() {{{
    /**
     *    Go to the first document
     */
    final function rewind()
    {
        return $this->_cursor->rewind();
    }
    // }}}

    // getID() {{{
    /**
     *  Return the current document ID. If there is
     *  no document it would return false.
     *
     *  @return object|false
     */
    final public function getID()
    {
        if ($this->_id instanceof MongoID) {
            return $this->_id;
        }
        return false;
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
        return $this->getID();
    }
    // }}}

    // void pre_save($action, & $document) {{{
    /**
     *    PRE-save Hook,
     *    
     *    This method is fired just before an insert or updated. The document
     *    is passed by reference, so it can be modified. Also if for instance
     *    one property is missing an Exception could be thrown to avoid 
     *    the insert.
     *
     *
     *    @param string $action     Update or Create
     *    @param array  &$document Document that will be sent to MongoDB.
     *
     *    @return void
     */
    protected function pre_save($action, Array &$document)
    {
    }
    // }}}

    // void on_save() {{{
    /**
     *    On Save hook
     *
     *    This method is fired right after an insert is performed.
     *
     *    @return void
     */
    protected function on_save()
    {
    }
    // }}}

    // void on_update() {{{
    /**
     *    On Update hook
     *
     *    This method is fired right after an update is performed.
     *
     *    @return void
     */
    protected function on_update()
    {
    }
    // }}}

    // void on_iterate() {{{
    /**
     *    On Iterate Hook
     *
     *    This method is fired right after a new document is loaded 
     *    from the recorset, it could be useful to load references to other 
     *    documents.
     *
     *    @return void
     */
    protected function on_iterate()
    {
    }
    // }}}

    // setup() {{{
    /**
     *  This method should contain all the indexes, and shard keys
     *  needed by the current collection. This try to make
     *  installation on development environments easier.
     */
    function setup()
    {
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

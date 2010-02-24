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
 *  Simple ActiveRecord pattern built on top of MongoDB
 *
 *    @author César D. Rodas <crodas@php.net>
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
     *    Number of total documents in this recordset
     *
     *    @type int
     */    
    private $_count   = 0;
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
        if (!isset(self::$_collections[$colName])) {
            self::$_collections[$colName] = self::_getConnection()->selectCollection($colName);
        }
        return self::$_collections[$colName];
    }
    // }}}


    // int count() {{{
    /**
     *  Return the number of documents in the actual request. If
     *  we're not in a request, it will return -1.
     *
     *  @return int
     */
    function count()
    {
        if ($this->valid()) {
            return $this->_count;
        }
        return -1;
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
    final protected function getCurrentDocument($update=false)
    {
        $vars    = array();
        $current = (array)$this->_current;
        $push    = array();
        $pull    = array();
        $unset   = array();
        $object  = get_object_vars_ex($this);

        foreach ($object as $key => $value) {
            if (!$value) {
                if ($update) {
                    $unset[$key] = 1;
                }
                continue;
            }

            if ($update) {
                if (is_array($value) && isset($current[$key])) {
                    $toPush = array_diff($value, $current[$key]);
                    $toPull = array_diff($current[$key], $value);
                    if (count($toPush) > 0) {
                        $push[$key] = array_values($toPush);
                    }
                    if (count($toPull) > 0) {
                        $pull[$key] = array_values($toPull);
                    }
                } else if(!isset($current[$key]) || $value !== $current[$key]) {
                    $filter = array($this, "{$key}_filter");
                    if (is_callable($filter)) {
                        $filter = call_user_func_array($filter, array(&$value, isset($current[$key]) ? $current[$key] : null));
                        if (!$filter) {
                            throw new FilterException("{$key} filter failed");
                        }
                    }
                    $vars[$key] = $value;
                }
            } else {
                $filter = array($this, "{$key}_filter");
                if (is_callable($filter)) {
                    $filter = call_user_func_array($filter, array(&$value, null));
                    if (!$filter) {
                        throw new FilterException("{$key} filter failed");
                    }
                }
                $vars[$key] = $value;
            }
        }

        /* Updated behaves in a diff. way */
        if ($update) {
            foreach (array_diff(array_keys($this->_current), array_keys($object)) as $property) {
                $unset[$property] = 1;
            }
            if (count($vars) > 0) {
                $vars = array('$set' => $vars);
            }
            if (count($push) > 0) {
                $vars['$pushAll'] = $push;
            }
            if (count($pull) > 0) {
                $vars['$pullAll'] = $pull;
            }
            if (count($unset) > 0) {
                $vars['$unset'] = $unset;
            }
        } 

        if (count($vars) == 0) {
            return array();
        }
        return $vars;
    }
    // }}}

    // void setCursor(MongoCollection $obj) {{{
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
    final protected function setCursor($obj)
    {
        $this->_cursor = $obj;
        $this->_count  = $obj->count();
        if ($this->_count) {
            $this->setResult($obj->getNext());
        } else {
            $this->setResult(array());
        }
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
        foreach (array_keys($this->_current) as $key) {
            unset($this->$key);
        }

        /* Add our current resultset as our object's property */
        foreach ($obj as $key => $value) {
            $this->$key = $value;
        }
        
        /* Save our record */
        $this->_current = $obj;
    }
    // }}}

    // this find() {{{
    /**
     *    Simple find
     *
     *    Really simple find, which uses this object properties
     *    for fast filtering
     *
     *    @return object this
     */
    final function find()
    {
        $vars = $this->getCurrentDocument();
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
        $this->on_save();
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
   
    // string key() {{{
    /**
     *    Return the current key
     *
     *    @return string
     */
    final function key()
    {
        return $this->_cursor->key();
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

}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */

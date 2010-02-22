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


abstract class ActiveMongo implements Iterator 
{
    private static $_collections;
    private static $_conn;
    private static $_db;
    private static $_host;
    private $_current = array();
    private $_cursor  = null;
    private $_count   = 0;
    public  $_id;

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

    final public static function connect($db, $host='localhost')
    {
        self::$_host = $host;
        self::$_db   = $db;
    }

    final protected static function _getConnection()
    {
        if (is_null(self::$_conn)) {
            self::$_conn = new Mongo(self::$_host);
        }
        return self::$_conn->selectDB(self::$_db);
    }


    final protected function _getCollection()
    {
        $colName = $this->_getCollectionName();
        if (!isset(self::$_collections[$colName])) {
            self::$_collections[$colName] = self::_getConnection()->selectCollection($colName);
        }
        return self::$_collections[$colName];
    }

    final protected function get_vars($update=false)
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

    final function setCursor($obj)
    {
        $this->_cursor = $obj;
        $this->_count  = $obj->count();
        $this->setResult($obj->getNext());
    }

    final function setResult($obj)
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

    final function find()
    {
        $vars = $this->get_vars();
        $res  = $this->_getCollection()->find($vars);
        $this->setCursor($res);
        return $this;
    }

    final function save($async=true)
    {
        $update = isset($this->_id) && $this->_id InstanceOf MongoID;
        $conn   = $this->_getCollection();
        $obj    = $this->get_vars($update);
        if (count($obj) == 0) {
            return; /*nothing to do */
        }
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
    }

    final function delete()
    {
        if (isset($this->_id) && $this->_id InstanceOf MongoId) {
            return $this->_getCollection()->remove(array('_id' => $this->_id));
        }
        return false;
    }

    final function drop()
    {
        $this->_getCollection()->drop();
        $this->setResult(array());
        $this->_cursor = null;
    }

    final function valid()
    {
        return $this->_cursor InstanceOf MongoCursor && $this->_cursor->valid();
    }

    final function next()
    {
        return $this->_cursor->next();
    }

    final function current()
    { 
        $this->setResult($this->_cursor->current());
        return $this;
    }

    final function rewind()
    {
        return $this->_cursor->rewind();
    }
    
    final function key()
    {
        return $this->_cursor->key();
    }

}




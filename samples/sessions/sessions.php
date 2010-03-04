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


class MongoSession extends ActiveMongo
{
    protected static $session;

    public $data;
    public $sid;
    public $valid;
    public $ts;

    function setup()
    {
        $collection = $this->_getCollection();
        $collection->ensureIndex(array("sid" => 1, "valid" => 1), array("background" => 1));
        $collection->ensureIndex(array("ts" => 1), array("background" => 1));
    }

    function pre_save($op, &$document)
    {
        if ($op == 'create') {
            $document['ts'] = new MongoDate();
        }
    }

    final public static function init()
    {
        $class = get_called_class();
        session_set_save_handler(
            array($class, "Open"),
            array($class, "Close"),
            array($class, "Read"),
            array($class, "Write"),
            array($class, "Destroy"),
            array($class, "GC")
        );
        self::$session = new $class;
    }

    final public static function Open($path, $name)
    {
        return true;
    }

    final public static function Close()
    {
        self::$session = null;
        return true;
    }

    final public static function Read($id)
    {
        $session = self::$session;
        $session->sid   = $id;
        $session->valid = true;
        if ($session->find()->count() == 0) {
            $session->valid = true;
            $session->save();
        }
        return $session->data;
    }

    final public static function Write($id, $ses_data)
    {
        $session = self::$session;
        $session->data = $ses_data;
        $session->ts   = new MongoDate();
        $session->save(false);

        return true;
    }

    final public static function Destroy($id)
    {
        $session = self::$session;
        $session->delete();
    }

    final public static function GC($max_time)
    {
        $class    = get_called_name(); 
        $sessions = new $class;
        $session->delete_old_sessions($max_time);
    }

    function delete_old_sessions($max_time)
    {
        $filter = array(
            'ts' => array(
                '$lt' => new MongoDate(time()-$max_time),
            )
        );
        $this->_getCollection->remove($filter);
    }
}

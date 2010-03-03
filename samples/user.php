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

class Users Extends ActiveMongo
{
    public $username;
    public $password;
    public $uid;

    function my_selector()
    {
        /* Get collection */
        $col = $this->_getCollection();

        /* Build our request */
        $res = $col->find(array('uid' => array('$gt' => 5, '$lt' => 10)));
        $res->sort(array('uid' => -1));

        /* Give to ActiveMongo our Cursor */
        $this->setCursor($res);

        /* You must return 'this' for easy iteration */
        return $this;
    }

    function username_filter(&$value, $past_value) 
    {
        if  ($past_value != null && $value != $past_value) {
            throw new FilterException("You cannot change the username");
        } else {
            if (strlen($value) < 5) {
                throw new FilterException("Name too short");
            }
        }
        return true;
    }

    function password_filter(&$value, $past_value)
    {
        if (strlen($value) < 5) {
            throw new FilterException("Password is too sort");
        }
        $value = sha1($value);
        return true;
    }

    function setup()
    {
        $this->_getCollection()->ensureIndex(array('uid' => 1), array('unique' => true, 'background' => true));
    }

    function mapreduce()
    {
        $col = self::_getConnection();
        $data = array(
            'mapreduce' => 'users',
            'map' => new MongoCode("emit(this.karma, this.uid);"),
            'reduce' => new MongoCode("return 1;"),
            'verbose' => true,
            'out' => 'salida',
        );
        $salida = $col->command($data);
        var_dump($salida);
    }
}


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

class MongoSession extends ActiveMongo
{
    protected static $session;

    public $data;
    public $sid;
    public $valid;
    public $ts;

    function getCollectionName()
    {
        return 'session';
    }

    function setup()
    {
        $this->addIndex(array("sid" => 1, "valid" => 1));
        $this->addIndex(array("ts" => 1));
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

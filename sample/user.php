<?php

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

    function setup()
    {
        $this->_getCollection()->ensureIndex(array('uid' => 1), array('unique' => true, 'background' => true));
    }
}


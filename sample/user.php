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

    function password_filter(&$value, &$past_value)
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
}


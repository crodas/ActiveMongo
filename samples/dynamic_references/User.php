<?php

class User extends ActiveMongo
{
    public $username;
    public $password;

    public $services;

    function password_filter(&$value, $old_value)
    {
        if (strlen($value) < 5) {
            throw new ActiveMongo_FilterException("Password is too short");
        }
        $value = sha1($value);
    }

    function after_update($document)
    {
        if (isset($GLOBALS['debug'])) {
            var_dump(array(get_class($this) => $document));
        }
    }

    function username_filter($value, $old_value)
    {
        if ($old_value!=null && $value != $old_value) {
            throw new ActiveMongo_FilterException("The username can't be changed");
        }

        if (!preg_match("/[a-z][a-z0-9\-\_]+/", $value)) {
            throw new ActiveMongo_FilterException("The username is not valid");
        }

        if (strlen($value) < 5) {
            throw new ActiveMongo_FilterException("Username too short");
        }
        return true;
    }

}

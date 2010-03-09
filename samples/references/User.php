<?php

class User extends ActiveMongo
{
    public $username;
    public $password;

    public $services;

    function password_filter(&$value, $old_value)
    {
        if (strlen($value) < 5) {
            throw new FilterException("Password is too short");
        }
        $value = sha1($value);
    }

    function pre_save($op, $document)
    {
        if (isset($GLOBALS['debug'])) {
            var_dump(array($op => $document));
        }
    }

    function username_filter($value, $old_value)
    {
        if ($old_value!=null && $value != $old_value) {
            throw new FilterException("The username can't be changed");
        }

        if (!preg_match("/[a-z][a-z0-9\-\_]+/", $value)) {
            throw new FilterException("The username is not valid");
        }

        if (strlen($value) < 5) {
            throw new FilterException("Username too short");
        }
        return true;
    }

    function add_service(Service $obj)
    {
        $this->services[] = $obj;
    }
}

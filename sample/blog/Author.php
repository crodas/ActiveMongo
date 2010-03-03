<?php

class AuthorModel extends ActiveMongo
{
    public $username;
    public $name;

    function getCollectionName()
    {
        return 'author';
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
            throw new Exception("Username too short");
        }
    }

    function on_update()
    {
        $post = new PostModel;
        $post->updateAuthorInfo($this->getID());
    }

    function setup()
    {
        $collection = & $this->_getCollection();
        $collection->ensureIndex(array('username' => 1), array('unique'=> 1, 'background' => 1));
    }
}

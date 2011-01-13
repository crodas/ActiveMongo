<?php

abstract class ActiveMongo_Cursor_Interface implements Iterator
{
    abstract public function __construct($collection, $query);

    function getReference($class)
    {
        throw new ActiveMongo_Exception("This cursor doesn't support reference");
    }

}

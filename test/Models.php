<?php

class Model1 extends ActiveMongo
{
    public $a;
    public $b;

    static $validates_presence_of = array(
        'a'
    );

}

<?php

class SleepModel extends ActiveMongo
{
    public $a;
    public $b;

    function __sleep()
    {
        return array('a');
    }
}

class SleepTest extends PHPUnit_Framework_TestCase
{
    function __construct()
    {
        try {
            SleepModel::drop();
        } catch (ActiveMongo_Exception $e) {}
    }

    function testSleep()
    {
        $c = new SleepModel;
        $c->a = 5;
        $c->b = 10;
        $c->save();

        $c->clean();
        $c->where('a', 5);
        $c->doQuery();
        $this->assertEquals($c->a, 5);
        $this->assertTrue(!isset($c->b));


    }
}

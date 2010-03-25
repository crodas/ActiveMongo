<?php
require "../lib/ActiveMongo.php";
require dirname(__FILE__)."/models.php";

class ActiveMongoTest extends PHPUnit_Framework_TestCase
{
    public function __construct()
    {
        ActiveMongo::connect("test", "localhost");
        Model1::drop();
    }

    public function testValidatesPresence()
    {
        $c = new Model1;
        $c->b = 'cesar';
        try {
            $c->save();
        } catch (ActiveMongo_FilterException $e) {
            $this->assertTrue(true);
            return;
        }
        $this->assertTrue(false);
    }
}

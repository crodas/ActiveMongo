<?php

class ValidatorsTest extends PHPUnit_Framework_TestCase
{
    public function testValidatesPresence()
    {
        try {
            $c = new Model1;
            $c->b = 'cesar';
            $c->save();
            $this->assertTrue(false);
        } catch (ActiveMongo_FilterException $e) {
            $this->assertTrue(true);
        }
    }
}

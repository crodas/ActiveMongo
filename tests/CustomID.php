<?php

class CustomID extends PHPUNIT_Framework_TestCase
{
    function testCustomId() {
        $new = Dummy::instance();
        $new->_id = 50;
        $new->save();

        $this->assertEquals(Dummy::instance()->where('_id', 50)->count(), 1);
    }

    function testCustomIdOnClean() {
        $new = Dummy::instance();
        $new->doQuery();

        $new->clean();
        $new->_id = 51;
        $new->save();

        $this->assertEquals(Dummy::instance()->where('_id', 51)->count(), 1);
    }


}

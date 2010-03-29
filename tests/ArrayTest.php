<?php

class ArrayTest extends PHPUnit_Framework_TestCase
{

    function testCount()
    {
        $m1 = new Model1;
        $m2 = new Model2;

        $this->assertEquals($m1->count(), count($m1));
        $this->assertEquals($m2->count(), count($m2));
    }

    function testArrayAccess()
    {
        $m2 = new Model2;

        foreach ($m2 as $item) {
            $this->assertFalse(isset($item['foobar']));
            $this->assertTrue(isset($item['a']));
            $this->assertEquals($item['a'], $item->a);
            $item['foobar'] = rand(1, 1000);
            $this->assertEquals($item['foobar'], $item->foobar);
        }
    }

}

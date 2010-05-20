<?php

/**
 *  Test for known bugs. If you find a bug and fixed
 *  it, here is the right place to write the tests.
 *
 */
class BugsTest extends PHPUnit_Framework_TestCase
{
    /**
     *  fixed by dfa (Dominik Fässler <d.faessler@ambf.ch>)
     */
    function testNormalIteration()
    {
        $m1 = new Model1;
        $m1->doQuery();
        $a1 = $m1->a;
        $m1->next();
        $a2 = $m1->a;
        $this->assertNotEquals($a1, $a2);
    }

}

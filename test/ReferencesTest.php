<?php

class ReferencesTest  extends PHPUnit_Framework_TestCase
{
    public function testReferences()
    {
        $c = new Model1;
        $c->a = "foobar";
        $c->save();
        $ref = array(
            '$ref' => 'model1', 
            '$id' => $c->getID(), 
            '$db' => DB, 
            'class' => 'Model1'
        );
        $this->assertEquals($c->getReference(), $ref);
    }

}

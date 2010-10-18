<?php

class AutoIncrement_Model extends ActiveMongo_Autoincrement
{
}

class AutoincrementTest extends PHPUnit_Framework_TestCase 
{
    function testInsert()
    {
        try {
            Autoincrement_Namespace::instance()->drop();
        } catch (ActiveMongo_Exception $e) {}

        for ($i = 0; $i < 1000; $i++) {
            $c = new Autoincrement_Model;
            $c->obj = $i;
            $c->save();
        }


        $c = new Autoincrement_Model;
        foreach($c as $obj) {
            if (isset($last)) {
                $this->assertEquals($obj->getID(), $last+1);
            }
            $last = $obj->getID();
        }

        $c->clean();
        $this->assertEquals($c->count(), 1000);

        // with custom query
        unset($last);
        $foo = Autoincrement_Model::instance();
        $cursor = $foo->collection()->find();

        $foo->setResult($cursor);
        foreach($foo as $obj) {
            if (isset($last)) {
                $this->assertEquals($obj->getID(), $last+1);
            }
            $last = $obj->getID();
        }

        $foo->clean();
        $this->assertEquals($foo->count(), 1000);

    }
}

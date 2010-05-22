<?php

class HookTest extends PHPUnit_Framework_TestCase
{
    private $deleted;

    /**
     *  Hooking test, update model2 when model1 changes
     */
    function model1_after_update($document, $object)
    {
        $m2 = new model2;
        $m2->update_refs($object);
    }

    function super_hook($class, $param1)
    {
        $this->assertEquals($class, 'Model1');
        $this->assertEquals($param1, 'param1');
    }

    function testFilters()
    {
        try {
            $c = new Model2;
            $c->M1 = 'foo';
            $c->save();
            $this->assertTrue(FALSE);
        } catch(Exception $e) {
            $this->assertTrue(TRUE);
            $this->assertEquals($e->getMessage(), "Invalid M1 value");
        }
        try {
            $c = new Model2;
            $c->M1 = 'foo';
            $c->no_throw = TRUE;
            $c->save();
            $this->assertTrue(FALSE);
        } catch(Exception $e) {
            $this->assertTrue(TRUE);
            $this->assertNotEquals($e->getMessage(), "Invalid M1 value");
        }
        try {
            /* start sub-document */
            $d = new Model1;
            $d->a = 5;
            $d->save();
            /**/
            $c = new Model2;
            $c->M1 = $d->getID();
            $c->save();
            $this->assertTrue(TRUE);
            $d->delete();
            $c->delete();
        } catch(Exception $e) {
            $this->assertTrue(FALSE);
        }
    }

    function testSuperHooks()
    {
        ActiveMongo::addEvent('test_event', array($this, 'super_hook'));
        $c = new Model1;
        $c->triggerEvent('test_event', array('param1'));
    }

    function testInvalidHooks()
    {
        try {
            Model1::addEvent('after_update', 'invalid_callback');
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertTrue(TRUE);
        }
    }

    /**
     *  Testing hook, modifing on update other documents
     */
    function testChanges()
    {
        Model1::addEvent('after_update', array($this, 'model1_after_update'));
        $m1 = new Model1;
        $m1->a = 1;
        $m1->b = 2;
        $m1->save();

        $data = array();
        for ($i=0; $i < 1000; $i++) {
            $m2['M1'] = $m1->getID();
            $m2['a']  = $m1->a;
            $data[]   = $m2;
        }
        Model2::batchInsert($data);

        $m1->a = 50;
        $m1->save();

        $m2 = new Model2;
        foreach ($m2->where("M1", $m1->getID()) as $item) {
            $this->assertEquals($m1->a, $item->a);
        }
    }

    static function hook_test_before_validate(&$obj)
    {
        $obj['b'] = md5($obj['a']);
    }

    function testBeforeValidate()
    {
        Model3::addEvent("before_validate", array($this, 'hook_test_before_validate'));
        $c = new Model3;
        $c->a = 'cesar';
        $c->int = rand(1, 50);
        $c->save();
        $this->assertEquals($c->b, md5($c->a));
        $this->assertNotEquals($c->getID(), "");

        /**/
        $c->a = 'rodas';
        $c->save();
        $this->assertEquals($c->b, md5($c->a));
    }
    
    function testBeforeDelete()
    {
        Model1::addEvent('before_delete', array($this, 'on_delete'));
        $m1 = new Model1;
        $m1->a = rand();
        $m1->save();
        $id = (string) $m1->getId();
        
        $m1->delete();
        
        $this->assertEquals($id, $this->deleted);
    }
    
    function on_delete($doc)
    {
        $this->deleted = (string) $doc['_id'];
    }
    
    function testAfterDelete()
    {
        Model3::addEvent('after_delete', array($this, 'on_delete'));
        $m3 = new Model3;
        $m3->a = '';
        $m3->int = rand();
        $m3->save();
        $id = (string) $m3->getId();
        
        $m3->delete();
        
        $this->assertEquals($id, $this->deleted);
    }
}

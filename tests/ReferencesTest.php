<?php

class ReferencesTest  extends PHPUnit_Framework_TestCase
{
    protected $query_info;

    public function testReferences()
    {
        /* This query return nothing currently */
        /* but will save a reference */
        $query = new Model1;
        $query->limit(2,1);
        $query->sort('a DESC');

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

        $d = new Model1;
        $d->a      = "barfoo";
        /* Reference into a document */
        $d->next   = $c;
        /* References into sub documents */
        $d->nested = array($c, $c);
        /* MongoDBRef */
        $d->mdbref = MongoDBRef::create('model1', $c->getID());

        /* Get Dynamic query; AKA save the query */
        /* in this case it would be a get all */
        $d->query  = $query->getReference(TRUE);

        $d->save();

    }

    function testFindAndReferences()
    {
        $c = new Model1;
        $c->a = 5;
        $c->save();

        $d = new Model1;
        $d->a = 9;
        $d->ref = $c;
        $d->save();

        $e = new Model1;
        $e->ref = $c;
        
        foreach($e->find() as $r) {
            $this->assertTrue(MongoDBREf::isRef($r->ref));
            $r->doDeferencing();
            $this->assertEquals($r->ref->a, $c->a);
            $this->assertEquals($d->a, $r->a);
        }
 
    }


    public function testInvalidRefernce()
    {
        $c = new Model2;
        $this->assertTrue($c->getReference() === NULL);
    }

    public function testReferencesWithFindAndReferences()
    {
        try {
            $c = new Model1;
            $c->where('int <= ', 1000);
            $c->where('processing exists', FALSE);
            $c->limit(50);
            $c->sort('int DESC');
            $c->findAndModify(array("processing" => TRUE));
            $invalid_ref = $c->getReference(TRUE);
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }
    }

    /**
     *  @depends testReferences
     */
    public function testDeferencing()
    {
        $d = new Model1;
        $d->where('a', 'barfoo');
        
        foreach ($d as $doc) {
            $this->assertTrue(isset($doc->next));
            $this->assertTrue(MongoDBRef::isRef($doc->next));
            $this->assertTrue(MongoDBRef::isRef($doc->nested[0]));
            $this->assertTrue(MongoDBRef::isRef($doc->nested[1]));
            $this->assertTrue(MongoDBRef::isRef($doc->query));

            /* Check dynamic references properties */
            $this->assertTrue(is_array($doc->query['dynamic']));
            $this->assertTrue(count($doc->query['dynamic']) > 0);

            /* Deference */
            $doc->doDeferencing();

            /* Test deferenced values */
            $this->assertTrue($doc->next      InstanceOf Model1);
            $this->assertTrue($doc->nested[0] InstanceOf Model1);
            $this->assertTrue($doc->nested[1] InstanceOf Model1);
            $this->assertTrue(is_array($doc->query));
            $this->assertTrue($doc->query[0] InstanceOf Model1);

            /* Testing mongodb refs */
            $this->assertTrue(is_array($doc->mdbref));
            foreach ($doc->mdbref as $property => $value) {
                if ($property == '_id') {
                    $this->assertEquals($value, $doc->next->getID());
                    continue;
                } else {
                    $this->assertEquals($value, $doc->next->$property);
                    continue;
                }
                $this->assertTrue(FALSE);
            }



            /* Testing Iteration in defered documents */
            /* They should fail because they are cloned */
            /* instances of a real document */
            try {
                $doc->next->next();
                $this->assertTrue(FALSE);
            } catch (ActiveMongo_Exception $e) {
                $this->assertTrue(TRUE);
            }
        }

    }

}

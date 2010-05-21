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

        $d = new Model1;
        $d->a      = "barfoo";
        /* Reference into a document */
        $d->next   = $c;
        /* References into sub documents */
        $d->nested = array($c, $c);
        /* Get Dynamic query; AKA save the query */
        /* in this case it would be a get all */
        $query = new Model1;
        $query->where('a', 'foobar');
        $query->doQuery();
        $d->query  = $query->getReference(TRUE);

        $d->save();

    }

    /**
     *  @depends testReferences
     */
    public function testReferenceSave()
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

            /* Test */
            $this->assertTrue($doc->next      InstanceOf Model1);
            $this->assertTrue($doc->nested[0] InstanceOf Model1);
            $this->assertTrue($doc->nested[1] InstanceOf Model1);
            $this->assertTrue(is_array($doc->query));
            $this->assertTrue($doc->query[0] InstanceOf Model1);

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

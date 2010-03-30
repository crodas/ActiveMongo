<?php

class QueryTest extends PHPUnit_Framework_TestCase
{

    function __construct()
    {
        Model3::drop();
        $c = new Model3;
        for ($i=0; $i < 5000; $i++) {
            $c->reset();
            $c->int = $i;
            $c->str = sha1(uniqid());
            $c->save(false);
        }
        $c->reset();
        $this->assertEquals($c->count(), 5000);
    }

    function testQuery()
    {
        $c = new Model1;

        /* rand values */
        $val1 = rand(1, 50);
        $val2 = rand(1, 50);
        $val3 = rand(1, 50);
        $val4 = rand(1, 50);
        $val5 = rand(1, 50);


        /* prepare the query */
        $c->properties('a,b')->where('a >', $val1)->where('b <', $val2)->where('c !=', $val3);
        $c->where('h regexp', '/[a-f0-9]+/');
        $c->where('x in', array(1, 2));
        $c->where('x nin', array(4));
        $c->where('y ==', array(4));
        $c->sort('c DESC, a ASC')->limit($val4, $val5);

        /* perform it */
        $c->doQuery();

        /* Get cursor info */
        $sQuery = $c->getReference(true);

        /* expected cursor info */
        $eQuery = array(
            'ns' => DB.'.model1',
            'limit' => $val4,
            'skip'  => $val5,
            'query' => array(
                '$query' => array(
                    'a' => array('$gt' => $val1),
                    'b' => array('$lt' => $val2),
                    'c' => array('$ne' => $val3),
                    'h' => new MongoRegex('/[a-f0-9]+/'),
                    'x' => array('$in' => array(1,2), '$nin' => array(4)),
                    'y' => array('$all' => array(4)),
                ),
                '$orderby' => array(
                    'c' => -1,
                    'a' => 1,
                ),
            ),
            'fields' => array(
                'a' => 1,
                'b' => 1,
            )
        );

        $this->assertEquals($sQuery['dynamic'], $eQuery);
    }

    function testQueryRequireArray()
    {
        $c = new Model1;
        try {
            $c->where('c near', 'string');
            $this->assertTrue(false);
        } catch  (ActiveMongo_Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $c->where('c in', 55);
            $this->assertTrue(false);
        } catch  (ActiveMongo_Exception $e) {
            $this->assertTrue(true);
        }
        try {
            $c->where('c nin', 559);
            $this->assertTrue(false);
        } catch  (ActiveMongo_Exception $e) {
            $this->assertTrue(true);
        }
    }

    function testMultipleOperationsPerProperty()
    {
        list($min, $max) = array(50, 100);

        $c = new Model3;
        foreach ($c->where('int >', $min)->where('int <', $max) as $item) {
            $this->assertGreaterThan($min, $item['int']);
            $this->assertLessThan($max, $item['int']);
        }

        /* this could be done with a single regexp but 
         * this test should cover the multiple ALL amoung 
         * properties
         *
         *  str regexp '//' AND str regexp '//' AND str regexp '//'
         */
        $c = new Model3;
        $c->where('str regex', '/^4/')->where('str regexp', '/a$/');
        foreach ($c->where('str regex', '/[a-z0-9]+/') as $item) {
            $this->assertEquals($item['str'][0], 4);
            $this->assertEquals($item['str'][strlen($item['str'])-1], 'a');
        }


        $c = new Model3;
        $c->where('int >', $min)->where('int <', $max);
        foreach ($c->where('int nin', array($min+1, $min+2, $min+3)) as $item) {
            $this->assertNotEquals($min+1, $item['int']);
            $this->assertNotEquals($min+2, $item['int']);
            $this->assertNotEquals($min+3, $item['int']);
        }
    }

    function testOnQueryModifyError()
    {
        try {
            $c = new Model1;
            $c->where('a', 1);
            $c->doQuery();
            $c->where('b', 4);
            $this->assertTrue(false);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(true);
        }
    }

    function testClone()
    {
        $c = new Model1;
        $c->a = 1;
        $c->save();

        $c->reset();
        $this->assertLessThan($c->count(), 1);
        foreach ($c as $item) {
            $item_cloned = clone $item;
            $item_cloned->c = 1;
            $item_cloned->save();
            try {
                /* iterations are forbidden in cloned objects */
                foreach ($item_cloned as $nitem) {
                    $this->assertTrue(false);
                }
            } catch (Exception $e) {
                $this->assertTrue(true);
            }
        }

    }
}

<?php

class QueryTest extends PHPUnit_Framework_TestCase
{

    function testQuery()
    {
        $c = new Model1;

        /* prepare the query */
        $c->properties('a,b')->where('a >', 1)->where('b <', 1)->where('c !=', 1);
        $c->sort('c DESC, a ASC')->limit(10, 15);

        /* perform it */
        $c->doQuery();

        /* Get cursor info */
        $sQuery = $c->getReference(true);

        /* expected cursor info */
        $eQuery = array(
            'ns' => DB.'.model1',
            'limit' => 10,
            'skip'  => 15,
            'query' => array(
                '$query' => array(
                    'a' => array('$gt' => 1),
                    'b' => array('$lt' => 1),
                    'c' => array('$ne' => 1),
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
}

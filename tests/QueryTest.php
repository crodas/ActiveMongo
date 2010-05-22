<?php

class QueryTest extends PHPUnit_Framework_TestCase
{

    function testBulkInserts()
    {
        try { 
            Model3::drop();
        } catch (Exception $e) {}
        $data = array();

        /* Valid data */
        for ($i=0; $i < 5000; $i++) {
            $data[] = array('int' => $i, 'str' => sha1(uniqid()));
        }

        /* Invalid data, shouldn't be inserted */
        $data[] = array('xint' => $i, 'str' => sha1(uniqid()));
        $data[] = array('xint' => $i, 'str' => sha1(uniqid()));
        $data[] = array('xint' => $i, 'str' => sha1(uniqid()));

        /* batchInsert */
        Model3::batchInsert($data, TRUE, TRUE);

        $c = new Model3;
        $this->assertEquals($c->count(), 5000);
    }

    function testInstall()
    {
        ActiveMongo::install();
        $indexes = Model1::getIndexes();
        $this->assertTrue(isset($indexes[1]['key']['a']));
    }

    /**
     *  @depends testBulkInserts
     */
    function testModQuery()
    {
        $c = new Model3;
        /* int % 2 == */
        $c->properties('int');
        $c->where('int %', array(2, 0));
        $this->assertLessThan($c->count(), 0);
        foreach ($c as $r) {
            $this->assertEquals($r->int % 2, 0);
        }
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
        $c->where('f exists');
        $c->where('f !=', array(5,6));
        $c->where(array("bar exists", "a exists"));
        $c->where('xxx ==', 5);
        $c->where('bar >=', 5);
        $c->sort('c DESC, a ASC')->limit($val4, $val5);

        /* perform it */
        $c->doQuery();

        /* Get cursor info */
        $sQuery = $c->getReference(TRUE);

        /* expected cursor info */
        $eQuery = array(
            'ns' => DB.'.model1',
            'limit' => $val4,
            'skip'  => $val5,
            'query' => array(
                '$query' => array(
                    'a' => array('$gt' => $val1, '$exists' => 1),
                    'b' => array('$lt' => $val2),
                    'c' => array('$ne' => $val3),
                    'h' => new MongoRegex('/[a-f0-9]+/'),
                    'x' => array('$in' => array(1,2), '$nin' => array(4)),
                    'y' => array('$all' => array(4)),
                    'f' => array('$exists' => 1, '$nin' => array(5,6)),
                    'xxx' => 5,
                    'bar' => array('$exists' => 1, '$gte' => 5),
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


    function testQueryArray()
    {
        $c = new Model1;

        /* rand values */
        $val1 = rand(1, 50);
        $val2 = rand(1, 50);
        $val3 = rand(1, 50);
        $val4 = rand(1, 50);
        $val5 = rand(1, 50);


        /* prepare the query */
        $filter = array(
            'a > ' => $val1, 
            'b < ' => $val2,
            'c != ' => $val3,
            'h regexp' => '/[a-f0-9]+/',
            'x in ' => array(1,2),
            'x nin ' => array(4),
            'y == ' => array(4)
        );
        $c->properties('a,b')->sort('c DESC, a ASC')->limit($val4, $val5);;
        $c->where($filter);

        /* perform it */
        $c->doQuery();

        /* Get cursor info */
        $sQuery = $c->getReference(TRUE);

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
            $this->assertTrue(FALSE);
        } catch  (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }
        try {
            $c->where('c in', 55);
            $this->assertTrue(FALSE);
        } catch  (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }
        try {
            $c->where('c >', array(1));
            $this->assertTrue(FALSE);
        } catch  (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }
        try {
            $c->where('c >>>', array(1));
            $this->assertTrue(FALSE);
        } catch  (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }
        try {
            $c->where('c nin', 559);
            $this->assertTrue(FALSE);
        } catch  (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
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

    function testMultipleUpdate()
    {
        $str = sha1(uniqid());
        $query = array(
            'int >' => 5,
            'int <' => 30,
            'int !=' => 6,
        );
        $c = new Model3;
        $c->where($query);
        $c->update(array('newproperty' => $str));

        $c->where(array('int >' => 5, 'int <' => 30));
        foreach ($c as $item) {
            if ($item['int'] == 6) {
                /* 6 is not included */
                $this->assertFalse(isset($item['newproperty']));
            } else {
                $this->assertEquals($str, $item['newproperty']);
            }
        }
    }

    function testNullUpdate()
    {
        $id = 0;


        $c = new  Model3;
        $c->int  = 5;
        $c->arr  = array(5, array(1));
        $c->bool = TRUE;
        $c->null = NULL;

        /* Testing Save also :-) */
        $this->assertEquals(TRUE, $c->save());
        /* Now nothing should be done */
        $this->assertEquals(NULL, $c->save());

        $c->int      = 0;
        $c->arr[]    = 0;
        $c->arr[1][] = 1;
        $c->arr[1][] = 2;
        $c->arr[1][] = 3;
        $c->bool     = FALSE;
        $c->foobar   = NULL;
        $id          = $c->getId();

        /* Updating */
        $this->assertEquals(TRUE, $c->save());
        $this->assertEquals(NULL, $c->save());

        unset($c->arr[1][1]);
        unset($c->foobar);

        /* Updating */
        $this->assertEquals(TRUE, $c->save());
        $this->assertEquals(NULL, $c->save());


        /* now empty $c and query for `int` value */
        $c->reset();
        $c->where('_id', $id);
        $c->doQuery();
        $this->assertEquals($c->int, 0);
        $this->assertEquals($c->arr, array(5,array(1,NULL, 2,3), 0));
        $this->assertEquals($c->bool, FALSE);
        $this->assertEquals($c->null, NULL);
    }

    function testOnQueryModifyError()
    {
        try {
            $c = new Model1;
            $c->where('a', 1);
            $c->doQuery();
            $c->where('b', 4);
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }
    }

    /**
     * @depends testBulkInserts
     */
    function testClone()
    {
        $c = new Model1;
        $c->a = 1;
        $c->save();

        $c->reset();

        foreach ($c as $item) {
            $item_cloned = clone $item;
            $item_cloned->c = 1;
            $item_cloned->save();
            try {
                /* iterations are forbidden in cloned objects */
                foreach ($item_cloned as $nitem) {
                    $this->assertTrue(FALSE);
                }
            } catch (Exception $e) {
                $this->assertTrue(TRUE);
            }
        }
    }
    
    function testToSTring()
    {
        $c = new Model3;
        $c->doQuery();
        $this->assertEquals((string)$c, (string)$c->getID());
        $this->assertEquals((string)$c, $c->key());
    }

    function testDelete()
    {
        /* Delete using a criteria */
        $c = new Model3;
        $c->where('int < ', 100);
        $c->delete();

        $this->assertEquals($c->count(), 4900);

        /* delete on iteration (element by element) */
        $c = new Model3;
        $c->where('int', array(200, 300));

        $i = 0;
        foreach ($c as $d) {
            $d->delete();
            $this->assertFalse(isset($c->int));
            $i++;
        }

        $c->reset();

        $this->assertEquals(2, $i);
        $this->assertEquals($c->count(), 4898);
    }

    function testDrop()
    {
        $c = new Dummy;
        $c['foo'] = 'bar';
        $c->save();

        $this->assertFalse(ActiveMongo::drop());
        $this->assertTrue(Dummy::drop());
        try {
            $this->assertFalse(Dummy::drop());
        } catch (Exception $e) {
            $this->assertTrue(TRUE);
        }
    }

    function testInvalidLimits()
    {
        $c = new Model1;
        $this->assertFalse($c->limit(-1, 5));
        $this->assertFalse($c->limit(5, -1));
        $this->assertFalse($c->limit(-1, -5));
    }

    function testInvalidProperties()
    {
        $c = new Model1;
        $this->assertFalse($c->properties(1));
        $this->assertFalse($c->columns(1));
        $this->assertFalse($c->columns(NULL));
        $this->assertFalse($c->columns(TRUE));
    }


    function testInvalidBatchInsert()
    {
        /* Invalid document for Model2 */
        $documents = array(
            array('foo' => 'bar'),
        );
        try {
            /* Invalid call */
            ActiveMongo::BatchInsert($documents);
            $this->assertTrue(False);
        } catch (Exception $e) {
            $this->assertTrue(TRUE);
        }

        try {
            Model2::BatchInsert($documents);
            $this->assertTrue(False);
        } catch (ActiveMongo_FilterException $e) {
            $this->assertTrue(FALSE);
        } catch (MongoException $e) {
            $this->assertTrue(TRUE);
        }

        try {
            Model2::BatchInsert($documents, TRUE, FALSE);
            $this->assertTrue(False);
        } catch (ActiveMongo_FilterException $e) {
            $this->assertTrue(TRUE);
        }

    }

    function testInvalidQueries()
    {
        $c = new Model3;

        try {
            $c->where("invalid field property", 3);
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertTrue(TRUE);
        }

        try {
            $c->where(array(
                "b" => 1,
            ), TRUE);
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertTrue(TRUE);
        }

        
        try {
            $c->sort(" , , ");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertTrue(TRUE);
        }

        try {
            $c->sort("c DESC, field BAR");
            $this->assertTrue(FALSE);
        } catch (Exception $e) {
            $this->assertTrue(TRUE);
        }

        /* These are valid, so no exception should be thrown */
        $c->sort("foo ASC, bar DESC");
        $c->sort("foo DESC");
        $c->sort("foo");
    }

    function testFindWithSingleID()
    {
        $d = new Model1;
        $d->a = 5;
        $d->save();

        $c = new Model1;
        $c->find($d->getID());
        $this->assertEquals(1, $c->count());
        $this->assertEquals($c->a, $d->a);
    }

    function testFindAndModify()
    {

        $c = new Model3;
        $c->where('int <= ', 1000);
        $c->where('processing exists', FALSE);
        $c->limit(50);
        $c->sort('int DESC');
        $c->findAndModify(array("processing" => TRUE));

        $i    = 0;
        $last = 0; 
        foreach ($c as $d) {
            $this->assertEquals($d->processing, TRUE);
            /* testing sort */
            if ($last) {
                $this->assertLessThan($last, $d->int);
            }
            $last = $d->int;
            $i++;
        }
        $this->assertEquals($i, 50);

        try {
            $c->reset();
            $c->where('int <= ', 1000);
            $c->where('processing exists', FALSE);
            $c->limit(50);
            $c->findAndModify(array());
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }
    }
}

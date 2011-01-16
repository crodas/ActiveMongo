<?php

class QueryTest extends PHPUnit_Framework_TestCase
{

    function testConnect()
    {
        ActiveMongo::connect(DB, "localhost");
        try {
            Dummy::instance()->drop();
        } catch (ActiveMongo_Exception $e) {}
        try {
            Model1::instance()->drop();
        } catch (ActiveMongo_Exception $e) {}
        try {
            Model2::instance()->drop();
        } catch (ActiveMongo_Exception $e) {}
        try {
            Model3::instance()->drop();
        } catch (ActiveMongo_Exception $e) {}
        try {
            AutoIncrement_Model::instance()->drop();
        } catch (ActiveMongo_Exception $e) {}
        $this->assertTrue(TRUE);
    }

    function testBulkInserts()
    {
        try { 
            Model3::instance()->drop();
        } catch (ActiveMongo_Exception $e) {}
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
        Model3::instance()->batchInsert($data, TRUE, TRUE);

        $c = Model3::instance();

        $i = 0;
        foreach ($c as $d) {
            $this->assertEquals($i++, $d->int);
        }

        $this->assertEquals($i, 5000);
        $this->assertEquals($c->count(), 5000);
    }

    function testOneResult()
    {
        $c = new Model3;
        $c->limit(1);

        $i = 0;
        foreach ($c as $r) {
            $i++;
        }

        $this->assertEquals($i, 1);
    }
    function testNamespace()
    {
        $this->assertFalse(ActiveMongo::setNamespace('bad namespace'));
        $this->assertFalse(ActiveMongo::setNamespace('bad=namespace'));
        $this->assertFalse(ActiveMongo::setNamespace('bad=namespace!'));
        $this->assertTrue(ActiveMongo::setNamespace('good_namespace'));
        ActiveMongo::setNamespace('testing');
        Model2::setNamespace('foobar');
        list($m1, $m2) = array(new model1, new model2);
        $this->assertEquals($m1->collectionName(), 'testing.model1');
        $this->assertEquals($m2->collectionName(), 'foobar.model2');
        $this->assertTrue(ActiveMongo::setNamespace(NULL)); /* set no-namespace */
        $this->assertTrue(Model2::setNamespace(NULL)); /* set no-namespace */
    }

    function testInstall()
    {
        ActiveMongo::install();
        $index = Model1::instance()->getIndexes();
        $this->assertTrue(isset($index[1]['key']['b']));
        $this->assertTrue(isset($index[2]['key']['a']));
        $this->assertEquals($index[1]['key']['b'], 1);
        $this->assertEquals($index[2]['key']['a'], -1);
        $this->asserTEquals(count($index), 3);
        $index = Model2::instance()->getIndexes();
        $this->assertTrue(isset($index[1]['key']['M1']));
        $this->assertEquals($index[1]['key']['M1'], 1);
        $this->asserTEquals(count($index), 2);
    }

    /**
     *  @depends testBulkInserts
     */
    function testClean()
    {
        $c = new Model3;
        $c->doQuery();

        $this->assertTrue(isset($c->int));
        $this->assertTrue(isset($c['int']));
        $c->clean();
        $this->assertFalse(isset($c->int));
        $this->assertFalse(isset($c['int']));
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
            'fields' => (object)array(
                'a' => 1,
                'b' => 1,
                '_id' => 1, /* from now on _id is included by default */
            )
        );

        // this values are new (In new drivers)
        unset($sQuery['dynamic']['started_iterating'], $sQuery['dynamic']['id']);
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
            'fields' =>(object) array(
                'a' => 1,
                'b' => 1,
                '_id' => 1,
            )
        );

        unset($sQuery['dynamic']['started_iterating'], $sQuery['dynamic']['id']);
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
        $c->update(array('$set' => array('newproperty' => $str)));

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
        $c->clean();
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

        $c->clean();

        /* object with no results can't be cloned */
        try {
            $foo = clone $c;
            $this->AssertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->AssertTrue(TRUE);
        }

        foreach ($c as $item) {
            $item_cloned = clone $item;
            $item_cloned->c = 1;
            $item_cloned->save();
            try {
                /* iterations are forbidden in cloned objects */
                foreach ($item_cloned as $nitem) {
                    $this->assertTrue(FALSE);
                }
            } catch (ActiveMongo_Exception $e) {
                $this->assertTrue(TRUE);
            }
        }

        /* cloned object can't be reused */
        try {
            $item_cloned->clean();
            $this->AssertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->AssertTrue(TRUE);
        }
        try {
            $item_cloned->where('a IN', array(1));
            $this->AssertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->AssertTrue(TRUE);
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

        $c->clean();

        $this->assertEquals(2, $i);
        $this->assertEquals($c->count(), 4898);
    }

    function testDrop()
    {
        $c = new Dummy;
        $c['foo'] = 'bar';
        $c->mFoo  = 'fooooo';
        $c->eFoo  = md5('foo');
        $c->save();

        try {
            $c->eFoo = 'bar';
            $c->save();
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_FilterException $e) {
            $this->assertTrue(TRUE);
        }

        try {
            ActiveMongo::instance()->drop();
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_exception $e) {
            $this->assertTrue(TRUE);
        }

        $this->assertTrue(Dummy::instance()->drop());
        try {
            $this->assertFalse(Dummy::instance()->drop());
        } catch (ActiveMongo_Exception $e) {
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
            ActiveMongo::instance()->BatchInsert($documents);
            $this->assertTrue(False);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }

        try {
            Model2::instance()->BatchInsert($documents);
            $this->assertTrue(False);
        } catch (ActiveMongo_FilterException $e) {
            $this->assertTrue(FALSE);
        } catch (MongoException $e) {
            $this->assertTrue(TRUE);
        }

        try {
            Model2::instance()->BatchInsert($documents, TRUE, FALSE);
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
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }

        try {
            $c->where(array(
                "b" => 1,
            ), TRUE);
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }

        
        try {
            $c->sort(" , , ");
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }

        try {
            $c->sort("c DESC, field BAR");
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
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
        $c->where('_id', $d->getID());
        $c->doQuery();

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

        try {
            $c->count();
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }


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
            $c->clean();
            $c->where('int <= ', 1000);
            $c->where('processing exists', FALSE);
            $c->limit(50);
            $c->findAndModify(array());
            $this->assertTrue(FALSE);
        } catch (ActiveMongo_Exception $e) {
            $this->assertTrue(TRUE);
        }
    }


    /*
    function testFindVariations()
    {
      // add a few entries:
      $documents = array(
        array('int' => 1, 'mod3' => '1'),
        array('int' => 2, 'mod3' => '2'),
        array('int' => 3, 'mod3' => '0'),
        array('int' => 4, 'mod3' => '1'),
        array('int' => 5, 'mod3' => '2'),
        array('int' => 6, 'mod3' => '0'),
        array('int' => 7, 'mod3' => '1'),
        array('int' => 8, 'mod3' => '2'),
        array('int' => 9, 'mod3' => '0'),
      );
      Model3::instance()->BatchInsert($documents, TRUE, FALSE);

      // test findCol (which also tests findPairs and fields)
      $c = new Model3;
      $findCol = $c->findCol('mod3', array('int'=>array('$lt'=>5)));
      $this->assertEquals(array_values($findCol), array('1', '2', '0', '1'));

      // test findOneValue (which will test findOne)
      $this->assertEquals($c->findOneValue('mod3', array('int'=> 5)), '2');

      // test findOneObj (which will test findOneAssoc)
      $obj = $c->findOneObj(array('int'=>5));
      $this->assertEquals($obj, (object)array('int'=>5, 'mod3'=>2, '_id'=>$obj->_id));
    }
    */

    function testDisconnect()
    {
        $this->assertTrue(ActiveMongo::isConnected());
        ActiveMongo::Disconnect();
        $this->assertFalse(ActiveMongo::isConnected());
    }
}

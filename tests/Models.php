<?php

class Model1 extends ActiveMongo
{
    public $a;
    public $b;

    static $validates_presence_of = array(
        'a'
    );

}

class Model2 extends ActiveMongo
{
    public $M1;
    public $a;

    static $validates_presence_of = array(
        'M1',
    );

    function M1_filter($obj)
    {
        if (!$obj InstanceOf MongoID) {
            throw new ActiveMongo_FilterException("Invalid M1 value");
        }
    }

    function update_refs($m1)
    {
        $col = $this->_getCollection();
        $filter  = array('M1' => $m1['_id']);
        $doc     = array('$set' => array('a' => $m1['a']));
        $options = array('multiple' => 1, 'safe' => true);
        $col->update($filter, $doc, $options);
    }

}

class Model3 extends ActiveMongo
{
}

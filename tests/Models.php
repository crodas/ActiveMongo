<?php


class Dummy extends ActiveMongo
{
    static $validates_size_of = array(
        array('mFoo', 'min' => 5, 'max' => 10),
        array('eFoo', 'is' => 32)
    );

}

class Model1 extends ActiveMongo
{
    public $a;
    public $b;

    static $validates_presence_of = array(
        'a'
    );

    function before_create()
    {
    }

    function setup()
    {
        $this->addIndex('b');
        $this->addIndex(array('a' => -1));
    }

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
            if (isset($this->no_throw)) {
                return FALSE;
            } else {
                throw new ActiveMongo_FilterException("Invalid M1 value");
            }
        }
    }

    function setup()
    {
        $this->addIndex(array('M1'));
    }

    function update_refs($m1)
    {
        /* reset just in case */
        $this->clean();
        $this->where('M1', $m1['_id']);
        $this->Update(array('a' => $m1['a']));
    }

}

class Model3 extends ActiveMongo
{
    public $int;
    public $str;

    static $validates_presence_of = array(
        'int'
    );
}

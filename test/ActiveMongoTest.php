<?php
require "../lib/ActiveMongo.php";
require "models.php";
require "ReferencesTest.php";
require "ValidatorsTest.php";

define ("DB", "test");

class ActiveMongoSuite extends PHPUnit_Framework_TestSuite
{
    public function __construct()
    {
        ActiveMongo::connect(DB, "localhost");
        Model1::drop();
    } 

    public static function suite()
    {
        $suite = new ActiveMongoSuite('ActiveMongo Tests');
        $suite->addTestSuite('ReferencesTest');
        $suite->addTestSuite('ValidatorsTest');
        return $suite;
    }

}

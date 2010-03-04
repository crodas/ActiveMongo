<?php
require "../../ActiveMongo.php";
require "sessions.php";


ActiveMongo::connect("activemongo");

MongoSession::init();

session_start();
var_dump(array('previous data' => $_SESSION));
$_SESSION['data']++;
var_dump(array('current data' => $_SESSION));

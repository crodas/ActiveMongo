<?php

require "../../ActiveMongo.php";
require "ActiveShard.php";


$admin = new ActiveShard;

$hosts = array(
    "localhost:10001",
    "localhost:10002",
    "localhost:10003",
);

try {
    foreach ($hosts as $host) {
        print "Adding {$host}:\t ";
        if ($admin->addShard($host, true)) {
            print "OK\n";
        } else {
            print "Failed\n";
        }
    }
} catch (Exception $e) {
    print "Fatal Error (".$e->getMessage().")\n";
}



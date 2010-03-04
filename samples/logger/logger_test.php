<?php

require "../../ActiveMongo.php";
require "logger.php";

/* Connect */
ActiveMongo::connect("activemongo");

MongoLogger::Init();

/* Generate errors */

fopen("/foo-bar-file", "w");

throw new Exception("error");

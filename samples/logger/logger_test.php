<?php

require "../../ActiveMongo.php";
require "logger.php";

/* Connect */
ActiveMongo::connect("activemongo");

MongoLogger::Init();

/* Generate erros */

fopen("/foo-bar-file", "w");

throw new Exception("error");

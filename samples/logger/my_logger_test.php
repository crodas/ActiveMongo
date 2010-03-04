<?php

require "../../ActiveMongo.php";
require "logger.php";
require "my_logger.php";

/* Connect */
ActiveMongo::connect("activemongo");

My_Logger::Init();

/* Generate errors */

fopen("/foo-bar-file", "w");

throw new Exception("error");


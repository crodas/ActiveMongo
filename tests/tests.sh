#!/bin/bash -x

phpunit ActiveMongoSuite.php
~/bin/php-5.2/bin/php -dextension=mongo.so $(which phpunit) ActiveMongoSuite.php 


#!/bin/bash -x
PHP52=~/bin/php-5.2/bin/php
OPTPHP52="-dextension=mongo.so"
PHP53=$(which php)
OPTPHP53=""
PHPUNIT=$(which phpunit)

$PHP53 $PHPOPT53 $PHPUNIT --colors --verbose --coverage-html coverage ActiveMongoSuite.php 
sleep 1
$PHP52 $OPTPHP52 $PHPUNIT --colors --verbose ActiveMongoSuite.php


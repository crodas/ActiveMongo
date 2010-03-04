<?php
/*
  +----------------------------------------------------------------------+
  | Copyright (c) 2009 The PHP Group                                     |
  +----------------------------------------------------------------------+
  | This source file is subject to version 3.0 of the PHP license,       |
  | that is bundled with this package in the file LICENSE, and is        |
  | available through the world-wide-web at the following url:           |
  | http://www.php.net/license/3_0.txt.                                  |
  | If you did not receive a copy of the PHP license and are unable to   |
  | obtain it through the world-wide-web, please send a note to          |
  | license@php.net so we can mail you a copy immediately.               |
  +----------------------------------------------------------------------+
  | Authors: Cesar Rodas <crodas@php.net>                                |
  +----------------------------------------------------------------------+
*/

require "../../ActiveMongo.php";
require "sessions.php";


ActiveMongo::connect("activemongo");

MongoSession::init();

session_start();
var_dump(array('previous data' => $_SESSION));
if (!isset($_SESSION['data'])) {
    $_SESSION['data'] = 0;
}
$_SESSION['data']++;
var_dump(array('current data' => $_SESSION));

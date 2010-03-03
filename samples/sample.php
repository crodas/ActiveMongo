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
require "../ActiveMongo.php";
require "user.php";

ActiveMongo::connect("activemongo_test");

/* Create index (and therefore our collection) */
$users = new Users;
$users->setup();

for ($i=0; $i < 500; $i++) {
    $user = new Users;
    $user->username = uniqid();
    $user->password = uniqid();
    $user->uid      = rand(0,10000);
    $user->karma    = rand(0, 20);
    $user->save(false); /* perform a non-safe but fast save() */
}

/* Simple selection */
$users = new Users;
$users->uid = 5;
foreach ($users->find() as $id=>$u) {
    var_dump(array('first loop', $id, $u->password));
}

/* Complex selection, it gives you the control 
 * over MongoDB Collection
 */
foreach ($users->my_selector() as $id => $user) {
    var_dump(array($id, $user->uid, $user->password));
}

$users->mapreduce();

$users->drop();

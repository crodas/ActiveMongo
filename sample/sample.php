<?php
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
    $user->uid      = $i;
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

$users->drop();

<?php
require "../ActiveMongo.php";
require "user.php";

ActiveMongo::connect("activemongo_test");

for ($i=0; $i < 50; $i++) {
    $user = new Users;
    $user->username = uniqid();
    $user->password = uniqid();
    $user->uid      = $i;
    $user->save(false); /* perform a non-safe but fast save() */
}

/* Simple selection */
$users = new Users;
$users->setup();
$users->uid = 5;
foreach ($users->find() as $id=>$u) {
    var_dump('first loop', $id);
}

/* Complex selection, it gives you the control 
 * over MongoDB Collection
 */
foreach ($users->my_selector() as $id => $user) {
    var_dump($id, $user->uid);
}

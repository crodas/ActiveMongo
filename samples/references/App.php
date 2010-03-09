<?php

require "../../ActiveMongo.php";
require "User.php";
require "Services.php";

ActiveMongo::connect("test");

User::drop();
Twitter::drop();
Blog::drop();

/* Create an user for our 'aggregator' */
$user = new User;
$user->username = "crodas";
$user->password = "crodas";
//$user->save();

/* Create one service */
$twt = new Twitter;
$twt->user = $user;
$twt->rss  = "http://twitter.com/statuses/user_timeline/crodas.rss";
$twt->save();

/* Create another service */
$blg = new Blog;
$blg->user = $user;
$blg->rss  = "http://crodas.org/feed/rss";
$blg->save();

/* Create another service */
$blg1 = new Blog;
$blg1->user = $user;
$blg1->rss  = "http://crodas.org/feed/rss";
$blg1->save();

/* Add references to the current user to its services */
$user->add_service($blg);
$user->add_service($blg1);
$user->add_service($twt);
$user->save();

/* Delete current objects */
unset($user, $blg, $blg1, $twt);

/* Output the document that is going to be sent to MongoDB */
$debug=true;

$users = new User;
$users->username = "crodas";
foreach ($users->find() as $user) {
    /* Load all references */
    $user->doDeferencing();

    /* Modify the first service */
    $user->services[0]->title = 'English Blog';

    /* You need to save the referenced document */
    /* explicitly */
    $user->services[0]->save();

    /* Modify the current user */
    $user->foobar = 'cesar';
    $user->Save();
}

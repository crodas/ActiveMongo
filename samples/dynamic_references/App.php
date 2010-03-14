<?php

require "../../lib/ActiveMongo.php";
require "User.php";
require "Services.php";

ActiveMongo::connect("test");

User::drop();
Service::drop();

/* Create an user for our 'aggregator' */
$user = new User;
$user->username = "crodas";
$user->password = "crodas";
$user->save();

/* Now we are going to query for the services for  
 * the new user, it will return an empty
 * dataset, but we're going to create a 'dynamic' reference
 * to the 'query'. When this reference is deference
 * then same 'query' is going to be asked to the database.
 */
$service = new Service;
$service->user = $user->getID();
$service->find();

/* save reference */
$user->services = $service->getReference(true);
$user->save();


/* Create one service, note that Service::user is $user->getID() */
$twt = new Twitter;
$twt->user = $user->getID();
$twt->rss  = "http://twitter.com/statuses/user_timeline/crodas.rss";
$twt->save();

/* Create another service */
$blg = new Blog;
$blg->user = $user->getID();
$blg->rss  = "http://crodas.org/feed/rss";
$blg->save();

/* Create another service */
$blg1 = new Blog;
$blg1->user = $user->getID();
$blg1->rss  = "http://crodas.org/feed/rss";
$blg1->save();

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

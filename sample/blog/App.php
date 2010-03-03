<?php
require "../../ActiveMongo.php";
require "Post.php";
require "Author.php";

ActiveMongo::connect("activemongo_blog");

/* This should be done just once */
ActiveMongo::install();


$author = new AuthorModel;
$author->username = "crodas";
$author->name = "Cesar Rodas";
$author->save();

$post = new PostModel;
$post->uri = "/hello-world";
$post->author = $author;
$post->save();

$post = new PostModel;
$post->uri = "/hello-world-1";
$post->author = $author;
$post->save();

/* Clean up the current the resultset */
$post->reset();
$post->author = $author->getID();
foreach ($post->find() as $bp) {
    var_dump("Author: ".$bp->author_name);
}

$author->name = "cesar d. rodas";
$author->save();

var_dump("Author profile has been updated");

/* Clean up the current the resultset */
$post->reset();
$post->author = $author->getID();
foreach ($post->find() as $bp) {
    var_dump("Author: ".$bp->author_name);
}

/* delete collections */
$post->drop();
$author->drop();

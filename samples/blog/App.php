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
require "Post.php";
require "Author.php";

ActiveMongo::connect("activemongo_blog");

/* This should be done just once */
ActiveMongo::install();

/* Create a new author
 * The property country is not defined
 * as an AuthorModel property, but it will
 * be saved. 
 */
$author = new AuthorModel;
$author->username = "crodas";
$author->name     = "Cesar Rodas";
$author->country  = "PY"; 
$author->save();

/* Add one blog post */
$post = new PostModel;
$post->uri = "/hello-world";
$post->title  = "Hello World";
$post->author = $author;
$post->save();

/* Add another post */
$post = new PostModel;
$post->uri = "/yet-another-post";
$post->title  = "Yet another post";
$post->author = $author;
/* add one comment */
$post->add_comment("testing", "root@foo.com", "testing comment");
$post->save();

/* add another comment */
$post->add_comment("testing", "root@foo.com", "cool post");
$post->save();

/* Clean up the current the resultset */
/* same as $post = null; $post = new Post Model */
/* but more efficient */
$post->reset();
$post->author = $author->getID();
foreach ($post->find() as $bp) {
    var_dump("Author: ".$bp->author_name);
}

$author->name = "cesar d. rodas";
$author->save();

var_dump("Author profile has been updated");

/** 
 *  List our blog posts in the correct order
 *  (descending by Timestamp).
 */
foreach ($post->listing_page() as $bp) {
    var_dump(array("Author" => $bp->author_name, "Title"=>$bp->title));
}

/* delete collections */
$post->drop();
$author->drop();

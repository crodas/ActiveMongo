<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2010 ActiveMongo                                                  |
  +---------------------------------------------------------------------------------+
  | Redistribution and use in source and binary forms, with or without              |
  | modification, are permitted provided that the following conditions are met:     |
  | 1. Redistributions of source code must retain the above copyright               |
  |    notice, this list of conditions and the following disclaimer.                |
  |                                                                                 |
  | 2. Redistributions in binary form must reproduce the above copyright            |
  |    notice, this list of conditions and the following disclaimer in the          |
  |    documentation and/or other materials provided with the distribution.         |
  |                                                                                 |
  | 3. All advertising materials mentioning features or use of this software        |
  |    must display the following acknowledgement:                                  |
  |    This product includes software developed by César D. Rodas.                  |
  |                                                                                 |
  | 4. Neither the name of the César D. Rodas nor the                               |
  |    names of its contributors may be used to endorse or promote products         |
  |    derived from this software without specific prior written permission.        |
  |                                                                                 |
  | THIS SOFTWARE IS PROVIDED BY CÉSAR D. RODAS ''AS IS'' AND ANY                   |
  | EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED       |
  | WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE          |
  | DISCLAIMED. IN NO EVENT SHALL CÉSAR D. RODAS BE LIABLE FOR ANY                  |
  | DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES      |
  | (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;    |
  | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND     |
  | ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT      |
  | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS   |
  | SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE                     |
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/

require "../../ActiveMongo.php";
require "Post.php";
require "Author.php";

ActiveMongo::connect("activemongo_blog");

/* delete collections */
PostModel::drop();
AuthorModel::drop();

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
/* add one comment */
$post->add_comment("testing", "root@foo.com", "testing comment");
$post->save();

/* add another comment */
$post->add_comment("testing", "root@foo.com", "cool post");
$post->save();

for ($i=0; $i < 1000; $i++) {
    /* Add another post */
    $post = new PostModel;
    $post->uri = "/".uniqid();
    $post->title  = "Yet another post ($i)";
    $post->author = $author;
    $post->save();
}

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


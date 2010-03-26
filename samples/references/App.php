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

require "../../lib/ActiveMongo.php";
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
$user->save();

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
foreach ($users->where('username', 'crodas') as $user) {
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

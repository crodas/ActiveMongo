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


PostModel::addEvent('before_create', function($obj) { print "Attempt to create {$obj['title']}\n"; });

class PostModel extends ActiveMongo
{
    public $title;
    public $uri;
    public $author;
    public $comment;
    public $ts;

    const LIMIT_PER_PAGE=20;

    /**
     *  Return this collection name.
     *
     *  @return string
     */
    function getCollectionName()
    {
        return 'post';
    }

    /**
     *  Display the blog posts entries in the correct
     *  order, getting only the needed columns, and 
     *  adding pagination.
     *
     *  @return PostModel This document
     */
    function listing_page()
    {
        /* Get collection */
        $collection = $this->_getCollection();

        /* Deal with MongoDB directly  */
        $columns = array(
            'title' => 1,
            'uri' => 1,
            'author_name' => 1,
            'author_username' => 1,
            'ts' => 1,
        );
        $cursor  = $collection->find(array(), $columns);
        $cursor->sort(array("ts" => -1))->limit(self::LIMIT_PER_PAGE);

        /* Pagination */
        if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
            $skip = (int)$_GET['page'] * self::LIMIT_PER_PAGE;
            $cursor->skip($skip);
        }

        /* Pass our results to ActiveMongo */
        $this->setCursor($cursor);

        return $this;
    }

    function before_validate(&$obj)
    { 
        $obj['ts'] = new MongoTimestamp();            
    }


    /**
     *  Author Filter.
     *
     *  Check if we save the Author _id, 
     *  or it throws an exception.
     *
     *  @param mixed &$value    Current value
     *  @param mixed $old_value Past value, used on Update
     *
     *  @return bool
     */
    function author_filter(&$value, $old_value)
    {
        if (!$value instanceof MongoID) {
            throw new Exception("Invalid MongoID");
        }
        return true;
    }

    /**
     *  Update Author information in the Posts. This function
     *  is trigger when a new Post is created or the Author
     *  has updated his/her profile.
     *
     *  @param MongoID $id  Author ID
     *  
     *  @return void
     */
    function updateAuthorInfo(MongoID $id)
    {
        $author = new AuthorModel;
        $author->find($id);

        $document = array(
            '$set' => array(
                'author_name' => $author->name,
                'author_username' => $author->username,
            ),
        );

        $filter = array(
            'author' => $id,
        );

        $this->_getCollection()->update($filter, $document, array('multiple' => true));

        return true;
    }

    /**
     *  A new post must have its author information
     *  on it. (to avoid multiple requests at render time).
     *
     *  @return void
     */
    function after_create()
    {
        $this->updateAuthorInfo($this->author);
    }

    /**
     *  Simple abstraction to add a new comment,
     *  to avoid typo errors at coding time.
     */
    function add_comment($user, $email, $comment)
    {
        $this->comment[] = array(
            "user" => $user,
            "email" => $email,
            "comment" => $comment,
        );
        return true;
    }

    function setup()
    {
        $this->addIndex(array('uri' => 1), array('unique'=> 1));
        $this->addIndex(array('author' => 1));
        $this->addIndex(array('ts' => -1));
    }
}



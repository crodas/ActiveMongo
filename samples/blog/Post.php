<?php

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

    function pre_save($op, &$obj)
    { 
        if ($op == "create") {
            $obj['ts'] = new MongoTimestamp();            
        }
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
            if (!$value InstanceOf AuthorModel) {
                throw new Exception("The author property must be an AuthorModel object");
            }
            $value = $value->getID();
            if ($value === false) {
                throw new Exception("The AuthorModel doesn't have any record");
            }
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
    function on_save()
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
        $collection = & $this->_getCollection();
        $collection->ensureIndex(array('uri' => 1), array('unique'=> 1, 'background' => 1));
        $collection->ensureIndex(array('author' => 1), array('background' => 1));
        $collection->ensureIndex(array('ts' => -1), array('background' => 1));
    }
}



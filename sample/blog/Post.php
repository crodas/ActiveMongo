<?php

class PostModel extends ActiveMongo
{
    public $title;
    public $uri;
    public $author;
    public $comment;

    function author_filter(&$value, $old_value)
    {
        if (!$value instanceof MongoID) {
            if (!$value InstanceOf AuthorModel) {
                throw new Exception("The author property must be an AuthorModel object");
            }
            $value = $value->getID();
        }
    }

    function getCollectionName()
    {
        return 'post';
    }

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

    function on_save()
    {
        $this->updateAuthorInfo($this->author);
    }

    function setup()
    {
        $collection = & $this->_getCollection();
        $collection->ensureIndex(array('uri' => 1), array('unique'=> 1, 'background' => 1));
    }
}



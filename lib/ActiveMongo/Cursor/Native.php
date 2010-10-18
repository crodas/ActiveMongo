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

class ActiveMongo_Cursor_Native extends ActiveMongo_Cursor_Interface
{
    protected $cursor;
    protected $current;
    protected $collection;

    function __construct($collection, $query=array())
    {
        if ($collection InstanceOf MongoCursor) {
            $this->collection = null;
            $this->cursor     = $collection;
            return;
        }
        $this->collection = $collection;
        
        if (count($query['properties']) > 0) {
            $cursor = $collection->find($query['query'], $query['properties']);
        } else {
            $cursor = $collection->find($query['query']);
        }

        if (count($query['sort']) > 0) {
            $cursor->sort($query['sort']);
        }

        if ($query['limit'] > 0) {
            $cursor->limit($query['limit']);
        }
        if ($query['skip'] > 0) {
            $cursor->skip($query['skip']);
        }

        $this->cursor = $cursor;
    }

    function count()
    {
        return $this->cursor->count();
    }

    function reset()
    {
        $this->cursor->getNext();
        $this->cursor->rewind();
    }

    function key()
    {
        return $this->cursor->key();
    }

    function valid()
    {
        return $this->cursor->valid();
    }

    function next()
    {
        return $this->cursor->next();
    }

    function current()
    {
        return ($this->current = $this->cursor->current());
    }

    function rewind()
    {
        return $this->cursor->rewind();
    }

    function getReference($class)
    {
        if (empty($this->collection)) {
            throw new ActiveMongo_Exception("Can't have references");
        }

        $ref =  array(
            '$ref'  => $this->collection->getName(),
            '$id'   => $this->current['_id'],
            '$db'   => (string)$this->collection->db,
            'class' => $class
        );

        $cursor = $this->cursor;
        if (!is_callable(array($cursor, "Info"))) {
            throw new ActiveMongo_Exception("Please upgrade your PECL/Mongo module to use this feature");
        }

        $ref['dynamic'] = array();
        $query  = $cursor->Info();

        foreach ($query as $type => $value) {
            $ref['dynamic'][$type] = $value;
        }

        return $ref;
    }

}

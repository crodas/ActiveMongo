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

class ActiveMongo_Cursor_FindAndModify extends ActiveMongo_Cursor_Interface
{
    protected $query;
    protected $collection;
    protected $update;
    protected $properties;
    protected $result;
    protected $has_result;
    protected $is_valid;
    protected $cnt;

    public function __construct($collection, $query)
    {
        $this->collection = $collection;
        $this->query      = $query; 
        $this->cnt        = 0;
    }

    function setUpdate(Array $document)
    {
        if (count($document) === 0) {
            throw new ActiveMongo_Exception("Empty \$document is not allowed");
        }

        if (substr(key($document), 0, 1) != '$') {
            /* document to execute is not a command, so let's append
               it as is */
            $document = array('$set' => $document);
        }

        $this->update = $document;
        $this->next();
    }

    public function next()
    {
        $this->is_valid = FALSE;

        if (isset($this->query['limit']) && $this->cnt >= $this->query['limit']) {
            return;
        }

        $command = array(
            'findandmodify' => $this->collection->getName(),
            'query'         => $this->query['query'],
            'update'        => $this->update,
            'new'           => TRUE,
            'upsert'        => !empty($this->query['upsert']),
        );

        if (isset($this->query['sort'])) {
            $command['sort'] = $this->query['sort'];
        }

        $this->result   = $this->collection->db->command($command);
        $this->is_valid = $this->result['ok'] == 1;

        $this->cnt++;
    }

    public function valid()
    {
        return $this->is_valid;
    }

    public function rewind()
    {
    }

    public function reset()
    {
    }

    public function current()
    {
        return $this->result['value'];
    }

    public function count()
    {
        throw new ActiveMongo_Exception("FindAndModify doesn't support count");
    }

    public function key()
    {
        return (string)$this->result['value']['_id'];
    }

}

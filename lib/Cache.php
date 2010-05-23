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

/**
 *  Cursor used for cached items
 */
class CacheCursor Extends MongoCursor
{
    protected $var;

    function __construct()
    {
    }

    function reset()
    {
    }

    function current()
    {
    }

    function next()
    {
    }

    function rewind()
    {
    }

    function valid()
    {
    }

    function getNext()
    {
    }
}

class ActiveMongoCache
{
    private static $instance;
    private $enabled;

    private function __construct()
    {
        ActiveMongo::addEvent('before_query', array($this, 'QueryHook'));
    }

    public static function Init()
    {
        if (self::$instance) {
            return;
        }
        self::$instance = new ActiveMongoCache;
    }

    public static function enable()
    {
        $this->instance->enabled = TRUE;
    }

    public static function disable()
    {
        $this->instance->enabled = FALSE;
    }
    
    /**
     *
     */
    function QueryHook($class, $query_document, &$resultset)
    {
        $enable = isset($class::$cacheable) ? $class::$cacheable : $this->enabled;
        if (!$enable) {
            return;
        }

        $resultset = new CacheCursor;

        throw new ActiveMongo_Results;
    }
}

ActiveMongoCache::Init();

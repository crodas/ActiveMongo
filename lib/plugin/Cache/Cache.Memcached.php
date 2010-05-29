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
if (!class_exists('Memcached')) {
    return FALSE;
}

final class MemcachedDriver extends CacheDriver
{
    protected $memcached;
    protected $mem;
    protected $host = 'localhost';
    protected $port = 11211;

    function config($name, $value)
    {
        $configs = array('host', 'port');
        if (array_search($name, $configs) === FALSE) {
            throw new Exception("Invalid {$name} configuration");
        }
        $this->$name = $value;
    }

    function isEnabled()
    {
        if (!$this->host || !$this->port) {
            return FALSE;
        }
        if ($this->memcached InstanceOf Memcached) {
            return TRUE;
        }
        $this->memcached = new Memcached;
        $this->memcached->addServer($this->host, $this->port);
        return TRUE;
    }

    function flush()
    {
        $this->memcached->flush();
    }

    function getMulti(Array $keys, Array &$object)
    {
        $object = $this->memcached->getMulti($keys);
        $nkeys  = array_keys($object);
        foreach (array_diff($keys, $nkeys) as $k) {
            $object[$k] = FALSE;
        }
        return TRUE;
    }

    function setMulti(Array $objects, Array $ttl)
    {
        $this->memcached->setMulti($objects);
    }

    function get($key, &$object)
    {
        $object = $this->memcached->get($key);
        if (!$object) {
            if ($this->memcached->getResultCode() == Memcached::RES_NOTFOUND) {
                return FALSE;
            }
        }
        return TRUE;
    }

    function set($key, $object, $ttl)
    {
        $this->memcached->set($key, $object, $ttl);
    }

    function delete(Array $keys)
    {
        foreach ($keys as $key) {
            $this->memcached->delete($key);
        }
    }
}

ActiveMongo_Cache::setDriver(new MemcachedDriver);

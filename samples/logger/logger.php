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
 *  MongoLogger class.
 *
 *  This function save PHP errors in MongoDB 
 *  database.
 *
 *  
 */
class MongoLogger extends ActiveMongo
{
    public $type;
    public $file;
    public $line;
    public $code;
    public $error;

    /**
     *  This method initialize the MongoLogger
     *  class.
     */
    final public static function init()
    {
        $class = get_called_class();
        set_exception_handler(array($class, "exception_logger"));
        set_error_handler(array($class, "error_handler"));
    }

    /**
     *  Save the PHP Error in MongoDB
     */
    final public static function error_handler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        $class = get_called_class();
        if (isset($errcontext['GLOBALS'])) {
            unset($errcontext['GLOBALS']);
        }
        $log = new $class;
        $log->type    = "ERROR";
        $log->code    = $errno;
        $log->error   = $errstr;
        $log->file    = $errfile;
        $log->line    = $errline;
        $log->context = $errcontext;

        /* save it fast */
        $log->save(FALSE);

        return FALSE;
    }

    /** 
     *  Save the Exception in MongoDB
     */
    final public static function exception_logger($exception)
    {
        $class = get_called_class();
        $log = new $class;
        $log->type      = "EXCEPTION";
        $log->exception = get_class($exception); 
        $log->error     = $exception->getMessage();
        $log->line      = $exception->getLine();
        $log->file      = $exception->getFile();
        $log->code      = $exception->getCode();
        $log->trace     = $exception->getTrace();
        $log->save(FALSE);
    }

    /**
     *  Setup the indexes
     */
    function setup()
    {
        $this->addIndex(array("type" => 1));
    }
}


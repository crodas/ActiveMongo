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
 *  get_called_class()
 *
 *  Get_called_class() for php5.2
 *
 *  @return string
 */
function get_called_class()
{
    static $cache = array();
    $class = '';
    foreach (debug_backtrace() as $bt) {
        if (isset($bt['class']) && ($bt['type'] == '->' ||$bt['type'] == '::') && isset($bt['file'])) {
            extract($bt);
            if (!isset($cache["{$file}_{$line}"])) {
                $lines = file($file);
                $expr  = '/([a-z0-9\_]+)::'.$function.'/i';
                $line  = $lines[$line-1];
                preg_match_all($expr, $line, $matches);
                $cache["{$file}_{$line}"] = $matches[1][0];
            }
            if ($cache["{$file}_{$line}"] != 'self' && !empty($cache["{$file}_{$line}"])) {
                $class = $cache["{$file}_{$line}"];
                break;
            }
        }
    }
    return $class;
}

/**
 *  Return TRUE or FALSE whether a static variable
 *  is declared or not
 *
 *  @param $class    Class name
 *  @param $variable Variable name
 *  
 *  @return bool
 */
function isset_static_variable($class, $variable)
{
    $vars = get_class_vars($class);
    return isset($vars[$variable]);
}

/**
 *  Return the content of a static variable
 *
 *  @param $class    Class name
 *  @param $variable Variable name
 *  
 *  @return mixed
 */
function get_static_variable($class, $variable)
{
    $vars = get_class_vars($class);
    return $vars[$variable];
}

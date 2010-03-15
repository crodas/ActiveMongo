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

require_once dirname(__FILE__)."/Validators.php";

/**
 *  This class manages the Events and Filterings
 *
 */
class ActiveMongo_Events
{
    static private $_events = array();
    static private $_super_events = array();

    // addEvent($action, $callback) {{{
    /**
     *  addEvent
     *
     */
    final static function addEvent($action, $callback)
    {
        if (!is_callable($callback)) {
            throw new Exception("Invalid callback");
        }

        $class = get_called_class();
        if ($class == __CLASS__ || $class == 'ActiveMongo') {
            $events = & self::$_super_events;
        } else {
            $events = & self::$_events[$class];
        }
        if (!isset($events[$action])) {
            $events[$action] = array();
        }
        $events[$action][] = $callback;
        return true;
    }
    // }}}

    // triggerEvent(string $event, Array $events_params) {{{
    final function triggerEvent($event, Array $events_params = array())
    {
        $events  = & self::$_events[get_class($this)][$event];
        $sevents = & self::$_super_events[$event];

        if (!is_array($events_params)) {
            return false;
        }

        /* Super-Events handler receives the ActiveMongo class name as first param */
        $sevents_params = array_merge(array(get_class($this)), $events_params);

        foreach (array('events', 'sevents') as $event_type) {
            if (count($$event_type) > 0) {
                $params = "{$event_type}_params";
                foreach ($$event_type as $fnc) {
                    call_user_func_array($fnc, $$params);
                }
            }
        }

        /* Some natives events are allowed to be called 
         * as methods, if they exists
         */
        switch ($event) {
        case 'before_create':
        case 'before_update':
        case 'before_validate':
        case 'before_delete':
        case 'after_create':
        case 'after_update':
        case 'after_validate':
        case 'after_delete':
            $fnc    = array($this, $event);
            $params = "events_params";
            if (is_callable($fnc)) {
                call_user_func_array($fnc, $$params);
            }
            break;
        }
    }
    // }}}

     // void runFilter(string $key, mixed &$value, mixed $past_value) {{{
    /**
     *  *Internal Method* 
     *
     *  This method check if the current document property has
     *  a filter method, if so, call it.
     *  
     *  If the filter returns false, throw an Exception.
     *
     *  @return void
     */
    protected function runFilter($key, &$value, $past_value)
    {
        $filter = array($this, "{$key}_filter");
        if (is_callable($filter)) {
            $filter = call_user_func_array($filter, array(&$value, $past_value));
            if ($filter===false) {
                throw new FilterException("{$key} filter failed");
            }
            $this->$key = $value;
        }
    }
    // }}}

}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */

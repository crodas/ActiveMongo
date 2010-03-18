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

// Valid Presence Of {{{
ActiveMongo::addEvent("before_validate_creation", function ($class, $obj) {
    if (isset($class::$validates_presence_of)) {
        foreach ((Array)$class::$validates_presence_of as $property) {
            if (!isset($obj[$property])) {
                throw new ActiveMongo_FilterException("Missing required property {$property}"); 
            }
        }
    }
});

ActiveMongo::addEvent("before_validate_update", function ($class, $obj) {
    if (isset($class::$validates_presence_of)) {
        foreach ((Array)$class::$validates_presence_of as $property) {
            if (isset($obj['$unset'][$property])) {
                throw new ActiveMongo_FilterException("Cannot delete required property {$property}"); 
            }
        }
    }
});
// }}}

// Valid Size Of / Valid Length Of {{{ 
ActiveMongo::addEvent("before_validate", function ($class, $obj) {
    $validates = array();

    if (isset($class::$validates_size_of)) {
        $validates = $class::$validates_size_of;
    } else if (isset($class::$validates_length_of)) {
        $validates = $class::$validates_length_of;
    }

    foreach ($validates as $property) {
        $name = $property[0];

        if (isset($obj[$name])) {
            $prop = $obj[$name];
        }

        if (isset($obj['$set'][$name])) {
            $prop = $obj['$set'][$name];
        }

        if (isset($prop)) {
            if (isset($property['min']) && strlen($prop) < $property['min']) {
                throw new ActiveMongo_FilterException("{$name} length is too short");
            }
            if (isset($property['is']) && strlen($prop) != $property['is']) {
                throw new ActiveMongo_FilterException("{$name} length is different than expected");
            }
            if (isset($property['max']) && strlen($prop) > $property['max']) {
                throw new ActiveMongo_FilterException("{$name} length is too large");
            }
        }
    }
});
// }}}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */

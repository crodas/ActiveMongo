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
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/

class AuthorModel extends ActiveMongo
{
    public $username;
    public $name;

    function getCollectionName()
    {
        return 'author';
    }

    /**
     *  Username filter.
     *
     *  - It must be unique (handled by MongoDB actually).
     *  - It can't be changed.
     *  - It must be /[a-z][a-z0-9\-\_]+/
     *  - It must be longer than 5 letters.
     *
     *  @return bool
     */
    function username_filter($value, $old_value)
    {
        if ($old_value!=null && $value != $old_value) {
            throw new FilterException("The username can't be changed");
        }

        if (!preg_match("/[a-z][a-z0-9\-\_]+/", $value)) {
            throw new FilterException("The username is not valid");
        }

        if (strlen($value) < 5) {
            throw new FilterException("Username too short");
        }

        return true;
    }

    /**
     *  When an User updates his profile, we need to 
     *  make sure that every post written by him is also
     *  updated with his name and username.
     *
     *  @return void
     */
    function on_update()
    {
        $post = new PostModel;
        $post->updateAuthorInfo($this->getID());
    }

    function setup()
    {
        $this->addIndex(array('username' => 1), array('unique'=> 1));
    }
}

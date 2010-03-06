<?php

class ActiveShard extends ActiveMongo
{
    function getDatabaseName()
    {
        return "admin";
    }

    function getCollectionName()
    {
        return '$cmd';
    }

    function addShard($address, $allowLocal = false)
    {
        $request  = array('addshard' => $address, 'allowLocal' => $allowLocal);
        $response =  $this->sendCmd( $request );

        if ($response['ok'] == 0) {
            if (isset($response['exception'])) {
                throw new MongoException($response['exception']);
            }
            return false;
        }
        return true;
    }
}



#!/bin/bash 

#   Simple script to run several sharded instances
#   of MongoDB. 

BASEDIR="/data/db/sharding"
SHARDS=5
BASEPORT=10000
MASTERPORT=20000
# MongoDB installation path
PATH=$PATH:/home/crodas/projects/hosting/mongo/

echo $PATH

for i in `seq 1 $SHARDS`
do
    DBDIR="$BASEDIR/$i"
    let PORT=$BASEPORT+$i
    mkdir -p $DBDIR
    mongod --fork --port $PORT --logpath "$BASEDIR/$i.log" --dbpath $DBDIR
done

#Starting master process
mkdir -p $BASEDIR/config
mongod --fork --port $MASTERPORT --logpath "$BASEDIR/config.log" --dbpath $BASEDIR/config

# Sleep a bit in order to wait all daemons
sleep 10

mongos --configdb localhost:$MASTERPORT


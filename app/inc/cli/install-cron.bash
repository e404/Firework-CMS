#!/bin/bash

JOBS=`echo "SELECT 1 FROM usr_bws_db1.install" | mysql`

if [ "$JOBS" == "" ]; then
	exit 0
fi

SCRIPTPATH=$( cd $(dirname $0) ; pwd -P )
TS=`date +"%Y%m%d%H%M%S"`
LOGFILE="${SCRIPTPATH}/log/${TS}.log"

cd $SCRIPTPATH

./install-cron.php > $LOGFILE 2>&1

if [ ! -s $LOGFILE ]; then
	rm $LOGFILE
fi

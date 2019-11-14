#!/bin/bash

altpicture-checkJobs.sh &

cd /var/www/html/server/config/sources
# remove old flags
#find . -maxdepth 1 -name "*.use" -type f -mmin +6 -delete

path="scripts"
list="service.list"

while true
do

    if test -f "$list"; then
	cmds=`cat $list`
	mv -f $list $list.last
	rm -f $list
        for cmd in $cmds
	do
	    if test -f "$path/$cmd"; then
		$path/$cmd >> service.log
	    fi
        done
    fi
    sleep 3
done
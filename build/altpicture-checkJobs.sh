#!/bin/bash

lf=/tmp/checkFailedJobsPidLockFile
# create empty lock file if none exists
cat /dev/null >> $lf
read lastPID < $lf
# if lastPID is not null and a process with that pid exists , exit
[ ! -z "$lastPID" -a -d /proc/$lastPID ] && exit
######################################################## echo not running
# save my pid in the lock file
echo $$ > $lf
# sleep just to make testing easier
#sleep 5

for (( ; ; ))
do
    cd /var/www/html/server && php index.php checkFailedJobs
    sleep 5m
done
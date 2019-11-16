#!/bin/bash

DEVICE=sdb1
FOLDER=/media

if [ -b /dev/$DEVICE ]; then
	if ! mountpoint -q -- "$FOLDER/$DEVICE" ; then
		mkdir -p $FOLDER/$DEVICE
        	mount -t auto /dev/$DEVICE $FOLDER/$DEVICE
	fi
else
    umount /dev/$DEVICE
    rm -d $FOLDER/$DEVICE
fi
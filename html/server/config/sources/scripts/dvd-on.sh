#!/bin/bash

DEVICE=cdrom
FOLDER=/media

if [ -b /dev/$DEVICE ]; then
    if ! mountpoint -q -- "$FOLDER/$DEVICE" ; then
        mkdir -p $FOLDER/$DEVICE
        mount -t auto /dev/$DEVICE $FOLDER/$DEVICE
    fi
else
    if [ -d $FOLDER/$DEVICE ]; then
        umount /dev/$DEVICE
        rm -d $FOLDER/$DEVICE
    fi
fi



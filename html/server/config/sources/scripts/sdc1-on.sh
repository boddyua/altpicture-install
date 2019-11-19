#!/bin/bash

DEVICE=sdc1
FOLDER=/media
OPTIONS="-o iocharset=utf8,codepage=1251"

if [ -b /dev/$DEVICE ]; then
    if ! mountpoint -q -- "$FOLDER/$DEVICE" ; then
        mkdir -p $FOLDER/$DEVICE
        mount -t auto /dev/$DEVICE $FOLDER/$DEVICE $OPTIONS
    fi
else
    if [ -d $FOLDER/$DEVICE ]; then
        umount /dev/$DEVICE
        rm -d $FOLDER/$DEVICE
    fi
fi




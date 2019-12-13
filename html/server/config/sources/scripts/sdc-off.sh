#!/bin/bash

debug=true

DEVICE=sdc
FOLDER=/media

i=0
until [ $i -gt 4 ]
do
  ((i++))
  partition=${DEVICE}${i}
  if [ -d $FOLDER/$DEVICE/$partition ]; then #		unmount multiple partition
    if [ $debug ]; then 
      echo "unmount multiple partition /dev/$partition from $FOLDER/$DEVICE/$partition"
    fi
    umount /dev/$partition
    rm -d $FOLDER/$DEVICE/$partition
  fi
done

if [ -d $FOLDER/$DEVICE ]; then
  if [ $debug ]; then 
    echo "unmount device with single partition /dev/$DEVICE from $FOLDER/$DEVICE"
  fi
    umount $FOLDER/$DEVICE
    rm -d $FOLDER/$DEVICE
fi


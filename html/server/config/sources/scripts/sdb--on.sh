#!/bin/bash

debug=false

DEVICE=sdb
FOLDER=/media
OPTIONS="-o iocharset=utf8,codepage=1251"

CNT=0
PARTS=()
i=0
until [ $i -gt 4 ]
do
  ((i++))
  partition=${DEVICE}${i}
  if [ -b /dev/$partition ]; then
    if [ $debug ]; then 
      echo "found $partition"
    fi
    ((CNT++))
    PARTS+=( $partition )
  else
    if [ -d $FOLDER/$DEVICE/$partition ]; then #		unmount multiple partition
      if [ $debug ]; then 
        echo "unmount multiple partition /dev/$partition from $FOLDER/$DEVICE/$partition"
      fi
        umount /dev/$partition
        rm -d $FOLDER/$DEVICE/$partition
    fi
  fi

done

if [ $debug ]; then 
  echo "$DEVICE blocks: $CNT";
fi


if [ $CNT == 0 ]; then #		unmount device with single partition
    if [ -d $FOLDER/$DEVICE ]; then
      if [ $debug ]; then 
        echo "unmount device with single partition /dev/$DEVICE from $FOLDER/$DEVICE"
      fi
        umount $FOLDER/$DEVICE
        rm -d $FOLDER/$DEVICE
    fi
fi

if [ $CNT == 1 ]; then #		mount single partition
  partition=${PARTS[0]}
  if ! mountpoint -q -- "$FOLDER/$DEVICE" ; then
      if [ $debug ]; then 
        echo "mount single partition /dev/$partition to $FOLDER/$DEVICE"
      fi
    mkdir -p $FOLDER/$DEVICE
    mount -t auto /dev/$partition $FOLDER/$DEVICE $OPTIONS
  fi
fi

if [ $CNT -gt 1 ]; then #		mount multiple partitions to separated folder
  for partition in ${PARTS[@]}; do
    if ! mountpoint -q -- "$FOLDER/$DEVICE/$partition" ; then
      if [ $debug ]; then 
        echo "mount multiple partition /dev/$partition to $FOLDER/$DEVICE/$partition"
      fi
      mkdir -p $FOLDER/$DEVICE/$partition
      mount -t auto /dev/$partition $FOLDER/$DEVICE/$partition $OPTIONS
    fi
  done
fi



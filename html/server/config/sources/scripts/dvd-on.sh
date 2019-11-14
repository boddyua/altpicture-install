#!/bin/bash

DEVICE=cdrom

if [ -b /dev/$DEVICE ]; then 
    mkdir /media/$DEVICE
    mount -t auto /dev/$DEVICE /media/$DEVICE
fi
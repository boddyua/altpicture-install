#!/bin/bash

DEVICE=sdc1
FOLDER=/media

umount /dev/$DEVICE
rm -d $FOLDER/$DEVICE
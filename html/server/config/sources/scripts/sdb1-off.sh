#!/bin/bash

DEVICE=sdb1
FOLDER=/media

umount /dev/$DEVICE
rm -d $FOLDER/$DEVICE
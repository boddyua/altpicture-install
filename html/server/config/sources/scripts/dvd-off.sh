#!/bin/bash

DEVICE=cdrom
FOLDER=/media

umount /dev/$DEVICE
rm -d $FOLDER/$DEVICE
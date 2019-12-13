#!/bin/bash

if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root".
    exit 1
fi

MAINDIR=/var/www/html
if [ ! -d "$MAINDIR" ]; then
    echo "Destination directory doesnt exist. Update stopped"
    exit 1
fi


########################################## html
UPDATEDIR=_update

mkdir -p $UPDATEDIR
cp -R ./html/* $UPDATEDIR
find ./$UPDATEDIR -name "*.json" -delete

rm -r $MAINDIR/static
rm -r $MAINDIR/*.*

cp -R ./$UPDATEDIR/* $MAINDIR
chown -R www-data:www-data $MAINDIR/

rm -r $UPDATEDIR

cp ./build/upd-finish $MAINDIR/upd-finish
cd $MAINDIR
$MAINDIR/upd-finish
rm -r $MAINDIR/upd-finish

echo "  Altpicture updated. " ######################################################################

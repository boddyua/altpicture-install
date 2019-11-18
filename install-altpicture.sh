#!/bin/bash

if [[ $EUID -ne 0 ]]; then
    echo "This script must be run as root".
    exit 1
fi

MAINDIR=/var/www/html
if [ -d "$MAINDIR" ]; then
    echo "Destination directory already exist. Installation stopped"
    exit 1
fi


########################################## html
mkdir -p $MAINDIR
cp -R ./html/* $MAINDIR


mkdir -p $MAINDIR/server/orders
mkdir -p $MAINDIR/server/src-net
mkdir -p $MAINDIR/server/src-bluetooth
chown -R www-data:www-data $MAINDIR/

echo ... installing service ... ######################################################################
NAME=altpicture
SERVICEFILE=/usr/local/sbin/altpicture-service.sh

cp ./build/altpicture-service.sh $SERVICEFILE
chmod 775 $SERVICEFILE
cp ./build/altpicture-checkJobs.sh /usr/local/sbin/altpicture-checkJobs.sh
chmod 775 /usr/local/sbin/altpicture-checkJobs.sh

if ! test -f "$SERVICEFILE"; then
    echo "file not found : '$SERVICEFILE'."
    exit 1
fi

if ! test -f /etc/systemd/system/$NAME.service; then
    echo $NAME ....

    echo "[Unit]" > /etc/systemd/system/$NAME.service
    echo "Description=AltPicture sources manager" >> /etc/systemd/system/$NAME.service
    echo "" >> /etc/systemd/system/$NAME.service
    echo "[Service]" >> /etc/systemd/system/$NAME.service
    echo "ExecStart=$SERVICEFILE" >> /etc/systemd/system/$NAME.service
    echo "" >> /etc/systemd/system/$NAME.service
    echo "[Install]" >> /etc/systemd/system/$NAME.service
    echo "WantedBy=multi-user.target" >> /etc/systemd/system/$NAME.service

    chmod 664 /etc/systemd/system/$NAME.service

    service $NAME restart
    if ! systemctl is-failed $NAME --quiet; then
	systemctl enable $NAME
        echo " ... installed "
    else
	echo " ... failed"
        exit 1
    fi
fi


echo ...installing packets... ######################################################################


########
########################################## APT
apt-get update && apt-get install -y \
        nginx \
        curl \
        samba \
        bluez \
        obexpushd \
        python \
        python-pip \
        php7.2 \
        php7.2-fpm \
        php7.2-common \
        php7.2-mbstring \
        php7.2-gd \
        php7.2-curl \
    && pip install Pillow


###################### logrotate
mkdir -p /var/www/log
cp ./build/nginx/altpicture.logrotate /etc/logrotate.d/altpicture

###################### nginx
CONFFILE=/etc/nginx/sites-available/default
if ! test -f "$CONFFILE.altpicture-bak"; then
    cp $CONFFILE $CONFFILE.altpicture-bak
fi
cp ./build/nginx/altpicture.conf $CONFFILE
service nginx restart

###################### php
CONFFILE=/etc/php/7.2/fpm/conf.d/40-php.ini
if ! test -f "$CONFFILE.altpicture-bak"; then
    cp $CONFFILE $CONFFILE.altpicture-bak
fi
cp ./build/nginx/40-php.ini $CONFFILE
service php7.2-fpm restart

###################### samba
CONFFILE=/etc/samba/smb.conf
if ! test -f "$CONFFILE.altpicture-bak"; then
    cp $CONFFILE $CONFFILE.altpicture-bak
fi
cp ./build/samba/smb.conf.ini $CONFFILE
service smbd restart

echo "  Altpicture insalled. " ######################################################################

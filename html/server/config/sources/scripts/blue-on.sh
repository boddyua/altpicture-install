#!/bin/bash

killall obexpushd &
killall bluetoothd &
sleep 1
bluetoothd --compat &
sleep 1
hciconfig hci0 piscan &

obexpushd -o /var/www/html/server/src-bluetooth/ -B &
exit 0

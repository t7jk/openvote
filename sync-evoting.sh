#!/bin/bash

SRC="/home/t7jk/Code/evoting/"
DST="/var/www/html/wordpress/wp-content/plugins/evoting/"

#sudo mkdir -p "$DST"
#sudo chown apache:apache "$DST"

#echo "Startowanie synchronizacji: $SRC -> $DST"

while true; do
    sudo rsync -a --delete \
        --exclude='.git' \
        --exclude='node_modules' \
        --exclude='.claude' \
        --exclude='*.zip' \
        --exclude='*.csv' \
        "$SRC" "$DST"
    
    sudo chown -R apache:apache "$DST"
    sleep 2
done

#!/bin/sh
ps -fe|grep mypool|grep -v grep
if [ $? -ne 0 ]
then
cd /var/www/html/process/examples && nohup php mypool.php &
echo "start process....."
else
echo "runing....."
fi

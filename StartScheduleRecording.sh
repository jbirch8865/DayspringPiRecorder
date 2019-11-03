#!/bin/bash
NOW=$(date +"%Y-%m-%d-%H")
find /var/www/html/DayspringRecorder/recordings -type f -mtime +7 -exec rm -f {} \;
php StartRecording.php -l=90 -n=$NOW

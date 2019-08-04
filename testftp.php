<?php
require 'vendor/jbirch8865/ftpclass/src/FTPClass.php';

$ftp = new \ftpclass\FTP_Link;

$ftp->Upload_Single_File('VolumeChecks.txt','/var/www/html/DayspringRecorder','.');

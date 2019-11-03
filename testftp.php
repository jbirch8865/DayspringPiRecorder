<?php
require 'vendor/jbirch8865/ftpclass/src/FTPClass.php';

$ftp = new \ftpclass\FTP_Link;

$ftp->Upload_Single_Raw_File('Sep-03-21:06:01.wav','/var/www/html/DayspringRecorder/recordings','.');

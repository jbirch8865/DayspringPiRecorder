<?php
require 'RecordingClass.php';
$opts = getopt('n:s:');
if(isset($opts['n']))
{
        if(is_array($opts['n']))
        {
                ForEach($opts['n'] as $opt)
                {
                        $name = $opt;
                }
        }else
        {
                $name = $opts['n'];
        }
}else
{
        $name = 'default';
}
if(isset($opts['s']))
{
	echo 'is set';
        if(is_array($opts['s']))
        {
		echo 'is array';
                ForEach($opts['s'] as $opt)
                {
                        $start_position = $opt;
                }
        }else
        {
		echo 'is not array';
                $start_position = $opts['s'];
        }
}
echo ' - '.$name.' - '.$start_position;
try {
	if(isset($name) && isset($start_position))
	{
		$Recording = new Recording($name);
		$start_position = $start_position;
	}else
	{
		exit("No name given.  Please use option -n=example to specify a recording to check.");
	}
	if($Recording->Is_Recording_Volume_Good($start_position, 300))
	{
		shell_exec("echo ".date("H:i").' '.$name.' - Volume is good at '.$Recording->What_Is_My_Volume().' dbs. >> VolumeChecks.txt');
	}else
	{
		shell_exec("echo ".date("H:i").' '.$name.' - Volume is bad at '.$Recording->What_Is_My_Volume().' dbs. >> VolumeChecks.txt');
		$Alert_Team = new SMSMessageWithChecks();
		$Alert_Team->Set_Message_Body('Volume is bad at '.$Recording->What_Is_My_Volume().' dbs.');
		//$Alert_Team->Send_Message(); disabled until twilio reactivates account
	}
} catch (\Exception $e)
{
	echo $e->getMessage();
}
?>

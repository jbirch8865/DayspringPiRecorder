<?php
require 'RecordingClass.php';
$opts = getopt('n:s:');
var_dump($opts);
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
}

if(isset($opts['s']))
{
	if(is_array($opts['s']))
	{
		ForEach($opts['s'] as $opt)
		{
			$start_position = $opt;
		}
	}else
	{
		$start_position = $opts['s'];
	}
}

try {
	if(isset($name) && isset($start_position))
	{
		$Recording = new Recording($name);
		if(!$Recording->Does_Recording_Exist())
		{
			exit("Recoring has not started");
		}
		$next_start_position = $start_position + 60;
	}else
	{
		exit("No name given.  Please use option -n=example to specify a recording to check.");
	}
	shell_exec("php CheckRecordingVolume.php -n=".$name." -s=".$start_position);
	if($Recording->Am_I_Finished_Recording())
	{
		@shell_exec("php FinalizeRecording.php -n=".$name);
	}else
	{
		@shell_exec("echo 'php MonitorRecording.php -n=".$name." -s=".$next_start_position."' | at ".date("h:i A",strtotime("+5 minutes")));
	}
} catch (\Exception $e)
{
	echo $e->getMessage();
}
?>

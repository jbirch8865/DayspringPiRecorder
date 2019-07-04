<?php
require 'RecordingClass.php';
$opts = getopt('n:l:');
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
if(isset($opts['l']))
{
	if(is_array($opts['l']))
	{
		ForEach($opts['l'] as $opt)
		{
			$length_to_record = $opt;
		}
	}else
	{
		$length_to_record = $opts['l'];
	}
}else
{
	$length_to_record = 60;
}
try {
	$Recording = new Recording($name);
	echo $name = $Recording->Get_Filename();
	$Recording->Set_Length_To_Record_In_Minutes($length_to_record);
	$Recording->Start_Recording();
	@shell_exec('echo "php MonitorRecording.php -n='.$name.' -s=0" | at '.date("h:i A",strtotime("+1 minute")));
} catch (\Exception $e)
{
	echo $e->getMessage();
}
?>

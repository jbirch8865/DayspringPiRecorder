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
}
try {
	if(isset($name))
	{
		$Recording = new Recording($name);
	}else
	{
		exit("No recording to finalize");
	}
	$Recording->Finalize_Recording();
} catch (RecordingNotReadyToFinalize $e)
{
	echo 'Error -'.$e->getMessage();
} catch (\Exception $e)
{
	echo $e->getMessage();
}
?>

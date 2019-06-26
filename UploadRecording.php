<?php
require_once 'vendor/autoload.php';
require 'vendor/jbirch8865/twilio/SendSMS.php';
require 'vendor/jbirch8865/ftpclass/src/FTPClass.php';

class NoRecordedings Extends \Exception{}
class TooManyRecordings Extends \Exception{}
class FailedToStartRecording Extends \Exception{}
class RecordingAlreadyStarted Extends \Exception{}

class Recording {
	private $filename;
	private $currently_recording;
	private $has_volume;
	private $overdriving;
	private $max_amplitude;
	private $length_in_seconds;

	function __construct()
	{
		$this->filename = false;
		$this->currently_recording = false;
	}

	public function Start_Recording($DurationinSeconds = 7200)
	{
		if(!$this->filename){$this->Set_Filename();}
		if($this->currently_recording)
		{
			throw new RecordingAlreadyStarted("This recording has already been started");
		}
		$this->Kill_A_Record();
		if($DurationinSeconds > 7200){$DurationinSeconds = 7200;}
		$SampleRateAdjustment = round($DurationinSeconds * 5.52,0); //This multiplier is based on the sample rate being run, the duration in seconds is based on a sample rate of 8000mhz, however the below command in our configuration will run at 44100mhz
		$run = "sudo -u pi arecord -f S16_LE -D hw:1,0 -d $SampleRateAdjustment /var/www/html/DayspringRecorder/recordings/$this->filename.wav > /dev/null 2>/dev/null &";
		//echo $run;
		$response = shell_exec($run);
		$SystemStatus = new SystemStatus;
		if(count($SystemStatus->Return_Files_Currently_Recording()) > 0)
		{
			$this->currently_recording = true;
		}else
		{
			throw new FailedToStartRecording("There was an issue running arecord command");
		}
	}

	public function Analyze_Recording()
	{
		$dir = new DirectoryIterator(dirname(__FILE__).'/recordings');
		foreach ($dir as $fileinfo) {
			clearstatcache();
			if (!$fileinfo->getfileName() == $this->filename.".wav") 
			{
				$response = shell_exec("sox ".dirname(__FILE__).'/recordings/'.$this->filename.'.wav -n stat > AnalyzeRecording.txt');
			}
		}
	}

	private function Set_Filename($Name = 'default')
	{
		if($Name == 'default')
		{
			$this->filename = uniqid();
		}else
		{
			$this->filename = $Name;
		}
	}

	private function Kill_A_Record()
	{
		$run = 'sudo -pi pkill arecord 2>&1';
		$response = shell_exec($run);
	}
}

class SystemStatus {
	private $file_state1;
	private $file_state2;
	private $current_file_state;
	private $currently_recording;

	function __construct()
	{
		$this->Files_Currently_Recording();
		print_r($this->cure
	}
	public function Files_Currently_Recording()
	{
		try {
			$this->currently_recording = array();
			$this->Load_File_States();
//			print_r($this->file_state1);
//			print_r($this->file_state2);
			if(count($this->file_state1) == count($this->file_state2))
			{
				ForEach($this->file_state1 as $file => $file_info)
				{
					if($this->file_state2[$file]['file_size'] != $file_info['file_size'])
					{
						$this->currently_recording[$file_info['file_name']] = $file_info['file_size'];
						return;
					}
				}
			}
		} catch (TooManyRecordings $e)
		{
			throw new TooManyRecordings("There are too many recordings to populate currently recording list");
		} catch (\Exception $e)
		{
			throw new \Exception("Unknown Error");
		}
	}

	public function Return_Files_Currently_Recording()
	{
		return $this->currently_recording;
	}
	private function Load_File_States()
	{
		try {
			$this->Load_File_State_1();
			sleep(2);
			$this->Load_File_State_2();
		} catch (TooManyRecordings $e)
		{
			throw new TooManyRecordings("Too many recordings to load file states");
		} catch (Exception $e)
		{
			throw new \Exception("Unknown error getting file states");
		}
	}

	private function Load_File_State_1()
	{
		try {
			$this->Get_Current_File_State();
			$this->file_state1 = $this->current_file_state;

		} catch (TooManyRecordings $e)
		{
			throw new TooManyRecordings("There are too many recordings to load file state 1");
		}
	}

	private function Load_File_State_2()
	{
		try {
			$this->Get_Current_File_State();
			$this->file_state2 = $this->current_file_state;

		} catch (TooManyRecordings $e)
		{
			throw new TooManyRecordings("There are too many recordings to load file state 2");
		}
	}

	private function Get_Current_File_State()
	{
		try
		{
			$this->current_file_state = array();
			$dir = new DirectoryIterator(dirname(__FILE__).'/recordings');
			$Files = array();
			if(iterator_count($dir) > 100){
				throw new TooManyRecordings("There are too many recordings to compare file states");
			}
			foreach ($dir as $fileinfo) {
				clearstatcache();
				if (!$fileinfo->isDot()) {
					$this->current_file_state[] = array('file_name' => $fileinfo->getfileName(),'file_size' => filesize($fileinfo->getpathName()), 'last_modified' => filemtime($fileinfo->getpathName()));
				}
			}
		} catch (TooManyRecordings $e)
		{
			throw new TooManyRecordings("There are too many recordings to itterate");
		} catch (\Exception $e)
		{
			throw new \Exception("Unknown error grabbing file state");
		}
	}

}


$recording = new Recording;

$recording->Start_Recording();

?>

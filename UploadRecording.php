<?php
function Return_Line_From_File($relative_file_to_search, $search_for)
{
	$file = $relative_file_to_search;
	$searchfor = $search_for;

	// the following line prevents the browser from parsing this as HTML.
	header('Content-Type: text/plain');

	// get the file contents, assuming the file to be readable (and exist)
	$contents = file_get_contents($file);
	// escape special characters in the query
	$pattern = preg_quote($searchfor, '/');
	// finalise the regular expression, matching the whole line
	$pattern = "/^.*$pattern.*\$/m";
	// search, and store all matching occurences in $matches
	if(preg_match($pattern, $contents, $matches)){
	   //echo "Found matches:\n";
	   return $matches;
	}
	else{
	   return false;
	}
}
require_once 'vendor/autoload.php';
require 'vendor/jbirch8865/twilio/SendSMS.php';
require 'vendor/jbirch8865/ftpclass/src/FTPClass.php';

class NoRecordedings Extends \Exception{}
class TooManyRecordings Extends \Exception{}
class FailedToStartRecording Extends \Exception{}
class RecordingAlreadyStarted Extends \Exception{}

class Recording {
	private $filename;
	private $recording_started;
	private $has_volume;
	private $overdriving;
	private $max_amplitude;
	private $length_in_seconds;
	private $recording_is_uploaded;
	private $recording_finished;

	function __construct($file_name = 'default')
	{
		$this->Set_Filename($file_name);
		if($this->Does_Recording_Exist())
		{
			$this->Load_Recording_Details();
		}else
		{
			$this->Configure_New_Recording_Default_Values();
		}
	}

	public function Start_Recording($should_i_free_recording_device_if_locked = true)
	{
		if($this->recording_started)
		{
			throw new RecordingAlreadyStarted("This recording has already been started");
		}
		if($should_i_free_recording_device_if_locked)
		{
			//No matter what make sure sound device is free
			$this->Kill_A_Record();
		}
		if($duration_in_seconds > 7200){$duration_in_seconds = 7200;}
		$sample_rate_adjustment = round($duration_in_seconds * 5.52,0); //This multiplier is based on the sample rate being run, the duration in seconds is based on a sample rate of 8000mhz, however the below command in our configuration will run at 44100mhz
		$command_to_start_recording = "sudo -u pi arecord -f S16_LE -D hw:1,0 -d $sample_rate_adjustment /var/www/html/DayspringRecorder/recordings/$this->filename.wav > /dev/null 2>/dev/null &";
		$response_from_device = shell_exec($command_to_start_recording);
		sleep(1); //give system time to start recording.  Can't wait for shell_exec to finish because it has to wait however long we decided to record for.  
		$system_status = new SystemStatus;
		if(count($system_status->Return_Files_Currently_Recording()) > 0)
		{
			$this->recording_started = true;
		}else
		{
			throw new FailedToStartRecording("There was an issue running arecord command");
		}
	}

	private function Does_Recording_Exist()
	{
		if(file_exists("recordings/".$this->filename.".wav"))
		{
			return true;
		}else
		{
			return false;
		}
	}

	private function Load_Recording_Details()
	{
		$this->recording_started = true;
		if($this->Am_I_Finished_Recording())
		{
			$this->recording_finished = true;
		}else
		{
			$this->recording_finisehd = false;
		}
		$this->Analyze_Recording_File();
		$this->Set_Audio_Details();
	}

	private function Am_I_Finished_Recording()
	{
		$value_to_return = true;
		$system_status = new SystemStatus;
		$files_currently_recording = $system_status->Return_Files_Currently_Recording();
		ForEach($filess_currently_recording as $File)
		{
			if($File['file_name'] == $this->filename.".wav")
			{
				$value_to_return = false;
			}
		}
		return $value_to_return;

	}

	private function Analyze_Recording_File()
	{
		$Analyze_Sox_Command = "sudo -u pi sox".dirname(__FILE__).'/recordings/'.$this->filename.'.wav -n stat > '.$this->filename.'.txt';
		$response = shell_exec($Analyze_Sox_Command);
		if(file_exists('recordings/'.$this->filename.'.txt'))
		{
			$this->max_amplitude = Return_Line_From_File('recordings/'.$this->filename.'.txt',"max_amplitude");
			$this->max_amplitude = preg_replace('/\s+/', '', $this->max_amplitude);
			$this->max_amplitude = str_replace("Maximumamplitude:","",$this->max_amplitude);
			
			$this->duration_in_seconds = Return_Line_From_File('recordings/'.$this->filename.'.txt',"Length");
			$this->duration_in_seconds = preg_replace('/\s+/', '', $this->duration_in_seconds);
			$this->duration_in_seconds = str_replace("Length(seconds):","",$this->duration_in_seconds);

		}else
		{
			throw new \Exception("There was an error analyzing this recording");
		}

	}

	private function Set_Filename($file_name)
	{
		if($file_name == 'default')
		{
			$this->filename = uniqid();
		}else
		{
			$this->filename = $file_name;
		}
	}

	private function Configure_New_Recording_Default_Values()
	{
		$this->Set_Length_To_Record_In_Minutes(60);
		$this->recording_started = false;
		$this->has_volume = false;
		$this->overdriving = false;
		$this->max_amplitude = 0;
		$this->recording_is_uploaded = false;
		$this->recording_finished = false;
	}

	private function Set_Recording_Started()
	{
		$this->recording_started = true;
	}

	public function Set_Length_To_Record_In_Minutes($length_to_record)
	{
		if($this->recording_started)
		{
			throw new RecordingAlreadyStarted("Sorry you can't update the length to record after a recording has already started");
		}
		try
		{
			$this->duration_in_seconds = $length_to_record * 60;
		} catch (\Exception $e)
		{
			throw new \Exception("Error setting duration in seconds");
		}
	}

	private function Kill_A_Record()
	{
		$run = 'sudo -pi pkill arecord 2>&1';
		$response = shell_exec($run);
		sleep(1);
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
	}

	public function Files_Currently_Recording()
	{
		try {
			$this->currently_recording = array();
			$this->Load_File_States();
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
$status = new systemstatus;
try {
	$recording->Start_Recording();
	echo 'success';
} catch (Exception $e)
{
	echo 'failed';
}
//print_r($status->Return_Files_Currently_Recording())

?>

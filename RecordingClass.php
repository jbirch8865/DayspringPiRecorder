<?php
require_once 'vendor/autoload.php';
require 'vendor/jbirch8865/twilio/SendSMS.php';
require 'vendor/jbirch8865/ftpclass/src/FTPClass.php';
require 'Sox.php';

class RecordingExceptions Extends \Exception{

	function __construct($message, $ErrorMessage)
	{
		parent::__construct($message, 0);
//		$recipients = array("+15038287180","+15038846528");
		$recipients = array("5038287180");
		//$sms = new SMSMessageWithChecks();
		//$sms->Set_Message_Body($ErrorMessage);
		ForEach($recipients as $recipient)
		{
		//	$sms->Set_To_Number("1".$recipient);
			//$sms->Send_SMS();
		}
	}
}
class NoRecordings Extends RecordingExceptions{

	function __construct($message)
	{
		parent::__construct($message, "We are trying to do something on a recording but the recording doesn't exist.");
	}
}
class TooManyRecordings Extends RecordingExceptions{
	function __construct($message)
	{
		parent::__construct($message, "This is bad there are too many recordings. HELP!!!");
	}
}
class FailedToStartRecording Extends RecordingExceptions{
	function __construct($message)
	{
		parent::__construct($message, "Failed to Start Recording");
	}
}
class RecordingAlreadyStarted Extends RecordingExceptions{
	function __construct($message)
	{
		parent::__construct($message, "We are trying to start the recording again!");
	}
}
class RecordingNotReadyToFinalize Extends RecordingExceptions{
	function __construct($message)
	{
		parent::__construct($message, "Can't finalize the recording because it isn't finished recording.");
	}
}
class CantDeleteFile Extends RecordingExceptions{
	function __construct($message)
	{
		parent::__construct($message, "Error Deleting File");
	}
}
class Recording {
	private $filename;
	private $recording_started;
	private $max_amplitude;
	private $length_in_seconds;
	private $sample_max_amplitude;
	private $sample_length_in_seconds;
	private $sample_size_in_seconds;
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
		$sample_rate_adjustment = round($this->duration_in_seconds * 5.52,0); //This multiplier is based on the sample rate being run, the duration in seconds is based on a sample rate of 8000mhz, however the below command in our configuration will run at 44100mhz
		$command_to_start_recording = "sudo -u pi arecord -f S16_LE -D hw:1,0 -d $sample_rate_adjustment ".dirname(__FILE__)."/recordings/$this->filename.wav > /dev/null 2>/dev/null &";
		shell_exec($command_to_start_recording);
		sleep(1); //give system time to start recording.  Can't wait for shell_exec to finish because it has to wait however long we decided to record for.  
		try{
			$system_status = new SystemStatus;
		}catch(\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
		if(count($system_status->Return_Files_Currently_Recording()) > 0)
		{
			$this->recording_started = true;
		}else
		{
			throw new FailedToStartRecording("There was an issue running arecord command");
		}
	}

	public function Does_Recording_Exist()
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
		if($this->Does_Recording_Exist())
		{
			$this->recording_started = true;
		}else
		{
			throw new NoRecordings("This recording doesn't exist");
		}
		try {
			if($this->Am_I_Finished_Recording())
			{
				$this->recording_finished = true;
			}else
			{
				$this->recording_finished = false;
			}
			$this->Analyze_Recording_File();
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
	}

	public function Am_I_Finished_Recording()
	{
		$value_to_return = true;  //Is this the best way to do this?
		try{
			$system_status = new SystemStatus;
		}catch(\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
		$files_currently_recording = $system_status->Return_Files_Currently_Recording();
		ForEach($files_currently_recording as $file_name => $file_size)
		{
			if($file_name == $this->filename.".wav")
			{
				$value_to_return = false;
			}
		}
		return $value_to_return;
	}

	private function Split_Audio_File_For_Analyzing($start_in_seconds)
	{
		try {
			if(is_file('recordings/'.$this->filename.'.wav'))
			{
				@shell_exec("/usr/bin/sox ".dirname(__FILE__)."/recordings/".$this->filename.".wav ".dirname(__FILE__)."/recordings/".$this->filename."Analyze.wav trim ".$start_in_seconds." ".$this->sample_size_in_seconds);
			}else
			{
				throw new NoRecordings("This recording doesn't exist");
			}
			if(!is_file('recordings/'.$this->filename."Analyze.wav"))
			{
				throw new \Exception("Error splitting file");
			}
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
	}

	private function Analyze_Sample_Of_Recording($start_position_in_seconds = 0)
	{
		try {
			$this->Split_Audio_File_For_Analyzing($start_position_in_seconds);
			if(is_file('recordings/'.$this->filename.'Analyze.wav')) {
		       		$sox = new sox('recordings/'.$this->filename.'Analyze.wav');
				$results = $sox->Analyze();
				$this->sample_max_amplitude = $results['max_amp'];
				$this->sample_duration_in_seconds = $results['length'];
			} else {
				throw new NoRecordings();
			}
		} catch (NoRecordings $e)
		{
			throw new NoRecordings("This file hasn't been created yet");
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}

	}

	public function Analyze_Recording_File()
	{
		try {
			if(is_file('recordings/'.$this->filename.'.wav')) {
		       		$sox = new sox('recordings/'.$this->filename.'.wav');
				$results = $sox->Analyze();
				$this->max_amplitude = $results['max_amp'];
				$this->duration_in_seconds = $results['length'];
			} else {
				throw new NoRecordings();
			}
		} catch (NoRecordings $e)
		{
			throw new NoRecordings("This file hasn't been created yet");
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}

	}

	public function Finalize_Recording()
	{
		try {
			if($this->recording_finished)
			{
				$this->Upload_Recording();
				$this->Delete_Recording_Locally();
			}elseif(!$this->recording_started)
			{
				throw new RecordingNotReadyToFinalize('Recording hasn\'t been started.');
			}else
			{
				throw new RecordingNotReadyToFinalize('Can\'t finalize recording because it hasn\'t finished recording yet.');
			}
		} catch (RecordingNotReadyToFinalize $e)
		{
			throw new RecordingNotReadyToFinalize($e->getMessage());
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
	}

	public function Is_Recording_Volume_Good($starting_point_in_seconds, $sample_size_in_seconds = 300)
	{
		try {
			$this->Set_Sample_Size_In_Seconds($sample_size_in_seconds);
			$this->Analyze_Sample_Of_Recording($starting_point_in_seconds);
			if(!$this->recording_started)
	        	{
	                	throw new NoRecordings("This recording hasn't started yet");
	        	}
		        if($this->Am_I_Overdriving())
		        {
		                return false;
		        }elseif(!$this->Do_I_Have_Any_Volume()) //Is doing ! a good$
		        {
		                return false;
		        }else
		        {
		                return true;
		        }
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
	}

	public function Get_Duration_In_Seconds()
	{
		return $this->duration_in_seconds;
	}

	private function Upload_Recording()
	{
		try {
			$ftp = new ftpclass\FTP_Link;
			$ftp->Upload_Single_Raw_File($this->filename.".wav",dirname(__FILE__)."/recordings/",".");
		} catch (BadIniFile $e)
		{
			throw new \Exception("FTP down.  Need to check the configuration ini file in the vendor/jbirch8865/ftpclass/src directory.");
		} catch (BadFTPLogin $e)
		{
			throw new \Exception("FTP credentials incorrect.  Check the ini file located in vendor/jbirch8865/ftpclass/src directory");
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
	}

	private function Am_I_Overdriving()
	{
		if($this->sample_max_amplitude > .98)
		{
			return true;
		}else
		{
			return false;
		}
	}

	private function Do_I_Have_Any_Volume()
	{
		if($this->sample_max_amplitude <.1)
		{
			return false;
		}else
		{
			return true;
		}
	}

	public function What_Is_My_Volume()
	{
		return $this->sample_max_amplitude;
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

	public function Get_Filename()
	{
		return $this->filename;
	}

	private function Set_Sample_Size_In_Seconds($sample_size)
	{
		try {
			$this->sample_size_in_seconds = $sample_size * 60 /60;
		} catch(\Exception $e) {
			throw new \Exception($e->getMessage());
		}
	}

	private function Configure_New_Recording_Default_Values()
	{
		$this->Set_Length_To_Record_In_Minutes(60);
		$this->recording_started = false;
		$this->max_amplitude = 0;
		$this->sample_max_amplitude = 0;
		$this->sample_length_in_seconds = 0;
		$this->sample_size_in_seconds = 300;
		$this->recording_is_uploaded = false;
		$this->recording_finished = false;
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

	public function Delete_Recording_Locally()
	{
		try {
			unlink("recordings/".$this->filename.".wav");
			@unlink("recordings/".$this->filename."Analyze.wav");
			if(file_exists("recordings/".$this->filename.".wav"))
			{
				throw new \Exception("File still exists");
			}
		} catch(\Exception $e)
		{
			throw new CantDeleteFile($e->getMessage());
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
		try {
			$this->Files_Currently_Recording();
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
		}
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
			throw new \Exception($e->getMessage());
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
			throw new \Exception($e->getMessage());
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
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage());
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
		} catch (\Exception $e)
		{
			throw new \Exception($e->getMessage);
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
			throw new \Exception($e->getMesssage());
		}
	}

}

?>

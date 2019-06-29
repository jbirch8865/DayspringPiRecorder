<?php
/* 
        wrapper for sox, optionally requires sox with mp3 support
	by unosonic	
*/

class sox {
        private $sox_cmd = "/usr/bin/sox";
        private $soxi_cmd = "/usr/bin/soxi";
        private $soxi_chan_cmd = "/usr/bin/soxi -c";
        private $soxi_brate_cmd = "/usr/bin/soxi -b";
        private $soxi_brate_avg_cmd = "/usr/bin/soxi -B";
        private $soxi_srate_cmd = "/usr/bin/soxi -r";
        private $soxi_type_cmd = "/usr/bin/soxi -t";
        private $sox_analyze_stats_cmd = "/usr/bin/sox %s -n stats 2>&1";
        private $sox_analyze_stat_cmd = "/usr/bin/sox %s -n stat 2>&1";


        public $filetype;
        public $channels;
        public $samplerate;
        public $bitrate;
        public $bitrate_avg;
        public $soundfile;

        function __construct ($file) {

                if(!is_file($this->sox_cmd)) {
                        throw new Exception('Error: sox not found.');
                }

                if(!is_file($this->soxi_cmd)) {
                        throw new Exception('Error: soxi not found.');
                }

                if(isset($file) && is_file($file)) {

                        $cmd = $this->soxi_cmd . ' ' . $file;
                        $type_cmd = $this->soxi_type_cmd . ' ' . $file;
                        $chan_cmd = $this->soxi_chan_cmd . ' ' . $file;
                        $srate_cmd = $this->soxi_srate_cmd . ' ' . $file;
                        $brate_avg_cmd = $this->soxi_brate_avg_cmd . ' ' . $file;
                        $brate_cmd = $this->soxi_brate_cmd . ' ' . $file;

                        @exec($cmd, $out, $err);

                        // file exists, but sox can't open it
                        if (empty($out) && intval($err == 1)) {
                                throw new Exception('Error: sound file format of ' . $this->soundfile . ' is not recognized.');
                        }

                        // file type
                        unset($out);
                        unset($err);
                        @exec($type_cmd, $out, $err);
                        // var_dump($out);
                        $this->filetype = $out[0];

                        // # of channels
                        unset($out);
                        unset($err);
                        @exec($chan_cmd, $out, $err);
                        // var_dump($out);
                        $this->channels = $out[0];

                        // sample rate
                        unset($out);
                        unset($err);
                        @exec($srate_cmd, $out, $err);
                        //var_dump($out);
                        $this->samplerate = $out[0];

                        // bitrate per sample (0 for mp3)
                        unset($out);
                        unset($err);
                        @exec($brate_cmd, $out, $err);
                        //var_dump($out);
                        $this->bitrate = $out[0];

                        // average bitrate over all samples
                        unset($out);
                        unset($err);
                        @exec($brate_avg_cmd, $out, $err);
                        //var_dump($out);
                        $this->bitrate_avg = $out[0];

                        // remember soundfile
                        $this->soundfile = $file;


                        // debug
                        // echo $this->soundfile . " is of type " . $this->filetype . ", has " . $this->channels . " channel(s), a sample rate of " . $this->samplerate . "Hz and a bitrate per sample of " . $this->bitrate . " (" . $this->bitrate_avg . "bit/s average over all samples)\n";


                } else {
                        throw new Exception('Error: no soundfile given or file is not readable.');
                }
        }

        /* exec the stat[s] cmd of sox, ugly parsing... save values in array 
                "/[\s,]+/"  => arbitrary number of space or comma
                "/:/"
        */
        public function analyze() {
                $stat_cmd = sprintf($this->sox_analyze_stat_cmd, $this->soundfile);
                $stats_cmd = sprintf($this->sox_analyze_stats_cmd, $this->soundfile);
                @exec($stat_cmd, $out_stat, $err);
                @exec($stats_cmd, $out_stats, $err);
                $arr = array();
                foreach ($out_stat as $value) {

                        // # of samples read
                        if(strpos($value, "Samples read") !== FALSE) {
                                $arr["samples"] = intval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // length in seconds
                        if(strpos($value, "Length") !== FALSE) {
                                $arr["length"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // The maximum sample value in the audio
                        if(strpos($value, "Maximum amplitude") !== FALSE) {
                                $arr["max_amp"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // The minimum sample value in the audio
                        if(strpos($value, "Minimum amplitude") !== FALSE) {
                                $arr["min_amp"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        //  ½min(xk)+½max(xk)
                        if(strpos($value, "Midline amplitude") !== FALSE) {
                                $arr["mid_amp"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // The  average  of the absolute value of each sample in the audio: ¹/nΣ│xk│
                        if(strpos($value, "Mean    norm") !== FALSE) {
                                $arr["mean_norm"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // ¹/nΣxk The average of each sample in the audio, >0 => DC offset
                        if(strpos($value, "Mean    amplitude") !== FALSE) {
                                $arr["mean_amp"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        //  √(¹/nΣxk²) The level of a D.C. signal with same power as average audio's power
                        if(strpos($value, "RMS     amplitude") !== FALSE) {
                                $arr["rms_amp"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // max(│xk-xk-1│)
                        if(strpos($value, "Maximum delta") !== FALSE) {
                                $arr["max_delta"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // min(│xk-xk-1│)
                        if(strpos($value, "Minimum delta") !== FALSE) {
                                $arr["min_delta"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // ¹/n-1Σ│xk-xk-1│
                        if(strpos($value, "Mean    delta") !== FALSE) {
                                $arr["mean_delta"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // √(¹/n-1Σ(xk-xk-1)²)
                        if(strpos($value, "RMS     delta") !== FALSE) {
                                $arr["rms_delta"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // rough frequency of audio?
                        if(strpos($value, "Rough   frequency") !== FALSE) {
                                $arr["freq"] = intval(trim(substr(strrchr($value, ":"), 1)));
                        }
                        // value to amplify to 0 dB
                        if(strpos($value, "Volume adjustment") !== FALSE) {
                                $arr["vol_adjust"] = floatval(trim(substr(strrchr($value, ":"), 1)));
                        }

                };

                // output of stats cmd, 
                foreach ($out_stats as $value) {

                        if(strpos($value, "DC offset") !== FALSE) {
                                $h = preg_split("/[\s]{2,}/", $value);
                                $arr["dc_offset"] = floatval(trim($h[1]));
                        }
                        if(strpos($value, "Crest factor") !== FALSE) {
                                // split at 2 or more spaces: 
                                $h = preg_split("/[\s]{2,}/", $value);
                                $c = isset($h[2]) && isset($h[3]) ? floatval((trim($h[2]) + trim($h[3]))/2) : floatval(trim($h[1]));
                                $arr["crest_factor"] = $c;
                        }
                        if(strpos($value, "Flat factor") !== FALSE) {
                                $h = preg_split("/[\s]{2,}/", $value);
                                $arr["flat_factor"] = floatval(trim($h[1]));
                        }
                        if(strpos($value, "Pk lev dB") !== FALSE) {
                                $h = preg_split("/[\s]{2,}/", $value);
                                $arr["peak_level"] = floatval(trim($h[1]));
                        }
                        if(strpos($value, "Pk count") !== FALSE) {
                                $h = preg_split("/[\s]{2,}/", $value);
                                // check if we have [k]ilos of peaks...
                                if(substr($h[1], -1) == "k") {
                                        $p = intval(rtrim($h[1], "k") * 1000);
                                } else {
                                        $p = intval($h[1]);
                                }
                                $arr["peak_count"] = $p;
                        }
                }

                /* check if a file is clipping at 0dB: get max/min ampitude and peak count:
                   if amplitude = +/- 1 and peak count > n => clipping */

                if (($arr["max_amp"] == 1 || $arr["min_amp"] == -1) && $arr["peak_count"] > 100) {
                        $arr["clipping"] = TRUE;
                } else {
                        $arr["clipping"] = FALSE;
                }

                // check for dc offset, 0.001 reasonable?
                if ($arr["dc_offset"] > 0.001) {
                        $arr["has_dc"] = TRUE;
                } else {
                        $arr["has_dc"] = FALSE;
                }
                return $arr;
        }
}

?>

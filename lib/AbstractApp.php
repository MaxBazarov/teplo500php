<?php

include 'utils.php';

class AbstractApp
{
	public $timezone = ''; // Europe/Moscow
	public $lang = ''; // en
	public $config;  // object with system.conf content
	public $salus = null; // SalusConnect instance

	public $locales = array();

	
	function __construct()
	{			
		$this->salus = new SalusConnect;
	}

	function __destruct() {
    }

	function init()
	{
		// load system config
		 $config = load_json_config('../local/system.conf');		 
		 if(!$config)  return false;
		 
		 $this->config = $config;
		 $this->lang =  $config->defaults->lang;
		 $this->timezone = $config->defaults->timezone;

		 date_default_timezone_set($this->timezone);
		 
		 return true;
	}

	function log_text($level=UTILS_LOG_OK,$text1,$text2='')
	{
		// should be re-impplented in child class
		exit;
	}


	function home_path()
	{
		return $this->config->home_path;
	}

	function clients_folder(){
		return $this->home_path().'/local/clients';
	}

	function translate_string($str){		
		$lang = $this->lang;
		if($lang == 'en') return $str;

		// load new locale
		if(!array_key_exists($lang,$this->locales)){
			$this->locales[$lang] = load_json_config('../system/locales/strings.'.$lang,true);
			if($this->locales===false){
				log_error('AbstractApp: translate_string: No locale for lang"'.$lang.'"');
				return 'NO LOCALE FOR '.$lang;
			}			
		}

		// find string
		if(array_key_exists($str,$this->locales[$lang]))				
			return $this->locales[$lang][$str];
		else{
			// check context in key id
			if(strpos($str,'/')===false) return $str;
			return explode('/',$str)[0];
		}
	}

}
?>

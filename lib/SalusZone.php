<?php

class SalusZone
{
	const MODE_UKNOWN = 0;
	const MODE_AUTO = 1;
	const MODE_OFF = 3;
	const MODE_MAN = 4;
	const MODE_ES = 5;

	const TEMP_UNDEFINED = -100;


	public $id = '';
	public $index = 1;
	public $name = '';
	public $current_temp = SalusZone::TEMP_UNDEFINED;
	public $current_mode_temp = SalusZone::TEMP_UNDEFINED;

	public $mode = SalusZone::MODE_UKNOWN;
	public $heating = false;

	public $device = null; // ref to parent 


	const MODES_TEXT=array(
		SalusZone::MODE_UKNOWN=>'',
		SalusZone::MODE_AUTO=>'Auto/Mode',
		SalusZone::MODE_OFF=>'Off/Mode',
		SalusZone::MODE_MAN=>'Cust/Mode',
		SalusZone::MODE_ES=>'ES/Mode'
	);


	// =================================== PUBLIC GETTERS =========================== 	
	function mode(){		
		return $this->mode;
	}
	function is_esm(){		
		return $this->mode==SalusZone::MODE_ES;
	}

	// return text presention of current mode
	function mode_str(){
		$str = locstr(SalusZone::MODES_TEXT[$this->mode]);
		if($this->mode!=SalusZone::MODE_OFF and $this->mode!=SalusZone::MODE_ES) $str .= ' '.temp_to_str($this->current_mode_temp);
		return $str;
	}


	// =================================== PUBLIC SAVE/LOAD =========================== 

	function __construct(SalusDevice $device, $index=1,$id='') 
    {
    	$this->device = $device;
    	$this->index = $index;
    	$this->id = $id;
    }


   	// arguments
	// data: object ref to client.data/devices[]/zones[]
	function load($data)
	{

		$this->id = $data->id;
		$this->index = $data->index;
		$this->updated = $data->updated;
		$this->name = $data->name;
		$this->current_temp = $data->current_temp;
		$this->current_mode_temp = $data->current_mode_temp;
		$this->mode = $data->mode;
		$this->heating = $data->heating;

		log_debug('SalusZone: load: completed, index='.$this->index);
		return true;
	}


	function get_data_for_save()
	{
		$data = array(
			'id'=>$this->id,
			'index'=>$this->index,
			'updated'=>$this->updated,
			'name'=>$this->name,
			'current_temp'=>$this->current_temp,
			'current_mode_temp'=>$this->current_mode_temp,
			'mode'=>$this->mode,
			'heating'=>$this->heating
		);

		return $data;
	}


	// =================================== INTERNAL LOAD FROM HTML  =========================== 

	function load_from_dom(&$xpath,&$parent_node,&$li_node,$is_new_zone)
	{
		$log_d = function($message){ return log_debug('SalusZone: load_from_dom(): '.$message);};
		$log_e = function($message){ return log_error('SalusZone: load_from_dom(): '.$message);};


		$index = $this->index;		
		
		$img_node = $xpath->query('img',$li_node)[0];
		if(!isset($img_node)) return $log_e('can not find li/img');

		$zone_div_node = $xpath->query('div[@class="TabbedPanelsContent"]',$parent_node)[$index-1];
		if(!isset($zone_div_node)) return $log_e('can not find TabbedPanelsContent #'.($index-1));
		
		$log_d('load zone #'.$index);

		
		if($is_new_zone) $this->name = get_node_attr_value($img_node,'alt');

		// get room temperature
		$temp_room_node = $xpath->query('.//p[@id="current_room_tempZ'.$index.'"]',$zone_div_node)[0];
		if(!isset($temp_room_node)) return $log_e('can not find room temperature for zone '.$index);
		$this->current_temp = floatval($temp_room_node->textContent);


		// get mode temperature
		$temp_node = $xpath->query('.//p[@id="current_tempZ'.$index.'"]',$zone_div_node)[0];
		if(!isset($temp_node)) return $log_e('can not find temperature for zone '.$index);
		$this->current_mode_temp = floatval($temp_node->textContent);

		// get heating on/off
		$heating_node = $xpath->query('.//img[@id="CH'.$index.'onOff"][contains(@class,"display")]',$zone_div_node)[0];				
		$this->heating = isset($heating_node);
		

		// get energy save status
		$es_enabled = false;
		{
			$node = $xpath->query('.//input[@name="esoption_submit"]',$zone_div_node)[0];
			if(!isset($node)) return  $log_e('can not find esoption_submit');

			$class = get_node_attr_value($node,'class');
			$log_d('class = '.$class);
			switch($class){
				case false:
					return $log_e('can not find esoption_submit/@class');
				case 'esOff':
					$es_enabled = false;
					break;
				case 'esOn':
					$es_enabled = true;
					break;
				default:
					return $log_e('can not understand esoption_submit/@class='.$class);
			}
		}


		// get mode		
		while(true){
			$mode_off_node = $xpath->query('.//p[@id="offButtonZ'.$index.'"][contains(@class,"set")]',$zone_div_node)[0];
			if(isset($mode_off_node)){
				$this->mode = $this::MODE_OFF;
				break;
			}
			if($es_enabled){
				$this->mode = $this::MODE_ES;
				break;
			}
			$mode_man_node = $xpath->query('.//p[contains(@class,"heatingNote")][contains(@class,"heatingMan")]',$zone_div_node)[0];
			if(isset($mode_man_node)){
				$this->mode = $this::MODE_MAN;
				break;
			}	
			$mode_auto_node = $xpath->query('.//p[contains(@class,"heatingNote")][contains(@class,"heatingAuto")]',$zone_div_node)[0];			
			if(isset($mode_auto_node)){
				$this->mode = $this::MODE_AUTO;
				break;
			}

			return $log_e('can not understand mode for zone '.$index);
		}

		
		// get current auto program
		//$this->load_autotemp_from_dom($xpath,$zone_div_node);

		log_ok('[ZONE #'.$this->index.'] Temp '.temp_to_str($this->current_temp).($this->heating?'[HEATING]':'').' Mode Temp '.temp_to_str($this->current_mode_temp));

		// touch last updated time
		$this->updated = time();
		
		return true;
	}	



	private function load_autotemp_from_dom(&$xpath,&$zone_div_node)
	{
		//$node = $xpath->query('.//p[@class="IT500programs"]/input[contains(@class,"IT500programFieldTemp")]',$zone_div_node)[0];
		//var_dump($node);

		$node = $xpath->query('.//p[@class="IT500programs"]/input[contains(@class,"IT500programFieldTemp")][contains(@class,"active")]',$zone_div_node)[0];

		if(!isset($node)){
			return;
		}
		$temp = get_node_attr_value($node,'value');
		if(!isset($temp)){
			log_error('SalusZone: load_autotemp_from_dom(): can not find IT500programFieldTemp/@value');
			return;
		}		
		$this->current_mode_temp = explode(' ',$temp,2)[0];		
	}
}



?>

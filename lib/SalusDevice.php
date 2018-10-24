<?php

class SalusDevice
{
	const STATUS_UNDEFINED = 0;
	const STATUS_OFFLINE = 1;
	const STATUS_ONLINE = 2;

	public $id = '';
	public $name = '';
	public $href = '';
	public $status = SalusDevice::STATUS_UNDEFINED;	
	private $token = '';

	public $zones = array(); // array of SalusZone instances
	private $client = null; // ref to parent SalusClient

	function is_offline(){ return $this->status==SalusDevice::STATUS_OFFLINE; }
	function is_online(){ return $this->status==SalusDevice::STATUS_ONLINE; }

	function __construct(SalusClient $client,$id=''){	
		$this->client = $client;
		$this->id = $id;
	}


	// init name, 
	function init_from_dom(&$xpath,&$input_node)
	{

		// GET NAME AND HREF
		// search for - <div class="deviceList 70181">
		$div_node = $input_node->parentNode->parentNode;		
		
		// search for <a class="deviceIcon online " href="control.php?devId=70181">STA00007781 </a>		
		$href_nodes =  $xpath->query("a[contains(@class,'deviceIcon')]",$div_node);		
		if($href_nodes===false || count($href_nodes)==0 )
		{
			log_error('SalusDevice:load_from_dom: Can not find <a/>');
			return false;	
		}
		$href_node = $href_nodes[0];
		$this->name = $href_node->nodeValue;
		if(strpos($this->name,'(')) $this->name = sscanf($this->name,'%s (%[^)]s')[1];
		$this->updated = time();
		
		$href = get_node_attr_value($href_node,'href');
		if($href===false){
			log_error('SalusDevice:load_from_dom: can not find @href');
			return false;
		}
		$this->href = SalusConnect::PUBLIC_URL.$href;
		log_debug('SalusDevice: init_from_dom href="'.$this->href.'"');

		// GET STATUS
		$status = get_node_attr_value($href_node,'class');
		if( strpos($status,'offline') )
			$this->status = $this::STATUS_OFFLINE;
		elseif( strpos($status,'online') )
			$this->status = $this::STATUS_ONLINE;	
		else{
			log_error('SalusDevice:load_from_dom:can not understand device status='.$status);
			return false;
		}
		return true;
	}


	function switch_esm($enable_esm){
		$log_d = function($message){ return log_debug('[SalusDevice '.$this->id.'] switch_esm: '.$message);};
		$log_ok = function($message){ return log_ok('[SalusDevice '.$this->id.'] switch_esm: '.$message);};

		if(!count($this->zones)) return $log_ok('No zones');
		global $app;

	
		foreach($this->zones as $zone)
		{
			$log_d('switching for zone #'.$zone->id);
			/* SETUP CONTEXT */
			$data = array (
				'devId'=>$this->id,
				'esoption_submit'=>'1',
				'esoption' => $enable_esm?'0':'1',
				'token'=> $this->token

			);
			// GET DEVICE HTML FROM IT500 SITE
			$request_result = net_http_request( SalusConnect::SET_URL,SalusConnect::DEVICES_URL, $data,'POST',$this->client->get_phpsessionid() );
			$html = $request_result['content'];
			file_put_contents($app->home_path().'/local/output/device_'.$this->id.'_switch_esm.html',$html);

		}

		
		$log_ok('switched');

		return $success;
	}


	// arguments
	// data: object ref to client.data/devices[]
	function load( $data )
	{
		$this->id = $data->id;
		$this->name = $data->name;
		$this->status = $data->status;
		$this->updated = $data->updated;
		$this->token = $data->token;

		$zone_last_index = 0;
		$this->zones = array();
		foreach($data->zones as $zone_data){
			$zone = new SalusZone($this);
			$zone->load($zone_data);

			array_push($this->zones,$zone);
		}

		log_debug('SalusDevice: load: completed, id='.$this->id);
		return true;
	}

	function get_data_for_save()
	{
		$data = array(
			'id'=>$this->id,
			'name'=>$this->name,
			'status'=>$this->status,
			'updated'=>$this->updated,
			'token'=>$this->token,
			'zones'=>array() 
		);

		log_debug('SalusDevice: get_data_for_save for '.$this->id);

		foreach($this->zones as $zone){
			array_push($data['zones'],$zone->get_data_for_save());
		}

		return $data;
	}



	function load_from_site()
	{
		global $app;
		$html = false;

		log_ok('[DEVICE '.$this->id.'] Updating from site...');		

		if($this->is_offline()) return true;

		// GET HTML CONTENT
		if($app->salus->is_real_mode()){
			if($this->is_online()){
				$html = $this->_load_content_from_site();
			}
		}else{
			$html = $this->_load_content_from_file();
		}

		if($html===false) return true;

		// PARSE HTML CONTENT	
		$dom = new DOMDocument;
		libxml_use_internal_errors(true);
		if(!$dom->loadHTML($html,LIBXML_NOWARNING)){
			log_error('SalusDevice: load_from_site(): Can not parse device HTML');
			return false;
		}
		libxml_clear_errors();	

		$xpath = new DOMXpath($dom);

		// search for <div id="TabbedPanels1" class="TabbedPanels">
		// 	 <ul class="TabbedPanelsTabGroup">
		// 		 <li class="TabbedPanelsTab" 

		$li_nodes = $xpath->query('//div[@id="TabbedPanels1"]/ul[@class="TabbedPanelsTabGroup"]/li');
		if($li_nodes===false){
			log_debug('SalusDevice: load_from_site(): not found zones');
			return true;
		}
		log_debug('SalusDevice: load_from_site(): found zones');

		$parent_node = $xpath->query('//div[@id="mainContentPanel"]')[0];
		if(!isset($parent_node)){
			log_error('SalusDevice: load_from_site() can not find <div id="mainContentPanel">');
			return false; 
		}

		// SEARCH FOR TOKEN
		{
			$token_node = $xpath->query('.//input[@id="token"]',$parent_node)[0];
			if(!isset($token_node)) return log_error('SalusDevice: load_from_site() can not find <input id="token">');								
			$this->token = 	 get_node_attr_value($token_node,'value');
		}
		
		// ADD ZONES
		$zone_last_index = 1;
		foreach ($li_nodes as $li_node) 
		{				
			$zone_id = get_node_attr_value($li_node,'id');
			if( $zone_id==='settings') continue;

			// TRY TO FIND EXISTING ZONE
			$existing_zone = $this->get_zone_by_index($zone_last_index);
			$zone = $existing_zone;

			// CREATE NEW ZONE IF NEEDED
			if(!$zone){
				$zone = new SalusZone($this,$zone_last_index,uniqid());
			}

			if(!$zone->load_from_dom($xpath,$parent_node,$li_node,!$existing_zone)){
				log_error('SalusDevice:load Can not init Zone');
				return false;
			}


			// completed zone info
			if(!$existing_zone){
				array_push($this->zones, $zone);
			}

			$zone_last_index++;

			
		}		

		return true;

	}


	function get_zone_by_index($index)
	{
		if( count($this->zones)<($index-1)) return false;
		return $this->zones[$index-1];
	}




	// return: 
	//   HTML content or false=failed
	function _load_content_from_file()
	{
		global $app;
		$file_name = $app->home_path().'/local/fakes/device_'.$this->id.'.html';
		log_debug('SalusDevices: _load_content_from_file: '.$this->id.' file="'.$file_name.'"');
		if (!file_exists($file_name)) {
			log_error('No '.$file_name.' file');
			return false;
		}

		$html = file_get_contents($file_name);
		if ($html === false) {
			log_error('Can not load '.$file_name);
			return false;
		}

		return $html;
	}


	// return: 
	//   HTML content or false=failed	
	function _load_content_from_site()
	{
		global $app;
		$html = '';

		log_debug('SalusDevices: _load_content_from_site: '.$this->id.' href="'.$this->href.'"');
		if($this->is_offline()) return false;

		{
			/* SETUP CONTEXT */
			$data = array (
				'lang'=>'en'
			);
			// GET DEVICE HTML FROM IT500 SITE
			$request_result = net_http_request( $this->href,SalusConnect::DEVICES_URL, $data,'GET',$this->client->get_phpsessionid() );
			$html = $request_result['content'];

			// dump HTML into file for future analyse
			file_put_contents($app->home_path().'/local/output/device_'.$this->id.'.html',$html);	
		}
		log_debug('SalusDevice: load_from_site: done');

		
		return $html;
	}


	function save_name_to_site(){
		log_debug('SalusDevice: save_name_to_site : starting');

		if($this->client->login_to_site()===false){
			return log_error('SalusDevice: save_name_to_site: failed to login');
		}

		/* SETUP CONTEXT */
		$data = array (
			'name' => $this->name,
			'devId' => $this->id,
			'lang'=>'en',
			'submitRename' => 'submit'
		);


		$result = net_http_request( SalusConnect::RENAME_DEVICE_URL,SalusConnect::DEVICES_URL, $data,'POST', $this->client->get_phpsessionid(),'text/html');
		if($result===false){
			return log_error('SalusDevice: save_name_to_site: failed to get "'.SalusConnect::RENAME_DEVICE_URL.'"');
		}
		log_ok('SalusDevice: save_name_to_site: completed');

		return true;
	}
	
}



?>

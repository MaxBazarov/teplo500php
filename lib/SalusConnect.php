<?php

include 'salus-emul.php';

include 'SalusZone.php';
include 'SalusDevice.php';
include 'SalusClient.php';

class SalusConnect
{
	// PUBLIC 
	// constansts
	const PUBLIC_URL = 'https://salus-it500.com/public/'; 
	const START_URL = 'https://salus-it500.com'; 
	const LOGIN_URL= 'https://salus-it500.com/public/login.php';
	const DEVICES_URL = 'https://salus-it500.com/public/devices.php';
	const RENAME_DEVICE_URL = 'https://salus-it500.com/includes/rename.php';
	const SET_URL = 'https://salus-it500.com/includes/set.php';

	const MODE_REAL = 1;  
	const MODE_EMUL = 2; 
	const MODE_CMD_HELP = 3; 

	const EMUL_ONLINE = 1; 
	const EMUL_OFFLINE = 2; 
	
	// variables		
	public $clients = array();  // list of SalusClient


	// execution modes
	private $mode = SalusConnect::MODE_REAL;
	private $emul_submode = SalusConnect::EMUL_ONLINE;
	
	 
	// GETTERS
	function is_real_mode(){ return $this->mode==SalusConnect::MODE_REAL; }
	function is_emul_mode(){ return $this->mode==SalusConnect::MODE_EMUL; }
	function mode(){ return $this->mode; }
	function emul_submode(){ return $this->emul_submode; }	

	function set_mode($mode){ $this->mode = $mode;}
	function set_emul_submode($emul_submode){ $this->emul_submode = $emul_submode;}
	

	function create_load_clients()
	{

		// load list of clients
		$client_ids = $this->_load_client_list();
		if($client_ids === false) return false;
		
		// load every client
		$this->clients = array();
		foreach($client_ids as $client_id)
		{
			$client = SalusClient::Factory_CreateAndLoad($client_id);
			if($client===false){
				log_error('SalusConnect: run: can create client with id"'.$client_id.'"');
				return false;
			}

			$this->clients[] = $client;
		}		
	
		return true;
	}

	function save_clients_data()
	{
		foreach($this->clients as $client){
			if(!$client->save_data()){
				log_error('SalusConnect: save_clients: error');
				return false;
			}
		}
		return true;
	}

	function update_clients_from_site($force=false)
	{
		foreach($this->clients as $client){
			log_debug('SalusConnect: update_clients_from_site: client='.$client->id);

			if(!$force and !$client->is_updated_required()){
				log_debug('SalusConnect: update_clients_from_site: skip updating');
				continue;
			}

			if(!$client->update_from_site()){
				log_error('SalusConnect: update_clients_from_site: can not update_from_site');
				return false;
			}

			if(!$client->save_history()){
				log_error('SalusConnect: update_clients_from_site: can save_history');
				return false;
			}
			
			if(!$client->run_alerts()){
				log_error('SalusConnect: update_clients_from_site: can not run alerts');				
			}

			if(!$client->save_data()){
				log_error('SalusConnect: update_clients_from_site: error');
				return false;
			}				

		}
		return true;
	}
	
	// result: array of file names  or false 
	private function _load_client_list()
	{
		global $app;
		$files_raw = scandir($app->clients_folder());
		if($files_raw===false){
			log_error('SalusConnect: _load_client_list: can not scan dir "'.$app->clients_folder().'"');
			return false;
		}

		return array_filter($files_raw,
			function($k){
				return strpos($k,'@')!==false;
			}
		);

	}

}

?>

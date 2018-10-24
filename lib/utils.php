<?php


const UTILS_LOG_OK=1;
const UTILS_LOG_DBG=2;
const UTILS_LOG_ERR=3;

// ========================== LOCALISATION =========================== 
// localise string
function locstr($str,$param1=null,$param2=null,$param3=null){
	global $app;
	$str = $app->translate_string($str);

	if($param1!=null){
		$str = str_replace('{1}',$param1,$str);
		if($param2!=null){
			$str = str_replace('{2}',$param2,$str);
			if($param3!=null){
				$str = str_replace('{3}',$param3,$str);
			}
		}
	}	
	return $str;
}

// ========================== LOW LEVEL LIBRARY =========================== 
function temp_to_str($temp)
{
	if($temp==0){
		return '0°';
	}elseif($temp>0){
		return $temp.'°';
	}else{
		return $temp.'°';
	}
}

// ========================== LOW LEVEL LIBRARY =========================== 
function time_diff($start_time,$end_time = false)
{
	$start = new DateTime(); 
	$start->setTimestamp($start_time);
	$end = new DateTime("now");
	if($end_time) $end->setTimestamp($end_time);


    $interval = $end->diff($start); 
    
    $format = array(); 
    if($interval->y !== 0) { 
        $format[] = "%y ".locstr('year(s)/timediff'); 
    } 
    if($interval->m !== 0) { 
        $format[] = "%m ".locstr('month(s)/timediff'); 
    } 
    if($interval->d !== 0) { 
        $format[] = "%d ".locstr('day(s)/timediff'); 
    } 
    if($interval->h !== 0) { 
        $format[] = "%h ".locstr('hour(s)/timediff'); 
    } 
    if($interval->i !== 0) { 
        $format[] = "%i ".locstr('minute(s)/timediff'); 
    } 
    if($interval->s == 0  and !count($format)) {
    	 return locstr('Updated right now/timediff');
    } 
    if($interval->s !== 0  and !count($format)) {
    	 return locstr('Updated less than a minute ago/timediff');
    }
    if($interval->s !== 0) { 
        $format[] = "%s ".locstr('second(s)/timediff');       
    } 
    
    // We use the two biggest parts 
    if(count($format) > 1) { 
        $format = locstr('Updated: {1} and {2}/timediff',array_shift($format),array_shift($format) );
    } else { 
        $format = locstr('Updated: {1}/timediff',array_pop($format));
    } 
    
    // Prepend 'since ' or whatever you like 
    return $interval->format($format); 
}


function clear_textid($id){
	$id = str_replace('\\','',$id);$id = str_replace('/','',$id);$id = str_replace('.','',$id);
	return $id;
}

function save_json_config($file_path,$data)
{	
	$content = json_encode($data,JSON_PRETTY_PRINT);

	if($content===false){
		log_error('save_json_config(): can not encode config. Error: '.json_last_error_msg());
		return false;
	}		

	if(!file_put_contents($file_path,$content)){
		log_error('save_json_config(): can not save config "'.$file_path.'"');
		return false;
	}		
	return 	true;
}


function load_json_config($path,$assoc=false)
{
	if(!file_exists($path)){
		return false;
	}
	$content = file_get_contents($path);
	if($content===false){
		log_error('load_json_config: can not load config "'.$path.'"');
		return false;
	}		
	$config = json_decode( $content, $assoc );
	if($config==NULL){
		log_error('load_json_config: _load_json_config: can not parse config "'.$path.'" Error: '.json_last_error_msg());
		return false;
	}

	return $config;

}



function log_text($level=UTILS_LOG_OK,$text1,$text2='')
{
	global $app;
	$app->log_text($level,$text1,$text2);

	return true;
}

function log_error($text1)
{
	log_text(UTILS_LOG_ERR, $text1);
	return false; // special result to use it in code like :       $file = fopen(); if(!$file) return log_error('ERROR!!!!');
}

function log_ok($text1)
{
	log_text(UTILS_LOG_OK, $text1);
	return true;
}

function log_debug($text1)
{

	log_text(UTILS_LOG_DBG, $text1);
	return true;
}

/**
from http://php.net/manual/ru/function.file-get-contents.php#102575
make an http POST request and return the response content and headers
@param string $url    url of the requested script
@param array $data    hash array of request variables
@return returns a hash array with response content and headers in the following form:
    array ('content'=>'<html></html>'
        , 'headers'=>array ('HTTP/1.1 200 OK', 'Connection: close', ...)
        )
*/

function net_http_request ($url,$referer,$data,$method,$PHPSESSID,$content_type='')
{	
    $data_url = http_build_query ($data);
    $data_len = strlen ($data_url);

    $UserAgent='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/11.0 Safari/604.1.38';
    $Cookie="";
    if($PHPSESSID!=''){
    	//Cookie: PHPSESSID=f3hstlalnpskirl286d4mhfhs6
    	$Cookie.='PHPSESSID='.$PHPSESSID.';';
    }
    
	log_debug('net_http_request(): url = '.$url);
	$header = "Connection: close\r\nHost: salus-it500.com\r\nOrigin: https://salus-it500.com\r\nReferer: $referer\r\nLocation: $referer\r\nUser-Agent: $UserAgent\r\nCookie: $Cookie\r\n";

	if($method=='GET'){
		$header.="Content-Type: text/html; charset=utf-8\r\n";
		$header.="Content-Length: $data_len\r\n";
	}else{
		$header.="Content-type: application/x-www-form-urlencoded\r\n";
	}
    
    $content = file_get_contents ($url, false, stream_context_create(array(
		'http'=>array(
    		'method'=>$method,
            'header'=>$header,
            'content'=>$data_url
        ),
		"ssl"=>array(
            "verify_peer"=>false,
            "verify_peer_name"=>false
        )
    )));

    if($content===false) return false;

    //echo '<br/>'.$url.'<br/>';
    //var_dump($header);

    return array (
    	'content'=>$content,
        'headers'=>$http_response_header
    );
}

/*
 Taken from http://snipplr.com/view/17242/parse-http-response/
*/
function parse_http_headers ($raw_headers) 
{
    $headers = array();
  	foreach($raw_headers as $str)
  	{
  		//log_debug('parse_http_headers: header='.$str);
		if(strpos($str,':')===false) continue;
	    list($headername, $headervalue) = explode(':', trim($str), 2);

	    $headername = $headername;
	    $headervalue = ltrim($headervalue);

	    if (isset($headers[$headername])) 
	        $headers[$headername] .= ',' . $headervalue;
	    else 
	        $headers[$headername] = $headervalue; 
    }
    return $headers;
}

//Set-Cookie: PHPSESSID=3m91ah43nfbcugj3h95il4fej2; path=/
function  get_cookies_from_rawheaders($raw_headers)
{
	$headers = parse_http_headers($raw_headers);
	$cookies_raw = $headers['Set-Cookie'];

	

	if (isset($cookies_raw)){
		$cookie_pairs = explode(';', $cookies_raw );
		$cookies = array();
		foreach($cookie_pairs as $str)
	  	{
		    list($cookie_name, $cookie_value) = explode('=', trim($str), 2);
		    $cookies[$cookie_name] = ltrim($cookie_value);
		}
		return $cookies;
	} else{
		return array();
	}
}

function get_node_attr_value(&$node,$attr_name)
{
	foreach ($node->attributes as $attr) 
    { 
    	if($attr->nodeName==$attr_name){
    		return $attr->nodeValue;
    	}
    } 
    return false;
}


function date_str($date){
	return $date?$date->format('Y-m-d'):'';
}

function date_txt($date){
	return $date?$date->format('d-m-Y'):'';
}

?>
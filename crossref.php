<?php

require_once(dirname(__FILE__) . '/config.inc.php');

// Use search API
function crossref_lookup( &$reference)
{
	global $config;
	
	// remove any existing DOI
	unset($reference->doi);
	
	$post_data = array();
	$post_data[] = $reference->citation;
	
	//print_r($post_data);
	
	$ch = curl_init(); 
	
	$url = 'http://search.labs.crossref.org/links';
	
	curl_setopt ($ch, CURLOPT_URL, $url); 
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 

	// Set HTTP headers
	$headers = array();
	$headers[] = 'Content-type: application/json'; // we are sending JSON
	
	// Override Expect: 100-continue header (may cause problems with HTTP proxies
	// http://the-stickman.com/web-development/php-and-curl-disabling-100-continue-header/
	$headers[] = 'Expect:'; 
	curl_setopt ($ch, CURLOPT_HTTPHEADER, $headers);
	
	if ($config['proxy_name'] != '')
	{
		curl_setopt($ch, CURLOPT_PROXY, $config['proxy_name'] . ':' . $config['proxy_port']);
	}

	curl_setopt($ch, CURLOPT_POST, TRUE);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
	
	$response = curl_exec($ch);
	
	//echo $response;
	
	$obj = json_decode($response);
	if (count($obj->results) == 1)
	{
		if ($obj->results[0]->match)
		{
			$match = false;
			
			// check
			$match = true;
		
			if ($match)
			{
				$reference->doi = $obj->results[0]->doi;
			}
		}
	}
}


?>
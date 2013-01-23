<?php


require_once (dirname(__FILE__) . '/citation.php');
require_once (dirname(__FILE__) . '/crossref.php');
require_once (dirname(__FILE__) . '/reference.php');

//--------------------------------------------------------------------------------------------------
/**
 * @brief Test whether HTTP code is valid
 *
 * HTTP codes 200 and 302 are OK.
 *
 * For JSTOR we also accept 403
 *
 * @param HTTP code
 *
 * @result True if HTTP code is valid
 */
function HttpCodeValid($http_code)
{
	if ( ($http_code == '200') || ($http_code == '302') || ($http_code == '403'))
	{
		return true;
	}
	else{
		return false;
	}
}


//--------------------------------------------------------------------------------------------------
/**
 * @brief GET a resource
 *
 * Make the HTTP GET call to retrieve the record pointed to by the URL. 
 *
 * @param url URL of resource
 *
 * @result Contents of resource
 */
function get($url, $userAgent = '', $timeout = 0)
{
	global $config;
	
	$data = '';
	
	$ch = curl_init(); 
	curl_setopt ($ch, CURLOPT_URL, $url); 
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,	1); 
	//curl_setopt ($ch, CURLOPT_HEADER,		  1);  

	curl_setopt ($ch, CURLOPT_COOKIEJAR, 'cookie.txt');
	
	if ($userAgent != '')
	{
		curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
	}	
	
	if ($timeout != 0)
	{
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
	}
	
	if ($config['proxy_name'] != '')
	{
		curl_setopt ($ch, CURLOPT_PROXY, $config['proxy_name'] . ':' . $config['proxy_port']);
	}
	
			
	$curl_result = curl_exec ($ch); 
	
	//echo $curl_result;
	
	if (curl_errno ($ch) != 0 )
	{
		echo "CURL error: ", curl_errno ($ch), " ", curl_error($ch);
	}
	else
	{
		$info = curl_getinfo($ch);
		
		//$header = substr($curl_result, 0, $info['header_size']);
		//echo $header;
		
		$http_code = $info['http_code'];
		
		//echo "<p><b>HTTP code=$http_code</b></p>";
		
		if (HttpCodeValid ($http_code))
		{
			$data = $curl_result;
		}
	}
	return $data;
}

//--------------------------------------------------------------------------------------------------
function parse($filename, $lookup = false)
{
	$xmlfile = @fopen($filename, "a+") or die("could't open file --\"$filename\"");
	$xml = fread($xmlfile, filesize($filename));
	fclose($xmlfile);
	
	// Clean up XML---------------------------------------------------------------------------------
	$xml = str_replace("\n", "", $xml);
	
	// Extraneous tags
	$xml = preg_replace('/<apple-converted-space>\s*<\/apple-converted-space>/', ' ', $xml);
	$xml = preg_replace('/<A ID="OLE_LINK(\d+)"><\/A>/', '', $xml);

	$xml = preg_replace('/<gs-a1>/', '', $xml);
	$xml = preg_replace('/<\/gs-a1>/', '', $xml);
	
	$xml = preg_replace('/<Hyperlink>/', '', $xml);
	$xml = preg_replace('/<\/Hyperlink>/', '', $xml);

	$xml = preg_replace('/<SC\d+>/', '', $xml);
	$xml = preg_replace('/<\/SC\d+>/', '', $xml);

	$xml = preg_replace('/<year>/', '', $xml);
	$xml = preg_replace('/<\/year>/', '', $xml);
	
	$xml = preg_replace('/<volume>/', '', $xml);
	$xml = preg_replace('/<\/volume>/', '', $xml);
	
	
	$xml = preg_replace('/<A href="#id\(pgfId-\d+\)" xml:link="simple" show="replace" actuate="user" CLASS="footnote">\d+<\/A>/', '', $xml);	
	
	$dom= new DOMDocument;
	$dom->loadXML($xml);
	$xpath = new DOMXPath($dom);
	
	// Find literature cited block------------------------------------------------------------------
	// This will be flagged in various, inconsistent ways (sigh)

	$rows = array();
	
	$literature_cited_position = PHP_INT_MAX;
	$store = false;
	
	// Paragraph with text "Literature cited" 2013-zt03599p093-without doi
	if ($literature_cited_position == PHP_INT_MAX)
	{
		$nc = $xpath->query ("//ZootaxaPara2011[. = 'Literature cited']");
		foreach ($nc as $n)
		{
			$literature_cited_position = $xpath->query('preceding-sibling::*', $n)->length;
			//echo "literature_cited_position=$literature_cited_position<br/>";
		}
	
	}
	
	// ZootaxaH1
	if ($literature_cited_position == PHP_INT_MAX)
	{
		$nc = $xpath->query ("//ZootaxaH1[. = 'References']/following-sibling::ZootaxaRef2011");
		foreach ($nc as $n)
		{
			$literature_cited_position = min($literature_cited_position, $xpath->query('preceding-sibling::*', $n)->length);
			//echo "literature_cited_position=$literature_cited_position<br/>";
		}
	
	}

	// ZootaxaH1 (pre 2011)
	if ($literature_cited_position == PHP_INT_MAX)
	{
		$nc = $xpath->query ("//ZootaxaH1[. = 'References']/following-sibling::ZootaxaRef");
		foreach ($nc as $n)
		{
			$literature_cited_position = min($literature_cited_position, $xpath->query('preceding-sibling::*', $n)->length);
			//echo "literature_cited_position=$literature_cited_position<br/>";
		}
	
	}
	
	// Process references 
	$nodeCollection = $xpath->query ("//ZootaxaRef2011");
	
	
	if ($nodeCollection->length == 0)
	{
		$nodeCollection = $xpath->query ("//ZootaxaRef");
	}
	
	foreach($nodeCollection as $node)
	{
		// Where is this node in relation to "Literature cited"?
		$position = $xpath->query('preceding-sibling::*', $node)->length;
		
		$store = $position > $literature_cited_position;
		$row = new stdclass;
		
		$row->c = array();
		// Content of child nodes
		$children = $node->childNodes; 
		for($i=0;$i<$children->length;$i++) 
		{ 
			$child = $children->item($i); 				
	
			$nx = new stdclass;
			$nx->name = $child->nodeName;				
			$nx->value = $child->nodeValue;
			
			$nx->attributes = array();
			
			$attrs = $child->attributes; 
					
			if (count($attrs) > 0)
			{
				foreach ($attrs as $a => $attr)
				{
					$nx->attributes[$attr->name] = $attr->value; 
				}
			}
			
			$row->c[] = $nx;				
		}
	
		$row->name =  $node->nodeName;
		
		//print_r($row);
		
		if ($store)
		{
			$rows[] = $row;
		}
	}

	// parse reference strings
	$references = array();
	foreach ($rows as $row)
	{
		$row_id = '';
		if (count($row->c) != 0)
		{
			foreach ($row->c as $c)
			{
				// Unlinked citation
				if ($c->name == '#text')
				{
					$reference = new stdclass;
					$matched = parse_citation($c->value, $reference, 0);
					
					$reference->id = $row_id;
						
					$references[] = $reference;
				}
				
				// Linked citation
				if ($c->name == 'A')
				{
					if ($c->value != '')
					{
						$reference = new stdclass;
						$matched = parse_citation($c->value, $reference, 0);
						
						if (isset($c->attributes))
						{
							if (isset($c->attributes['href']))
							{
								if (preg_match('/http:\/\/dx.doi.org\/(?<doi>.*)$/', $c->attributes['href'], $m))
								{
									$reference->doi = $m['doi'];
								}
							}
						}
						
						$reference->id = $row_id;
						
						$references[] = $reference;
					}
					else
					{
						if (isset($c->attributes))
						{
							if (isset($c->attributes['ID']))
							{
								$row_id = $c->attributes['ID'];
							}
						}
					
					}
				}
				
			}
		}
	}
	
	//----------------------------------------------------------------------------------------------
	// Post process	
	if ($lookup)
	{
		foreach ($references as $reference)
		{
			crossref_lookup($reference);
		}
	}	
	return $references;
}

?>
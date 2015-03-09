<?php

require_once(dirname(__FILE__) . '/config.inc.php');
require_once(dirname(__FILE__) . '/fingerprint.php');
require_once(dirname(__FILE__) . '/lcs.php');
require_once(dirname(__FILE__) . '/lib.php');

//--------------------------------------------------------------------------------------------------
// Use content negotian and citeproc, may fail with some DOIs
// e.g. [14/12/2012 12:52] curl --proxy wwwcache.gla.ac.uk:8080 -D - -L -H   "Accept: application/citeproc+json;q=1.0" "http://dx.doi.org/10.1080/03946975.2000.10531130" 
function get_doi_metadata($doi)
{
	$reference = null;
	
	$url = 'http://dx.doi.org/' . $doi;
	$json = get($url, '', "application/citeproc+json;q=1.0");
	
	//echo $url;
	//echo $json;
	//exit();
	
	if ($json == '')
	{
		return $reference;
	}
	
	$citeproc = json_decode($json);

	if ($citeproc == null)
	{
		return $reference;
	}
		
	$reference = new stdclass;
	$reference->type = 'generic';
	
	$reference->metadata = new stdclass;
	$reference->metadata->harvested = (Integer)time();
	
	$reference->title = $citeproc->title;
	if ($reference->title != '')
	{
		$reference->title = strip_tags($reference->title);
	}
	
	$reference->identifier = array();
	$identifier = new stdclass;
	$identifier->type = 'doi';
	$identifier->id = $citeproc->DOI;
	$reference->identifier[] = $identifier;
	
	if ($citeproc->type == 'article-journal')
	{
		$reference->type = 'article';
		$reference->journal = new stdclass;
		$reference->journal->name = $citeproc->{'container-title'};
		$reference->journal->volume = $citeproc->volume;
		if ($citeproc->issue)
		{
			$reference->journal->issue = $citeproc->issue;
		}
		$reference->journal->pages = $citeproc->page;
	}

	if ($citeproc->issued->{'date-parts'})
	{
		$reference->year = $citeproc->issued->{'date-parts'}[0][0];
	}
	else
	{
		if (isset($citeproc->issued->raw))
		{
			if (preg_match('/^[0-9]{4}$/', $citeproc->issued->raw))
			{
				$reference->year = $citeproc->issued->raw;
			}
		}
	}
	$reference->issued = $citeproc->issued;
	
	if (isset($citeproc->publisher))
	{
		$reference->publisher = $citeproc->publisher;
	}
	
	if (isset($citeproc->author))
	{
		$reference->author = array();
		foreach ($citeproc->author as $a)
		{
			// clean up name
			$author = new stdclass;
			
			if (isset($a->literal))
			{
				if (preg_match('/^(?<lastname>.*),\s+(?<firstname>.*)$/Uu', $a->literal, $m))
				{
					$author->firstname = $m['firstname'];
					$author->lastname = $m['lastname'];
					$author->name = $author->firstname . ' ' . $author->lastname;
				}
				else
				{
					$author->name = $a->literal;
				}
			}
			else
			{
				if (isset($a->given))
				{			
					$author->firstname = $a->given;
					$author->firstname = preg_replace('/\.([A-Z])/Uu', ' $1', $author->firstname);
					$author->firstname = preg_replace('/\./Uu', '', $author->firstname);
					$author->firstname = mb_convert_case($author->firstname, MB_CASE_TITLE, 'UTF-8');
					$author->lastname = mb_convert_case($a->family, MB_CASE_TITLE, 'UTF-8');
					$author->name = $author->firstname . ' ' . $author->lastname;
				}
			}
			$reference->author[] = $author;
		}
	}
	//print_r($reference);exit();
	return $reference;
}

//--------------------------------------------------------------------------------------------------
// Use search API
function crossref_lookup(&$reference, $double_check=false)
{
	global $config;
	
	// remove any existing DOI
	unset($reference->doi);
	
	$post_data = array();
	$post_data[] = $reference->citation;
	
	//print_r($post_data);
	
	$ch = curl_init(); 
	
	$url = 'http://search.crossref.org/links';
	
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
	if (isset($obj->results) && (count($obj->results) == 1))
	{
		if ($obj->results[0]->match)
		{
			$reference->crossref_score = $obj->results[0]->score;
		
			$match = true;
			
			// check
			
			if ($double_check)
			{
				$threshold = 0.9;
				
				$doi = $obj->results[0]->doi;
				$doi = str_replace('http://dx.doi.org/', '', $doi);
				
				$actual_reference = get_doi_metadata($doi);
				if ($actual_reference)
				{
					$match = true;
					
					// criteria
					if (0)
					{
						echo '<pre>';
						print_r($actual_reference);
						echo '</pre>';
						echo '<pre>';
						print_r($reference);
						echo '</pre>';
					}
					
					$v1 = '';
					$v2  ='';
					
					if (isset($actual_reference->title) && isset($reference->title))
					{
					
						$v1 = finger_print($actual_reference->title);
						$v2 = finger_print($reference->title);
					}
					else
					{
						$v1 = reference_to_citation_string($actual_reference);
						$v2 = $reference->citation;
						
						$v1 = finger_print($v1);
						$v2 = finger_print($v2);					
					}
					
					if (($v1 != '') && ($v2 != ''))
					{
						//echo $v1 . '<br/>';
						//echo $v2 . '<br/>';
						
						$lcs = new LongestCommonSequence($v1, $v2);
						$d = $lcs->score();
						
						//echo $d;
						
						$score = min($d / strlen($v1), $d / strlen($v2));
						
						$reference->check_score = $score;
						
						if ($score >= $threshold)
						{
							// ok
						}
						else
						{
							$match = false;
						}
					}

					
					
				}
			}
		
			if ($match)
			{
				$reference->doi = $obj->results[0]->doi;
				$reference->doi = str_replace('http://dx.doi.org/', '', $reference->doi);
			}
		}
	}
}


?>
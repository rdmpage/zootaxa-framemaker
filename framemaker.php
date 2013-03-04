<?php


require_once (dirname(__FILE__) . '/citation.php');
require_once (dirname(__FILE__) . '/crossref.php');
require_once (dirname(__FILE__) . '/lib.php');
require_once (dirname(__FILE__) . '/reference.php');

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
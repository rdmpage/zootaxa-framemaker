<?php

require_once(dirname(__FILE__) . '/nameparse.php'); 
 
//--------------------------------------------------------------------------------------------------
/**
 * @brief Add an author to a reference from a string containing the author's name
 *
 * @param reference Reference object
 * @param author Author name as a string
 *
 */
function reference_add_author_from_string(&$reference, $author, $secondary = false)
{
	if ($secondary)
	{
		if (!isset($reference->secondary_authors))
		{
			$reference->secondary_authors = array();
		}
	}
	else
	{
		if (!isset($reference->authors))
		{
			$reference->authors = array();
		}
	}	
	
	$parts = parse_name($author);
	$author = new stdClass();
	if (isset($parts['last']))
	{
		$author->lastname = $parts['last'];
	}
	if (isset($parts['suffix']))
	{
		$author->suffix = $parts['suffix'];
	}
	if (isset($parts['first']))
	{
		$author->forename = $parts['first'];
		
		if (array_key_exists('middle', $parts))
		{
			$author->forename .= ' ' . $parts['middle'];
		}
		
		// Space initials nicely
		$author->forename = preg_replace("/\.([A-Z])/", ". $1", $author->forename);
		$author->forename = str_replace(".", "", $author->forename);
		
	}
	if ($secondary)
	{
		$reference->secondary_authors[] = $author;	
	}
	else
	{
		$reference->authors[] = $author;	
	}
	

}
 
 
//--------------------------------------------------------------------------------------------------
/**
 * @brief Create a COinS (ContextObjects in Spans) for a reference
 *
 * COinS encodes an OpenURL in a <span> tag. See http://ocoins.info/.
 *
 * @param reference Reference object to be encoded
 *
 * @return HTML <span> tag containing a COinS
 */
function reference_to_coins($reference)
{	
	$coins = '';
	
	switch ($reference->genre)
	{
		case 'article':
			$coins .= '<span class="Z3988" title="';
			$coins .= reference_to_openurl($reference); 
			$coins .= '">';
			$coins .= '</span>';
			break;
			
		default:
			break;
	}
	
	return $coins;
} 

//--------------------------------------------------------------------------------------------------
/**
 * @brief Create an OpenURL for a reference
 * *
 * @param reference Reference object to be encoded
 *
 * @return OpenURL
 */
function reference_to_openurl($reference)
{
	global $config;
	
	$openurl = '';
	
	switch ($reference->genre)	
	{
		case 'article':
			$openurl .= 'ctx_ver=Z39.88-2004&amp;rft_val_fmt=info:ofi/fmt:kev:mtx:journal';
			$openurl .= '&amp;genre=article';
			if (count($reference->authors) > 0)
			{
				$openurl .= '&amp;rft.aulast=' . urlencode($reference->authors[0]->lastname);
				$openurl .= '&amp;rft.aufirst=' . urlencode($reference->authors[0]->forename);
			}
			
			if (isset($reference->authors))
			{
				foreach ($reference->authors as $author)
				{
					$openurl .= '&amp;rft.au=' . urlencode($author->forename . ' ' . $author->lastname);
				}
			}
			$openurl .= '&amp;rft.atitle=' . urlencode($reference->title);
			$openurl .= '&amp;rft.jtitle=' . urlencode($reference->secondary_title);
			if (isset($reference->series))
			{
				$openurl .= '&amp;rft.series/' . urlencode($reference->series);
			}
			if (isset($reference->issn))
			{
				$openurl .= '&amp;rft.issn=' . $reference->issn;
			}
			$openurl .= '&amp;rft.volume=' . $reference->volume;
			$openurl .= '&amp;rft.spage=' . $reference->spage;
			if (isset($reference->epage))
			{
				$openurl .= '&amp;rft.epage=' . $reference->epage;
			}
			$openurl .= '&amp;rft.date=' . $reference->year;
			
			if (isset($reference->sici))
			{
				$openurl .= '&amp;rft.sici=' . urlencode($reference->sici);
			}
						
			if (isset($reference->doi))
			{
				$openurl .= '&amp;rft_id=info:doi/' . urlencode($reference->doi);
			}
			else if (isset($reference->hdl))
			{
				$openurl .= '&amp;rft_id=info:hdl/' . urlencode($reference->hdl);
			}
			else if (isset($reference->url))
			{
				$openurl .= '&amp;rft_id='. urlencode($reference->url);
			}
			break;
			
		default:
			break;
	}
	
	return $openurl;
}


?>
<?php

require_once (dirname(__FILE__) . '/framemaker.php');

function main()
{
	if (isset($_FILES['uploadedfile']))
	{
		//print_r($_FILES);
		
		if ($_FILES["uploadedfile"]["type"] == "text/xml")
		{
			if ($_FILES["uploadedfile"]["error"] > 0)
			{
				echo "Return Code: " . $_FILES["uploadedfile"]["error"];
			}
			else
			{
				$filename = "tmp/" . $_FILES["uploadedfile"]["name"];
				move_uploaded_file($_FILES["uploadedfile"]["tmp_name"], $filename);
				
				$doi = false;
				if (isset($_POST['doi']) && ($_POST['doi'] == 'doi'))
				{
					$doi = true;
				}
				
				//echo $_FILES["uploadedfile"]["name"] . '<br />';
				//echo '<pre>';
				$references = parse($filename, $doi);
				
				//----------------------------------------------------------------------------------------------
				// HTML dump
				
				echo '<!DOCTYPE html>
	<html>
        <head>
            <meta charset="utf-8"/>
			<style type="text/css">
			  body {
				margin: 20px;
				font-family:sans-serif;
			  }
			</style>
            <title>Zootaxa Framemaker Output</title>
        </head>
		<body>';
		
				echo '<h1>' . $_FILES["uploadedfile"]["name"] . '</h1>';
				
				echo '<p>';
				echo '<span style="border:1px dotted black;background-color:lawngreen;">DOI</span>';
				echo '&nbsp;<span style="border:1px dotted black;background-color:orange;">Parsed (no DOI)</span>';
				echo '&nbsp;<span style="border:1px dotted black;background-color:white;">Not parsed</span>';
				echo '</p>';
		
				echo '<table border="0" cellspacing="0">';
				foreach ($references as $reference)
				{
					echo '<tr style="';
					
					// do we have a DOI?
					if (isset($reference->doi))
					{
						echo 'background-color:lawngreen;';
					}
					else
					{
						// OK, no DOI, but did we parse it OK?
						if (isset($reference->secondary_title))
						{
							echo 'background-color:orange;';
						}
					
					}
					
					echo '">';
					
					echo '<td valign="top" style="border-bottom:1px dotted black;">';
					echo $reference->id;
					echo '</td>';
					
					echo '<td style="border-bottom:1px dotted black;">';
					echo '<table cellpadding="2" cellspacing="2">';
					echo '<tr>';
					echo '<td>';
					echo $reference->citation;
					echo '</td>';
					echo '</tr>';
			
					echo '<tr>';
					echo '<td>';
					
					// OpenURL and/or other lookups
					//echo '<ul>';
					//echo '<li><a href="http://biostor.org/openurl?' . reference_to_openurl($reference) . '" target="_new">BioStor</a></li>';
					//echo '<li><a href="http://bioguid.info/openurl?' . reference_to_openurl($reference) . '" target="_new">bioGUID</a></li>';
					//echo '</ul>';
					
					// COinS
					echo reference_to_coins($reference);
					echo '</td>';
					echo '</tr>';
			
					echo '<tr>';
					echo '<td>';
					if (isset($reference->doi))
					{
						echo '<a href="http://dx.doi.org/' . $reference->doi . '" target="_new">' . $reference->doi . '</a>';
					}
					else
					{
						echo '&nbsp';
					}
					echo '</td>';
					echo '</tr>';
			
					echo '</tr>';
					echo '</table>';
					echo '</td>';
					
					echo '</tr>';
					
				
				}
				echo '</table>';
				
				echo '		</body>
	</html>';
				
 			}
		}
		else
		{
			echo 'File is wrong type';
		}
	}
	else
	{
$html = <<<EOT
<!DOCTYPE html>
	<html>
        <head>
            <meta charset="utf-8"/>
			<style type="text/css">
			  body {
				margin: 20px;
				font-family:sans-serif;
			  }
			</style>
            <title>Zootaxa Framemaker</title>
        </head>
		<body>
			<h1>Zootaxa Framemaker Reference Extractor</h1>
			<p>Upload a Zootaxa Framemaker XML file</p>
			<form enctype="multipart/form-data" action="index.php" method="POST">
				<input type="checkbox" name="doi" value="doi"  /> Lookup DOIs for cited articles (may take a while)<br />
				<br />
				Choose a file to upload: <input name="uploadedfile" type="file" /><br />
				<input type="submit" value="Upload File" /><br />
			</form>
		</body>
	</html>
EOT;

echo $html;

	}
}

main();

?>
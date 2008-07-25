<?php

$Module = array( "name" => "Import XML" );

$ViewList = array();
$ViewList["export"] =
	array(
	    'default_navigation_part' => 'ezimportxmlnavigationpart',
		'script' => 'export.php',
		'params' => array()
	);

$ViewList["import"] =
	array(
	    'default_navigation_part' => 'ezimportxmlnavigationpart',
		'script' => 'import.php',
		'params' => array()
	);


?>

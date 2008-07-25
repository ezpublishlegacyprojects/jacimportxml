<?php
/*
    filter replaces all placeholder   {##remote_id=idxy##} with object_ids

    $name_conf = new eZImportConverter( 'felix name 1 with <h1>html in it</h1>' );
// adding a filter to remove all html stuff
// you can write your own filters @see importfilters/ [filtername]filter.php
$name_conf->addFilter( "plaintext");

*/
class xmlimportezxmlfilter extends eZImportConverter
{
	function xmlimportezxmlfilter()
	{

	}
	function filter ( &$data )
	{
        ImportXML::log('xmlimportezxmlfilter');
		return $data;
	}

}
?>
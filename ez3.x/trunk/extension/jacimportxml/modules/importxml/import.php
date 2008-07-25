<?php


include_once( "kernel/common/template.php" );
include_once( "lib/ezutils/classes/ezini.php" );

include_once( 'lib/ezxml/classes/ezxml.php' );
include_once( 'lib/ezfile/classes/ezfile.php' );

include_once( 'extension/jacimportxml/classes/importxml.php');

$Module =& $Params["Module"];


$http =& eZHTTPTool::instance();


include_once( "lib/ezutils/classes/ezini.php" );
$siteINI =& eZINI::instance( 'site.ini' );
$varDir = $siteINI->variable('FileSettings','VarDir');

//$mainDirName = 'jacimportxml';
$jacImportXmlINI =& eZINI::instance( 'jacimportxml.ini' );

$DirRoot = $jacImportXmlINI->variable('JacImportXmlSettings','DirRoot');
if( $DirRoot != 'standard' )
  $varDir = $DirRoot;


$DirNameForImportExport = $jacImportXmlINI->variable('JacImportXmlSettings','DirNameForImportExport');
$mainDirName = $DirNameForImportExport;


$exportDir = $varDir.'/'.$mainDirName.'/importxml/';


$importDirsTemp = eZDir::findSubdirs( $exportDir );

eZDebug::writeDebug( "Versuche Verzeichnisse einzulesen aus: ".$exportDir ." # Anzahl SubDirs = ".count($importDirsTemp) , 'jacimportxml: import.php');

foreach( $importDirsTemp as $dir )
{
    $importDirs[] = $exportDir.$dir.'/';
}

$importToNodeId = 43;


if( $http->hasVariable('importToNodeId') )
    $importToNodeId =  $http->variable('importToNodeId');

if( $http->hasVariable('importDir') )
    $importDir =  $http->variable('importDir');



$http_vars = array();

$importResult = array();

if( $importDir != null  )
{

    $dir = $importDir;

    //$file = $dir . 'definition.xml';

   // $importToNodeId = 43;

    //$importResult = importXMLToEzpublish( $file, $importToNodeId );


    $startTime = time();

    $importXMLObject = new ImportXML( $dir, $importToNodeId );
    $importResult = $importXMLObject->import();

    $count = count( $importResult );
        $endTime = time();

    $processingTime = $endTime - $startTime;
    $persecond = $count / $processingTime;



    $view_html.= "ImportDir: $dir \n=> importToNodeId: $importToNodeId \n";
    $view_html.= "\nAnzahl = $count\n";
    $view_html.= "<br>Zeit:<br>". $processingTime ." s | ".($processingTime/60) ." min";
    $view_html.= "<br>Importierte Objekte pro Sekunde: ".$persecond . ' objekte / s';

    $view_html .= "\n\n## Import with php-cli: ##\n php extension/jacimportxml/bin/php/importxml.php -s ". $GLOBALS['eZCurrentAccess']['name'] ." --to_node_id=43 --dir='$dir'";


}
else
{
    $view_html = "Etwas f&uuml;r den import ausw&auml;hlen";

}

$content = $view_html;




$Result = array();

// Template
$tpl =& templateInit();

//$content = "inhalt" . print_r( $eZDomDocument, true);

//$Result['content'] = $content;


// Template variablen setzen
$tpl->setVariable( "content", $content);


$tpl->setVariable( "import_to_node_id", $importToNodeId );
$tpl->setVariable( "import_dirs", $importDirs );

//$tpl->setVariable( "import_result", $importResult );

$importMethods = array('use_xml_settings',EZ_IMPORT_METHOD_AUTO, EZ_IMPORT_METHOD_UPDATE, EZ_IMPORT_METHOD_ALWAYS_CREATE, EZ_IMPORT_METHOD_NO_UPDATE_IF_EXIST );
/*
define( "EZ_IMPORT_METHOD_AUTO", "auto" );
	define( "EZ_IMPORT_METHOD_UPDATE", "update" );

	// never update records always create
	define( "EZ_IMPORT_METHOD_NO_UPDATE", "always_create" );
	define( "EZ_IMPORT_METHOD_ALWAYS_CREATE", "always_create" );

	// if record exists - no update - no creation - only return node
	define( "EZ_IMPORT_METHOD_NO_UPDATE_IF_EXIST", "no_update_if_exist" );
	*/
$tpl->setVariable( "import_methods", $importMethods );


//$tpl->setVariable( "contentobject", $contentobject);

$Result['content'] =& $tpl->fetch( "design:importxml/import.tpl" );
$Result['left_menu'] = 'design:parts/importxml/menu.tpl';

$Result['path'] = array( array( 'url' => false,
                                'text' => 'importxml/import' ) );



// hier kann man die benutzte pagelayout festlegen
//$Result['pagelayout'] = 'pagelayout_breit.tpl';



?>


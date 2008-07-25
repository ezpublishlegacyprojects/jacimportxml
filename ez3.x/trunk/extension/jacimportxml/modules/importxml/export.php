<?php


include_once( "kernel/common/template.php" );
include_once( "lib/ezutils/classes/ezini.php" );

include_once( 'lib/ezxml/classes/ezxml.php' );
include_once( 'lib/ezfile/classes/ezfile.php' );
include_once( 'lib/ezfile/classes/ezfilehandler.php' );

include_once( 'extension/jacimportxml/classes/exportxml.php');



$Module =& $Params["Module"];


$http =& eZHTTPTool::instance();

$nodeId = '';
$treeDepth = 2;




include_once( "lib/ezutils/classes/ezini.php" );
$siteINI =& eZINI::instance( 'site.ini' );
$varDir = $siteINI->variable('FileSettings','VarDir');


//$mainDirName = 'jacimportxml';
$jacImportXmlINI =& eZINI::instance( 'jacimportxml.ini' );
$DirNameForImportExport = $jacImportXmlINI->variable('JacImportXmlSettings','DirNameForImportExport');
$mainDirName = $DirNameForImportExport;

$exportDir = $varDir.'/'.$mainDirName.'/importxml/';

if( $http->hasVariable('exportNodeId') )
    $nodeId =  $http->variable('exportNodeId');

if( $http->hasVariable('treeDepth') )
    $treeDepth =  $http->variable('treeDepth');




$http_vars = array();


$exportedFiles = array();

if( $nodeId != null && $nodeId != 0 )
{



    /*
    $xmlFile = "extension/jacimportxml/doc/test.xml";

    $file = new eZFile();
    $gbXML = $file->getContents( $xmlFile );

    $xml = new eZXML();
    $eZDomDocument =& $xml->domTree( $gbXML );

    $rootNode = $eZDomDocument->get_root();
    */

    //$nodeId = 12850;
    //$nodeId = 12854;
    //$nodeId = 12844;
    //$nodeId = 1502;

    //$nodeId = 12853;
    //$nodeId = 13193;
    //$nodeId = 12968;
    //$nodeId = 65;


    $contentObject = eZContentObject::fetchByNodeID( $nodeId );
    $content = '';

    if( is_object($contentObject) )
    {


        $exportObject = new ExportXML();

        $dir = $exportObject->exportNodeTree( $exportDir, $nodeId, $treeDepth );

        $exportedFiles = $exportObject->getExportFiles();

    //$dom =& $exportObject->createObjectFolder( $exportDir, $nodeId );

    //$xml = $dom->toString();

    //$result = eZFile::create ( 'definition.xml', 'var/export/', $xml );

         $content .= "Parameter exportNodeId = $nodeId \n";
        $content .= "Parameter treeDepth = $treeDepth \n";

    }
    else
    {
        $content .= "\n!!!!!!!! NodeId $nodeId does not exists !!!!!!!\n";
    }

    $view_html = str_replace("<","&lt;",$xml);
    $view_html = str_replace(">","&gt;",$view_html);



    $content .= $view_html;





   // $content .= print_r( $exportedFiles, true );



}
else
{
    $content .= "Keine NodeId zum Exportieren ausgew&auml;hlt";
}

$Result = array();


// Template
$tpl =& templateInit();

//$content = "inhalt" . print_r( $eZDomDocument, true);

//$Result['content'] = $content;


// Template variablen setzen
$tpl->setVariable( "content", $content);

$tpl->setVariable( "exported_files", $exportedFiles);



$tpl->setVariable( 'export_node_id', ($nodeId!=''?$nodeId:''));
$tpl->setVariable( 'tree_depth', $treeDepth);

//$tpl->setVariable( "contentobject", $contentobject);

$Result['content'] =& $tpl->fetch( "design:importxml/export.tpl" );
$Result['left_menu'] = 'design:parts/importxml/menu.tpl';

$Result['path'] = array( array( 'url' => false,
                                'text' => 'importxml/export' ) );



?>


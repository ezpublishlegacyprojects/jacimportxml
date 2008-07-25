<?php
/*

developed by: Felix Woldt September 2007

Import Contenobjects from xml strukture

examples:

php extension/jacimportxml/bin/php/importxml.php --to_node_id=2 --dir='var/importxml/test'

*/

$readme  = <<< README

README:
This scrip tis dependant on the extension import and jacimportxml.
README;
include_once( 'lib/ezutils/classes/ezcli.php' );
include_once( 'kernel/classes/ezscript.php' );
$cli =& eZCLI::instance();
$script =& eZScript::instance( array( 'description' => ( "Import script. " . $readme .
                                                         "\n" .
                                                         "php extension/jacimportxml/bin/php/importxml.php --to_node_id=2 --dir='var/importxml/test' \n" ),
                                      'use-session' => true,
                                      'use-modules' => true,
                                      'use-extensions' => true ) );

$script->startup();

$options = $script->getOptions( "[to_node_id:][dir:]",
                                "",
                                array( 'to_node_id' =>  "The ContainerNode where contentobject will be importet",
                                       'dir' => "dir with xml data"
                                        )
                                       );

$script->initialize();


$sys =& eZSys::instance();

// get all classes


if ( empty( $options['to_node_id']) or empty( $options['dir']) )
{

	$script->showHelp();
	return $script->shutdown();
}

$toNodeId = (int) $options['to_node_id'];
$dir =  $options['dir'];

$cli->output('++++ Start Import xml Data +++++');
$cli->output('to_node_id: '.$toNodeId . ' - dir: '.$dir );

// check node
include_once( 'kernel/classes/ezcontentobject.php');
$object = eZContentObject::fetchByNodeID( $toNodeId );

$errors = array();
$string = "+ to_node_id $toNodeId ok";

if( !is_object( $object ))
{
    $string = "!! to_node_id $toNodeId not exists !!";
    $errors[] = $string;
}
$cli->output( $string );

// check Dir

$isDir = is_dir( $dir );
$string = "+ dir $dir ok";
if( !$isDir )
{
    $string = "!! dir $dir is not dir / or not exists !!";
    $errors[] = $string;
}
$cli->output( $string );

if( count( $errors ) == 0 )
{
    $cli->output('++ Start Import xml Data ++');

    include_once( 'extension/jacimportxml/classes/importxml.php');

    $startTime = time();

    $importXMLObject = new ImportXML( $dir, $toNodeId, $cli );
    $importResult = $importXMLObject->import();

    $count = count( $importResult );

    $endTime = time();

    $processingTime = $endTime - $startTime;
    $persecond = $count / $processingTime;


    $view_html = "ImportDir: $dir \n=> importToNodeId: $toNodeId \n";
    $view_html.= "\nAnzahl = $count\n";
    $view_html.= "Zeit:". $processingTime ." s | ".($processingTime/60) ." min\n";
    $view_html.= "Importierte Objekte pro Sekunde: ".$persecond . ' objekte / s';

    $cli->output( $view_html );

    $cli->output('-- END Import xml Data --');
}
else
{
    $cli->output('ERRORS - not data were imported');

}


return $script->shutdown();
?>
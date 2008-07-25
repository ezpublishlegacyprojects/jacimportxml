<?php

include_once( "kernel/common/template.php" );
include_once( "lib/ezutils/classes/ezini.php" );

include_once( 'lib/ezxml/classes/ezxml.php' );
include_once( 'lib/ezfile/classes/ezfile.php' );
include_once( 'lib/ezfile/classes/ezfilehandler.php' );


class ExportXML
{


    function ExportXML()
    {
        $this->exportFileArray = array();
    }

    function addExportFile( $path )
    {
        array_push( $this->exportFileArray, $path );
    }

    function getExportFiles()
    {
        return $this->exportFileArray;
    }

// hier kann man die benutzte pagelayout festlegen
//$Result['pagelayout'] = 'pagelayout_breit.tpl';


function exportNodeTree( $exportDir, $parentNodeID, $depth = 1 )
{


    $result = array();
    $nodeObject = eZContentObjectTreeNode::fetch( $parentNodeID );

    if( !is_object( $nodeObject ) )
        return 'NodeId does not exists '. $parentNodeID;

    $resultDir = $this->createObjectFolder( $exportDir, $parentNodeID );

    $treeParameters = false;
    //$children =& eZContentObjectTreeNode::subTree( $treeParameters,  $parentNodeID );

    if( $depth >= 1 )
        $children =& $nodeObject->attribute('children');
    else
        $children = array();


    $depth = $depth - 1;

    foreach ( $children as $childNode )
    {
        $dir = $this->exportNodeTree( $resultDir.'/', $childNode->attribute('node_id'), $depth );

    }



    return $resultDir;

}




function createObjectFolder( $exportDir, $nodeId, $objectId = false, $isObjectRelation = false )
{

    $domDocument = null;

    if( $objectId != false )
    {
        $domDocument = $this->objectToXML( $objectId );
    }
    else
    {
        $domDocument = $this->nodeObjectToXML( $nodeId );
    }

    if( !is_object($domDocument) )
        return false;

    $xml = $domDocument->toString();
    unset( $domDocument );

    // xml neu einlesen da ansonsten manche funktionen nicht klappen
    $xml2 = new eZXML();
    $eZDomDocument =& $xml2->domTree( $xml );
    $rootDomNode = $eZDomDocument->root();

    $object = $rootDomNode->elementFirstChildByName('objects');
   // $exportDir = 'var/importxml/';

    $objectClassIdentifier = $object->attributeValue('class_identifier');
    $objectCustomId = $object->attributeValue('custom_id');
    $objectId = $object->attributeValue('contentobject_id');

    $remoteId = $object->attributeValue('remote_id');

 //   $objectName = $object->attributeValue('name');


 //   $dirName = $objectName.'#'.$objectId.'.'.$objectClassIdentifier;
    $dirName = $remoteId.'#'.$objectClassIdentifier;

    if( $isObjectRelation === true )
    {
        $dirName = 'r#'.$dirName;
    }

    $dir = $exportDir.$dirName;
    $relationDir = $exportDir.'r#'.$dirName;

    // wenn es schon ein relationordnergibt von diesem object, dann nix machen
    if( !file_exists( $relationDir ) )
    {
        $result = eZFile::create ( 'definition.xml', $dir, $xml );
        $this->addExportFile( $dir.'/definition.xml');


        $eZImageArray = $eZDomDocument->elementsByName('ezimage');

        foreach( $eZImageArray as $eZImageDomNode )
        {
            $fullPath = $eZImageDomNode->attributeValue('full_path');
            $destinationDirectory = $dir;

            $item = $eZImageDomNode->attributeValue('original_filename');

            //kopiere bild in export ortner
            if ( is_file( $fullPath ) )
            {
                  eZFileHandler::copy( $fullPath, $destinationDirectory . '/' . $item );
                  $this->addExportFile( $destinationDirectory . '/' . $item );
            }

        }

        $eZBinarayArray = $eZDomDocument->elementsByName('ezbinaryfile');

        foreach( $eZBinarayArray as $eZBinarayFileDomNode )
        {
            $fullPath = $eZBinarayFileDomNode->attributeValue('full_path');
            $destinationDirectory = $dir;

            $item = $eZBinarayFileDomNode->attributeValue('original_filename');

            //kopiere bild in export ortner
            if ( is_file( $fullPath ) )
            {
                  eZFileHandler::copy( $fullPath, $destinationDirectory . '/' . $item );
                  $this->addExportFile( $destinationDirectory . '/' . $item );
            }

        }


       // $domDocument = new eZDOMDocument()nodeObjectToXML( $nodeId );

       //  $rootDomNode = $domDocument->root();
       //  $domNodeObject = $rootDomNode->elementsByName('objects');


        //$domNodeObject = $rootDomNode->elementsByName('objects');

       // hier alle objektrelation fetchen und als unterordner anlegen
       // embeded objekte
        $objectRelations = $eZDomDocument->elementsByName('objectrelation');

        array_merge( $objectRelations, $eZDomDocument->elementsByName('ezobjectrelation') );

        //array_merge( $objectRelations, $eZDomDocument->elementsByName('ezobjectrelationlist') );


        // TODO was passiert mit ezobjectrelationlist werten????
        // evtl. global ablegen

        foreach ( $objectRelations as $domNode )
        {
               // F�r jede objektrelation daten fetchen und eigenen ordner anlegen
               $contentObjectId = $domNode->attributeValue('option_contentobject_id');
              // $custom_id = $domNode->attributeValue('custom_id');
               $returnDomDocument = $this->createObjectFolder( $dir .'/', false, $contentObjectId, true );
               unset( $contentObjectId );
               unset( $returnDomDocument );

        }
    }
    return $dir;
}



function nodeObjectToXML( $nodeId )
{

    $nodeObject = eZContentObjectTreeNode::fetch( $nodeId );

    if( is_object( $nodeObject ) )
    {
        $contentObject = $nodeObject->attribute('object');
        return $this->objectToXML( $nodeObject->attribute('contentobject_id') );
    }
    else
    {
        return false;
    }


}

function &objectToXML( $objectID, $asDomTree=false, $parameters =array(), $domObjectElmentName = 'object' )
{

    if( count($parameters) == 0)
    {

        $parameters = array( 'remote_id' => 'unique_import_remote_id_'.$objectID,
                             'created' => time(),
                             'modified' => time() );
    }

    $xml = '';

    // get data for xml

    $contentObject = eZContentObject::fetch( $objectID );


    $classIdentifier = $contentObject->attribute('class_identifier');
    $name = $contentObject->attribute('name');
    $initialLanguage = $contentObject->attribute('current_language');
  //  $remote_id = $contentObject->attribute('remote_id');
    $mainNodeId = $contentObject->attribute('main_node_id');




 //   $objectRelations =eZContentObject::relatedObjects(false, $nodeObject->attribute('contentobject_id'));
 //   $objectRelations =eZContentObject::relatedObjects(false, $nodeObject->attribute('contentobject_id'));

    $objectInfoArray = array( 'class_identifier' => $classIdentifier,
                              'name' => $name,
//                              'initial_language' => $initialLanguage,
//                              'remote_id' => $remote_id,
                               'option_contentobject_id' => $objectID,
//                              'main_node_id' =>$mainNodeId
		                   //   'custom_id' => 'eindeutige_object_id_2007_10_artikelid|name',
		                   //   'created' => time(),
		                   //   'modified' => time(),
		                   //   'import_method' => 'update'
		                   );

    $objectInfoArray = array_merge( $objectInfoArray, $parameters );

    // create xml document
    $dom = new eZDOMDocument();
    $dom->setName( "import" );

    $root = $dom->createElementNode( "import" );
    $dom->setRoot( $root );

    $objects = $dom->createElementNode( "objects" );
    $root->appendChild( $objects );



    //$object = $dom->createElementNode( 'object', $objectInfoArray );
    $object = $dom->createElementNode( $domObjectElmentName, $objectInfoArray );
    $objects->appendChild($object);

    $attributes = $dom->createElementNode( 'attributes' );
    $object->appendChild($attributes);

    // get all attributes of the object

    $dataMap = 	$contentObject->attribute('data_map');


    $embedObjectArray = array();

    foreach ( $dataMap as $index => $attributeObject )
    {

        $dataTypeString = $attributeObject->attribute('data_type_string');

        $attributeText = $attributeObject->attribute('content');
        $attributeData = array('identifier' => $index,
                                'data_type' => $dataTypeString
                             );


        switch ( $dataTypeString )
        {
            case 'ezfloat':
            case 'ezprice':
                $data = $attributeObject->attribute( 'data_float' );
                $attribute = $dom->createElementTextNode( 'attribute', $data, $attributeData );
                break;

            case 'ezinteger':
            case 'ezboolean':
            case 'ezdate' :
            case 'ezdatetime' :
            case 'eztime' :
                $data = $attributeObject->attribute( 'data_int' );
                $attribute = $dom->createElementTextNode( 'attribute', $data, $attributeData );
                break;

            case 'ezemail' :
            case 'ezisbn' :
            case 'ezstring' :
            case 'eztext' :
            case 'ezcountry' :
                $data = $attributeObject->attribute( 'data_text' );
                $attribute = $dom->createElementTextNode( 'attribute', $data, $attributeData );
                break;

            case 'ezselection' :

                $selectionKey = (int) $attributeObject->attribute( 'data_text' );
                $classAttribute = $attributeObject->attribute('contentclass_attribute');
                $classAttributeContent = $classAttribute->attribute('content');

                $data = $classAttributeContent['options'][ $selectionKey ]['name'];

                $attribute = $dom->createElementTextNode( 'attribute', $data, $attributeData );
                break;

            case 'ezxmltext' :
            {
                 //   $attribute = $dom->createElementCDATANode( 'attribute', $attributeText->attribute('xml_data'), $attributeData );

                    $text = $attributeText->attribute('xml_data');

                    $xml = new eZXML();
                    $eZDomDocument =& $xml->domTree( $text );

                    if( is_object( $eZDomDocument ))
                    {

                        $embedObjects = $eZDomDocument->elementsByName('embed');
                        $embedInlineObjects = $eZDomDocument->elementsByName('embed-inline');

                        $embedObjects = array_merge($embedObjects, $embedInlineObjects );


                        foreach( $embedObjects as $embedDomNode )
                        {
                            $object_id = $embedDomNode->attributeValue('object_id');

                            // object_id im xml ersetzten durch custom tag


                            $regex='/object_id="'.$object_id.'"/';
                            $custom_id = 'unique_import_remote_id_'. $object_id;
                            $replace='object_id="{##import_remote_id='.$custom_id.'##}"';

            				$text = preg_replace($regex, $replace, $text );

                            $embedObjectArray[$custom_id] = array( 'contentobject_id' => $object_id,
                                                                  'contentobject_custom_id' => $custom_id);
                        }

                    }

                    //$text = 'xml_data';
                    $attribute = $dom->createElementCDATANode( 'attribute', $text, $attributeData );
                break;
            }
            case 'ezkeyword':

                $text = $attributeText->keywordArray();
                $attribute = $dom->createElementTextNode( 'attribute', implode(',',$text), $attributeData );
                break;

            case 'ezobjectrelation':


                $attribute = $dom->createElementNode( 'attribute', $attributeData );

                    if( is_object( $attributeText ))
                    {
                        $text = $attributeText->attribute('content');



                    	$contentObjectId = $attributeText->attribute('id');

                   /* 	$contentObject = eZContentObject::fetch( $contentObjectId );
                    	if( is_object($contentObject) )
                    	   $remote_id = $contentObject->attribute('remote_id');

                        $dataArray = array( 'contentobject_id' => $contentObjectId,
                                            'remote_id' => $remote_id,
                                            'custom_id' => "mycustomid". $contentObjectId );

                        $relation = $dom->createElementNode( 'objectrelation', $dataArray );*/

                        $relation =& $this->getObjectRelationDomNode( $dom, $contentObjectId );
                        $attribute->appendChild($relation);

                    }

                    // objektdaten holen
                   // $domNode = objectToXML( $contentObjectId, true, $dataArray, 'objectrelation' );

                  //  $string = $domNode->toString();
                  //  $objects_array = $domNode->elementsByName('object');



                    unset( $domNode );
                    unset( $objectrelation );


                     unset($relation);
                break;
            case 'ezobjectrelationlist':

                    $attribute = $dom->createElementNode( 'attribute', $attributeData );

                   // $relationList = $dom->createElementNode( 'objectrelations' );

                   // $objectIdArray = array();
                    $i = 1;
                    foreach ( $attributeText['relation_list'] as $relationData )
                    {
                        $contentObjectId = $relationData['contentobject_id'];
                        $relation =& $this->getObjectRelationDomNode( $dom, $contentObjectId );
                       // $relationList->appendChild($relation);

                        $attribute->appendChild($relation);
                        unset($relation);
                        //$objectIdArray[] = $relationData['contentobject_id'];
                        $i++;
                    }

                    //$text = implode(',', $objectIdArray );
                    //$relationList = $dom->createElementTextNode( 'relation_list', $text, array() );

                    $attribute->appendChild( $relationList );

                    unset( $relationList );

                    //$text = print_r($attributeText,true);
                	//$attribute = $dom->createElementTextNode( 'attribute', $text, $attributeData );
                break;
            case 'ezimage':

                    $originalAttributeData = $attributeText->originalAttributeData();
                    $imageAttributes = $attributeText->attributes();

                    $ezimageAliasHandler = $attributeText;

                    $alternativeText = $ezimageAliasHandler->attribute('alternative_text');
                    $originalFileName = $ezimageAliasHandler->attribute('original_filename');

                    // alle Bilddaten vom orignalbild
                    $original = $ezimageAliasHandler->attribute('original');

                    $pfad_info = pathinfo ( $original['original_filename'] );

                    $new = array( 'original_filename' => $pfad_info['basename'],
                                  'full_path' => $original['full_path'] );

                    $originalImageDomNode = $dom->createElementNode('ezimage', $new );



                    //$imageDomTree = $attributeText->domTree();

                    //$imageRootNode = $imageDomTree->root();

                    //$imageDomNode = $imageDomTree->elementsByName('ezimage');

                	$attribute = $dom->createElementNode( 'attribute', $attributeData );

                	$attribute->appendChild( $originalImageDomNode );
                	unset( $originalImageDomNode );


                break;
            case 'ezbinaryfile':

                $fileAttributes = $attributeText->attributes();


                    $originalFilename = $attributeText->attribute('original_filename');
                    $storedFileName = eZBinaryFileHandler::storedFilename( $attributeText , false );
                    $attribueArray = array( 'original_filename' => $originalFilename,
                                            'full_path' => $storedFileName );

                    $ezBinaryFileNode = $dom->createElementNode( "ezbinaryfile" , $attribueArray );


         //           $storedFileName = $attributeText->storedFilename();

                    $attribute = $dom->createElementNode( 'attribute', $attributeData );
                    $attribute->appendChild( $ezBinaryFileNode );
                	unset( $ezBinaryFileNode );

                break;
            case 'ezauthor':

                $attribute = $dom->createElementNode( 'attribute', $attributeData );
                $authorList = $attributeText->attribute('author_list');
                $authorDomNodes = array();
                foreach( $authorList as $author )
                {
                    $authorDomNode = $dom->createElementNode( 'ezauthor', $author );
                    $attribute->appendChild( $authorDomNode );
                    unset( $authorDomNode );
                }

                break;

      //      case 'ezmatrix':
      //          if( is_object( $attributeText) )
      //              $attribute = $dom->createElementTextNode( 'attribute', $attributeText->xmlString(), $attributeData );
      //          break;

            default:
           /*     if( is_object($attributeText) && method_exists( $attributeText, 'toString' ) )
                {
                    $attribute = $dom->createElementTextNode( 'attribute', $attributeText->toString(), $attributeData );
                }
                elseif ( is_object($attributeText) && method_exists( $attributeText, 'xmlString' ) )
                {
                    $attribute = $dom->createElementTextNode( 'attribute', $attributeText->xmlString(), $attributeData );
                }
                else
                {
                    $attribute = $dom->createElementTextNode( 'attribute', $attributeText, $attributeData );
                }
*/
            $attributeText = '!!! export/import for this datatype not implemented yet !!!';
            $attribute = $dom->createElementTextNode( 'attribute', $attributeText, $attributeData );
        }




   //     $attribute = $dom->createElementNode( 'attribute', $attributeData );
        $attributes->appendChild( $attribute );
        unset( $attribute );
     //   unset( $attributeData );


    }

    // nur wenn eingebettet Objekrelations in xml dann bereich erzeugen
    // wird für das Kopieren der binarays später gebraucht
    // für den eigentlichen import aber nicht

    if( count($embedObjectArray) > 0 )
    {
        $objectrelations = $dom->createElementNode( 'objectrelations' );
        foreach ( $embedObjectArray as $embedData )
        {
             $contentObjectId = $embedData['contentobject_id'];
             $relation =& $this->getObjectRelationDomNode( $dom, $contentObjectId );
             $objectrelations->appendChild($relation);
             unset( $relation );
        }

        $object->appendChild( $objectrelations );
    }

    // eigebettete objecte erfassen


  /*  foreach ( $attributesArray as $attributeData )
    {
        $attribute = $dom->createElementNode( 'attribute', $attributeData );
        $attributes->appendChild( $attribute );
        unset( $attribute );
    }
*/

    $xml = $dom->toString();



    if( $asDomTree === true )
    {
        $rootDomNode = $dom->root();
        $domNodeObject = $rootDomNode->elementFirstChildByName('objects');

        return $domNodeObject;
    }
    else
        return $dom;//xml;

}

function getObjectRelationDomNode( $dom, $contentObjectId, $attributeName = 'objectrelation' )
{
     $contentObject = eZContentObject::fetch( $contentObjectId );

     if( is_object($contentObject) )
     {
        $remote_id = $contentObject->attribute('remote_id');
        $name = $contentObject->attribute('name');
     }


  /*   $dataArray = array( 'contentobject_id' => $contentObjectId,
                                    'remote_id' => $remote_id,
                                    'name' => $name,
                                    'custom_id' => "mycustomid". $contentObjectId );

*/

      $dataArray = array( 'option_contentobject_id' => $contentObjectId,
                          'remote_id' => "unique_import_remote_id_". $contentObjectId,
                          'name' => $name );


     $relation = $dom->createElementNode( $attributeName, $dataArray );

     return $relation;
}


// hilfsfunktion zum erstellen von der xmlstruktur wenn daten als array forliegen


    /*
		 schreibt die daten aus dem array als xml in das $file

        $dataArray = array( 'options' => array( 'remote_id' => ...,
                                                'class_identifier' => ...),
                            'attributes' => array( 'name#ezstring' => ...,
                                                   'image#ezimage' => ...,
                                                   'intro#ezxmltext' => ...)
                                                )

        der attributekey setzt sich zusammen aus  identifier # ezdatentyp   z.B. name#ezstring
        => attributeidentifer=name und ezdatentyp = ezstring

       \return gibt das generierte xml zurück
	 */
    function writeDataSetToImportXMLFile( $filename = 'definition.xml', $dir, $dataArray )
    {
         $xml = ExportXML::dataSetToImportXML( $dataArray );

         $result = eZFile::create ( $filename, $dir, $xml );

         return $xml;
    }

    /*
        convertier daten aus dem array zu import xml

        $dataArray = array( 'options' => array( 'remote_id' => ...,
                                                'class_identifier' => ...),
                            'attributes' => array( 'name#ezstring' => ...,
                                                   'image#ezimage' => ...,
                                                   'intro#ezxmltext' => ...)
                                                )

        der attributekey setzt sich zusammen aus  identifier # ezdatentyp   z.B. name#ezstring
        => attributeidentifer=name und ezdatentyp = ezstring

       \return gibt das generierte xml zurück
    */
    function dataSetToImportXML( $dataArray )
    {

         // create xml document
        $dom = new eZDOMDocument();
        $dom->setName( "import" );

        $root = $dom->createElementNode( "import" );
        $dom->setRoot( $root );

        $objects = $dom->createElementNode( "objects" );
        $root->appendChild( $objects );


        $objectInfoArray = $dataArray['options'];
        $attributeArray = $dataArray['attributes'];
        $domObjectElementName = 'object';

        //$object = $dom->createElementNode( 'object', $objectInfoArray );
        $object = $dom->createElementNode( $domObjectElementName, $objectInfoArray );
        $objects->appendChild($object);

        $attributes = $dom->createElementNode( 'attributes' );
        $object->appendChild($attributes);

        foreach ( $attributeArray as $index => $attributeContent )
        {

            $explode = explode('#', $index);

            //$dataTypeString = 'ezstring';


            $identifier = $explode[0];
            $dataTypeString = $explode[1];

            $attributeData = array('identifier' => $identifier,
                                'data_type' => $dataTypeString
                             );


            switch ( $dataTypeString )
            {
                case 'ezimage':
                    $attribute = $dom->createElementNode( 'attribute', $attributeData );
                    $ezimage = $dom->createElementNode( 'ezimage' , $attributeContent );
                    $attribute->appendChild($ezimage);
                    break;
                case 'ezbinaryfile':
                    $attribute = $dom->createElementNode( 'attribute', $attributeData );
                    $ezbinaryfile = $dom->createElementNode( 'ezbinaryfile' , $attributeContent );
                    $attribute->appendChild($ezbinaryfile);
                    break;
                case 'ezobjectrelation':
                    // array('remote_id' => wert)
                    // array('contentobject_id' => wert)

                    $attribute = $dom->createElementNode( 'attribute', $attributeData );

                    if ( is_array( $attributeContent ) )
                    {

                        $ezobjectrelation = $dom->createElementNode( 'ezobjectrelation' , $attributeContent );

                        $attribute->appendChild($ezobjectrelation);
                        unset( $ezobjectrelation );
                    }
                    break;
                case 'ezobjectrelationlist':
                    //  array('remote_id' => wert)
                    // array('contentobject_id' => wert)

                    $attribute = $dom->createElementNode( 'attribute', $attributeData );

                    foreach ( $attributeContent as $relationData )
                    {

                        $ezobjectrelationlist = $dom->createElementNode( 'ezobjectrelationlist' , $relationData );

                        $attribute->appendChild($ezobjectrelationlist);
                        unset( $ezobjectrelationlist );
                    }
                    break;
                case 'ezxmltext':
                    $attribute = $dom->createElementCDATANode( 'attribute', $attributeContent, $attributeData );
                    break;
                case 'ezstring':
                    $attribute = $dom->createElementTextNode( 'attribute', $attributeContent, $attributeData );
                    break;
                default:

                        $attribute = $dom->createElementTextNode( 'attribute', $attributeContent, $attributeData );

                    break;
            }


            if( is_object($attribute))
                $attributes->appendChild( $attribute );
            unset( $attribute );
        }



         $xml = $dom->toString();
         unset( $dom );


    /*    $dir = $exportDir.$dirName;
        $result = eZFile::create ( 'definition.xml', $dir, $xml );
        $this->addExportFile( $dir.'/defintion.xml');

        */


        //$result = eZFile::create ( 'definition.xml', $dir, $xml );
        //$result = eZFile::create ( $filename, $dir, $xml );

        return $xml;
    }


    /*
        erzeugt einen Ordner und eine xml datei mit der bezeichnung
        => um schnell einen neues contentobjekt anzulegen

        BSP:

        $name = "Quellen";
        $remoteId = "Quellen";
        $exportDir = "var/exportdir";
        $classIdentifier = "folder";
        $nameIdentifier = "name";

        $sourceDir = ExportXML::createSimpleFolderAndXmlFile( $exportDir, $name, $remoteId, $classIdentifier, $nameIdentifier );

        @return   $sourceDir = false oder
                  $sourceDir = 'var/exportdir/Quellen#folder';

        => erzeugt 1 Ordner 'var/exportdir/ Quellen#folder' und
                   1 Datei  'var/exportdir/ Quellen#folder /defintion.xml' wie nachfolgend abgebildet


        <?xml version="1.0" encoding="UTF-8"?>
        <import>
          <objects>
            <object remote_id="Quellen"
                    class_identifier="folder">
              <attributes>
                <attribute identifier="name"
                           data_type="ezstring">Quellen</attribute>
              </attributes>
            </object>
          </objects>
        </import>

    */
    function createSimpleFolderAndXmlFile( $exportDir, $name, $remoteId=false, $classIdentifier='folder', $nameIdentifier='name', $objectForObjectRelation=false, $contentObjectId=false)
    {
         $objectrelationChar="#";

         if( $objectForObjectRelation == false )
            $objectrelationChar = '';

         $newDir = $exportDir.'/'.$objectrelationChar.$name.'#'.$classIdentifier;
         $newFileName = 'definition.xml';
         $newRemoteId = false;
         $existingContentObjectId = false;



         if( $remoteId )
            $newRemoteId = $remoteId;

         $options = array(  'remote_id' => $newRemoteId, 'class_identifier' => $classIdentifier );

         if( $contentObjectId != false )
         {
            $existingContentObjectId = (int) $contentObjectId;
            $options['contentobject_id'] = $existingContentObjectId;
         }

         $attributes = array( "$nameIdentifier#ezstring" => $name );

         $dataSetArray = array( 'options' => $options, 'attributes' => $attributes );

         $fileResult = ExportXML::writeDataSetToImportXMLFile( $newFileName, $newDir, $dataSetArray );

         if( $fileResult )
            return $newDir;
         else
            return false;
    }

    var $exportFileArray;

}

?>
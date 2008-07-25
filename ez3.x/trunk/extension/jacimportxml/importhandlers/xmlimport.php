<?php
//include_once("extension/ezpowerlib/ezpowerlib.php");
//include_once("File/CSV.php");

include_once( 'lib/ezxml/classes/ezxml.php' );
include_once( 'lib/ezfile/classes/ezfile.php' );

class XMLImportHandler extends eZImportFramework
{
    function XMLImport( $processHandler )
    {
        parent::eZImportFramework( $processHandler );
    }
    function getData( $xmlFileName, $namespace = false )
    {

        if( $namespace )
        {
              $this->namespaces[] = $namespace;
              $this->namespaces = array_unique( $this->namespaces );
        }

        if ( !file_exists( $xmlFileName ) )
        {
        //    $cli = eZCLI::instance();
        //    $cli->output( "No such file '".$file."'" );
            return false;
        }


        $file = new eZFile();
        $gbXML = $file->getContents( $xmlFileName );

        $xml = new eZXML();
        $eZDomDocument =& $xml->domTree( $gbXML );

        $objects = $eZDomDocument->elementsByName('object');

        // alle Daten f�r den import
        $dataSetArray = array();

        foreach ( $objects as $domNodeObject )
        {
           $result  = XMLImportHandler::getImportDataSetFromDomNode( $domNodeObject, $xmlFileName );

           if( $result != false )
                $dataSetArray[] = $result;

            unset( $result );
        }


        if ( $namespace )
            $this->data[$namespace] = array_merge( $this->data, $dataSetArray );
        else
            $this->data = array_merge( $this->data[$namespace], $dataSetArray );
    }


    /*reimpl
        erweitern um daten zwischenzuspeichern
    */
  /*  function importData( $processHandler,  $namespace = false, $options=array() )
	{


	    $result = parent::importData( $processHandler,  $namespace, $options );

	    foreach ( $result as $object)
	    {
    	    $classId = $object->attribute('class_id');
    	    $remoteId = $object->attribute('remote_id');
    	    $contentObjectId = $object->attribute('id');
	    }

		return $result;
	}
*/

    /*!
        erzeugt zu einem domdokument einen importdatensatz
    */
    function &getImportDataSetFromDomNode( $domNodeObject, $xmlFileName )
    {
        $importDataSet = array();

       // $parentNodeId = 2;

        $srcDir = dirname( $xmlFileName );


        $object = $domNodeObject;

        $objectArr = array();

   //     $objectArr['parent_node_id'] = $parentNodeId;
        $objectArr['class_identifier'] = $object->attributeValue('class_identifier');
        $objectArr[EZ_IMPORT_PRESERVED_KEY_CREATION_TIMESTAMP] = $object->attributeValue('creation_timestamp');
        $objectArr[EZ_IMPORT_PRESERVED_KEY_MODIFICATION_TIMESTAMP] = $object->attributeValue('modification_timestamp');

        $contentobject_id = $object->attributeValue('contentobject_id');
        $remote_id = $object->attributeValue('remote_id');

        if( $contentobject_id != '')
            $objectArr['contentobject_id'] = $contentobject_id;

        if( $remote_id != '')
            $objectArr['remote_id'] = $remote_id;

        $objectArr['name'] = $object->attributeValue('name');

       // $objectArr[EZ_IMPORT_METHOD] = EZ_IMPORT_METHOD_NO_UPDATE;

       $updateMethod = $object->attributeValue('import_method');

     //  $updateMethod = EZ_IMPORT_METHOD_UPDATE;



       $jacImportXmlINI =& eZINI::instance( 'jacimportxml.ini' );
       $defaultImportMethod = $jacImportXmlINI->variable('JacImportXmlSettings','DefaultImportMethod');

       if( $updateMethod == EZ_IMPORT_METHOD_AUTO
            or $updateMethod == EZ_IMPORT_METHOD_ALWAYS_CREATE
            or $updateMethod == EZ_IMPORT_METHOD_UPDATE )

            $objectArr[EZ_IMPORT_METHOD] = $updateMethod;

       else if( $defaultImportMethod  == EZ_IMPORT_METHOD_AUTO
                or $defaultImportMethod == EZ_IMPORT_METHOD_ALWAYS_CREATE
                or $defaultImportMethod == EZ_IMPORT_METHOD_UPDATE )

            $objectArr[EZ_IMPORT_METHOD] = $defaultImportMethod;
       else
            $objectArr[EZ_IMPORT_METHOD] = EZ_IMPORT_METHOD_NO_UPDATE_IF_EXIST;
            //$objectArr[EZ_IMPORT_METHOD] = EZ_IMPORT_METHOD_AUTO;

        $attributes = $object->firstChild();
        $attributesArr = array();

        $attribute = $attributes->children();

        foreach ( $attribute  as $domNode )
        {
            $string = $domNode->toString();

            $attribut_identifier = $domNode->attributeValue('identifier');
            $data_type = $domNode->attributeValue('data_type');

            switch( $data_type )
            {


                case 'ezxmltext':
                    $child = $domNode->firstChild();
                    $content = $child->content();

                    // alle {##import_remote_id=$id ##} ersetzen durch contenobjectId von $id
                    $convertedContent = $this->findAndReplaceRemoteIds( $content );

                    $xml = new eZXML();

                    $eZDomDocument =& $xml->domTree( $convertedContent );
                     // wenn der content xml ist - direkt abspeichern
                    if( $eZDomDocument != null)
                    {
                        $content =& $eZDomDocument;
                    }


  /*                  // wenn der content xml ist - direkt abspeichern
                    if( $eZDomDocument != null)
                    {

                        $xmlReplaceConverter = new eZImportConverter( $content );
                        $xmlReplaceConverter->addFilter( "xmlimportezxml" );
                    }
*/
                    break;
                case 'ezimage':
                case 'ezbinaryfile':
                    $child = $domNode->firstChild();
                    $originalFilename = $child->attributeValue('original_filename');
                    if( $originalFilename == '')
                        $originalFilename = $child->attributeValue('filename');

                    $fullPath = $srcDir .'/'.$originalFilename;
                    $content = $fullPath;
                    break;

                case 'ezobjectrelation':

                    // alle kinder <ezobjectrelation remote_id="..."/> suchen und contentobjectid
                    // oder <ezobjectrelation contentobject_id="123"/>

                    $content = null;

                    if( $domNode->hasChildren() )
                    {
                        $children = $domNode->elementsByName('objectrelation');

                        if( count( $children) > 0 )
                        {
                            $child = $children[0];

                            $contentObjectId = $child->attributeValue('contentobject_id');

                            if( $contentObjectId )
                            {

                                 // TODO contentobject_id setzen und prüfen ob existiert

                                  $object =& eZContentObject::fetch( $contentObjectId );

                                  if( is_object( $object) )
                                  {
                                      $content = $contentObjectId;
                                      $this->log('[ezobjectrelation] set contentobject_id for objectrelation'. $contentObjectId);
                                  }
                                  else
                                  {
                                      $this->log('[Error] ezobjectrelation not exists'. $contentObjectId);
                                  }
                            }
                            else
                            {

                                $remoteId = $child->attributeValue('remote_id');

                                $contentObjectId = $this->eZKeyConverter->convert( $this->namespaces, $remoteId );

                                // objekt_id in array speichern
                                if( $contentObjectId )
                                {
                                    $content = $contentObjectId;
                                    $this->log('[KeyConverter Value] '. $remoteId .' => '. $contentObjectId );
                                }
                                else
                                    $this->log('[Error] eZKeyConverter: no Value found for: '. $remoteId );

                                unset($remoteId);
                            }

                        }
                    }
                    break;

                case 'ezobjectrelationlist':
                    // alle kinder <ezobjectrelationlist remote_id="..."/> suchen und contentobjectid

                    $content = array();

                    if( $domNode->hasChildren() )
                    {
                        $children = $domNode->elementsByName('objectrelation');

                        foreach ( $children as $child )
                        {


                            $contentObjectId = $child->attributeValue('contentobject_id');

                            if( $contentObjectId )
                            {

                                 // TODO contentobject_id setzen und prüfen ob existiert

                                  $object =& eZContentObject::fetch( $contentObjectId );

                                  if( is_object( $object) )
                                  {
                                      $content[] = $contentObjectId;
                                      $this->log('[ezobjectrelationlist] set contentobject_id for objectrelation'. $contentObjectId);
                                  }
                                  else
                                  {
                                      $this->log('[Error] ezobjectrelationlist not exists'. $contentObjectId);
                                  }
                            }
                            else
                            {

                                $remoteId = $child->attributeValue('remote_id');

                                // ImportXML:classidentifer
                                // TODO namespace implementieren für remote_id in objectrelationen
                                //$namespace = $child->attributeValue('namespace');

                                $contentObjectId = $this->eZKeyConverter->convert( $this->namespaces, $remoteId );

                                // objekt_id in array speichern
                                if( $contentObjectId )
                                {
                                    $content[] = $contentObjectId;
                                    $this->log('[KeyConverter Value] '. $remoteId .' => '. $contentObjectId );
                                }
                                else
                                    $this->log('[Error] eZKeyConverter: no Value found for: '. $remoteId );

                                unset($remoteId);
                            }

                        }
                    }
                    break;
                case 'ezselection':
                    // TODO multiselection

                case 'ezfloat':
                case 'ezprice':

                case 'ezinteger':
                case 'ezboolean':
                case 'ezdate':
                case 'ezdatetime':
                case 'eztime':

                case 'ezemail':
                case 'ezisbn':
                case 'ezstring':
                case 'eztext':
                case 'ezcountry':
                case 'ezkeyword':
                    $content = $domNode->textContent();
                    break;

                default:
                     $content = null;

            }

            $attributesArr[$attribut_identifier] = $content;
        }




    /*
        $importDataSet = array(	'name' 				=> $name_conf . time(),
        						'short_name'		=> null,
        						'description'		=> $description_conf,
        						EZ_IMPORT_PRESERVED_KEY_CREATION_TIMESTAMP => time(),
        						EZ_IMPORT_PRESERVED_KEY_MODIFICATION_TIMESTAMP => time(),
        						EZ_IMPORT_PRESERVED_KEY_REMOTE_ID => 'id 5',
        						EZ_IMPORT_METHOD => EZ_IMPORT_METHOD_AUTO
        						// if an object with remote_id exists update this contentobject and generate a new Version
        	);
      */

        $importDataSet = array_merge($objectArr, $attributesArr);


        return $importDataSet;
    }


    function findAndReplaceRemoteIds( $dataString )
    {

        // finde alle werte die zwichen  {##imported_remote_id=  ... ##} steht
        // '/\\{#{2}.*=(.*)#{2}\\}/'
        $result = null;
        preg_match_all('/\\{#{2}import_remote_id=(.*)#{2}\\}/', $dataString, $result, PREG_PATTERN_ORDER);
        $matchedStringArray = $result[0];
        $matchedIdArray = $result[1];

        foreach( $matchedIdArray as $index => $customRemoteId )
        {

			 $contentObjectId = $this->findContentObjectIdByRemoteId( $customRemoteId, '[ezxml]' );

			 // alle import_remote_id ersetzen durch object_ids
			 //$regex = '/{##import_remote_id='.$customRemoteId.'##}/';

			 if( $contentObjectId )
			 {
			     $replace = $contentObjectId;
			     $regex = '/'. $matchedStringArray[$index] .'/';
			     $dataString = preg_replace( $regex, $replace, $dataString );

			     unset( $regex );
			 }

        }


        return $dataString;
    }


    /*
        sucht im kexconverter nach einer remote_id und gibt objectid zurück
    */
    function findContentObjectIdByRemoteId( $cusotmRemoteId , $logMessageMarker = '')
    {

        $remoteId = $cusotmRemoteId;
        $contentObjectId = $this->eZKeyConverter->convert( $this->namespaces, $remoteId );

        // objekt_id in array speichern
        if( $contentObjectId )
        {
            $this->log( $logMessageMarker.' [KeyConverter Value] '. $remoteId .' => '. $contentObjectId );
            return $contentObjectId;
        }
        else
        {
            $this->log( $logMessageMarker.' [Error] eZKeyConverter: no Value found for: '. $remoteId );
            return false;
        }
    }



}
?>
<?php

if ( !function_exists('htmlspecialchars_decode') )
{
    function htmlspecialchars_decode($text)
    {
       // $entityArray = get_html_translation_table(HTML_SPECIALCHARS);
        $entityArray['"']= '&#8243;';
        $entityArray['&']= '&amp;';
        $entityArray['<']= '&lt;';
        $entityArray['>']= '&gt;';

        return strtr($text, array_flip( $entityArray ));
    }
}

/*
	function removeHTMLEntities( $string )
    {

        $string = html_entity_decode( $string );

        // replace literal entities


        //$entityArray = get_html_translation_table(HTML_ENTITIES);
   		//$entityArray = array_flip( $entityArray );

   		$entityArray['"']= '&#8243;';
        $entityArray['&']= '&amp;';
        $entityArray['<']= '&lt;';
        $entityArray['>']= '&gt;';

        $string = strtr( $string, $entityArray );

        // replace numeric entities
        $string = preg_replace('~&#x([0-9a-f]+);~ei', 'chr(hexdec("\\1"))', $string );
        $string = preg_replace('~&#([0-9]+);~e', 'chr("\\1")', $string );

   		return $string;
	}
*/

/*
class ImportXmlDataSet

fragt ez nach classendefintion
*/

class ImportXmlDataSet
{
    function ImportXmlDataSet( $classIdentifier )
    {

        $this->ClassObject = eZContentClass::fetchByIdentifier( $classIdentifier );

        $this->ClassIdentifer = $classIdentifier;


        $dataMap = $this->ClassObject->dataMap();

        foreach( $dataMap as $identifier => $attribute )
        {
            $this->MappingArray[ $identifier ] = $identifier.'#'.$attribute->attribute('data_type_string');
        }

    }

    function setClassAttribute( $attributeIdentifer, $value, $utf8Encode = false, $htmlEntityDecode = false )
    {
        // TODO validate value ob richtig für type
        if( array_key_exists( $attributeIdentifer, $this->MappingArray ))
        {

            if( $htmlEntityDecode )
            {
                if( is_string( $value ))
                {

                   // $value = html_entity_decode( $value, ENT_QUOTES , 'UTF-8' );
                    $value = html_entity_decode( $value );
                    $value = htmlspecialchars_decode( $value );

                    //$value = removeHTMLEntities( $value );
                }
            }


            if ($utf8Encode)
            {
                if( is_string( $value ))
                    $value = utf8_encode( $value );
            }


            $this->DataMap[ $this->MappingArray[ $attributeIdentifer ] ] = $value;

            return true;
        }
        else
        {
            return false;
        }
    }

    /*
        $dataArray = array( 'name' => ..., 'identifier' => value, 'remote_id' = 'eindeutige nr' )

        versucht ein ganzes array von daten zu setzen
    */
    function setDataByArray( $dataArray, $remoteIdArrayKey = 'remote_id', $convertToUft8 = false, $htmlEntityDecode = false )
    {
        $successArr = array();
        $errorArr = array();

        $remote_id = 'not set';

        if( $remoteIdArrayKey != false )
        {
            if( array_key_exists( $remoteIdArrayKey, $dataArray ))
            {
                $remote_id = $dataArray[ $remoteIdArrayKey ];
                $this->RemoteId = $remote_id;
            }

        }

        foreach ( $dataArray as $identifier => $value )
        {
            $result = $this->setClassAttribute( $identifier, $value, $convertToUft8, $htmlEntityDecode );
            if( $result )
                $successArr[] = $identifier;
            else
                $errorArr[] = $identifier;

        }

        return array( 'error' => $errorArr, 'success' => $successArr, 'remote_id' => $remote_id );

    }


    function attribute( $name )
    {
        switch ( $name )
        {
            case 'data_map':
            {
                return $this->DataMap;
            }break;
            case 'class_object':
            {
                return $this->ClassObject;
            }break;
            case 'xml':
            {
                return $this->createImportXML();

            }break;
            default:
                return false;
        }
    }

    function attributes()
    {
        return array( 'data_map', 'class_object', 'xml');

    }

    function createImportXML( )
    {

        $definitionArray = array( 'options' => array( 'remote_id' => $this->RemoteId,
                                           'class_identifier' => $this->ClassIdentifer ),
                                            'attributes' => $this->DataMap
                                                );


        $xml = ExportXml::dataSetToImportXML( $definitionArray );

        return $xml;
    }

    /*
        erstellt einen Ordner mit einer definition.xml
    */
    function createFolderWithXmlDefinition ( $filePath = 'var/importxml/test/' , $direNameKey = 'remote_id', $filename = 'definition.xml' )
    {
         $xml = $this->createImportXML();


         $dirName =  $this->ClassIdentifer.'#'.microtime();

         if( $direNameKey != false )
         {
             switch ( $direNameKey )
             {
                 case 'remote_id':
                 {
                     if( $this->RemoteId != null )
                     {
                         $dirName = $this->ClassIdentifer.'#'.$this->RemoteId;
                     }
                 }
             }
         }

         $dir = $filePath . '/' . $dirName;

         $result = eZFile::create ( $filename, $dir, $xml );

         return $result;
    }


    var $ClassIdentifer;
    var $RemoteId;

    var $convertToUft8 = false;
    var $ClassObject;
    var $MappingArray;
    var $DataMap;

}

?>
<?php
// start import Framework
// -----------------------------------------------------
include_once( 'lib/ezutils/classes/ezextension.php' );
ext_class( 'import' , 'ezimportframework' );

/*
importiert nach folgender logik

dir1
+ #dir2
+ #dir3
dir4
+ r#dir5
definition.xml

1. alle dirs mit # werden zuerst importiert bei normaler ordnerrekursion
2. wenn in einem ordner r#dirs liegen werden diese vor dem eigentlichen import
der definition.xml importiert in einen dummynode und nach import in den gerade angelegten knoten
verschoben ( r#dirs wird benötigt bei eingebetten bildern in xml, die unter dem eigentlichen objekt liegen sollen )

*/


class ImportXML
{

    var $ImportFrameWork = null;
    var $Dir = null;
    var $DestinationNodeId = null;
    var $ImportArray = null;
    var $UserId = null;
    var $ImportMethod = null;
    var $LogDir = '';
    var $CsvFileName = '';
    var $MyKeyConverter;
    var $Namespaces;
    var $CliObject;
    var $ImportCounter = 0;

    function ImportXML( $dir, $destinationNodeId , $cliObject = null )
    {
        $this->ImportFrameWork =& eZImportFramework::instance( 'xml' );
        $this->MyKeyConverter =& eZKeyConverter::instance();
        $this->Namespaces =& $this->ImportFrameWork->namespaces;

        $this->CliObject = $cliObject;

        $this->Dir = $dir;
        $this->DestinationNodeId = $destinationNodeId;
        $this->ImportArray = array();
        //$this->ImportMethod = EZ_IMPORT_METHOD_ALWAYS_CREATE;
        $this->ImportMethod = EZ_IMPORT_METHOD_AUTO;

        $time = strftime( "%Y%m%d_%H%M%S", strtotime( "now" ) );
        $this->CsvFileName = 'importxml_'.$time.'.csv';

        $this->writeCsvHead();
    }

    /*
        return false if the script is not running in cli mode
        otherwise the cli object
    */
    function runInCliMode()
    {
        if( is_object( $this->CliObject) )
            return $this->CliObject;
        else
            return false;
    }

    function getImportFrameWork()
    {
        return $this->ImportFrameWork;
    }

    function getImportMethod()
    {
        return $this->ImportMethod;
    }

    function getImportArray()
    {
        return $this->ImportArray;
    }

    function getUserIdByName( $userName = 'admin')
    {

        if( $this->UserId == null )
        {
            $user = eZUser::fetchByName($userName);
            $this->UserId = $user->attribute( 'contentobject_id' );
        }

        return $this->UserId;
    }

    function setImportedDataSetInfo( $contentObjectArr, $parentNodeId )
    {

        foreach( $contentObjectArr as $contentObject )
        {

            if( is_object($contentObject) )
            {

              // eine db abfrage pro contentobject mehr
              $mainNode = $contentObject->attribute('main_node');
              $pathString = $mainNode->attribute('path_string');
              $realParentNodeId = $mainNode->attribute('parent_node_id');


              $infoArray = array(
                                    'contentobject_id' => $contentObject->attribute('id'),
                                    'remote_id' => $contentObject->attribute('remote_id'),
                                    'main_node_id' => $contentObject->attribute('main_node_id'),
                                    'version' => $mainNode->attribute('contentobject_version'),
                                    'parent_node_id' => $realParentNodeId,
                                    'path_string' => $pathString,

                                    //'class_identifier' => $contentObject->attribute('content_class'),
                                    //'owner' => $contentObject->attribute('owner'),
                                    'name' => $contentObject->attribute('name'),
                                    'errors' => array() );

                $this->ImportArray[ $infoArray['main_node_id'] ] = $infoArray['remote_id'];




                $message = "[import object] |o| " .$infoArray['contentobject_id']. " |v| ". $infoArray['version']. " |n| ".$infoArray['main_node_id'] . " |pn| ".$realParentNodeId. " |PathString| ".$pathString." |r| ".$infoArray['remote_id']." |name| ".$infoArray['name'];
                $this->log( $message );

                return $infoArray;

            }
            else
            {
                $message = "ERROR - kein contentobject als ergebnis";
                $this->log( $message );

                return false;
            }
        }
    }

    function import()
    {

       $message = "\n========================\nSTART - neuer Import to NodeID (".$this->DestinationNodeId.")\nFolder (".$this->Dir.')';
       $message .= "\n=======================";
       $this->log( $message );

       $startTime = time();

       $result = $this->importStructure( $this->Dir, $this->DestinationNodeId );

       $endTime = time();
       $processingTime = $endTime - $startTime;

       $count = count( $this->getImportArray() );

       $message = "Anzahl = $count";
       $persecond = $count / $processingTime;


       $message .= " | Zeit: ". $processingTime ." s | ".round( ($processingTime/60), 4) ." min";
       $message .= "\nImportierte Objekte pro Sekunde = ".$persecond;
       $message .= "\nENDE";


       $this->log( $message );
       $this->ImportFrameWork->log( $message );

       $this->writeCsvFooter( $message );

       return $this->getImportArray();
    }

    function setProccessingStatusToFile( )
    {
       include_once( 'lib/ezfile/classes/ezfile.php' );
       $directory='var/';
       $filename = 'importxml_processing.txt';
       $file = $directory.$filename;

        // flag in dateischreiben um process abbrechen zu können
        eZFile::create( $filename, $directory, 1 );
    }

    /*
        Rekursives importieren der Baumstruktur
    */
    function importStructure( $importFolderPath , $ContainerNodeId )
    {

        // 1. alle unterordner suchen die mit r# anfangen ( beinhalten objekte die dem aktuellen objekt als relation hinzugefügt wurden )
           // alle subdirs finden die nicht versteckt sind und nicht mit  r# anfangen
        $subDirsRelation = eZDir::findSubitems( $importFolderPath, 'd', false, $includeHidden, '/^(?!(r#)).*/' );

        $tmpNodeForRelationObjects = 43;
        $relationImportResultArray = array();

        foreach ( $subDirsRelation as $subDir )
        {
            $relationFile = $importFolderPath.$subDir.'/definition.xml';
            $importResult = $this->importXMLToEzpublish( $relationFile, $tmpNodeForRelationObjects );

            if( is_array( $importResult ) and array_key_exists( 'main_node_id', $importResult ))
            {
                array_push( $relationImportResultArray, $importResult['main_node_id'] );
            }
        }

        // 2. Daten importieren

        $file = $importFolderPath . 'definition.xml';

        //$importResult = importXMLToEzpublish( $file, $importToNodeId );

        $importResult = $this->importXMLToEzpublish( $file, $ContainerNodeId );


        $importedMainNodeId = $importResult['main_node_id'];

        // 3. Objektrelationsobjekte aus r# ordnern aus tmpnode verschieben unter aktuellen node

        // todo
        foreach( $relationImportResultArray as $mainNodeId )
        {

            //$mainNode = eZContentObjectTreeNode::fetch( $mainNodeId );
            //$mainNode->move( $importedMainNodeId );

            // verschiebt $mainNodeId nach $importedMainNodeId
            eZContentObjectTreeNode::move( $importedMainNodeId, $mainNodeId );

        }

        // 4. alle weiteren unterordner rekursiv importieren
        if( is_array($importResult)  )
        {

            // unterordner suchen und funktion rekursiv aufrufen
            $newContainerNodeId = $importResult['main_node_id'];

            // alle subdirs finden die nicht versteckt sind und nicht mit  r# anfangen
            $subDirs = eZDir::findSubdirs( $importFolderPath, false, '/^r#/' );


            // Sortieren damit ordner mit objektrelation '#foldername' zurerst importiert werden
            sort( $subDirs );


            foreach( $subDirs as $subDir )
            {
                 $newImportFolderPath = $importFolderPath.$subDir.'/';

                 // alles importieren ohne die verzeichnisse die mit r# anfangen
                 $newImportResult = $this->importStructure( $newImportFolderPath, $newContainerNodeId );

            }
        }

        return $importResult;
    }


    function importXMLToEzpublish( $xmlFileName, $ContainerNodeId, $hideObjectsIfValidationError = true )
    {

        /*
        // get an Instance of the import Framework with importhandler 'csv' set start log message in import.log
        $if =& eZImportFramework::instance( 'csv' );

        // set all data to $if->datasets
        $if->getData( "extension/import/example.csv" );
        */

        /* get an Instance of the import Framework with importhandler 'default'
          set start log message in import.log
          @see importhandlers/ [handlername]import.php
          you can write your own importhandlser - standard is default
          ====================================================================== */
        // $iframework =& eZImportFramework::instance( 'xml' );
        $iframework =& $this->getImportFrameWork();




        // used for RemoteId  => import:$namespace: $remote_id_old||$remote_id_custom
        // e.g import:TestDefault:lskjfojwe23ewrljfso
        // so you can remove all import objects with the remove.php script
        $namespace = "ImportXML";


        // set all data to $if->datasets
        $iframework->getData( $xmlFileName, $namespace );

      //  $user = eZUser::fetchByName("admin");
      //  $userID = $user->attribute( 'contentobject_id' );

        $userID = $this->getUserIdByName( 'admin' );
        //$class = eZContentClass::fetchByIdentifier( 'article' );


        $options = array(
        					'contentClass' => 'article',
        					EZ_IMPORT_PRESERVED_KEY_OWNER_ID => $userID,
        					'parentNodeID' => $ContainerNodeId
        				);

        // ----------- import all Data	----------------------------------
        $result = $iframework->importData( 'ezcontentobject', $namespace, $options );

        foreach ( $result as $object)
	    {
	        $this->ImportCounter++;
    	    $classId = $object->attribute('contentclass_id');
    	    $class = $object->attribute('content_class');
    	    $classIdentifier = $class->attribute('identifier');
            $mainNodeId = $object->attribute('main_node_id');

    	    // classidentifier wird für keykonverter gentutzt muss immer da sein!

    	   // $classId = $object->attribute('class_name');
    	    $remoteId = $object->attribute('remote_id');
    	    $contentObjectId = $object->attribute('id');
    	    $currentVersionId = $object->attribute('current_version');
    	    $name = $object->attribute('name');

    	    $published = $object->attribute('published');
    	    $modified = $object->attribute('modified');
    	    $ownerId = $object->attribute('owner_id');

    	    $mainParentNodeId = $object->attribute('main_parent_node_id');

    	  //  $namespace = $namespace.':'.$classIdentifier;

    	    $this->registerValuesToKeyConverter( $namespace, $remoteId, (int) $contentObjectId, $classIdentifier);

    	    $action = 'create/new version/do nothing';

    	    $csvLine = "'$action';'$mainParentNodeId';'$mainNodeId';'$classIdentifier';'$contentObjectId';'$currentVersionId';'$remoteId';'$name';'$published';'$modified';'$ownerId'";

    	    if( $this->runInCliMode() )
    	    {
    	       $cli = $this->runInCliMode();
    	       $cli->output( '['.$this->ImportCounter.'.] '.$csvLine );
    	    }

    	    $this->writeToCsv( $csvLine );

    	    	        // wenn object validationerrors hat, dann soll das object versteckt werden
	        $objectHasValidationsErrors = $this->checkIfContentObjectHasValidationErrors( $object );

	        if( $objectHasValidationsErrors != false )
   			{
   				$errorLogMessage .= "[Validation Error] ObjectId | NodeId | AttibuteIdentifier | ErrorMessage";

   				foreach ( $objectHasValidationsErrors as $index => $validationError )
   				{
   					$string = implode("| ", $validationError );
   					$errorLogMessage .= "\n($index) ". $string;

   				}
   				// wenn fehler node verstecken / hide
   				if( $hideObjectsIfValidationError === true )
   				{
   					$artikelNodeObject = eZContentObjectTreeNode::fetchByContentObjectID( $object->attribute('id') );
   					eZContentObjectTreeNode::hideSubTree( $artikelNodeObject[0] );
  					$errorLogMessage .= "\n[HIDE Node] NodeId= ".$artikelNodeObject[0]->attribute('node_id')." url_alias: ".$artikelNodeObject[0]->attribute('url_alias');
   				}
   				$this->log( $errorLogMessage , 'error');
   			}
   			else
   			{
//   				$this->log( $logMessage );
				$artikelNodeObject = eZContentObjectTreeNode::fetchByContentObjectID( $object->attribute('id') );

				// wenn object zwar da ist aber keine MainNode hat z.B. im Trasch
				if( $object->attribute('main_node_id') == false )
				{
				    $message = "[TRASH Object] Error can't use object because it is in trash - ObjectId= ". $object->attribute('id') ." name: ". $object->attribute('name');
                    $this->log( "\n".$message , 'error' );
                    return $message;
				}
   				// wenn artikel bereit unhidden => sichtbar machen
   				elseif( $artikelNodeObject[0]->attribute( 'is_invisible' ) )
   				{

   					eZContentObjectTreeNode::unhideSubTree( $artikelNodeObject[0] );

   					$this->log( "\n[UNHIDE Node] NodeId= ".$artikelNodeObject[0]->attribute('node_id')." url_alias: ".$artikelNodeObject[0]->attribute('url_alias') );
   				}

   			}
	    }

        $iframework->freeMem();

     //   $iframework->log("Import Done - this will write stuff to import.log");

        // set stop log message in import.log
       // $iframework->destroy();
        return $this->setImportedDataSetInfo( $result, $ContainerNodeId );
    }

    	/*
		speichert importiere daten zwischen  d.h. zuordnung alter wert => object_id
		die beiden array m�ssen die gleich anzahl von elementen haben

		main_node_id oder object_id
	*/
	function registerValuesToKeyConverter( $namespace, $oldValue , $newValue, $classId = false )
	{

	   $importPraefix = 'ezimport:'.$namespace.':';

	   if( $classId )
	       $namespace .= ':'.$classId;

	    if( $namespace )
        {
            $this->Namespaces[] = $namespace;
            $this->Namespaces = array_unique( $this->Namespaces );
        }

	   // bei remote_id /$oldValue    ezimport:ImportXML  wegnehmen
	   // damit später die remote_id aus den xml files direkt zum suchen genutzt werden kann ( da fehlt ja ezimport:ImportXML)

       $beginsWith = $this->stringBeginsWith( $oldValue, $importPraefix);
       if( $beginsWith == true )
            $oldValue = substr( $oldValue, strlen($importPraefix));
       $this->ImportFrameWork->log("[keykonverter create] $namespace | $oldValue =>  $newValue ");

	   $result = $this->MyKeyConverter->register( $namespace , $oldValue, $newValue );

	 /*   if( $result == false )
	    {
	        $this->log("registerValuesToKeyConverter: [$namespace] Kein Object Fuer index => $index",true,true,true);
	    }
*/
		//$this->log("registerValuesToKeyConverter: [$namespace] Kein Object Fuer index => $index",true,true,true);
	}


	# does $a begin with $b?
    function stringBeginsWith($a, $b)
    {
        return (substr($a, 0, strlen($b)) == $b);
    }

    function log( $message, $level = 'normal')
    {

        if( $this->LogDir == '')
        {
            $siteINI =& eZINI::instance( 'site.ini' );
            $varDir = $siteINI->variable('FileSettings','VarDir');
            $this->LogDir = $varDir.'/'.$siteINI->variable( 'FileSettings', 'LogDir' );
        }

        $cliObject = $this->runInCliMode();

        // errors in extra logfile schreiben
        if( $level == 'error' )
        {
            if( $cliObject != false )
            {
                $cliObject->error( $message );
            }
            eZLog::write( $message, 'jacimportxml_error.log', $this->LogDir );
        }
        else
        {
            eZLog::write( $message, 'jacimportxml.log', $this->LogDir );
        }
    }

    /*
        schreibt daten in die aktuelle csv datei des imorts
    */
    function writeToCsv( $data, $headline = false )
    {
        $csvLine = '';
        if( is_array($data) )
            $csvLine .= implode(';', $data );
        else
            $csvLine .= $data;

        $this->writeToImportXml( $csvLine, $this->CsvFileName, 'csv', $headline );
    }

    function writeCsvHead( )
    {


        $csvLine .= "'action';'main_parent_node_id';'main_node_id';'class_identifier';'contentobject_id';'current_version_id';'remote_id';'name';'published';'modified';'owner_id'";
        $this->writeToCsv( $csvLine, 'head' );


    }

    function writeCsvFooter( $message )
    {
        $csvLine = "\n'statistic_data';'ignore the following for csv read'\n";
        $csvLine .= "ImportXML to;\n'node_id';\n". $this->DestinationNodeId. ";\n'from dir';\n". $this->Dir ."\n";
        $csvLine .= $message;
        $this->writeToCsv( $csvLine, 'footer' );
    }

     /*!
      \public
      Writes a message $message to a given file name $name and directory $dir for logging
     */
     function writeToImportXml( $message, $file = 'importxml.csv', $dirName = 'csv', $headline = false )
     {
         $ini =& eZINI::instance();
         $varDir = $ini->variable( 'FileSettings', 'VarDir' );

         $jacImportXmlIni =& eZINI::instance('jacimportxml.ini' );
         $importExportDir = $jacImportXmlIni->variable('JacImportXmlSettings','DirNameForImportExport');

         $dir = $varDir .'/'.$importExportDir.'/importxml_'.$dirName;


         $fileName = $dir . '/' . $file;
         if ( !file_exists( $dir ) )
         {
             include_once( 'lib/ezfile/classes/ezdir.php' );
             eZDir::mkdir( $dir, 0777, true );
         }
         $oldumask = @umask( 0 );


         $logFile = @fopen( $fileName, "a" );
         if ( $logFile )
         {
             if( $headline == false )
             {
                $time = strftime( "%Y%m%d_%H:%M:%S", strtotime( "now" ) );
                $logMessage = "'$time';$message\n";
             }
             else
             {
                if( $headline == 'head')
                    $logMessage = "'time';$message\n";
                else
                    $logMessage = $message;
             }
             @fwrite( $logFile, $logMessage );
             @fclose( $logFile );
             @umask( $oldumask );
         }
     }


    /*
		überprüft ob das ContentObject Validation errors enthält
		Wenn ja gib array zur�ck ( contenobject_id, main_node_id, attribute_identifier, validation_error )
	*/
	function checkIfContentObjectHasValidationErrors( $contentObject )
	{
	    $errorArray = array ();

	    if( is_object( $contentObject ) )
	    {
    		$dataMap = $contentObject->dataMap();

    		foreach( $dataMap as $attributeItentifier => $contentObjectAttribute )
    		{
    			if( $contentObjectAttribute->hasValidationError() )
    			{
    				$attributeArray = array( 'contentobject_id' => $contentObject->attribute('id'),
    										 'main_node_id' => $contentObject->attribute('main_node_id'),
    										 'attribute_identifier' =>  $attributeItentifier ,
    										 'validation_error' => $contentObjectAttribute->validationError() );
    				array_push($errorArray, $attributeArray);
    			}
    		}
	    }

		if( count( $errorArray ) > 0 )
			return $errorArray;
		else
			return false;
	}
}
?>

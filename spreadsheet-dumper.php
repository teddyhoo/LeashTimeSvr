<? //spreadsheet-dumper.php
// use PHPExcel (for now) to dump a spreadsheet
// upgrade to whatever it was after we upgrade the server

function newSpreadsheetDumper($workbookName, $firstSheetName) {
	if(true) return new SpreadsheetDumperPHPExcel($workbookName, $firstSheetName); // change when switch is made to PHExcel successor
}

interface SpreadsheetDumperFns
{
    /**
     *  Save PHPExcel to file
     *
     *  @param   string       $pFilename  Name of the file to save
     *  @throws  PHPExcel_Writer_Exception
     */
    public function dumpToStandardOutput($format, $filename, $contentType);
 
    public function setSpreadsheetName($name);

    public function addASheet($name);

    public function addARow($row, $rowNumber, $sheet=null);
}



abstract class SpreadsheetDumper implements SpreadsheetDumperFns {

    protected $spreadsheetObject;

    /**
     * Set parent IWriter object
     *
     * @param PHPExcel_Writer_IWriter    $pWriter
     * @throws PHPExcel_Writer_Exception
     */
    public function setSpreadsheetObject($ssobj)
    {
        $this->spreadsheetObject = $ssobj;
    }
    
    public function setSpreadsheetName($name)
    {
    }

    public function addASheet($name) // add a sheet and make it active
    {
    }

	 public function setActiveSheetIndex($index) {}
	 
   public function addARow($row, $columns=null, $rowNumber=null, $sheet=null)
    {
    }
    
	function columnLabel($c) {
			$c = intval($c);
			if ($c <= 0) return '';
			$letter = '';
			while($c != 0){
				 $p = ($c - 1) % 26;
				 $c = intval(($c - $p) / 26);
				 $letter = chr(65 + $p) . $letter;
			}
			return $letter;
	}
	
	function dumpToStandardOutput($format, $filename, $cotentType) {
	}
}

class SpreadsheetDumperPHPExcel extends SpreadsheetDumper {
///** Include PHPExcel */
//require_once dirname(__FILE__) . '/../Classes/PHPExcel.php';

		private $rowCount = 0;

    public function __construct($workbookName, $firstSheetName=null)
    {
				require_once 'PHPExcel-1.8/Classes/PHPExcel.php';
				// Create new PHPExcel object
				$objPHPExcel = new PHPExcel();

				// Set document properties
				$objPHPExcel->getProperties()->setCreator("LeashTime")
											 ->setLastModifiedBy("LeashTime")
											 ->setTitle($workbookName)
											 ->setSubject($workbookName)
											 //->setDescription("Test document for PHPExcel, generated using PHP classes.")
											 //->setKeywords("office PHPExcel php")
											 //->setCategory("Test result file")
				;
				$this->setSpreadsheetObject($objPHPExcel);


				$firstSheetName = $firstSheetName ? $firstSheetName : $workbookName;
				$this->setActiveWorksheetName($firstSheetName);
    }

    public function addASheet($name) // add a sheet and make it active
    {
				$newSheet = new PHPExcel_Worksheet($spreadsheetObject);
				$newSheet->setTitle($name, $updateFormulaCellReferences = true);
				$this->spreadsheetObject->addSheet($newSheet, $iSheetIndex = null);
				$this->spreadsheetObject->setActiveSheetIndex($this->spreadsheetObject->getActiveSheetIndex()+1);
				$this->rowCount = 0;
    }
    
    public function setActiveSheetIndex($index) {
			$this->spreadsheetObject->setActiveSheetIndex($index);
		}
    
    public function setActiveWorksheetName($name) {
			$this->spreadsheetObject->getActiveSheet()->setTitle($name, $updateFormulaCellReferences = true);
		}
		
    public function addARow($row, $columnKeys=null, $rowNumber=null, $sheet=null)
    {
			$this->rowCount = $this->rowCount + 1;
			$rowNumber = $rowNumber ? $rowNumber : $this->rowCount;
			$i = 0;
			$keys = $columnKeys ? $columnKeys : array_keys($row);
			foreach($keys as $key) {
				$i += 1;
				if($row[$key]) {
					$colID = $this->columnLabel($i);
//echo print_r("{$colID}{$rowNumber}:  {$row[$key]}", 1).", ";
//echo print_r($this->spreadsheetObject->getActiveSheet()->getTitle().'<hr>',1);
					
					$this->spreadsheetObject->getActiveSheet()->setCellValue("{$colID}{$rowNumber}", $row[$key]);
				}
			}
    }
    
	function dumpToStandardOutput($format, $filename, $contentType=null) {
		$formatLookup = array(
			'OpenDoc'=>'OpenDocument',
			'OpenDocument'=>'OpenDocument',
			'Excel'=>'Excel2007',
			'Excel2007'=>'Excel2007');
		$format = $formatLookup[$format];
		$contentTypes = array(
			'OpenDocument'=>'',
			'Excel2007'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		if(!$contentType) $contentType = $contentTypes[$format];
		
		header("Cache-Control: no-store, no-cache");
		header("Pragma:");
		header("Content-Type: $contentType");
		//header("Content-Type: application/vnd.ms-excel");
		header("Content-Disposition: attachment; filename=$filename ");
		$objWriter = PHPExcel_IOFactory::createWriter($this->spreadsheetObject, $format); // OpenDocument, 'Excel2007'
		$objWriter->save("php://output");
	}

}



	
	/*$objPHPExcel->getProperties()->setCreator("Maarten Balliauw")
								 ->setLastModifiedBy("Maarten Balliauw")
								 ->setTitle("PHPExcel Test Document")
								 ->setSubject("PHPExcel Test Document")
								 ->setDescription("Test document for PHPExcel, generated using PHP classes.")
								 ->setKeywords("office PHPExcel php")
								 ->setCategory("Test result file");
								 
	 $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'Hello')
            ->setCellValue('B2', 'world!')
            ->setCellValue('C1', 'Hello')
            ->setCellValue('D2', 'world!');
            
   $objPHPExcel->setActiveSheetIndex(0)
	             ->setCellValue('A4', 'Miscellaneous glyphs')
	             ->setCellValue('A5', 'éàèùâêîôûëïüÿäöüç');
	 
	 
	 $objPHPExcel->getActiveSheet()->setCellValue('A8',"Hello\nWorld");
	 $objPHPExcel->getActiveSheet()->getRowDimension(8)->setRowHeight(-1);
	 $objPHPExcel->getActiveSheet()->getStyle('A8')->getAlignment()->setWrapText(true);

		*/


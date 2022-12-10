<? //spreadsheet-reader.php
// use PHPExcel (for now) to read a spreadsheet
// upgrade to whatever it was after we upgrade the server

function newSpreadsheetReader($workbookName, $lineByLine=false) {
	if(true) return new SpreadsheetReaderPHPExcel($workbookName, $lineByLine); // change when switch is made to PHExcel successor
}

interface SpreadsheetReaderFns
{
    /**
     *  Save PHPExcel to file
     *
     *  @param   string       $pFilename  Name of the file to save
     *  @throws  PHPExcel_Writer_Exception
     */
    public function getActiveSheetAsArray();
 
    public function getActiveSheetName();

    public function getSheetNames();

    public function setActiveSheetNamed($name);

    public function setActiveSheetNumber($num);

    public function setNextSheetActive();

}



abstract class SpreadsheetReader implements SpreadsheetReaderFns {

    protected $spreadsheetObject;
    protected $spreadsheetReader;

    /**
     * Set parent IWriter object
     *
     * @param PHPExcel_Writer_IWriter    $pWriter
     * @throws PHPExcel_Writer_Exception
     */
	 public function setSpreadsheetObject($ssobj) {
        $this->spreadsheetObject = $ssobj;
    }
    
   public function getActiveSheetAsArray() {
		}
 
    public function getActiveSheetName() {
		}

    public function getSheetNames() {
		}

    public function getSheetCount() {
		}

    public function setActiveSheetNamed($name) {
		}

    public function setActiveSheetNumber($num) {
		}

    public function setNextSheetActive() {
		}
}

class SpreadsheetReaderPHPExcel extends SpreadsheetReader {
///** Include PHPExcel */
//require_once dirname(__FILE__) . '/../Classes/PHPExcel.php';

    protected $currentRow = 0;
    protected $workbookName;


    public function __construct($workbookName, $lineByLine=false)
    {
				require_once 'PHPExcel-1.8/Classes/PHPExcel.php';
				set_include_path(get_include_path() . PATH_SEPARATOR . 'PHPExcel-1.8/Classes/');
				include 'PHPExcel/IOFactory.php';
				
				if($lineByLine) {
					set_include_path(get_include_path() . PATH_SEPARATOR . 'PHPExcel-1.8/Classes/Reader');
					//include 'PHPExcel-1.8/Classes/Reader/Reader.php';
					$reader = PHPExcel_IOFactory::createReaderForFile($workbookName);
					$reader->setReadFilter(new rowReadFilter());
					$this->spreadsheetReader = $reader;
					$this->workbookName = $workbookName;
				}
				
				else {
				// Create new PHPExcel object
					$objPHPExcel = PHPExcel_IOFactory::load($workbookName);
					$this->setSpreadsheetObject($objPHPExcel);
				}
    }


    public function readRowAsArray() {
			$row = $this->getCurrentRowAsArray();
			$this->currentRow += 1;
			return $row;
		}
		
		public function setCurrentRow($num) {
			$this->currentRow = $num;
		}
 
    public function getCurrentRowAsArray() {
			$objPHPExcel = $this->spreadsheetReader->load($this->workbookName);
			return $objPHPExcel->getActiveSheet()->toArray(null,true,true,true);
		}
 
    public function getActiveSheetAsArray() {
			$this->setCurrentRow(0);
			if($this->spreadsheetReader) {
				$rows = array();
				while($row = $this->readRowAsArray())
					$rows[] = $row;
print_r($row);exit;					
//if($this->currentRow == 5) return $rows;					
				return $rows;
			}
			else return $this->spreadsheetObject->getActiveSheet()->toArray(null,true,true,true);
		}
 
    public function getActiveSheetName() {
			return $this->spreadsheetObject->getActiveSheet()->getName();
		}

    public function getSheetNames() {
			return $this->spreadsheetObject->getSheetNames();
		}

    public function getSheetCount() {
			return $this->spreadsheetObject->getSheetCount();
		}

    public function setActiveSheetNamed($name) {
		}

    public function setActiveSheetNumber($num) {
		}

    public function setNextSheetActive() {
		}

}

require_once 'PHPExcel-1.8/Classes/PHPExcel/Reader/IReadFilter.php';
class rowReadFilter implements PHPExcel_Reader_IReadFilter
{
	private $_targetRow = 0;

	/**  Set the list of rows that we want to read  */
	public function setRow($startRow) {
		$this->_targetRow	= $startRow;
	}

	public function readCell($column, $row, $worksheetName = '') {
		//  Only read the heading row, and the rows that are configured in $this->_targetRow
		return $row == $this->_targetRow;
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


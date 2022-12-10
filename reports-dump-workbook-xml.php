<? // reports-dump-workbook-xml.php

// assumes $workbook is defined
// workbook structure:
//

require_once "common/init_session.php";
//require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "export-fns.php";

set_time_limit(300);
$t0 = time();

$locked = locked('o-');

$exportBillingFlagsGlobal = $_SESSION['preferences']['betaBillingEnabled'];

dumpWorkbook();

function dumpWorksheet($sheet) {
	if($sheet['generator']) call_user_func($sheet['generator']);
	else {
		startWorksheet($sheet);
		foreach($sheet['rows'] as $row) {
			dumpRow($row);
		}
		endWorksheet($sheet);
	}
}

function dumpRow($row) {
	startRow($row);
	foreach((array)$row as $cell) {
		startCell($cell);
		dumpCellContent($cell);
		endCell($cell);
	}
	endRow($row);
}

function dumpCellContent($cell) {
	if($cell && !is_array($cell)) echo "<text:p><![CDATA[{$cell}]]></text:p>";
	else if($cell['type'] == 'XYZ')  ;
	else if($cell['content']) echo "<text:p>{$cell['content']}</text:p>";
}

function startCell($cell) {
	echo " <table:table-cell office:value-type=\"string\" calcext:value-type=\"string\">";
}

function endCell($cell) {
	echo "</table:table-cell>";
}

function startRow($row) {
	echo "<table:table-row>";
}

function endRow($row) {
	echo "</table:table-row>";
}

function startWorksheet($sheet) {
	echo "<table:table table:name=\"{$sheet['name']}\">";
}

function endWorksheet($sheet) {
	echo "</table:table>";
}


function dumpWorkbook() {
	global $workbook;
$TEST = FALSE; // mattOnlyTEST(); //
	global $exportBillingFlagsGlobal;
if(!$TEST) {
	header("Cache-Control: no-store, no-cache");
	header("Pragma:");
	//header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
	//header("Content-Type: application/vnd.ms-excel");
	header("Content-Type: application/vnd.oasis.opendocument.spreadsheet");
	header("Content-Disposition: attachment; filename={$workbook['name']} ");
	echo '<?xml version="1.0" encoding="UTF-8"?>

<office:document xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" xmlns:number="urn:oasis:names:tc:opendocument:xmlns:datastyle:1.0" xmlns:presentation="urn:oasis:names:tc:opendocument:xmlns:presentation:1.0" xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0" xmlns:chart="urn:oasis:names:tc:opendocument:xmlns:chart:1.0" xmlns:dr3d="urn:oasis:names:tc:opendocument:xmlns:dr3d:1.0" xmlns:math="http://www.w3.org/1998/Math/MathML" xmlns:form="urn:oasis:names:tc:opendocument:xmlns:form:1.0" xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0" xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0" xmlns:ooo="http://openoffice.org/2004/office" xmlns:ooow="http://openoffice.org/2004/writer" xmlns:oooc="http://openoffice.org/2004/calc" xmlns:dom="http://www.w3.org/2001/xml-events" xmlns:xforms="http://www.w3.org/2002/xforms" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:rpt="http://openoffice.org/2005/report" xmlns:of="urn:oasis:names:tc:opendocument:xmlns:of:1.2" xmlns:xhtml="http://www.w3.org/1999/xhtml" xmlns:grddl="http://www.w3.org/2003/g/data-view#" xmlns:tableooo="http://openoffice.org/2009/table" xmlns:drawooo="http://openoffice.org/2010/draw" xmlns:calcext="urn:org:documentfoundation:names:experimental:calc:xmlns:calcext:1.0" xmlns:loext="urn:org:documentfoundation:names:experimental:office:xmlns:loext:1.0" xmlns:field="urn:openoffice:names:experimental:ooo-ms-interop:xmlns:field:1.0" xmlns:formx="urn:openoffice:names:experimental:ooxml-odf-interop:xmlns:form:1.0" xmlns:css3t="http://www.w3.org/TR/css3-text/" office:version="1.2" office:mimetype="application/vnd.oasis.opendocument.spreadsheet">';

	echo ' <office:body>
  <office:spreadsheet>
';}
	foreach($workbook['sheets'] as $sheet) {
		dumpWorksheet($sheet);
	}
	
	echo '
  </office:spreadsheet>
 </office:body>
</office:document>';
}
	
	

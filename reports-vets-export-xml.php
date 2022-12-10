<? // reports-vets-export-xml.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";
require_once "js-gui-fns.php";
require_once "export-fns.php";

$locked = locked('o-');


dumpWorkbook();


/*
  `clinicid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `clinicname` varchar(45) NOT NULL DEFAULT '',
  `lname` varchar(45) DEFAULT NULL COMMENT 'for sole practitioner',
  `fname` varchar(45) DEFAULT NULL COMMENT 'for sole practitioner',
  `street1` varchar(45) DEFAULT NULL,
  `street2` varchar(45) DEFAULT NULL,
  `city` varchar(45) DEFAULT NULL,
  `state` varchar(45) DEFAULT NULL,
  `zip` varchar(45) DEFAULT NULL,
  `fax` varchar(45) DEFAULT NULL,
  `officephone` varchar(20) DEFAULT NULL,
  `cellphone` varchar(20) DEFAULT NULL,
  `homephone` varchar(20) DEFAULT NULL COMMENT 'for sole practitioner',
  `notes` text,
  `afterhours` text,
  `directions` text,
  `pager` varchar(20) DEFAULT NULL,
  `email` varchar(60) DEFAULT NULL,
  `solepractitioner` tinyint(1) NOT NULL DEFAULT '0',
  
  
  
    `vetid` int(10) unsigned NOT NULL AUTO_INCREMENT,
	  `lname` varchar(45) DEFAULT NULL,
	  `fname` varchar(45) DEFAULT NULL,
	  `clinicptr` varchar(45) DEFAULT NULL,
	  `street1` varchar(45) DEFAULT NULL,
	  `street2` varchar(45) DEFAULT NULL,
	  `city` varchar(45) DEFAULT NULL,
	  `state` varchar(45) DEFAULT NULL,
	  `zip` varchar(45) DEFAULT NULL,
	  `fax` varchar(45) DEFAULT NULL,
	  `officephone` varchar(20) DEFAULT NULL,
	  `cellphone` varchar(20) DEFAULT NULL,
	  `homephone` varchar(20) DEFAULT NULL,
	  `notes` text,
	  `afterhours` text,
	  `pager` varchar(20) DEFAULT NULL,
	  `email` varchar(60) DEFAULT NULL,
  `directions` text,
*/

function dumpWorkbook() {
	header("Cache-Control: no-store, no-cache");
	header("Pragma:");
	//header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
if(!$_REQUEST['debug']) {
	header("Content-Type: application/vnd.ms-excel");
	header("Content-Disposition: attachment; filename=ClinicsAndVets.xls ");
}
	echo '<?xml version="1.0"?><ss:Workbook xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
	$name = 'Veterinary Clinics';
	$clinics = fetchAssociations("SELECT * FROM tblclinic");
	$columns = explodePairsLine(
		'clinicid|ID||clinicname|Name||street1|Street||street2|Street2||city|City||state|State||zip|ZIP||'
		.'officephone|Office Phone||cellphone|Cell phone||fax|FAX||homephone|Home phone||pager|Pager||email|Email||'
		.'notes|Notes||afterhours|After hours||directions|Directions');
//if(mattOnlyTEST())	{echo print_r($rows, 1)."\n\n";exit;	}
	dumpWorksheet($name, array_keys($columns), $clinics, $useColumnsInRows=true);
	
	$name = 'Veterinarians';
	$vets = fetchAssociations("SELECT v.*, clinicname FROM tblvet v LEFT JOIN tblclinic ON clinicid = clinicptr");
	$columns = explodePairsLine(
		'vetid|ID||fname|First Name||lname|LastName||clinicname|Clinic||clinicptr|Clinic ID||street1|Street||street2|Street2||city|City||state|State||zip|ZIP||'
		.'officephone|Office Phone||cellphone|Cell phone||fax|FAX||homephone|Home phone||pager|Pager||email|Email||'
		.'notes|Notes||afterhours|After hours||directions|Directions');
//if(mattOnlyTEST())	{echo print_r($rows, 1)."\n\n";exit;	}
	dumpWorksheet($name, array_keys($columns), $vets, $useColumnsInRows=true);
	
	
	echo '</ss:Workbook>';
}
	
	
function dumpWorksheet($name, $columns, $rows, $useColumnsInRows=false) {
	echo '<ss:Worksheet ss:Name="'.$name.'"><ss:Table>';
	dumpRow(array_map('htmlentities', $columns));
	foreach($rows as $row) {
//echo "ROW: ".print_r($row,1)."\n";	
		dumpRow($row, ($useColumnsInRows ? $columns : null));
	}
	echo '</ss:Table></ss:Worksheet>';
}

function dumpRow($row, $columns=null) {
	echo '<ss:Row>';
	$columns = $columns ? $columns : array_keys($row);
	foreach($columns as $col) dumpCell($row[$col]);
	echo '</ss:Row>';
}

function dumpCell($val) {
	echo '<ss:Cell><ss:Data ss:Type="String">'.$val.'</ss:Data></ss:Cell>';
}
	

<?
// print-labels.php

require_once "common/init_session.php";
require_once "class.ezpdf.php";
require_once "key-fns.php";



function inches($inches) { return $inches*72; }
function cm($inches) { return $inches*28.34; }

function labelPage($keys) {  // Avery 5160
  $pdf =& new Cezpdf( 'letter' , 'portrait' );
  $margins = array(inches(0.6), inches(0.5), inches(0.75), inches(0.75)); //ezSetMargins($top,$bottom,$left,$right);
  $pdf->ezSetMargins($margins[0], $margins[1], $margins[2], $margins[3]);
  $pdf->selectFont( './fonts/Helvetica.afm' );
  $labelWidth = inches(2.657);
  $labelHeight = inches(1.0);
  $row = 0;
  $data = array_chunk($keys, 3);
  foreach($data as $vals) {
    if($row == 10) {
      $pdf->ezNewPage();
      $row = 0;
    }
    foreach($vals as $col => $key) {
      $pdf->y = $pdf->ez['pageHeight']-$margins[0]-$row*$labelHeight;
      $pdf->ezSetMargins($margins[0]+$row*$labelHeight, $margins[1], $margins[2]+$col*$labelWidth, $margins[3]);
      $y = $pdf->y;
      if(isset($key['bin'])) $pdf->ezText('Bin: '.$key['bin'] , 12 );
      label($key, $pdf, $pdf->y, $margins[2]+$col*$labelWidth);
      $pdf->y = $y;
      if(isset($key['bin'])) $pdf->ezText('Bin: '.$key['bin'] , 12 );
    }
    $row++;
  }
  //$pdf->ezNewPage();
  $pdf->stream();
}

function dymo30252AddressORIG($key) {
	$pageWidth = cm(9.3);
	$pageHeight = cm(3.1);
  $pdf =& new Cezpdf( array(0,0,$pageWidth,$pageHeight) , 'landscape' );
  $margins = array(cm(0.635), cm(0.0), cm(1.0), cm(1)); //ezSetMargins($top,$bottom,$left,$right);
  $pdf->ezSetMargins(cm(0.635), $margins[1], $margins[2], $margins[3]);
  $pdf->y = $pageHeight - $margins[0];
	$y = $pdf->y;
	if(isset($key['bin'])) $pdf->ezText('Bin: '.$key['bin'] , 14 );
	label($key, $pdf, $pdf->y, $margins[2]+$col*$labelWidth);
	$pdf->y = $y;
	if(isset($key['bin'])) $pdf->ezText('Bin: '.$key['bin'] , 14 );
  $pdf->stream();
}

function dymo30252_PDF() {
	$pageWidth = cm(9.3);
	$pageHeight = cm(3.1);
  $margins = array(cm(0.635), cm(0.0), cm(0.0), cm(0.7874)); //ezSetMargins($top,$bottom,$left,$right);
  $pdf =& new Cezpdf( array(0,0,$pageWidth,$pageHeight) , 'landscape' );
	$pdf->ezSetMargins($margins[0], $margins[1], $margins[2], $margins[3]);
  return $pdf;
}

function dymo30252Address(&$pdf, $key) {
  $pdf->y = $pdf->ez['pageHeight'] - $pdf->ez['topMargin'];
	$y = $pdf->y;
	if(isset($key['bin'])) $pdf->ezText('Bin: '.$key['bin'] , 14 );
	label($key, $pdf, $pdf->y, $pdf->ez['leftMargin']);
	$pdf->y = $y;
	if(isset($key['bin'])) $pdf->ezText('Bin: '.$key['bin'] , 14 );
}

function printStripLabels($pdf, $labelFunction, $keys) {
	foreach($keys as $index => $key) {
		call_user_func_array($labelFunction, array($pdf, $key));
		if($index + 1 < count($keys))	$pdf->ezNewPage();
	}
  $pdf->stream();
}

function printKeyLabels($keys, $type='fullSheetFormat') {
	if($type == 'dymo30252') 
		printStripLabels(dymo30252_PDF(), 'dymo30252Address', $labels);
}

  
	

function label(&$key, &$pdf, $top, $left) {
	//echo "Page Height: ".$pdf->ez['pageHeight']."<br>Top: $top";exit;
	$keyLabel = formattedKeyId($key['keyid'], $key['copyNumber']);
	$pdf->addJpegFromFile(barCodeImageFile($keyLabel) , $left , $top - inches(.75)); //.665
}


/*
define("BCS_BORDER"	    	,    1);
define("BCS_TRANSPARENT"    ,    2);
define("BCS_ALIGN_CENTER"   ,    4);
define("BCS_ALIGN_LEFT"     ,    8);
define("BCS_ALIGN_RIGHT"    ,   16);
define("BCS_IMAGE_JPEG"     ,   32);
define("BCS_IMAGE_PNG"      ,   64);
define("BCS_DRAW_TEXT"      ,  128);
define("BCS_STRETCH_TEXT"   ,  256);
*/
function barCodeImageFile($keyLabel) {
	global $mein_host;
	$barcodeImg = "./barcode_images/$keyLabel.jpg";
	if(!file_exists($barcodeImg)) {
		$style = /*1+*/ 2 + 8 + 32  + 128; //BCS_TRANSPARENT | BCS_ALIGN_LEFT | BCS_IMAGE_JPEG | BCS_DRAW_TEXT
		$width = 120;
		$height = 60;
		$imgUrl = "barcode/image.php?code=$keyLabel&style=$style&type=C128A&width=$width&height=$height&xres=1&font=5&file=.$barcodeImg";
		$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
		$imgUrl = "$mein_host$this_dir/$imgUrl";
		copy($imgUrl,$barcodeImg);
		//if(!@copy($imgUrl,$barcodeImg)) {
			//$errors= error_get_last(); 
			//echo "COPY ERROR: ".$errors['type'];
			//echo "<br />\n".$errors['message'];
		//}
		//else echo "File copied from remote!";
	}
	return $barcodeImg;
}

/** TEST
*/
if($_GET['ptest']) {
Header('Pragma: public');
$labels = array(
	/*array('keyid'=>2,'copyNumber'=>1, 'bin'=>'ast'),
	array('keyid'=>2,'copyNumber'=>2, 'bin'=>'ast'),
	array('keyid'=>2,'copyNumber'=>3, 'bin'=>'ast'),
	array('keyid'=>2,'copyNumber'=>4, 'bin'=>'ast'),
	array('keyid'=>2,'copyNumber'=>5, 'bin'=>'ast'),
	array('keyid'=>3,'copyNumber'=>1, 'bin'=>'rob'),*/
	array('keyid'=>3,'copyNumber'=>2, 'bin'=>'rob'),
	array('keyid'=>3,'copyNumber'=>3, 'bin'=>'rob'),
	array('keyid'=>3,'copyNumber'=>4, 'bin'=>'rob')
	);
//labelPage($labels);
//dymo30252Address(array('keyid'=>2,'copyNumber'=>1, 'bin'=>'ast'));
printStripLabels(dymo30252_PDF(), 'dymo30252Address', $labels);
}
?>
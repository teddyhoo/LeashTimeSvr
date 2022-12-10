<?
// label-print-fns.php

require_once "common/init_session.php";
require_once "class.ezpdf.php";
require_once "key-fns.php";




function getGlobalKeyLabelSpec() {
	$labelFormats = parse_ini_file('key-label-formats.txt', true);
	$chosenLabel = $_SESSION['preferences']['keyLabelSize'];
	foreach($labelFormats as $format) 
		if($format['label'] == $chosenLabel) $chosenFormat = $format;
	reset($labelFormats);
	return $chosenFormat ? $chosenFormat : current($labelFormats);
}

function inches($inches) { return $inches*72; }
function cm($cm) { return $cm*28.34; }

function printKeyLabels($keys, $stockFormat='fullSheetFormat') {
	$spec = getGlobalKeyLabelSpec();
	if($spec['stockFormat']) $stockFormat = $spec['stockFormat'];
	if($stockFormat == 'fullSheetFormat') {
		printPageLabels(fullSheet_PDF(), 'specSheetFormat', $keys, 9, 4, $spec);
		//else printPageLabels(fullSheet_PDF(), 'fullSheetFormat', $keys, 9, 4, $spec);
	}
	else if($stockFormat == 'avery8195SheetFormat') {
		printPageLabels(avery8195Sheet_PDF(), 'specSheetFormat', $keys, 15, 4, $spec);
		//else printPageLabels(fullSheet_PDF(), 'fullSheetFormat', $keys, 9, 4, $spec);
	}
	else if($stockFormat == 'dymo30336') 
		printStripLabelsBySpec(dymo30336_PDF(), 'specSheetFormat', $spec, $keys);
	else if($stockFormat == 'dymo30252') 
		printStripLabels(dymo30252_PDF(), 'dymo30252Address', $keys);
}


/*
Avery 8195 sheet
top: 1.4cm bottom: 1.4cm left: 0.8cm right 0.7cm
height: 1.6cm width: 4.4cm
*/
function avery8195Sheet_PDF() {
	$pageWidth = cm(21.59);
	$pageHeight = cm(27.94);
  $margins = array(cm(1.4), cm(1.4), cm(0.75438), cm(0.75438)); //ezSetMargins($top,$bottom,$left,$right);
  $pdf =& new Cezpdf( array(0,0,$pageWidth,$pageHeight) , 'landscape' );
	$pdf->ezSetMargins($margins[0], $margins[1], $margins[2], $margins[3]);
  return $pdf;
}

function fullSheet_PDF() {
	$pageWidth = cm(21.59);
	$pageHeight = cm(27.94);
  $margins = array(cm(2.5), cm(1.5), cm(1.5), cm(0.7874)); //ezSetMargins($top,$bottom,$left,$right);
  $pdf =& new Cezpdf( array(0,0,$pageWidth,$pageHeight) , 'landscape' );
	$pdf->ezSetMargins($margins[0], $margins[1], $margins[2], $margins[3]);
  return $pdf;
}

function specSheetFormat(&$pdf, $key, $left, $top, $spec=null) {
	// left margin (beyond label) is .75cm
	//if(!$spec) $spec = array('units'=>'cm','borderwidth'=>4.2, 'borderheight'=>2.3, 'fontsize'=>'10,12');
	$originalSpec = $spec;
	if($spec['units'] == 'in') {
		$spec['borderwidth'] = 2.54 * $spec['borderwidth'];
		$spec['borderheight'] = 2.54 * $spec['borderheight'];
		$spec['leftpadding'] = 2.54 * $spec['leftpadding'];
	}
	$fonts = explode(',', $spec['fontsize']);
	$pdf->y = $top;
	$fontSize = $key['bin'] && (strlen($key['bin']) >= 12) ? $fonts[0] : $fonts[1];
	$prefix = strlen($key['bin']) >= 12 ? ' ' : ' Hook: ';
	$binLabel = trim($prefix.$key['bin']) ? $prefix.$key['bin'] : '*';
	$labelTop = $top; //!$_SESSION['staffuser'] ? $top : $top - cm(.1);
	$pdf->y = $labelTop;
//print_r($spec);exit;	
	$leftpadding = cm($spec['leftpadding'] ? $spec['leftpadding'] : 0);
	if($binLabel) $pdf->ezText($binLabel , $fontSize, array('left'=>$leftpadding));
	label($key, $pdf, $pdf->y, $left+$leftpadding, $originalSpec, $fontSize);
	$pdf->y = $labelTop;
	if($binLabel) $pdf->ezText($binLabel , $fontSize, array('left'=>$leftpadding));
	
	$bottom = $top-cm($spec['borderheight']);
	$pdf->line($left+cm(0.0) , $top, $left+cm(0.0) , $top-cm(.15) );
	$pdf->line($left+cm(0.0) , $top, $left+cm(0.15) , $top );

	$pdf->line($left+cm(0.0) , $bottom, $left+cm(0.0) , $bottom+cm(0.15) );
	$pdf->line($left+cm(0.0) , $bottom, $left+cm(0.15) , $bottom );
	
	$pdf->line($left+cm($spec['borderwidth']) , $top, $left+cm($spec['borderwidth']) , $top-cm(.15) );
	$pdf->line($left+cm($spec['borderwidth']) , $top, $left+cm($spec['borderwidth']-0.15) , $top );
	
	$pdf->line($left+cm($spec['borderwidth']) , $bottom, $left+cm($spec['borderwidth']) , $bottom+cm(.15) );
	$pdf->line($left+cm($spec['borderwidth']) , $bottom, $left+cm($spec['borderwidth']-0.15) , $bottom );

}

function printStripLabels($pdf, $labelFunction, $keys) {
	foreach($keys as $index => $key) {
		call_user_func_array($labelFunction, array(&$pdf, $key));
		if($index + 1 < count($keys))	$pdf->ezNewPage();
	}
  $pdf->stream();
}

function printStripLabelsBySpec($pdf, $labelFunction, $spec, $keys) {
	foreach($keys as $index => $key) {
		call_user_func_array($labelFunction, array(&$pdf, $key, 
																										$pdf->ez['leftMargin'], 
																										$pdf->ez['pageHeight'],
																								$spec));
		if($index + 1 < count($keys))	$pdf->ezNewPage();
	}
  $pdf->stream();
}

function printPageLabels($pdf, $labelFunction, $keys, $rows, $cols, $spec) {
	$row = 0;
	$col = 0;
	$origMargin = $pdf->ez['leftMargin'];
	$rowSeparation = $spec['verticalseparation'] ?
		($spec['units'] == 'cm' ? cm($spec['verticalseparation']+$spec['borderheight']) 
			: inches($spec['verticalseparation']+$spec['borderheight']) )
		: cm(2.3);
	if(isset($spec['horizontalseparation'])) {
		$horizontalLeap = $spec['horizontalseparation'] + $spec['borderwidth'];
		$horizontalLeap = $spec['units'] == 'cm' ? cm($horizontalLeap) : inches($horizontalLeap);
	}
	else $horizontalLeap = cm(5.0);
	
	foreach($keys as $index => $key) {
//if(mattOnlyTEST()) {echo "$labelFunction ($index): ";print_r($key);}
		call_user_func_array($labelFunction, array(&$pdf, $key, 
																								$pdf->ez['leftMargin'], 
																								$pdf->ez['pageHeight']
																									-$pdf->ez['topMargin']
																									-$row*$rowSeparation,
																								$spec));
//echo $pdf->ez['pageHeight']-$pdf->ez['topMargin']-$row*cm(2.5).'<p>';																			
		$row++;
		if($row == $rows) {
			$row = 0;
			$col++;
			$pdf->ez['leftMargin'] += $horizontalLeap;
			if($col == $cols) {
				$col = 0;
				$pdf->ez['leftMargin'] = $origMargin;
				$pdf->ezNewPage();
			}
		}
	}
//exit;	
  $pdf->stream();
}

  
	

function label(&$key, &$pdf, $top, $left, $spec=null, $fontSize=null) {
	//echo "Page Height: ".$pdf->ez['pageHeight']."<br>Top: $top";exit;
	$keyLabel = formattedKeyId($key['keyid'], $key['copyNumber']);
	// addJpegFromFile($img,$x,$y,$w=0,$h=0)
	$yOffset = inches(.75);
	if($spec) {
		$factor = $spec['units'] == 'in' ? 2.54 : 1;
		$width = $spec['barwidth'] ? $spec['barwidth'] : $spec['borderwidth'];
		$width = $width ? cm($width*$factor)  : null;
		$height = $spec ? cm($spec['borderheight']*$factor *.9)  : null;
		$barcodeTopPadding = $spec['verticalseparation'] ? $spec['verticalseparation'] * $factor : cm(1.20);
		$fontOffsets = array(12 => .6, 10=>.6, 9=>.3, 8=>.3);
		//echo "w: $width h: $height font: $fontSize inches: ".($fontOffsets[$fontSize ? $fontSize : 9]);exit;
		$yOffset = $barcodeTopPadding + cm($fontOffsets[$fontSize ? $fontSize : 9]);
		$yOffset += $spec['units'] == 'cm' ? cm($spec['extraYlabeloffset']) : inches($spec['extraYlabeloffset']);

	}
	$pdf->addJpegFromFile(barCodeImageFile($keyLabel) , $left+cm(0.1) , $top - $yOffset, $width, $height); //.665
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
		
		createBarcodeImage($code=$keyLabel, $style, $width, $height, $file=$barcodeImg, $type='C128A', $xres=1, $font=5);
		//return $barcodeImg;
		
		// OLD CODE
		/*$imgUrl = "barcode/image.php?code=$keyLabel&style=$style&type=C128A&width=$width&height=$height&xres=1&font=5&file=.$barcodeImg";
		$this_dir = substr($_SERVER['REQUEST_URI'],0,strrpos($_SERVER['REQUEST_URI'],"/"));
		$imgUrl = globalURL($imgUrl);
		copy($imgUrl,$barcodeImg);*/
		//if(!@copy($imgUrl,$barcodeImg)) {
			//$errors= error_get_last(); 
			//echo "COPY ERROR: ".$errors['type'];
			//echo "<br />\n".$errors['message'];
		//}
		//else echo "File copied from remote!";
	}
	return $barcodeImg;
}

function createBarcodeImage($code, $style, $width, $height, $file, $type='C128A', $xres=1, $font=5) {
	set_include_path(get_include_path().':'.'/var/www/prod/barcode');

	/*
	Barcode Render Class for PHP using the GD graphics library 
	Copyright (C) 2001  Karim Mribti

		 Version  0.0.7a  2001-04-01  

	This library is free software; you can redistribute it and/or
	modify it under the terms of the GNU Lesser General Public
	License as published by the Free Software Foundation; either
	version 2.1 of the License, or (at your option) any later version.

	This library is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
	Lesser General Public License for more details.

	You should have received a copy of the GNU Lesser General Public
	License along with this library; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

	Copy of GNU Lesser General Public License at: http://www.gnu.org/copyleft/lesser.txt

	Source code home page: http://www.mribti.com/barcode/
	Contact author at: barcode@mribti.com
	*/
  
  define ('__TRACE_ENABLED__', true);
  define ('__DEBUG_ENABLED__', true);
    
  require_once("barcode.php");  
  require_once("i25object.php");
  require_once("c39object.php");
  require_once("c128aobject.php");
  require_once("c128bobject.php");
  require_once("c128cobject.php");
              			   
  if (!isset($style))  $style   = BCD_DEFAULT_STYLE;
  if (!isset($width))  $width   = BCD_DEFAULT_WIDTH;
  if (!isset($height)) $height  = BCD_DEFAULT_HEIGHT;
  if (!isset($xres))   $xres    = BCD_DEFAULT_XRES;
  if (!isset($font))   $font    = BCD_DEFAULT_FONT;
  			    
  switch ($type)
  {
    case "I25":
			  $obj = new I25Object($width, $height, $style, $code);
			  break;
    case "C39":
			  $obj = new C39Object($width, $height, $style, $code);
			  break;
    case "C128A":
			  $obj = new C128AObject($width, $height, $style, $code);
			  break;
    case "C128B":
			  $obj = new C128BObject($width, $height, $style, $code);
			  break;
    case "C128C":
              $obj = new C128CObject($width, $height, $style, $code);
			  break;
	default:
			echo "Need bar code type ex. C39";
			$obj = false;
  }
   
  if ($obj) {
  	  $obj->fileName = $file;
      $obj->SetFont($font);   
      $obj->DrawObject($xres);
  	  $obj->FlushObject();
  	  $obj->DestroyObject();
  	  unset($obj);  /* clean */
  }
}

//////////////////////////////////////////////////
function dymo30252_PDF() {
	$pageWidth = cm(9.3);
	$pageHeight = cm(3.1);
  $margins = array(cm(0.635), cm(0.0), cm(0.0), cm(0.7874)); //ezSetMargins($top,$bottom,$left,$right);
  $pdf =& new Cezpdf( array(0,0,$pageWidth,$pageHeight) , 'landscape' );
	$pdf->ezSetMargins($margins[0], $margins[1], $margins[2], $margins[3]);
  return $pdf;
}

function dymo30336_PDF() {
	$pageWidth = cm(6.2);
	$pageHeight = cm(3.1);
  $margins = array(cm(0.635), cm(0.0), cm(0.0), cm(0.7874)); //ezSetMargins($top,$bottom,$left,$right);
  $pdf =& new Cezpdf( array(0,0,$pageWidth,$pageHeight) , 'landscape' );
	$pdf->ezSetMargins($margins[0], $margins[1], $margins[2], $margins[3]);
  return $pdf;
}

function dymo30252Address(&$pdf, $key) {
	// left margin (beyond label) is .75cm
  $pdf->y = $pdf->ez['pageHeight'] - $pdf->ez['topMargin'];
	$y = $pdf->y;
	$fontSize = $key['bin'] && (strlen($key['bin']) >= 12) ? 10 : 12;
	$prefix = strlen($key['bin']) >= 12 ? ' ' : ' Hook: ';
	if(isset($key['bin'])) $pdf->ezText($prefix.$key['bin'] , $fontSize );
	label($key, $pdf, $pdf->y, $pdf->ez['leftMargin']);
	$pdf->y = $y;
	if(isset($key['bin'])) $pdf->ezText($prefix.$key['bin'] , $fontSize );
	$pdf->line(cm(4.2) , $y, cm(4.2) , $y+cm(.15) );
	$pdf->line(cm(4.2) , cm(.35), cm(4.2) , cm(.35 + .15) );
	$pdf->line(cm(0.0) , $y, cm(0.0) , $y+cm(.15) );
	$pdf->line(cm(0.0) , cm(.35), cm(0.0) , cm(.35 + .15) );

}

function fullSheetFormat(&$pdf, $key, $left, $top, $spec='unused') {
	// left margin (beyond label) is .75cm
	$pdf->y = $top;
	$fontSize = $key['bin'] && (strlen($key['bin']) >= 12) ? 10 : 12;
	$prefix = strlen($key['bin']) >= 12 ? ' ' : ' Hook: ';
	if(isset($key['bin'])) $pdf->ezText($prefix.$key['bin'] , $fontSize );
	label($key, $pdf, $pdf->y, $pdf->ez['leftMargin']);
	$pdf->y = $top;
	if(isset($key['bin'])) $pdf->ezText($prefix.$key['bin'] , $fontSize );
	
	$bottom = $top-cm(2.3);
	$pdf->line($left+cm(0.0) , $top, $left+cm(0.0) , $top-cm(.15) );
	$pdf->line($left+cm(0.0) , $top, $left+cm(0.15) , $top );

	$pdf->line($left+cm(0.0) , $bottom, $left+cm(0.0) , $bottom+cm(0.15) );
	$pdf->line($left+cm(0.0) , $bottom, $left+cm(0.15) , $bottom );
	
	$pdf->line($left+cm(4.2) , $top, $left+cm(4.2) , $top-cm(.15) );
	$pdf->line($left+cm(4.2) , $top, $left+cm(4.2-0.15) , $top );
	
	$pdf->line($left+cm(4.2) , $bottom, $left+cm(4.2) , $bottom+cm(.15) );
	$pdf->line($left+cm(4.2) , $bottom, $left+cm(4.2-0.15) , $bottom );

}




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
      //if(isset($key['bin'])) $pdf->ezText('Hook: '.$key['bin'] , 12 );
      label($key, $pdf, $pdf->y, $margins[2]+$col*$labelWidth);
      $pdf->y = $y;
      if(isset($key['bin'])) $pdf->ezText('Hook: '.$key['bin'] , 12 );
    }
    $row++;
  }
  //$pdf->ezNewPage();
  $pdf->stream();
}


?>
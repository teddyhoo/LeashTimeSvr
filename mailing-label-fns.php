<?
//

require_once "class.ezpdf.php";

function inches($inches) { return $inches*72; }

function labelPagesForPersons($persons, &$pdf) {
  $labels = array();
  foreach($persons as $person)
    $labels[] = label($person);
  return labelPage($labels, $pdf);
}

function labelPage($addresses, &$pdf) {
  if(!$pdf) $pdf =& new Cezpdf( 'letter' , 'portrait' );
  $margins = array(inches(0.7), inches(0.5), inches(0.75), inches(0.75)); //ezSetMargins($top,$bottom,$left,$right);
  $pdf->ezSetMargins($margins[0], $margins[1], $margins[2], $margins[3]);
  $pdf->selectFont( './fonts/Helvetica.afm' );
  $labelWidth = inches(2.657);
  $labelHeight = inches(1.0);
  $row = 0;
  $data = array_chunk($addresses, 3);
  foreach($data as $vals) {
    if($row == 10) {
      $pdf->ezNewPage();
      $row = 0;
    }
    $col = 0;
    foreach($vals as $val) {
      $pdf->y = $pdf->ez['pageHeight']-$margins[0]-$row*$labelHeight;
      $pdf->ezSetMargins($margins[0]+$row*$labelHeight, $margins[1], $margins[2]+$col*$labelWidth, $margins[3]);
      $col++;
      $pdf->ezText($val , 10 );
    }
    $row++;
  }
  //$pdf->ezNewPage();
  //$pdf->stream();
  return $pdf;
}

function label(&$person) {
  $lab = $person['fname'].' '.$person['lname'];
  if(!$lab) $lab = '-- No name supplied --';
  if($person['street1']) $lab .= "\n{$person['street1']}";
  if($person['street2']) $lab .= "\n{$person['street2']}";
  if($person['city'] || $person['state'] || $person['zip'] )
    $lab .= "\n";
  $csz = '';
  if($person['city'])  $lab .= $person['city'];
  if($person['city'] && ($person['state'] || $person['zip'] ))
     $lab .= ', ';
  $lab .= $person['state'].' '.$person['zip'];
  return $lab;
}

/** TEST
*

Header('Pragma: public');
$labels = array();
foreach(fetchAssociations('select * from patients where facility = 203 order by
concat(lastName,",",firstName)') as $patient)
  $labels[] = label($patient);
$labels = array_merge($labels,$labels);
labelPage($labels);
**/
?>
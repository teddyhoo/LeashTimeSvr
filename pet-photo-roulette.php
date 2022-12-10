<? //pet-photo-roulette.php

require_once "common/init_session.php";
require_once "common/init_db_petbiz.php";

locked('o-');

$petIDsWithPhotos = fetchCol0("SELECT petid FROM tblpet WHERE photo IS NOT NULL");
$rand = $petIDsWithPhotos[rand(0, count($petIDsWithPhotos)-1)];

$pet = fetchFirstAssoc("SELECT * FROM tblpet WHERE petid = $rand");
$inactive = $pet['active'] ? '' : 'inactive)';
echo "Pet: {$pet['name']} ({$pet['petid']}) $inactive<p>";

// ===================================================================
setlocale(LC_ALL, '');

require_once 'lsolesen/autoload.php';
use lsolesen\pel\PelDataWindow;
use lsolesen\pel\Pel;
use lsolesen\pel\PelJpeg;
use lsolesen\pel\PelTiff;
if($_REQUEST['exif']) {


$data = new PelDataWindow(file_get_contents("http://training.leashtime.com/HowToVideosHOVER.jpg"));//https://leashtime/pet-photo.php?id=$rand

if (PelJpeg::isValid($data)) {
    $img = new PelJpeg();
} elseif (PelTiff::isValid($data)) {
    $img = new PelTiff();
} else {
    print("Unrecognized image format! The first 16 bytes follow:\n");
    PelConvert::bytesToDump($data->getBytes(0, 16));
    exit(1);
}

/* Try loading the data. */
$img->load($data);
echo "<hr><pre>";
print($img);
echo "<hr></pre>";

/* Deal with any exceptions: */
if (count(Pel::getExceptions()) > 0) {
    print("\nThe following errors were encountered while loading the image:\n");
    foreach (Pel::getExceptions() as $e) {
        print("\n" . $e->__toString());
    }
}
}
// ===================================================================
//$sz = getimagesize($pet['photo']);
//echo "File size: ".number_format(file_get_contents($pet['photo']))." bytes.";
//echo "<br>".number_format($sx[0])." pixels wide by ".number_format($sx[1])." high." ;



echo "<img src='pet-photo.php?id=$rand'>";
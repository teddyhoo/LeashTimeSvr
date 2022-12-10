<? // breeds.php 
$pettype = $_REQUEST['pettype'] ? $_REQUEST['pettype'] : 'dog';
require_once "frame-bannerless.php";
require_once "gui-fns.php";
require_once "common/db_fns.php";
?>
<h2>Breed Chooser</h2>
<span class='tiplooks'>Start typing to see a list.</span><br>
<form name='pickbreed' method='post'>
Type: 
<?
$kinds = explodePairsLine('Cat|cat||Dog|dog||Bird|bird||Lizard|lizard||Turtle|turtle||Rabbit|rabbit');
foreach($kinds as $kind) if(strpos(strtoupper($pettype), strtoupper($kind)) !== FALSE) $chosen = $kind;
if(!$chosen) $chosen = 'dog';
$radios = radioButtonSet('kind', $chosen, $kinds, $onClick='lookup()', $labelClass=null, $inputClass=null);
foreach($radios as $radio) echo "$radio ";
?>
<p>
Breed: <input id='breed' name='breed' onkeyup='lookup()'>
<p>
<div id='breeds'></div>

<script language='javascript'>
function lookup() {
	var breeds = document.getElementById('breeds');
	breeds.innerHTML = '';
	var pat = document.getElementById('breed').value.toUpperCase();
	if(pat.length < 1) return;
	var allbreeds, str = '', initialMatch = '';
	if(document.getElementById('kind_cat').checked) allbreeds = catbreeds;
	else if(document.getElementById('kind_dog').checked) allbreeds = dogbreeds;
	else if(document.getElementById('kind_bird').checked) allbreeds = birdbreeds;
	else if(document.getElementById('kind_lizard').checked) allbreeds = lizardbreeds;
	else if(document.getElementById('kind_turtle').checked) allbreeds = turtlebreeds;
	else if(document.getElementById('kind_rabbit').checked) allbreeds = rabbitbreeds;
	var pos;
	for(var i = 0; i < allbreeds.length; i++) {
		if((pos = allbreeds[i].toUpperCase().indexOf(pat)) >= 0) {
			if(pos == 0) initialMatch += "<a id='"+allbreeds[i]+"' href= '#' onclick='choosebreed(this)'>"+allbreeds[i]+"</a><br>";
			else str += "<a id='"+allbreeds[i]+"' href= '#' onclick='choosebreed(this)'>"+allbreeds[i]+"</a><br>";
		}
	}
	if(initialMatch.length) initialMatch = initialMatch+'<hr>';
	
	breeds.innerHTML = initialMatch+str;		
}

function choosebreed(el) {
	parent.update('breed:<?= $_REQUEST['target'] ?>', el.id);
	parent.$.fn.colorbox.close();
}
<?
catbreeds();
dogbreeds();
birdbreeds();
lizardbreeds();
turtlebreeds();
rabbitbreeds();
?>
</script>

<?
function turtlebreeds() {
	$breeds = explode("\r\n", 
"Box Turtle
Mud Turtle
Painted Turtle
Red Eared Slider
Russian Tortoise
Slider");
  echo "var turtlebreeds = ['".join("','", $breeds)."'];\n";
}

function lizardbreeds() {
	$breeds = explode("\r\n", 
"Bearded Dragon
Chameleon
Crested Gecko
Green Iguana
Leopard Gecko
Green Anole
Savannah Monitor
Water Dragon");
  echo "var lizardbreeds = ['".join("','", $breeds)."'];\n";
}

function birdbreeds() {
	$breeds = explode("\r\n", 
"African Gray Parrot
Budgerigar
Canary
Cockatiel
Cockatoo
Conure
Eclectus
Finch
Gouldian Finch
Love Bird
Macaw
Parakeet
Parrot
Parrotlet
Pionus Parrot
Poicephalus Parrot
Society Finch
Zebra Finch");
  echo "var birdbreeds = ['".join("','", $breeds)."'];\n";
}

function catbreeds() {
	$breeds = explode("\r\n", 
"Abyssinian
American Bobtail
American Curl
American Shorthair
American Wirehair
Balinese
Bengal
Birman
Blue Russian
Bombay
British Shorthair
Burmese
California Spangled Cat
Chartreux
Colorpoint Shorthair
Cornish Rex
Cymric
Devon Rex
Egyptian Mau
European Burmese
Exotic Shorthair
Havana Brown
Himalayan
Japanese Bobtail
Javanese
Korat
Maine Coon
Manx
Mix
Munchkin
Nebelung
Norwegian Forest Cat
Ocicat
Oriental
Persian
Ragdoll
Randombred Cat
Russian Blue
Scottish Fold
Selkirk Rex
Siamese
Siberian
Singapura
Snowshoe
Somali
Sphynx
Tiffany/Chantilly
Tonkinese
Turkish Angora
Turkish Van
York Chocolate");
  echo "var catbreeds = ['".join("','", $breeds)."']; // ".count($breeds)."\n";
}

function dogbreeds() {
	$breeds = explode("\r\n", 
"Affenpinscher
Afghan Hound
Airedale Terrier
Akita
Alaskan Klee Kai
Alaskan Malamute
American English Coonhound
American Eskimo Dog
American Foxhound
American Hairless Terrier
American Staffordshire Terrier
American Water Spaniel
Anatolian Shepherd Dog
Appenzeller Sennenhunde
Australian Cattle Dog
Australian Kelpie
Australian Shepherd
Australian Terrier
Azawakh
Barbet
Basenji
Basset Hound
Beagle
Bearded Collie
Beauceron
Bedlington Terrier
Belgian Laekenois
Belgian Malinois
Belgian Sheepdog
Belgian Tervuren
Bergamasco
Berger Picard
Bernese Mountain Dog
Bichon Frise
Black and Tan Coonhound
Black Russian Terrier
Bloodhound
Bluetick Coonhound
Boerboel
Bolognese
Border Collie
Border Terrier
Borzoi
Boston Terrier
Bouvier des Flandres
Boxer
Boykin Spaniel
Bracco Italiano
Braque du Bourbonnais
Briard
Brittany
Brussels Griffon
Bull Terrier
Bulldog
Bullmastiff
Cairn Terrier
Canaan Dog
Cane Corso
Cardigan Welsh Corgi
Catahoula Leopard Dog
Caucasian Ovcharka
Cavalier King Charles Spaniel
Central Asian Shepherd Dog
Cesky Terrier
Chesapeake Bay Retriever
Chihuahua
Chinese Crested
Chinese Shar-Pei
Chinook
Chow Chow
Cirneco dell'Etna
Clumber Spaniel
Cocker Spaniel
Collie
Coton de Tulear
Curly-Coated Retriever
Czechoslovakian Vlcak
Dachshund
Dalmatian
Dandie Dinmont Terrier
Danish-Swedish Farmdog
Deutscher Wachtelhund
Doberman Pinscher
Dogo Argentino
Dogue de Bordeaux
Drentsche Patrijshond
English Cocker Spaniel
English Foxhound
English Setter
English Springer Spaniel
English Toy Spaniel
Entlebucher Mountain Dog
Estrela Mountain Dog
Eurasier
Field Spaniel
Finnish Lapphund
Finnish Spitz
Flat-Coated Retriever
French Bulldog
German Longhaired Pointer
German Pinscher
German Shepherd Dog
German Shorthaired Pointer
German Spitz
German Wirehaired Pointer
Giant Schnauzer
Glen of Imaal Terrier
Golden Retriever
Gordon Setter
Grand Basset Griffon Vendeen
Great Dane
Great Pyrenees
Greater Swiss Mountain Dog
Greyhound
Hamiltonstovare
Harrier
Havanese
Hovawart
Ibizan Hound
Icelandic Sheepdog
Irish Red and White Setter
Irish Setter
Irish Terrier
Irish Water Spaniel
Irish Wolfhound
Italian Greyhound
Jack Russel Terrier
Japanese Chin
Jindo
Kai Ken
Karelian Bear Dog
Keeshond
Kerry Blue Terrier
King Charles Spaniel
Kishu Ken
Komondor
Kooikerhondje
Kuvasz
Labrador Retriever
Lagotto Romagnolo
Lakeland Terrier
Lancashire Heeler
Leonberger
Lhasa Apso
Lowchen
Maltese
Manchester Terrier
Mastiff
Miniature American Shepherd
Miniature Bull Terrier
Miniature Dachshund
Miniature Pinscher
Miniature Poodle
Miniature Schnauzer
Mudi
Neapolitan Mastiff
Newfoundland
Norfolk Terrier
Norrbottenspets
Norwegian Buhund
Norwegian Elkhound
Norwegian Lundehund
Norwich Terrier
Nova Scotia Duck Tolling Retriever
Old English Sheepdog
Otterhound
Papillon
Parson Russell Terrier
Pekingese
Pembroke Welsh Corgi
Perro de Presa Canario
Peruvian Inca Orchid
Petit Basset Griffon Vendeen
Pharaoh Hound
Pit Bull
Plott
Pointer
Polish Lowland Sheepdog
Pomeranian
Poodle
Portuguese Podengo
Portuguese Podengo Pequeno
Portuguese Pointer
Portuguese Water Dog
Pug
Puli
Pumi
Pyrenean Shepherd
Rafeiro do Alentejo
Rat Terrier
Redbone Coonhound
Rhodesian Ridgeback
Rottweiler
Russell Terrier
Russian Toy
Saint Bernard
Saluki
Samoyed
Schapendoes
Schipperke
Scottish Deerhound
Scottish Terrier
Sealyham Terrier
Shetland Sheepdog
Shiba Inu
Shih Tzu
Shiloh Shepherd
Siberian Husky
Silky Terrier
Skye Terrier
Sloughi
Slovensky Cuvac
Small Munsterlander Pointer
Smooth Fox Terrier
Soft Coated Wheaten Terrier
Spanish Mastiff
Spanish Water Dog
Spinone Italiano
Stabyhoun
Staffordshire Bull Terrier
Standard Poodle
Standard Schnauzer
Sussex Spaniel
Swedish Lapphund
Swedish Vallhund
Thai Ridgeback
Tibetan Mastiff
Tibetan Spaniel
Tibetan Terrier
Tosa
Toy Fox Terrier
Toy Poodle
Treeing Tennessee Brindle
Treeing Walker Coonhound
Vizsla
Weimaraner
Welsh Springer Spaniel
Welsh Terrier
West Highland White Terrier
Whippet
Wire Fox Terrier
Wirehaired Pointing Griffon
Wirehaired Vizsla
Xoloitzcuintli
Yorkshire Terrier");
  echo 'var dogbreeds = ["'.join("\",\"", $breeds).'"];'."// ".count($breeds)."\n";
}

function rabbitbreeds() {
	$breeds = explode("\r\n", 
"Alaska 
Altex 
American 
American Chinchilla
American Fuzzy Lop 
American Sable 
Argente Bleu 
Argente Brun 
Argente Clair 
Argente Cr&egrave;me 
Argente de Champagne 
Argente Noir 
Argente St Hubert 
Baladi 
Bauscat 
Beige 
Belgian Hare 
Beveren 
Big Silver Marten 
Blanc de Bouscat 
Blanc de Hotot 
Blanc de Popielno 
Blanc de Termonde 
Blue of Ham 
Blue of Sint-Niklaas 
Bourbonnais Grey 
Brazilian 
Britannia Petite 
British Giant 
Brown Chestnut of Lorraine 
Caldes 
Californian 
Carmagnola Grey 
Cashmere Lop 
Champagne d'Argent
Chaudry 
Checkered Giant 
Chinchilla (Standard) 
Chinchilla (American) 
Chinchilla (Giganta) 
Chinchilla (Giant) 
Cinnamon 
Continental Giant 
Creme d'Argent
Criollo 
Cuban Brown 
Czech Albin 
Czech Spot 
Czech Red 
Deilenaar 
Dutch 
Dutch (Tri-Coloured) 
Dwarf Hotot 
Dwarf Lop (Mini Lop in USA) 
Elfin 
Enderby Island 
English Angora 
English Spot 
English Lop 
Fauve de Bourgogne 
Fee de Marbourg (Marburger) 
Flemish Giant 
Florida White 
French Angora 
French Lop 
Gabali 
German Angora 
German Lop 
Giant Angora 
Giant Chinchilla
Giant Papillon 
Giza White 
Golden Glavcot 
Gotland 
Grey Pearl of Halle 
G&uuml;zel&ccedil;amli rabbit 
Harlequin 
Havana 
Himalayan 
Holland Lop
Hulstlander 
Hungarian Giant 
Japanese White 
Jersey Wooly 
Kabyle 
Lilac 
Lionhead 
Liptov Baldspotted Rabbit 
Meissner Lop 
Mellerud rabbit 
Miniature Lop (Holland Lop in USA) 
Mini Lion Lop 
Mini Rex
Mini Satin
Netherland Dwarf 
New Zealand 
New Zealand Red 
Orestad 
Palomino 
Pannon White 
Perlfee 
Plush Lop (Standard) 
Plush Lop (Mini) 
Pointed Beveren 
Polish 
Rex (Standard) 
Rex (Astrex) 
Rex (Mini) 
Rex (Opossum) 
Rhinelander 
Sallander 
San Juan 
Satin 
Satin (Mini) 
Satin Angora 
Sachsengold 
Siberian 
Siamese Sable 
Silver 
Silver Fox 
Silver Marten 
Smoke Pearl 
Spanish Giant 
Squirrel 
Standard Chinchilla
Sussex 
Swiss Fox 
Tadla 
Tan 
Teddywidder 
Thrianta 
Thuringer 
Vienna 
Wheaten 
Wheaten Lynx 
Zemmouri");
  echo 'var rabbitbreeds = ["'.join("\",\"", array_map('trim', $breeds)).'"];'."// ".count($breeds)."\n";
}



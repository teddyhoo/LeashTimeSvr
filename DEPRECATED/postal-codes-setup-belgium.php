<? // postal-codes-setup-belgium.php
// http://en.wikipedia.org/wiki/List_of_postal_codes_in_Belgium

require_once "common/init_session.php";
require_once "common/init_db_common.php";

$tables = fetchCol0("SHOW TABLES");
if(in_array('zipcodes_belgium', $tables)) {
	echo "zipcodes_belgium exists.  Drop table before running this script.";
	exit;
}

function state($code) { // unused
	$regions = explodePairsLine('1000|Brussels Capital Region||1300|Walloon Brabant||1500|Flemish Brabant||2000|Antwerp||3000|Flemish Brabant continued||3500|Limburg||4000|Liege||5000|Namur||6000|Hainaut||6600|Luxemburg||7000|Hainaut continued||8000|West Flanders||9000|East Flanders');
	return $regions($code);
}

doQuery(
	"CREATE TABLE IF NOT EXISTS `zipcodes_belgium` (
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` char(3) NOT NULL DEFAULT ' ',
  `zipcode` varchar(12) NOT NULL DEFAULT '' COMMENT 'ZIPS ARE NOT UNIQUE',
  `lon` varchar(8) DEFAULT NULL,
  `lat` varchar(8) DEFAULT NULL,
  `county` varchar(100) DEFAULT NULL,
  `zipclass` varchar(20) NOT NULL DEFAULT '',
  `nonunique` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='From http://blog.datalicious.com/free-download-all-australia';
");

foreach(rawData() as $line) {
	$line = trim($line);
	if(!$line) continue;
	else if(is_numeric($line) && $line == (int)$line) {
	echo "LINE: $line == [".((int)$line)."]: [TRUE]<br>";
		$code = $line;
		continue;
	}
	else {
//echo "PARTS: ".print_r($parts,1)."<br>";
		$parts = explode(' ', $line);
		if(count($parts) > 1 && is_numeric($parts[0]) && $parts[0] == (int)$parts[0]) {
			$code = $parts[0];
			$city = substr($line, strpos($line, ' ')+1);
		}
		else $city = $line;
	}
	insertTable('zipcodes_belgium', array('zipcode'=>$code, 'city'=>$city, 'state'=>' '), 1);
	echo "[$code] $city<br>";
}
			


function rawData() {
$data = <<<DATA
1000 Brussel / Bruxelles
1005
Ver.Verg.Gemeensch.Gemeensch.Comm. / Ass. R�un. Com. Communau. Commune
Brusselse Hoofdstedelijke Raad / Conseil Region Bruxelles-Capitale
1006 Raad Vlaamse Gemeenschapscommissie
1007 Ass. Commiss. Communau. fran�aise
1008 Kamer van Volksvertegenwoordigers / Chambre des Repr�sentants
1009 Belgische Senaat / Senat de Belgique
1010 Rijksadministratief Centrum / Cit� Administrative de l'Etat
1011 Vlaamse Raad / Vlaams Parlement
1012 Parlement de la Communaut� fran�aise
1020 Brussel (Laken) / Bruxelles (Laeken)
1030 Schaarbeek / Schaerbeek
1031 Christelijke Sociale Organisaties / Organisations Sociales Chr�tiennes
1033 RTL-TVI
1040 Etterbeek
1041 International Press Center
1043 VRT
1044 RTBF
1045 D.I.V.
1047 Europees Parlement - Parlement Europ�en
1048 E.U.-Raad / U.E.-Conseil
1049 E.U.-Commissie / U.E.-Commission
1050 Elsene / Ixelles
1060 Sint-Gillis / Saint-Gilles
1070 Anderlecht
1080 Sint-Jans-Molenbeek / Molenbeek-Saint-Jean
1081 Koekelberg
1082 Sint-Agatha-Berchem / Berchem-Sainte-Agathe
1083 Ganshoren
1090 Jette
1100 Postcheque
1105 SOC
1110 NAVO / OTAN
1120 Brussel (Neder-Over-Heembeek) / Bruxelles (Neder-Over-Heembeek)
1130 Brussel (Haren) / Bruxelles (Haeren)
1140 Evere
1150 Sint-Pieters-Woluwe / Woluwe-Saint-Pierre
1160 Oudergem / Auderghem
1170 Watermaal-Bosvoorde / Watermael-Boitsfort
1180 Ukkel / Uccle
1190 Vorst / Forest
1200 Sint-Lambrechts-Woluwe / Woluwe-Saint-Lambert
1210 Sint-Joost-ten-Node / Saint-Josse-ten-Noode
1300
Limal
Wavre
1301 Bierges
1310 La Hulpe
1315
Glimes
Incourt
Opprebais
Pi�trebais
Roux-Miroir
1320
Beauvechain
Hamme-Mille (former commune, now a village and district in the commune of Beauchevain)
l'Ecluse (former commune, now a village and district in the commune of Beauchevain)
Nodebais (former commune, now a village and district in the commune of Beauchevain)
Tourinnes-la-Grosse (former commune, now a village and district in the commune of Beauchevain)
1325
Bonlez
Chaumont-Gistoux
Corroy-le-Grand
Dion-Valmont
Longueville
1330 Rixensart
1331 Rosi�res
1332 Genval
1340
Ottignies
Ottignies-Louvain-la-Neuve
1341 C�roux-Mousty
1342 Limelette
1348
Louvain-la-Neuve
� Mont-Saint-Guibert �
1350
Enines
Folx-les-Caves
Jandrain-Jandrenouille
Jauche
Marilles
Noduwez
Orp-Jauche
Orp-le-Grand
1357
H�l�cine
Linsmeau
Neerheylissem
Opheylissem
1360
Mal�ves-Sainte-Marie-Wastines
Orbais
Perwez
Thorembais-les-B�guines
Thorembais-Saint-Trond
1367
Autre-Eglise
Bomal (Bt.)
Geest-G�rompont-Petit-Rosi�re
G�rompont
Grand-Rosi�re-Hottomont
Huppaye
Mont-Saint-Andr�
Ramillies
1370
Dongelberg
Jauchelette
Jodoigne
Jodoigne-Souveraine
Lathuy
M�lin
Pi�train
Saint-Jean-Geest
Saint-Remy-Geest
Z�trud-Lumay
1380
Couture-Saint-Germain
Lasne
Lasne-Chapelle-Saint-Lambert
Maransart
Ohain
Plancenoit
� Waterloo �
1390
Archennes
Biez
Bossut-Gottechain
Grez-Doiceau
Nethen
1400
Monstreux
Nivelles
� Petit-Roeulx-lez-Nivelles �
1401 Baulers
1402 Thines
1404 Bornival
1410
Waterloo
� La Hulpe �
1414 Promo-Control
1420 Braine-l'Alleud
1421 Ophain-Bois-Seigneur-Isaac
1428 Lillois-Witterz�e
1430
Bierghes
Quenast
Rebecq
Rebecq-Rognon
1435
Corbais
H�villers
Mont-Saint-Guibert
1440
Braine-le-Ch�teau
Wauthier-Braine
1450
Chastre
Chastre-Villeroux-Blanmont
Cortil-Noirmont
Gentinnes
Saint-G�ry
1457
Nil-Saint-Vincent-Saint-Martin
Tourinnes-Saint-Lambert
Walhain
Walhain-Saint-Paul
1460
Ittre
Virginal-Samme
1461 Haut-Ittre
1470
Baisy-Thy
Bousval
Genappe
1471 Loupoigne
1472 Vieux-Genappe
1473 Glabais
1474 Ways
1476 Houtain-le-Val
1480
Clabecq
Oisquercq
Saintes
Tubize
1490 Court-Saint-Etienne
1495
Marbais (Bt.)
Mellery
Sart-Dames-Avelines
Tilly
Villers-la-Ville

1500 Halle
1501 Buizingen
1502 Lembeek
1540
Herfelingen
Herne
1541 Sint-Pieters-Kapelle (Bt.)
1547
Bever (Biev�ne)
1560 Hoeilaart
1570
Galmaarden
Tollembeek
Vollezele
1600
Oudenaken
Sint-Laureins-Berchem
Sint-Pieters-Leeuw
1601 Ruisbroek (Bt.)
1602 Vlezenbeek
1620 Drogenbos
1630 Linkebeek
1640
Sint-Genesius-Rode (Rhode-Saint-Gen�se)
1650 Beersel
1651 Lot
1652 Alsemberg
1653 Dworp
1654 Huizingen
1670
Bogaarden
Heikruis
Pepingen
1671 Elingen
1673 Beert
1674 Bellingen
1700
Dilbeek
Sint-Martens-Bodegem
Sint-Ulriks-Kapelle
1701 Itterbeek
1702 Groot-Bijgaarden
1703 Schepdaal
1730
Asse
Bekkerzeel
Kobbegem
Mollem
1731
Relegem
Zellik
1740 Ternat
1741 Wambeek
1742 Sint-Katherina-Lombeek
1745
Mazenzele
1745
Opwijk
1750
Gaasbeek
Lennik
Sint-Kwintens-Lennik
Sint-Martens-Lennik
1755
Gooik
Kester
Leerbeek
Oetingen
1760
Onze-Lieve-Vrouw-Lombeek
Pamel
Roosdaal
Strijtem
1761 Borchtlombeek
1770 Liedekerke
1780 Wemmel
1785
Brussegem
Hamme (Bt.)
Merchtem
1790
Affligem
Essene
Hekelgem
Teralfene
1800
Peutie
Vilvoorde
1804 Cargovil
1818 VTM
1820
Melsbroek
Perk
Steenokkerzeel
1830 Machelen (Bt.)
1831 Diegem
1840
Londerzeel
Malderen
Steenhuffel
1850 Grimbergen
1851 Humbeek
1852 Beigem
1853 Strombeek-Bever
1860 Meise
1861 Wolvertem
1880
Kapelle-op-den-Bos
Nieuwenrode
Ramsdonk
1910
Berg (Bt.)
Buken
Kampenhout
Nederokkerzeel
1930
Nossegem
Zaventem
1931 Brucargo
1932 Sint-Stevens-Woluwe
1933 Sterrebeek
1934
Brussel X-Luchthaven Remailing
Bruxelles X-Aeroport Remailing
1935 Corporate Village
1950 Kraainem
1970 Wezembeek-Oppem
1980
Eppegem
Zemst
1981 Hofstade (Bt.)
1982
Elewijt
Weerde

2000 Antwerpen
2018 Antwerpen
2020 Antwerpen
2030 Antwerpen
2040
Antwerpen
Berendrecht
Lillo
Zandvliet
2050 Antwerpen
2060 Antwerpen
2070
Burcht
Zwijndrecht
2100 Deurne (Antwerpen)
2110 Wijnegem
2140 Borgerhout (Antwerpen)
2150 Borsbeek (Antw.)
2160 Wommelgem
2170 Merksem (Antwerpen)
2180
Ekeren (Antwerpen)
� Antwerpen �
2200
Herentals
Morkhoven
Noorderwijk
2220 Heist-op-den-Berg
Hallaar
2221 Booischot
Pijpelheide
2222
Itegem
Wiekevorst
2223 Schriek
Grootlo
2230
Herselt
Ramsel
2235
Houtvenne
Hulshout
Westmeerbeek
2240
Massenhoven
Viersel
Zandhoven
2242 Pulderbos
2243 Pulle
2250 Olen
2260
Oevel
Tongerlo (Antw.)
Westerlo
Zoerle-Parwijs
2270 Herenthout
2275
Gierle
Lille
Poederlee
Wechelderzande
2280 Grobbendonk
2288 Bouwel
2290 Vorselaar
2300 Turnhout
2310 Rijkevorsel
2320 Hoogstraten
2321 Meer
2322 Minderhout
2323 Wortel
2328 Meerle
2330 Merksplas
2340
Beerse
Vlimmeren
2350 Vosselaar
2360 Oud-Turnhout
2370 Arendonk
2380 Ravels
2381 Weelde
2382 Poppel
2387 Baarle-Hertog
2390
Malle
Oostmalle
Westmalle
2400 Mol
2430
Eindhout
Laakdal
Vorst (Kempen)
2431
Varendonk
Veerle
2440 Geel
2450 Meerhout
2460
Kasterlee
Lichtaart
Tielen
2470 Retie
2480 Dessel
2490 Balen
2491 Olmen
2500
Koningshooikt
Lier
2520
Broechem
Emblem
Oelegem
Ranst
2530 Boechout
2531 Vremde
2540 Hove
2547 Lint
2550
Kontich
Waarloos
2560
Bevel
Kessel
Nijlen
2570 Duffel
2580
Beerzel
Putte
2590
Berlaar
Gestel
2600 Berchem (Antwerpen)
2610 Wilrijk (Antwerpen)
2620
Hemiksem
� Wilrijk (Antwerpen) �
2627 Schelle
2630 Aartselaar
2640 Mortsel
2650 Edegem
2660 Hoboken (Antwerpen)
2800
Mechelen
Walem
2801 Heffen
2811
Hombeek
Leest
2812 Muizen (Mechelen)
2820
Bonheiden
Rijmenam
2830
Blaasveld
Heindonk
Tisselt
Willebroek
Klein Willebroek
2840
Reet
Rumst
Terhagen
2845 Niel
2850 Boom
2860 Sint-Katelijne-Waver
2861 Onze-Lieve-Vrouw-Waver
2870
Breendonk
Liezele
Puurs
Ruisbroek (Antw.)
2880
Bornem
Hingene
Mariekerke (Bornem)
Weert
2890
Lippelo
Oppuurs
Sint-Amands
2900 Schoten
2910 Essen
2920 Kalmthout
2930 Brasschaat
2940
Hoevenen
Stabroek
2950 Kapellen (Antw.)
2960
Brecht
Sint-Job-in-'t-Goor
Sint-Lenaarts
2970
's Gravenwezel
Schilde
2980
Halle (Kempen)
Zoersel
2990
Loenhout
Wuustwezel
� Brecht �

3000 Leuven
3001 Heverlee
3010 Kessel-Lo (Leuven)
3012 Wilsele
3018 Wijgmaal (Brabant)
3020
Herent
Veltem-Beisem
Winksele
3040
Huldenberg
Loonbeek
Neerijse
Ottenburg
Sint-Agatha-Rode
3050
Oud-Heverlee
3051 Sint-Joris-Weert
3052 Blanden
3053 Haasrode
3054 Vaalbeek
3060
Bertem
Korbeek-Dijle
3061 Leefdaal
3070 Kortenberg
3071 Erps-Kwerps
3078
Everberg
Meerbeek
3080
Duisburg
Tervuren
Vossem
3090 Overijse
3110 Rotselaar
3111 Wezemaal
3118 Werchter
3120 Tremelo
3128 Baal
3130
Begijnendijk
Betekom
3140 Keerbergen
3150
Haacht
Tildonk
Wespelaar
3190 Boortmeerbeek
3191
Hever
Schiplaken
3200
Aarschot
Gelrode
3201 Langdorp
3202 Rillaar
3210
Linden
Lubbeek
3211 Binkom
3212 Pellenberg
3220
Holsbeek
Kortrijk-Dutsel
Sint-Pieters-Rode
3221 Nieuwrode
3270
Scherpenheuvel
Scherpenheuvel-Zichem
3271
Averbode
Zichem
3272
Messelbroek
Testelt
3290
Deurne (Bt.)
Diest
Schaffen
Webbekom
3293 Kaggevinne
3294 Molenstede
3300
Bost
Goetsenhoven
Hakendover
Kumtich
Oorbeek
Oplinter
Sint-Margriete-Houtem (Tienen)
Tienen
Vissenaken
3320
Hoegaarden
Meldert (Bt.)
3321 Outgaarden
3350
Drieslinter
Linter
Melkwezer
Neerhespen
Neerlinter
Orsmaal-Gussenhoven
Overhespen
Wommersom
3360
Bierbeek
Korbeek-Lo
Lovenjoel
Opvelp
3370
Boutersem
Kerkom
Neervelp
Roosbeek
Vertrijk
Willebringen
3380
Bunsbeek
Glabbeek-Zuurbemde
3381 Kapellen (Bt.)
3384 Attenrode
3390
Houwaart
Sint-Joris-Winge
Tielt (Bt.)
Tielt-Winge
3391 Meensel-Kiezegem
3400
Eliksem
Ezemaal
Laar
Landen
Neerwinden
Overwinden
Rumsdorp
Wange
3401
Waasmont
Walsbets
Walshoutem
Wezeren
3404
Attenhoven
Neerlanden
3440
Budingen
Dormaal
Halle-Booienhoven
Helen-Bos
Zoutleeuw
3450
Geetbets
Grazen
3454 Rummen
3460
Assent
Bekkevoort
3461 Molenbeek-Wersbeek
3470
Kortenaken
Ransberg
3471 Hoeleden
3472 Kersbeek-Miskom
3473 Waanrode

3500
Hasselt
Sint-Lambrechts-Herk
3501 Wimmertingen
3510
Kermt (Hasselt)
Spalbeek
3511
Kuringen
Stokrooie
3512 Stevoort
3520 Zonhoven
3530
Helchteren
Houthalen
Houthalen-Helchteren
3540
Berbroek
Donk
Herk-de-Stad
Schulen
3545
Halen
Loksbergen
Zelem
3550
Heusden (Limb.)
Heusden-Zolder
Zolder
3560
Linkhout
Lummen
Meldert (Limb.)
3570 Alken
3580 Beringen
3581 Beverlo
3582 Koersel
3583 Paal
3590 Diepenbeek
3600 Genk
3620
Gellik
Lanaken
Neerharen
Veldwezelt
3621 Rekem
3630
Eisden
Leut
Maasmechelen
Mechelen-aan-de-Maas
Meeswijk
Opgrimbie
Vucht
3631
Boorsem
Uikhoven
3640
Kessenich
Kinrooi
Molenbeersel
Ophoven
3650
Dilsen-Stokkem
Elen
Lanklaar
Rotem
Stokkem
3660
Opglabbeek
� Meeuwen-Gruitrode �
3665 As
3668 Niel-bij-As
3670
Ellikom
Gruitrode
Meeuwen
Meeuwen-Gruitrode
Neerglabbeek
Wijshagen
3680
Maaseik
Neeroeteren
Opoeteren
3690 Zutendaal
3700
's Herenelderen
Berg (Limb.)
Diets-Heur
Haren (Tongeren)
Henis
Kolmont (Tongeren)
Koninksem
Lauw
Mal
Neerrepen
Nerem
Overrepen (Kolmont)
Piringen (Haren)
Riksingen
Rutten
Sluizen
Tongeren
Vreren
Widooie (Haren)
3717 Herstappe
3720 Kortessem
3721 Vliermaalroot
3722 Wintershoven
3723 Guigoven
3724 Vliermaal
3730
Hoeselt
Romershoven
Sint-Huibrechts-Hern
Werm
3732 Schalkhoven
3740
Beverst
Bilzen
Eigenbilzen
Grote-Spouwen
Hees
Kleine-Spouwen
Mopertingen
Munsterbilzen
Rijkhoven
Rosmeer
Spouwen
Waltwilder
3742 Martenslinde
3746 Hoelbeek
3770
Genoelselderen
Herderen
Kanne
Membruggen
Millen
Riemst
Val-Meer
Vlijtingen
Vroenhoven
Zichen-Zussen-Bolder
3790
Moelingen (Mouland)
Sint-Martens-Voeren (Fouron-Saint-Martin)
Voeren (Fourons)
3791 Remersdaal
3792
Sint-Pieters-Voeren (Fouron-Saint-Pierre)
3793 Teuven
3798
's Gravenvoeren (Fouron-le-Comte)
3800
Aalst (Limb.)
Brustem
Engelmanshoven
Gelinden
Groot-Gelmen
Halmaal
Kerkom-bij-Sint-Truiden
Ordingen
Sint-Truiden
Zepperen
3803
Duras
Gorsem
Runkelen
Wilderen
3806 Velm
3830
Berlingen
Wellen
3831 Herten
3832 Ulbeek
3840
Bommershoven (Haren)
Borgloon
Broekom
Gors-Opleeuw
Gotem
Groot-Loon
Haren (Borgloon)
Hendrieken
Hoepertingen
Jesseren (Kolmont)
Kerniel
Kolmont (Borgloon)
Kuttekoven
Rijkel
Voort
3850
Binderveld
Kozen
Nieuwerkerken (Limb.)
Wijer
3870
Batsheers
Bovelingen
Gutschoven
Heers
Heks
Horpmaal
Klein-Gelmen
Mechelen-Bovelingen
Mettekoven
Opheers
Rukkelingen-Loon
Vechmaal
Veulen
3890
Boekhout
Gingelom
Jeuk
Kortijs
Montenaken
Niel-bij-Sint-Truiden
Vorsen
3891
Borlo
Buvingen
Mielen-Boven-Aalst
Muizen (Limb.)
3900 Overpelt
3910
Neerpelt
Sint-Huibrechts-Lille
3920 Lommel
3930
Achel
Hamont
Hamont-Achel
3940
Hechtel
Hechtel-Eksel
3941 Eksel
3945
Ham
Kwaadmechelen
Oostham
3950
Bocholt
Kaulille
Reppel
3960
Beek
Bree
Gerdingen
Opitter
Tongerlo (Limburg)
3970 Leopoldsburg
3971 Heppen
3980 Tessenderlo
3990
Grote-Brogel
Kleine-Brogel
Peer
Wijchmaal

4000
Glain
Li�ge
Rocourt
� Saint-Nicolas (Lg.) �
4020
Bressoux
Jupille-sur-Meuse
Li�ge
Wandre
4030
Grivegn�e
Li�ge
4031 Angleur
4032 Ch�n�e
4040 Herstal
4041
Milmort
Vottem
4042 Liers
4050 Chaudfontaine
4051 Vaux-sous-Ch�vremont
4052 Beaufays
4053 Embourg
4090
B.S.D. (Belgische Strijdkrachten Duitsland)
F.B.A. (Forces Belges en Allemagne)
4100
Boncelles
Seraing
4101 Jemeppe-sur-Meuse
4102 Ougr�e
4120
Ehein
Neupr�
Rotheux-Rimi�re
4121 Neuville-en-Condroz
4122 Plainevaux
4130
Esneux
Tilff
4140
Dolembreux
Gomz�-Andoumont
Rouvreux
Sprimont
4141 Louveign�
4160 Anthisnes
4161 Villers-aux-Tours
4162 Hody
4163 Tavier
4170 Comblain-au-Pont
4171 Poulseur
4180
Comblain-Fairon
Comblain-la-Tour
Hamoir
4181 Filot
4190
Ferri�res
My
Vieuxville
Werbomont
Xhoris
4210
Burdinne
Hann�che
Lamontz�e
Marneffe
Oteppe
4217
H�ron
Lavoir
Waret-l'Ev�que
4218 Couthuin
4219
Acosse
Ambresin
Meeffe
Wasseiges
4250
Bo�lhe
Geer
Hollogne-sur-Geer
Lens-Saint-Servais
4252 Omal
4253 Darion
4254 Ligney
4257
Berloz
Corswarem
Rosoux-Crenwick
4260
Avennes
Braives
Ciplet
Fallais
Fumal
Ville-en-Hesbaye
4261 Latinne
4263 Tourinne (Limburg)
4280
Abolens
Avernas-le-Bauduin
Avin
Bertr�e
Blehen
Cras-Avernas
Crehen
Grand-Hallet
Hannut
Lens-Saint-Remy
Merdorp
Moxhe
Petit-Hallet
Poucet
Thisnes
Trogn�e
Villers-le-Peuplier
Wansin
4287
Lincent
Pellaines
Racour
4300
Bettincourt
Bleret
Bovenistier
Grand-Axhe
Lantremange
Oleye
Waremme
4317
Aineffe
Borlez
Celles (Lg.)
Faimes
Les Waleffes
Viemme
4340
Awans
Fooz
Oth�e
Villers-l'Ev�que
4342 Hognoul
4347
Fexhe-le-Haut-Clocher
Freloux
Noville (Lg.)
Roloux
Voroux-Goreux
4350
Lamine
Momalle
Pousset
Remicourt
4351 Hodeige
4357
Donceel
Haneffe
Jeneffe (Lg.)
Limont
4360
Bergilers
Grandville
Lens-sur-Geer
Oreye
Otrange
4367
Crisn�e
Fize-le-Marsal
Kemexhe
Odeur
Thys
4400
Awirs
Chokier
Fl�malle
Fl�malle-Grande
Fl�malle-Haute
Gleixhe
Ivoz-Ramet
Mons-lez-Li�ge
4420
Montegn�e
Saint-Nicolas (Lg.)
Tilleur
4430 Ans
4431 Loncin
4432
Alleur
Xhendremael
4450
Juprelle
Lantin
Slins
4451 Voroux-lez-Liers
4452
Paifve
Wihogne
4453 Villers-Saint-Sim�on
4458 Fexhe-Slins
4460
Bierset
Gr�ce-Berleur
Gr�ce-Hollogne
Hollogne-aux-Pierres
Horion-Hoz�mont
Velroux
4470 Saint-Georges-sur-Meuse
4480
Clermont-sous-Huy
Engis
Hermalle-sous-Huy
4500
Ben-Ahin
Huy
Tihange
4520
Antheit
Bas-Oha
Huccorgne
Moha
Vinalmont
Wanze
4530
Fize-Fontaine
Vaux-et-Borset
Vieux-Waleffe
Villers-le-Bouillet
Warnant-Dreye
4537
Chapon-Seraing
Seraing-le-Ch�teau
Verlaine
4540
Amay
Ampsin
Fl�ne
Jehay
Ombret
4550
Nandrin
Saint-S�verin
Villers-le-Temple
Yern�e-Fraineux
4557
Ab�e
Fraiture
Ramelot
Seny
Soheit-Tinlot
Tinlot
4560
Bois-et-Borsu
Clavier
Les Avins
Ocquier
Pailhe
Terwagne
� Modave �
4570
Marchin
Vyle-et-Tharoul
4577
Modave
Outrelouxhe
Str�e-lez-Huy
Vierset-Barse
4590
Ellemelle
Ouffet
Warz�e
4600
Lanaye
Lixhe
Richelle
Vis�
4601 Argenteau
4602 Cheratte
4606 Saint-Andr�
4607
Berneau
Bombaye
Dalhem
Feneur
Mortroux
4608
Neufch�teau (Lg.)
Warsage
4610
Bellaire
Beyne-Heusay
Queue-du-Bois
4620 Fl�ron
4621 Retinne
4623 Magn�e
4624 Roms�e
4630
Ayeneux
Micheroux
Soumagne
Tign�e
4631 Evegn�e
4632 C�rexhe-Heuseux
4633 Melen
4650
Chaineux
Grand-Rechain
Herve
Jul�mont
4651 Battice
4652 Xhendelesse
4653 Bolland
4654 Charneux
4670
Bl�gny
Mortier
Trembleur
4671
Barchon
Housse
Saive
� Cheratte �
4672 Saint-Remy (Lg.)
4680
Herm�e
Oupeye
4681 Hermalle-sous-Argenteau
4682
Heure-le-Romain
Houtain-Saint-Sim�on
4683 Vivegnis
4684 Haccourt
4690
Bassenge
Boirs
Eben-Emael
Glons
Roclenge-sur-Geer
Wonck
4700
Eupen
� Waimes �
4701 Kettenis
4710 Lontzen
4711 Walhorn
4720
Kelmis
La Calamine
4721 Neu-Moresnet
4728 Hergenrath
4730
Hauset
Raeren
4731 Eynatten
4750
Butgenbach
B�tgenbach
Elsenborn
4760
Bullange
B�llingen
Manderfeld
4761 Rocherath
4770
Ambl�ve
Amel
Meyerode
4771 Heppenbach
4780
Recht
Saint-Vith
Sankt Vith
4782
Schoenberg
Sch�nberg
4783 Lommersweiler
4784 Crombach
4790
Burg-Reuland
Reuland
4791 Thommen
4800
Ensival
Lambermont
Petit-Rechain
Verviers
4801 Stembert
4802 Heusy
4820 Dison
4821 Andrimont
4830 Limbourg
4831 Bilstain
4834 Go�
4837
Baelen (Lg.)
Membach
4840 Welkenraedt
4841 Henri-Chapelle
4845
Jalhay
Sart-lez-Spa
4850
Montzen
Moresnet
Plombi�res
4851
Gemmenich
Sippenaeken
4852 Hombourg
4860
Cornesse
Pepinster
Wegnez
4861 Soiron
4870
For�t
Fraipont
Nessonvaux
Trooz
4877 Olne
4880 Aubel
4890
Clermont (Lg.)
Thimister
Thimister-Clermont
4900
Spa
� Theux �
4910
La Reid
Polleur
Theux
4920
Aywaille
Ernonheid
Harz�
Sougn�-Remouchamps
4950
Faymonville
Robertville
Sourbrodt
Waimes
Weismes
4960
Bellevaux-Ligneuville
Beverc�
Malmedy
4970
Francorchamps
Stavelot
4980
Fosse (Lg.)
Trois-Ponts
Wanne
4983 Basse-Bodeux
4987
Chevron
La Gleize
Lorc�
Rahier
Stoumont
4990
Arbrefontaine
Bra
Lierneux

5000
Beez
Namur
5001 Belgrade
5002 Saint-Servais
5003 Saint-Marc
5004 Bouge
5020
Champion
Daussoulx
Flawinne
Malonne
Suarl�e
Temploux
Vedrin
5021 Boninne
5022 Cognel�e
5024
Gelbress�e
Marche-les-Dames
5030
Beuzet
Ernage
Gembloux
Grand-Manil
Lonz�e
Sauveni�re
5031 Grand-Leez
5032
Bossi�re
Bothey
Corroy-le-Ch�teau
Isnes
Mazy
5060
Arsimont
Auvelais
Falisolle
Keumi�e
Moignel�e
Sambreville
Tamines
Velaine-sur-Sambre
5070
Aisemont
Fosses-la-Ville
Le Roux
Sart-Eustache
Sart-Saint-Laurent
Vitrival
5080
Emines
La Bruy�re
Rhisnes
Villers-lez-Heest
Warisoulx
5081
Bovesse
Meux
Saint-Denis-Bovesse
5100
Dave
Jambes (Namur)
Naninne
W�pion
Wierde
5101
Erpent
Lives-sur-Meuse
Loyers
5140
Boign�e
Ligny
Sombreffe
Tongrinne
5150
Floreffe
Floriffoux
Frani�re
Soye (Nam.)
5170
Arbre (Nam.)
Bois-de-Villers
Lesve
Lustin
Profondeville
Rivi�re
5190
Bal�tre
Ham-sur-Sambre
Jemeppe-sur-Sambre
Mornimont
Moustier-sur-Sambre
Onoz
Saint-Martin
Spy
5300
Andenne
Bonneville
Coutisse
Landenne
Maizeret
Nam�che
Sclayn
Seilles
Thon
Vezin
5310
Aische-en-Refail
Bolinne
Boneffe
Branchon
Dhuy
Eghez�e
Hanret
Leuze (Nam.)
Liernu
Longchamps (Nam.)
Mehaigne
Noville-sur-M�haigne
Saint-Germain
Taviers (Nam.)
Upigny
Waret-la-Chauss�e
5330
Assesse
Maillen
Sart-Bernard
5332 Crupet
5333 Sorinne-la-Longue
5334 Flor�e
5336 Courri�re
5340
Faulx-les-Tombes
Gesves
Haltinne
Mozet
Sor�e
5350
Evelette
Ohey
� Gesves �
5351 Haillot
5352 Perwez-Haillot
5353 Goesnes
5354 Jallet
5360
Hamois
Natoye
5361
Mohiville
Scy
5362 Achet
5363 Emptinne
5364 Schaltin
5370
Barvaux-Condroz
Flostoy
Havelange
Jeneffe (Nam.)
Porcheresse (Nam.)
Verl�e
5372 M�an
5374 Maffe
5376 Mi�cret
5377
Baillonville
Bonsin
Heure (Nam.)
Hogne
Nettinne
Noiseux
Sinsin
Somme-Leuze
Waillet
5380
Bierwart
Cortil-Wodon
Fernelmont
Forville
Franc-Waret
Hemptinne (Fernelmont)
Hingeon
Marchovelette
Noville-les-Bois
Pontillas
Tillier
5500
Anseremme
Bouvignes-sur-Meuse
Dinant
Dr�hance
Falmagne
Falmignoul
Furfooz
5501 Lisogne
5502 Thynes
5503 Sorinnes
5504 Foy-Notre-Dame
5520
Anth�e
Onhaye
5521 Serville
5522 Fala�n
5523
Sommi�re
Weillen
5524 Gerin
5530
Dorinne
Durnal
Evrehailles
Godinne
Houx
Mont (Nam.)
Purnode
Spontin
Yvoir
5537
Anh�e
Annevoie-Rouillon
Bioul
Den�e
Haut-le-Wastia
Sosoye
Warnant
5540
Hasti�re
Hasti�re-Lavaux
Hermeton-sur-Meuse
Waulsort
5541 Hasti�re-par-Del�
5542 Blaimont
5543 Heer
5544 Agimont
5550
Alle
Bagimont
Bohan
Chairi�re
Lafor�t
Membre
Mouzaive
Nafraiture
Orchimont
Pussemange
Sugny
Vresse-sur-Semois
5555
Baillamont
Bellefontaine (Nam.)
Bievre
Cornimont
Graide
Gros-Fays
Monceau-en-Ardenne
Naom�
Oizy
Petit-Fays
5560
Ciergnon
Finnevaux
Houyet
Hulsonniaux
Mesnil-Eglise
Mesnil-Saint-Blaise
5561 Celles (Nam.)
5562 Custinne
5563 Hour
5564 Wanlin
5570
Baronville
Beauraing
Dion
Felenne
Feschaux
Honnay
Javingue
Von�che
Wancennes
Winenne
5571 Wiesme
5572 Focant
5573 Martouzin-Neuville
5574 Pondr�me
5575
Bourseigne-Neuve
Bourseigne-Vieille
Gedinne
Houdremont
Louette-Saint-Denis
Louette-Saint-Pierre
Malvoisin
Patignies
Rienne
Sart-Custinne
Vencimont
Willerzie
� Beauraing �
5576 Froidfontaine
5580
Ave-et-Auffe
Buissonville
Eprave
Han-sur-Lesse
Jemelle
Lavaux-Sainte-Anne
Lessive
Mont-Gauthier
Rochefort
Villers-sur-Lesse
Wavreille
5590
Ach�ne
Braibant
Chevetogne
Ciney
Conneux
Haversin
Leignon
Pessoux
Serinchamps
Sovet
5600
Fagnolle
Franchimont
Jamagne
Jamiolle
Merlemont
Neuville (Philippeville)
Omez�e
Philippeville
Roly
Romedenne
Samart
Sart-en-Fagne
Sautour
Surice
Villers-en-Fagne
Villers-le-Gambon
Vodec�e
5620
Corenne
Flavion
Florennes
Hemptinne-lez-Florennes
Morville
Ros�e
Saint-Aubin
5621
Hanzinelle
Hanzinne
Morialm�
Thy-le-Bauduin
5630
Cerfontaine
Daussois
Senzeille
Silenrieux
Soumoy
Villers-Deux-Eglises
5640
Biesme
Biesmer�e
Graux
Mettet
Oret
Saint-G�rard
5641 Furnaux
5644 Ermeton-sur-Biert
5646 Stave
5650
Castillon
Chastr�s
Clermont (Nam.)
Fontenelle
Fraire
Pry
Vogen�e
Walcourt
Yves-Gomez�e
5651
Berz�e
Gourdinne
Laneffe
Rogn�e
Somz�e
Tarcienne
Thy-le-Ch�teau
5660
Aublain
Boussu-en-Fagne
Br�ly
Br�ly-de-Pesche
Couvin
Cul-des-Sarts
Dailly
Frasnes (Nam.)
Gonrieux
Mariembourg
Pesche
Petigny
Petite-Chapelle
Presgaux
5670
Dourbes
Le Mesnil
Maz�e
Nismes
Oignies-en-Thi�rache
Olloy-sur-Viroin
Treignes
Vierves-sur-Viroin
Viroinval
5680
Doische
Gimn�e
Gochen�e
Matagne-la-Grande
Matagne-la-Petite
Niverl�e
Romer�e
Soulme
Vaucelles
Vodel�e

6000 Charleroi
6001 Marcinelle
6010 Couillet
6020 Dampremy
6030
Goutroux
Marchienne-au-Pont
6031 Monceau-sur-Sambre
6032 Mont-sur-Marchienne
6040 Jumet (Charleroi)
6041 Gosselies
6042 Lodelinsart
6043 Ransart
6044 Roux
6060 Gilly (Charleroi)
6061 Montignies-sur-Sambre
6110 Montigny-le-Tilleul
6111 Landelies
6120
Cour-sur-Heure
Ham-sur-Heure
Ham-sur-Heure-Nalinnes
Jamioulx
Marbaix (Ht.)
Nalinnes
6140 Fontaine-l'Ev�que
6141 Forchies-la-Marche
6142 Leernes
6150 Anderlues
6180 Courcelles
6181 Gouy-lez-Pi�ton
6182 Souvret
6183 Trazegnies
6200
Bouffioulx
Ch�telet
Ch�telineau
6210
Frasnes-lez-Gosselies
Les Bons Villers
R�ves
Villers-Perwin
Wayaux
6211 Mellet
6220
Fleurus
Heppignies
Lambusart
Wangenies
6221 Saint-Amand
6222 Brye
6223 Wagnel�e
6224 Wanferc�e-Baulet
6230
Buzet
Obaix
Pont-�-Celles
Thim�on
Viesville
6238
Liberchies
Luttre
6240
Farciennes
Pironchamps
6250
Aiseau
Aiseau-Presles
Pont-de-Loup
Presles
Roselies
6280
Acoz
Gerpinnes
Gougnies
Joncret
Loverval
Villers-Poterie
6440
Boussu-lez-Walcourt
Fourbechies
Froidchapelle
Vergnies
6441 Erpion
6460
Baili�vre
Chimay
Robechies
Saint-Remy (Ht.)
Salles
Villers-la-Tour
6461 Virelles
6462 Vaulx-lez-Chimay
6463 Lompret
6464
Baileux
Bourlers
Forges
l'Escaill�re
Ri�zes
6470
Grandrieu
Montbliart
Rance
Sautin
Sivry
Sivry-Rance
6500
Barben�on
Beaumont
Leugnies
Leval-Chaudeville
Renlies
Solre-Saint-G�ry
Thirimont
6511 Str�e (Ht.)
6530
Leers-et-Fosteau
Thuin
6531 Biesme-sous-Thuin
6532 Ragnies
6533 Bierc�e
6534 Goz�e
6536
Donstiennes
Thuillies
6540
Lobbes
Mont-Sainte-Genevi�ve
6542 Sars-la-Buissi�re
6543 Bienne-lez-Happart
6560
Bersillies-l'Abbaye
Erquelinnes
Grand-Reng
Hantes-Wih�ries
Montignies-Saint-Christophe
Solre-sur-Sambre
6567
Fontaine-Valmont
Labuissi�re
Merbes-le-Ch�teau
Merbes-Sainte-Marie
6590 Momignies
6591 Macon
6592 Monceau-Imbrechies
6593 Macquenoise
6594 Beauwelz
6596
Forge-Philippe
Seloignes

6600
Bastogne
Longvilly
Noville (Lux.)
Villers-la-Bonne-Eau
Wardin
6630 Martelange
6637
Fauvillers
Hollange
Tintange
6640
Hompr�
Morhet
Nives
Sibret
Vaux-lez-Rosi�res
Vaux-sur-Sure
6642 Juseret
6660
Houffalize
Nadrin
6661
Mont (Lux.)
Tailles
6662 Tavigny
6663 Mabompr�
6666 Wibrin
6670
Gouvy
Limerl�
6671 Bovigny
6672 Beho
6673 Cherain
6674 Montleban
6680
Amberloup
Sainte-Ode
Tillet
6681 Lavacherie
6686 Flamierge
6687 Bertogne
6688 Longchamps (Lux.)
6690
Bihain
Vielsalm
6692 Petit-Thier
6698 Grand-Halleux
6700
Arlon
Bonnert
Heinsch
Toernich
6704 Guirsch
6706 Autelbas
6717
Attert
Nobressart
Nothomb
Thiaumont
Tontelange
6720
Habay
Habay-la-Neuve
Hachy
6721 Anlier
6723 Habay-la-Vieille
6724
Houdemont
Rules
6730
Bellefontaine (Lux.)
Rossignol
Saint-Vincent
Tintigny
6740
Etalle
Sainte-Marie-sur-Semois
Villers-sur-Semois
6741 Vance
6742 Chantemelle
6743 Buzenol
6747
Ch�tillon
Meix-le-Tige
Saint-L�ger (Lux.)
6750
Musson
Mussy-la-Ville
Signeulx
6760
Bleid
Ethe
Ruette
Virton
6761 Latour
6762 Saint-Mard
6767
Dampicourt
Harnoncourt
Lamorteau
Rouvroy
Torgny
6769
G�rouville
Meix-Devant-Virton
Robelmont
Sommethonne
Villers-la-Loue
6780
Hondelange
Messancy
Wolkrange
6781 S�lange
6782 Habergy
6790 Aubange
6791 Athus
6792
Halanzy
Rachecourt
6800
Bras
Freux
Libramont-Chevigny
Moircy
Recogne
Remagne
Sainte-Marie-Chevigny
Saint-Pierre
6810
Chiny
Izel
Jamoigne
6811 Les Bulles
6812 Suxy
6813 Termes
6820
Florenville
Fontenoille
Muno
Sainte-C�cile
6821 Lacuisine
6823 Villers-Devant-Orval
6824 Chassepierre
6830
Bouillon
Les Hayons
Poupehan
Rochehaut
6831 Noirefontaine
6832 Sensenruth
6833
Ucimont
Vivy
6834 Bellevaux
6836 Dohan
6838 Corbion
6840
Grandvoir
Grapfontaine
Hamipr�
Longlier
Neufch�teau
Tournay
6850
Carlsbourg
Offagne
Paliseul
6851 Nollevaux
6852
Maissin
Opont
6853 Framont
6856 Fays-les-Veneurs
6860
Assenois
Ebly
L�glise
Mellier
Witry
6870
Arville
Awenne
Hatrival
Mirwart
Saint-Hubert
Vesqueville
� Bras �
6880
Auby-sur-Semois
Bertrix
Cugnon
Jehonville
Orgeo
6887
Herbeumont
Saint-M�dard
Straimont
6890
Anloy
Libin
Ochamps
Redu
Smuid
Transinne
Villance
6900
Aye
Hargimont
Humain
Marche-en-Famenne
On
Roy
Waha
6920
Sohier
Wellin
� Rochefort �
6921 Chanly
6922 Halma
6924 Lomprez
6927
Bure
Grupont
Resteigne
Tellin
6929
Daverdisse
Gembes
Haut-Fays
Porcheresse(Lux.)
6940
Barvaux-sur-Ourthe
Durbuy
Grandhan
Septon
W�ris
6941
Bende
Bomal-sur-Ourthe
Borlon
Heyd
Izier
Tohogne
Villers-Sainte-Gertrude
6950
Harsin
Nassogne
6951 Bande
6952 Grune
6953
Ambly
Forri�res
Lesterny
Masbourg
6960
Dochamps
Grandmenil
Harre
Malempr�
Manhay
Odeigne
Vaux-Chavanne
6970 Tenneville
6971 Champlon
6972 Erneuville
6980
Beausaint
La Roche-en-Ardenne
6982 Samr�e
6983 Ortho
6984 Hives
6986 Halleux
6987
Beffe
Hodister
Marcourt
Rendeux
6990
Fronville
Hampteau
Hotton
Marenne
6997
Amonines
Erez�e
Mormont
Soy

7000 Mons
7010
S.H.A.P.E. Belgi�
S.H.A.P.E. Belgique
7011 Ghlin (Mons)
7012
Fl�nu (Mons)
Jemappes (Mons)
7020
Maisi�res (Mons)
Nimy (Mons)
7021 Havr� (Mons)
7022
Harmignies (Mons)
Harveng (Mons)
Hyon (Mons)
Mesvin (Mons)
Nouvelles (Mons)
7024 Ciply (Mons)
7030 Saint-Symphorien (Mons)
7031 Villers-Saint-Ghislain (Mons)
7032 Spiennes (Mons)
7033 Cuesmes (Mons)
7034
Obourg (Mons)
Saint-Denis (Mons)
7040
Asquillies
Aulnois
Blaregnies
Bougnies
Genly
Goegnies-Chauss�e
Qu�vy
Qu�vy-le-Grand
Qu�vy-le-Petit
7041
Givry
Havay
7050
Erbaut
Erbisoeul
Herchies
Jurbise
Masnuy-Saint-Jean (Jurbise)
Masnuy-Saint-Pierre
7060
Horrues
Soignies
7061
Casteau (Soignies)
Thieusies
7062 Naast
7063
Chauss�e-Notre-Dame-Louvignies
Neufvilles
7070
Gottignies
Le Roeulx
Mignault
Thieu
Ville-sur-Haine (Le Roeulx)
7080
Eugies (Frameries)
Frameries
La Bouverie
Noirchain
Sars-la-Bruy�re
7090
Braine-le-Comte
Hennuy�res
Henripont
Petit-Roeulx-lez-Braine
Ronqui�res
Steenkerque (Ht.)
7100
Haine-Saint-Paul
Haine-Saint-Pierre
La Louvi�re
Saint-Vaast
Trivi�res
7110
Boussoit
Houdeng-Aimeries
Houdeng-Goegnies (La Louvi�re)
Maurage
Str�py-Bracquegnies
7120
Croix-lez-Rouveroy
Estinnes
Estinnes-au-Mont
Estinnes-au-Val
Fauroeulx
Haulchin
Peissant
Rouveroy (Ht.)
Vellereille-les-Brayeux
Vellereille-le-Sec
7130
Battignies
Binche
Bray
7131 Waudrez
7133 Buvrinnes
7134
Epinois
Leval-Trahegnies
P�ronnes-lez-Binche
Ressaix
7140
Morlanwelz
Morlanwelz-Mariemont
7141
Carni�res
Mont-Sainte-Aldegonde
7160
Chapelle-lez-Herlaimont
Godarville
Pi�ton
7170
Bellecourt
Bois-d'Haine
Fayt-lez-Manage
La Hestre
Manage
7180
Seneffe
� Ecaussinnes �
7181
Arquennes
Familleureux
Feluy
Petit-Roeulx-lez-Nivelles
7190
Ecaussinnes
Ecaussinnes-d'Enghien
Marche-lez-Ecaussinnes
7191 Ecaussinnes-Lalaing
7300 Boussu
7301 Hornu
7320 Bernissart
7321
Blaton
Harchies
7322
Pommeroeul
Ville-Pommeroeul
7330 Saint-Ghislain
7331 Baudour
7332
Neufmaison
Sirault
7333 Tertre
7334
Hautrage
Villerot
7340
Colfontaine
Paturages
Warquignies
Wasmes
7350
Hainin
Hensies
Montroeul-sur-Haine
Thulin
7370
Blaugies
Dour
Elouges
Wih�ries
7380
Baisieux
Qui�vrain
7382 Audregnies
7387
Angre
Angreau
Athis
Autreppe
Erquennes
Fayt-le-Franc
Honnelles
Marchipont
Montignies-sur-Roc
Onnezies
Roisin
7390
Quaregnon
Wasmuel
7500
Ere
Saint-Maur
Tournai
7501 Orcq
7502 Esplechin
7503 Froyennes
7504 Froidmont
7506 Willemeau
7520
Ramegnies-Chin
Templeuve
7521 Chercq
7522
Blandain
Hertain
Lamain
Marquain
7530 Gaurain-Ramecroix (Tournai)
7531 Havinnes
7532 Beclers
7533 Thimougies
7534
Barry
Maulde
7536 Vaulx (Tournai)
7538 Vezon
7540
Kain
Melles
Quartes
Rumillies
7542 Mont-Saint-Aubert
7543 Mourcourt
7548 Warchin
7600 P�ruwelz
7601 Roucourt
7602 Bury
7603 Bon-Secours
7604
Baugnies
Braffe
Brasmenil
Callenelle
Wasmes-Audemez-Briffoeil
7608 Wiers
7610 Rumes
7611 La Glanerie
7618 Taintignies
7620
Bl�haries
Brunehaut
Guignies
Hollain
Jollain-Merlin
Wez-Velvain
7621 Lesdain
7622 Laplaigne
7623 Rongy
7624 Howardries
7640
Antoing
Maubray
P�ronnes-lez-Antoing
7641 Bruyelle
7642 Calonne
7643 Fontenoy
7700
Luingne
Moeskroen
Mouscron
7711
Dottenijs
Dottignies
7712 Herseaux
7730
Bailleul
Estaimbourg
Estaimpuis
Evregnies
Leers-Nord
N�chin
Saint-L�ger (Ht.)
7740
Pecq
Warcoing
7742 H�rinnes-lez-Pecq
7743
Esquelmes
Obigies
7750
Amougies
Anseroeul
Mont-de-l'Enclus
Orroir
Russeignies
7760
Celles (Ht.)
Escanaffles
Molenbaix
Popuelles
Pottes
Velaines
7780
Comines
Comines-Warneton
Komen
Komen-Waasten
7781 Houthem (Comines)
7782 Ploegsteert
7783 Bizet
7784
Bas-Warneton
Neerwaasten
Waasten
Warneton
7800
Ath
Lanquesaint
7801 Irchonwelz
7802 Ormeignies
7803 Bouvignies
7804
Ostiches
Rebaix
7810 Maffle
7811 Arbre (Ht.)
7812
Houtaing
Ligne
Mainvault
Moulbaix
Villers-Notre-Dame
Villers-Saint-Amand
7822
Ghislenghien
Isi�res
Meslin-l'Ev�que
7823 Gibecq
7830
Bassilly
Fouleng
Gondregnies
Graty
Hellebecq
Hoves (Ht.)
Silly
Thoricourt
7850
Edingen
Enghien
Lettelingen
Marcq
Mark
Petit-Enghien
7860 Lessines
7861
Papignies
Wannebecq
7862 Ogy
7863 Ghoy
7864 Deux-Acren
7866
Bois-de-Lessines
Ollignies
7870
Bauffe
Cambron-Saint-Vincent
Lens
Lombise
Montignies-lez-Lens
7880
Flobecq
Vloesberg
7890
Ellezelles
Lahamaide
Wodecq
7900
Grandmetz
Leuze-en-Hainaut
7901 Thieulain
7903
Blicquy
Chapelle-�-Oie
Chapelle-�-Wattines
7904
Pipaix
Tourpes
Willaupuis
7906 Gallaix
7910
Anvaing
Arc-Aini�res
Arc-Wattripont
Cordes
Ellignies-lez-Frasnes
Forest (Ht.)
Frasnes-lez-Anvaing
Wattripont
7911
Buissenal
Frasnes-lez-Buissenal
Hacquegnies
Herquegies
Montroeul-au-Bois
Moustier (Ht.)
Oeudeghien
7912
Dergneau
Saint-Sauveur
7940
Brugelette
Cambron-Casteau
7941 Attre
7942 M�vergnies-lez-Lens
7943 Gages
7950
Chi�vres
Grosage
Huissignies
Ladeuze
Tongre-Saint-Martin
7951 Tongre-Notre-Dame
7970 Beloeil
7971
Bas�cles
Ramegnies
Thumaide
Wadelincourt
7972
Aubechies
Ellignies-Sainte-Anne
Quevaucamps
7973
Grandglise
Stambruges

8000
Brugge
Koolkerke
8020
Hertsberge
Oostkamp
Ruddervoorde
Waardamme
8200
Sint-Andries
Sint-Michiels
8210
Loppem
Veldegem
Zedelgem
8211 Aartrijke
8300
Knokke
Knokke-Heist
Westkapelle
8301
Heist-aan-Zee
Ramskapelle (Knokke-Heist)
8310
Assebroek
Sint-Kruis (Brugge)
8340
Damme
Hoeke
Lapscheure
Moerkerke
Oostkerke (Damme)
Sijsele
8370
Blankenberge
Uitkerke
8377
Houtave
Meetkerke
Nieuwmunster
Zuienkerke
8380
Dudzele
Lissewege
Zeebrugge (Brugge)
8400
Oostende
Stene
Zandvoorde (Oostende)
8420
De Haan
Klemskerke
Wenduine
8421 Vlissegem
8430 Middelkerke
8431 Wilskerke
8432 Leffinge
8433
Mannekensvere
Schore
Sint-Pieters-Kapelle (W.-Vl.)
Slijpe
Spermalie
8434
Lombardsijde
Westende
8450 Bredene
8460
Ettelgem
Oudenburg
Roksem
Westkerke
8470
Gistel
Moere
Snaaskerke
Zevekote
8480
Bekegem
Eernegem
Ichtegem
8490
Jabbeke
Snellegem
Stalhille
Varsenare
Zerkegem
8500 Kortrijk
8501
Bissegem
Heule
8510
Bellegem
Kooigem
Marke (Kortrijk)
Rollegem
8511 Aalbeke
8520 Kuurne
8530 Harelbeke
8531
Bavikhove
Hulste
8540 Deerlijk
8550 Zwevegem
8551 Heestert
8552 Moen
8553 Otegem
8554 Sint-Denijs
8560
Gullegem
Moorsele
Wevelgem
8570
Anzegem
Gijzelbrechtegem
Ingooigem
Vichte
� Wortegem-Petegem �
8572 Kaster
8573 Tiegem
8580 Avelgem
8581
Kerkhove
Waarmaarde
8582 Outrijve
8583 Bossuit
8587
Espierres
Espierres-Helchin
Helchin
Helkijn
Spiere
Spiere-Helkijn
8600
Beerst
Diksmuide
Driekapellen
Esen
Kaaskerke
Keiem
Lampernisse
Leke
Nieuwkapelle
Oostkerke (Diksmuide)
Oudekapelle
Pervijze
Sint-Jacobs-Kapelle
Stuivekenskerke
Vladslo
Woumen
8610
Handzame
Kortemark
Werken
Zarren
8620
Nieuwpoort
Ramskapelle (Nieuwpoort)
Sint-Joris (Nieuwpoort)
8630
Avekapelle
Beauvoorde
Booitshoeke
Bulskamp
De Moeren
Eggewaartskapelle
Houtem (West Vlaanderen)
Steenkerke (West Vlaanderen)
Veurne
Vinkem
Wulveringem
Zoutenaaie
8640
Oostvleteren
Vleteren
Westvleteren
Woesten
8647
Lo
Lo-Reninge
Noordschote
Pollinkhove
Reninge
8650
Houthulst
Klerken
Merkem
8660
Adinkerke
De Panne
8670
Koksijde
Oostduinkerke
Wulpen
8680
Bovekerke
Koekelare
Zande
8690
Alveringem
Hoogstade
Oeren
Sint-Rijkers
8691
Beveren-aan-den-Ijzer
Gijverinkhove
Izenberge
Leisele
Stavele
8700
Aarsele
Kanegem
Schuiferskapelle
Tielt
8710
Ooigem
Sint-Baafs-Vijve
Wielsbeke
8720
Dentergem
Markegem
Oeselgem
Wakken
8730
Beernem
Oedelem
Sint-Joris (Beernem)
8740
Egem
Pittem
8750
Wingene
Zwevezele
8755 Ruiselede
8760 Meulebeke
8770 Ingelmunster
8780 Oostrozebeke
8790 Waregem
8791 Beveren (Leie)
8792 Desselgem
8793 Sint-Eloois-Vijve
8800
Beveren (Roeselare)
Oekene
Roeselare
Rumbeke
8810 Lichtervelde
8820 Torhout
8830
Gits
Hooglede
8840
Oostnieuwkerke
Staden
Westrozebeke
8850 Ardooie
8851 Koolskamp
8860 Lendelede
8870
Emelgem
Izegem
Kachtem
8880
Ledegem
Rollegem-Kapelle
Sint-Eloois-Winkel
8890
Dadizele
Moorslede
8900
Brielen
Dikkebus
Ieper
Sint-Jan
8902
Hollebeke
Voormezele
Zillebeke
8904
Boezinge
Zuidschote
8906 Elverdinge
8908 Vlamertinge
8920
Bikschote
Langemark
Langemark-Poelkapelle
Poelkapelle
8930
Lauwe
Menen
Rekkem
8940
Geluwe
Wervik
8950
Heuvelland
Nieuwkerke
8951 Dranouter
8952 Wulvergem
8953 Wijtschate
8954 Westouter
8956 Kemmel
8957
Mesen
Messines
8958 Loker
8970
Poperinge
Reningelst
8972
Krombeke
Proven
Roesbrugge-Haringe
8978 Watou
8980
Beselare
Geluveld
Passendale
Zandvoorde (Zonnebeke)
Zonnebeke

9000 Gent
9030 Mariakerke (Gent)
9031 Drongen
9032 Wondelgem
9040 Sint-Amandsberg (Gent)
9041 Oostakker
9042
Desteldonk
Mendonk
Sint-Kruis-Winkel
9050
Gentbrugge
Ledeberg (Gent)
9051
Afsnee
Sint-Denijs-Westrem
9052 Zwijnaarde
9060 Zelzate
9070
Destelbergen
Heusden (Oost Vlaanderen)
9080
Beervelde
Lochristi
Zaffelare
Zeveneken
9090
Gontrode
Melle
9100
Nieuwkerken-Waas
Sint-Niklaas
9111 Belsele (Sint-Niklaas)
9112 Sinaai-Waas
9120
Beveren-Waas
Haasdonk
Kallo (Beveren-Waas)
Melsele
Vrasene
9130
Doel
Kallo (Kieldrecht)
Kieldrecht (Beveren)
Verrebroek
9140
Elversele
Steendorp
Temse
Tielrode
9150
Bazel
Kruibeke
Rupelmonde
9160
Daknam
Eksaarde
Lokeren
9170
De Klinge
Meerdonk
Sint-Gillis-Waas
Sint-Pauwels
9180 Moerbeke-Waas
9185 Wachtebeke
9190
Kemzeke
Stekene
9200
Appels
Baasrode
Dendermonde
Grembergen
Mespelare
Oudegem
Schoonaarde
Sint-Gillis-bij-Dendermonde
9220
Hamme (Oost Vlaanderen)
Moerzeke
9230
Massemen
Westrem
Wetteren
9240 Zele
9250 Waasmunster
9255
Buggenhout
Opdorp
9260
Schellebelle
Serskamp
Wichelen
9270
Kalken
Laarne
9280
Denderbelle
Lebbeke
Wieze
9290
Berlare
Overmere
Uitbergen
9300 Aalst
9308
Gijzegem (Aalst)
Hofstade (Aalst)
9310
Baardegem (Aalst)
Herdersem (Aalst)
Meldert (Aalst)
Moorsel (Aalst)
9320
Erembodegem (Aalst)
Nieuwerkerken (Aalst)
9340
Impe
Lede
Oordegem
Smetlede
Wanzele
9400
Appelterre-Eichem
Denderwindeke
Lieferinge
Nederhasselt
Ninove
Okegem
Voorde
9401 Pollare
9402 Meerbeke
9403 Neigem
9404 Aspelare
9406 Outer
9420
Aaigem
Bambrugge
Burst
Erondegem
Erpe
Erpe-Mere
Mere
Ottergem
Vlekkem
9450
Denderhoutem
Haaltert
Heldergem
9451 Kerksken
9470 Denderleeuw
9472 Iddergem
9473 Welle
9500
Geraardsbergen
Goeferdinge
Moerbeke
Nederboelare
Onkerzele
Ophasselt
Overboelare
Viane
Zarlardinge
9506
Grimminge
Idegem
Nieuwenhove
Schendelbeke
Smeerebbe-Vloerzegem
Waarbeke
Zandbergen
9520
Bavegem
Sint-Lievens-Houtem
Vlierzele
Zonnegem
9521 Letterhoutem
9550
Herzele
Hillegem
Sint-Antelinks
Sint-Lievens-Esse
Steenhuize-Wijnhuize
Woubrechtegem
9551 Ressegem
9552 Borsbeke
9570
Deftinge
Lierde
Sint-Maria-Lierde
9571 Hemelveerdegem
9572 Sint-Martens-Lierde
9600
Renaix
Ronse
9620
Elene
Erwetegem
Godveerdegem
Grotenberge
Leeuwergem
Oombergen (Zottegem)
Sint-Goriks-Oudenhove
Sint-Maria-Oudenhove (Zottegem)
Strijpen
Velzeke-Ruddershove
Zottegem
9630
Beerlegem
Dikkele
Hundelgem
Meilegem
Munkzwalm
Paulatem
Roborst
Rozebeke
Sint-Blasius-Boekel
Sint-Denijs-Boekel
Sint-Maria-Latem
Zwalm
9636 Nederzwalm-Hermelgem
9660
Brakel
Elst
Everbeek
Michelbeke
Nederbrakel
Opbrakel
Zegelsem
9661 Parike
9667
Horebeke
Sint-Kornelis-Horebeke
Sint-Maria-Horebeke
9680
Etikhove
Maarkedal
Maarke-Kerkem
9681 Nukerke
9688 Schorisse
9690
Berchem (Oost Vlaanderen)
Kluisbergen
Kwaremont
Ruien
Zulzeke
9700
Bevere
Edelare
Eine
Ename
Heurne
Leupegem
Mater
Melden
Mullem
Nederename
Oudenaarde
Volkegem
Welden
9750
Huise
Ouwegem
Zingem
9770 Kruishoutem
9771 Nokere
9772 Wannegem-Lede
9790
Elsegem
Moregem
Ooike (Wortegem-Petegem)
Petegem-aan-de-Schelde
Wortegem
Wortegem-Petegem
9800
Astene
Bachte-Maria-Leerne
Deinze
Gottem
Grammene
Meigem
Petegem-aan-de-Leie
Sint-Martens-Leerne
Vinkt
Wontergem
Zeveren
9810
Eke
Nazareth
9820
Bottelare
Lemberge
Melsen
Merelbeke
Munte
Schelderode
9830 Sint-Martens-Latem
9831 Deurle
9840
De Pinte
Zevergem
9850
Hansbeke
Landegem
Merendree
Nevele
Poesele
Vosselare
9860
Balegem
Gijzenzele
Landskouter
Moortsele
Oosterzele
Scheldewindeke
9870
Machelen (Oost Vlaanderen)
Olsene
Zulte
9880
Aalter
Lotenhulle
Poeke
9881 Bellem
9890
Asper
Baaigem
Dikkelvenne
Gavere
Semmerzake
Vurste
9900 Eeklo
9910
Knesselare
Ursel
9920 Lovendegem
9921 Vinderhoute
9930 Zomergem
9931 Oostwinkel
9932 Ronsele
9940 Evergem
Ertvelde
Kluizen
Sleidinge
9950 Waarschoot
9960 Assenede
9961 Boekhoute
9968
Bassevelde
Oosteeklo
9970 Kaprijke
9971 Lembeke
9980 Sint-Laureins
9981 Sint-Margriete
9982 Sint-Jan-in-Eremo
9988
Waterland-Oudeman
Watervliet
9990 Maldegem
9991 Adegem
9992 Middelburg
DATA;
	return explode("\n", $data);
}
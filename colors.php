<?
$colors = "    #59922b,
  #d17905,
  #453d3f,
  #d70206,
  #f05b4f,
  #f4c63d,
  #0544d3,
  #6b0392,
  #f05b4f,
  #dda458,
  #eacf7d,
  #86797d,
  #b2c326,
  #6188e2,
  #a748ca,
  black,
  red,
  darkred,
  blue,
  darkblue,
  green,
  darkgreen
";
$colors = array_map('trim', explode(',', $colors));
foreach($colors as $c) 
	echo "<div style='width:20px;height:15px;background:$c;display:inline-block;'></div> $c <br>";;
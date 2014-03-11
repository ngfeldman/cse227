<?php
error_reporting(E_ALL);

// connect
$m = new MongoClient();
$db = $m->Tracking3;

$c1 = $db->sites;
$c2 = $db->netlog;

$numSites = $c1->count();
$sites = $c1->find()->sort(array("sitenum"=>1));

$siteArr = array();
foreach ($sites as $site) {
	$siteArr[] = $site;
}
$siteCount = count($siteArr);
$missing = array();
for($i=0; $i < $siteCount-1; $i++) {
	if ($siteArr[$i+1]["sitenum"] - $siteArr[$i]["sitenum"] != 1) {
		$j = $siteArr[$i]["sitenum"]+1;
		while($j < $siteArr[$i+1]["sitenum"]) {
			$missing[] = $j;	
			$j++;
		}
	}
}
$missingCount = count($missing);
	
?>
<html>
	<head>
		<title>DB Complete</title>
	</head>
	<body>
		<div id="formbox">
			<form id="dbform" action="dbView.php" method="post">
				
			</form>
		</div>
		<div id="databox">
				<?php 
				echo "siteCount: " . $siteCount . "<br/>";
				echo "missingCount: " . $missingCount . "<br/>";
				
				foreach ($missing as $m) {
					echo "phantomjs netlog.js 1 " . $m . " 1<br/>";
				} 
				?>
		</div>
	</body>
</html>
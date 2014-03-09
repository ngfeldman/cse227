<?php
error_reporting(E_ALL);

// connect
$m = new MongoClient();
$db = $m->Tracking3;

$c1 = $db->sites;
$c2 = $db->netlog;

$numSites = $c1->count();
$sites = $c1->find()->sort(array("sitenum"=>1))->limit(100);
$siteArr = array();

$i=0;
foreach ($sites as $site) {
	$siteArr[$site["sitenum"]] = $site;
	$requestCount = $c2->find(array("siteId"=>$site['_id'], "packetType"=>"request"))->count();
	$responseCount = $c2->find(array("siteId"=>$site['_id'], "packetType"=>"response"))->count();
	$siteArr[$site["sitenum"]]["requestCount"] = $requestCount;
	$siteArr[$site["sitenum"]]["responseCount"] = $responseCount;
}


if (isset($_GET['s']) && (!empty($_GET['s']) || $_GET['s'] == 0)) {
	$sitenum = intval($_GET['s']);
	
	$s = $c1->findOne(array("sitenum"=>$sitenum));
	$r = $c2->find(array("siteId"=>$s['_id']))->sort(array("time"=>1));
	$records = array();
	foreach ($r as $rec) {
		$records[] = $rec;
	}
	$disp = TRUE;
}
else {
	$disp = FALSE;
}
	
?>
<html>
	<head>
		<title>DB Viewer</title>
	</head>
	<body>
		<div id="sitebox">
			<?php
			if ($disp) {
				?>
			<h3></h3>
			<strong>Records:</strong>
			<table border="1">
				<tr>
					<th>#</th>
					<th>Time</th>
					<th>Type</th>
					<th>Id</th>
					<th>URL</th>
					<th>ContentType</th>
				</tr>
				<?php
				$i=0;
				foreach ($records as $record) {
					$d = $record["data"];
					
					if ($record['packetType'] == "response"  || $record['packetType'] == "request") {
						$ct = "";
						foreach ($d['headers'] as $head) {
							if ($head['name'] == "Content-Type") {
								$ct = $head['value'];
							}
						}
				?>
				<tr>
					<td><?php echo $i; ?></td>
					<td><?php echo $record["time"]; ?></td>
					<td><?php echo $record["packetType"]; ?></td>
					<td><?php echo $d["id"]; ?></td>
					<td><?php echo $d["url"]; ?></td>
					<td><?php echo $ct; ?></td>
				</tr>
				<?php
					}
					else {
						switch($d) {
							case 0: $msg = "page loaded"; break;
							case 1: $msg = "mouse activity starts"; break;
							case 2: $msg = "mouse activity pauses"; break;
							case 3: $msg = "mouse activity restarts"; break;
							case 4: $msg = "mouse finished"; break;
						}
				?>
				<tr>
					<td><?php echo $i; ?></td>
					<td><?php echo $record["time"]; ?></td>
					<td>flag</td>
					<td colspan="3"><?php echo $msg; ?></td>
				</tr>
				<?php
					}
					$i++;
				}
				?>
			</table>
				
				<?php
			}
			?>
		</div>
		<div id="listingbox">
			<table border="1">
				<tr>
					<th>Site#</th>
					<th>URL</th>
					<th>Requests</th>
					<th>Responses</th>
				</tr>
				<?php 
				foreach ($siteArr as $site) {
					?>
				<tr>
					<td><?php echo $site['sitenum']; ?></td>
					<td><a href="dbView.php?s=<?php echo $site['sitenum']; ?>"><?php echo $site['url']; ?></a></td>
					<td><?php echo $site['requestCount']; ?></td>
					<td><?php echo $site['responseCount']; ?></td>
				</tr>
					<?php
				} 
				?>
			</pre>
		</div>
	</body>
</html>
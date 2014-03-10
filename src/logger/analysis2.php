<?php
$m = new MongoClient();
$db = $m->Tracking3;

$sites_col = $db->sites;
$netlog_col = $db->netlog;

$xhrs = $netlog_col->find(array("data.url" => array( '$regex' => ".*clicktale.*" ), "packetType" => "request"))->timeout(300000);

$site_ids = array();

foreach ($xhrs as $xhr) {
  $site_id = $xhr["siteId"];
  if (isset($site_ids[(string) $site_id]))
    ++$site_ids[(string) $site_id][0];
  else
    $site_ids[(string) $site_id] = array(1, $site_id);
}

$sites = array();


foreach ($site_ids as $site_id => $a) {
  $count = $a[0];
  $site_id = $a[1];
  $site = $sites_col->findOne(array("_id" => $site_id));
  $sites[$site["sitenum"]] = array($site["url"], $count);
}

ksort($sites);

foreach ($sites as $i => $a) {
  echo $i . ": \t" . $a[0] . " \t" . $a[1] . "\n";
}
?>

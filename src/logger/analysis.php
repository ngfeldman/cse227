<?php
$m = new MongoClient();
$db = $m->Tracking3;

$sites_cur = $db->sites->find()->sort(array("sitenum"=>1));
$netlog_col = $db->netlog;

$numSites = $sites_cur->count();


$sites = array();

foreach ($sites_cur as $site) {
  $sites[$site["sitenum"]] = $site;
}

$site = $sites[1656];

$i =0;
foreach ($sites as $site) {
  $site_id = $site["_id"];
  $events = $netlog_col->find(array("siteId" => $site_id, "data" => array('$type' => 16)))->sort(array("data" => 1));
  $event_times = array(0,0,0,0,0);
  foreach ($events as $event) {
    $data = $event["data"];
    $time = $event['time'];
    $event_times[$data] = $time;
  }  
  
  //echo "times: \n";
  //var_dump($event_times);
  
  $after_load = getInfo($netlog_col, $site_id, $event_times[0], $event_times[1]);
  $wave1 = getInfo($netlog_col, $site_id, $event_times[1], $event_times[2]);
  $pause = getInfo($netlog_col, $site_id, $event_times[2], $event_times[3]);
  $wave2 = getInfo($netlog_col, $site_id, $event_times[3], $event_times[4]);
  
  //echo $site["url"]."\n";
  //var_dump($after_load);
  //var_dump($wave1);
  //var_dump($pause);
  //var_dump($wave2);
  //echo "\n";
  
  if ($wave1['count'] > $pause['count'] && $pause['count'] < $wave2['count'] && ($wave1['count'] >=2 && $wave2['count'] >= 2) ) {
    echo $site['sitenum'] . " \t" . $site['url'] . " by count: \t" . $after_load['count'] . " \t" . $wave1['count'] . " \t" . $pause['count'] . " \t" . $wave2['count'] . "\n";
  }
  
    if ($wave1['size'] > $pause['size'] && $pause['size'] < $wave2['size']) {
    echo $site['sitenum'] . " \t" . $site['url'] . " by size: \t" . $after_load['size'] . " \t" . $wave1['size'] . " \t" . $pause['size'] . " \t" . $wave2['size'] . "\n";
  }
  
  //++$i; if($i == 3000) break;
}



function getInfo($netlog_col, $site_id, $start, $end) {
  $xhrs = $netlog_col->find(array("siteId" => $site_id, "packetType" => "request", "time" => array('$gt' => $start, '$lte' => $end),"data" => array('$not' => array('$type' => 16))))->sort(array("time" => 1));
  
  $count = 0;
  $size = 0;
  $urls = array();
  foreach ($xhrs as $xhr) {
    ++$count;
    if (isset($xhr["data"])) {
      if (isset($xhr["data"]["bodySize"])) {
        $size += intval($xhr["data"]["bodySize"]);
      }
      if (isset($xhr["data"]["url"])) {
        $url = $xhr["data"]["url"];
        $qpos = strpos($url , "?");
        if ($qpos)
          $url = substr($url, 0, $qpos);
        $colonslashslashpos = strpos($url, "://");
        $nextslashpos = strpos($url, "/", $colonslashslashpos+3);
        $domain = substr($url, 0, $nextslashpos);
        $url = $domain;
        if (strpos($domain,"clicktale")) {
          //echo "CLICKTALE FOUND!\n";
          //var_dump($site_id);
        }
        if (isset($urls[$url])) {
          ++$urls[$url];
        }
        else {
          $urls[$url] = 1;
        }
      }
    }
  }
  return array('count' => $count, 'size' => $size, 'urls' => $urls);
}

?>

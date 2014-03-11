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
    $time = doubleval($event['time']) + 0.0;
    $event_times[$data] = $time;
  }  
  
  //echo "times: \n";
  //var_dump($event_times);
  
  $after_load = getInfo($netlog_col, $site_id, $event_times[0]+100, $event_times[1]+100);
  $wave1 = getInfo($netlog_col, $site_id, $event_times[1]+100, $event_times[2]+100);
  $pause = getInfo($netlog_col, $site_id, $event_times[2]+100, $event_times[3]+100);
  $wave2 = getInfo($netlog_col, $site_id, $event_times[3]+100, $event_times[4]+100);
  
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
    if (filter($xhr)) {
      ++$count;
      
      if (isset($xhr["data"])) {
        if ( !isset($xhr["data"]["id"]))
           continue;
        $xhr_id = $xhr['data']['id'];
        $responses = $netlog_col->find(array("siteId" => $site_id, "data.id" =>  $xhr_id));
        $skip = false;
        foreach($responses as $response) {
          if (isset($response['data']['bodySize'])) {
            $body_size = $response['data']['bodySize'];
            if (intval($body_size > 300)) {
              //echo "skipping at ".$response["_id"]."\n";
              $skip=true;
            }
          }
        }
        if ($skip)
          continue;
        if (isset($xhr["data"]["bodySize"])) {
          echo "bodySize was set\n";
          $size += intval($xhr["data"]["bodySize"]);
        }
        if (isset($xhr["data"]["url"])) {
          $url = $xhr["data"]["url"];
          $size += strlen($url);
          $url = getUrlWithoutParameters($url);
          //echo "$url\n";
          $domain = getDomainFromUrl($url);
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
    else {
      //echo $xhr["data"]["url"] . "\n\n";
    }
  }
  return array('count' => $count, 'size' => $size, 'urls' => $urls);
}

function getUrlWithoutParameters($url) {
  $res = $url;
  $qpos = strpos($res , "?");
  if ($qpos)
    $res = substr($res, 0, $qpos);
  return $res;
}

/**
 * includes everything up to but not including first slash after ://
 */
function getDomainFromUrl($url) {
  $colonslashslashpos = strpos($url, "://");
  $nextslashpos = strpos($url, "/", $colonslashslashpos+3);
  $domain = substr($url, 0, $nextslashpos);
  return $domain;
}

/**
 * Returns true if it should be kept
 */
function filter($xhr) {
  if (! (isset($xhr['data']) && isset($xhr['data']['url'])) )
    return false;
  
  $url = getUrlWithoutParameters($xhr['data']['url']);
  
  
  if (substr($url,0,5) == 'data:') {
    return false;
  }
    
  //throw it out if it's an image
  $exts = array('gif', 'jpg', 'jpeg', 'png', 'bmp');
  $dot_pos = strrpos($url, '.');
  if ($dot_pos) {
    $real_ext = substr($url, $dot_pos+1);
    if ($real_ext) {
      foreach ($exts as $ext) {
        if (strcasecmp($real_ext, $ext)==0) {
          //echo "img $real_ext matches $ext \n";
          return false;
        }
      }
    }
  }
  
  $domain = getDomainFromUrl($url);
  
  if (preg_match("/gstatic.com^/", $domain)) {
    //echo "preg_match\n";
    return false;
  }
  
  return true;
}

?>

<?php
define ('WINDOW_SHIFT', 100.0);
define ('MIN_INFO_DIFFERENCE', 250);
define ('MAX_WAVE_INFO_DIFFERENCE', 1000);
$m = new MongoClient();
$db = $m->Tracking3;

$sites_cur = $db->sites->find()->sort(array("sitenum"=>1));
$netlog_col = $db->netlog;

$numSites = $sites_cur->count();


$sites = array();

foreach ($sites_cur as $site) {
  $sites[$site["sitenum"]] = $site;
}

//$site = $sites[1656];

$receiving_domains = array();

foreach ($sites as $site) {
  $site_id = $site["_id"];
  $events = $netlog_col->find(array("siteId" => $site_id, "data" => array('$type' => 16)))->sort(array("data" => 1));
  $event_times = array(0,0,0,0,0);
  foreach ($events as $event) {
    $data = $event["data"];
    $time = doubleval($event['time']) + 0.0;
    $event_times[$data] = $time + WINDOW_SHIFT;
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
  
/*  if ($wave1['count'] > $pause['count'] && $pause['count'] < $wave2['count'] && ($wave1['count'] >=2 && $wave2['count'] >= 2) ) {
    echo $site['sitenum'] . " \t" . $site['url'] . " by count: \t" . $after_load['count'] . " \t" . $wave1['count'] . " \t" . $pause['count'] . " \t" . $wave2['count'] . "\n";
  }*/
  
    $sitenum = $site['sitenum'];
    $url = $site['url'];
    $sizeal = $after_load['size'];
    $sizew1 = $wave1['size'];
    $sizep = $pause['size'];
    $sizew2 = $wave2['size'];
    
    $w1doms = array_keys($wave1['domains']);
    $w1domcounts = array_values($wave1['domains']);
    $w2doms = array_keys($wave2['domains']);
    $w2domcounts = array_values($wave2['domains']);
    
    $max = max(count($w1doms), count($w2doms));
    
    //$whitespace = str_repeat(' ', strlen($sitenum)) . ' \t' . str_repeat(' ', strlen($url.' by size: ')) . '\t' . str_repeat(' ',strlen($sizeal)). ' \t';
    
    if ($sizew1 > $sizep + MIN_INFO_DIFFERENCE && $sizep + MIN_INFO_DIFFERENCE < $sizew2 && $sizew2 >= $sizew1 - MAX_WAVE_INFO_DIFFERENCE) {
    echo "$sitenum \t$url by count: \t" . $after_load['count'] . " \t" . $wave1['count'] . " \t" . $pause['count'] . " \t" . $wave2['count'] . " \n";
    echo "$sitenum \t$url by size: \t$sizeal \t$sizew1 \t$sizep \t$sizew2\n";
    for ($i=0; $i < $max; $i++) {
      if ($i < count($w1doms))
        $w1dom = $w1doms[$i] . ' ' . $w1domcounts[$i];
      else
        $w1dom = " ";
      if ($i < count($w2doms))
        $w2dom = $w2doms[$i] . ' ' . $w2domcounts[$i];
      else
        $w2dom = " ";
      echo "\t\tw1: $w1dom w2: $w2dom\n";
    }
  }
  
  //++$i; if($i == 3000) break;
}

arsort($receiving_domains);
foreach ($receiving_domains as $domain => $count) {
  if ($count >= 10)
    echo "\t$domain: $count\n";
}

function getInfo($netlog_col, $site_id, $start, $end) {
  $xhrs = $netlog_col->find(array("siteId" => $site_id, "packetType" => "request", "time" => array('$gt' => $start, '$lte' => $end),"data" => array('$not' => array('$type' => 16))))->sort(array("time" => 1));

  $count = 0;
  $size = 0;
  $domains = array();
  foreach ($xhrs as $xhr) {
    if (filter($xhr)) {
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
          
        ++$count;
        /* This doesn't ever seem to be set in requests
         if (isset($xhr["data"]["bodySize"])) {
          echo "bodySize was set\n";
          $size += intval($xhr["data"]["bodySize"]);
        }*/
        if (isset($xhr['data']['method']) && $xhr['data']['method'] == 'POST' && isset($xhr['data']['headers'])) {
          $headers = $xhr['data']['headers'];
          foreach ($headers as $header) {
            if (isset($header['name']) && $header['name'] == 'Content-Length') {
              $content_length = $header['value'];
              //echo "content length $content_length\n";
              $size += $content_length;
            }
          }
        }
        if (isset($xhr["data"]["url"])) {
          $url = $xhr["data"]["url"];
          
          $size += strlen($url);
          
          $url = getUrlWithoutParameters($url);
          //echo "$url\n";
          $domain = getDomainFromUrl($url);
          
          $size -= strlen($domain);
          
          if (strpos($domain,"clicktale")) {
            //echo "CLICKTALE FOUND!\n";
            //var_dump($site_id);
          }
          setOrIncrement($domains, $domain);
          if (!isset($domains[$domain])) echo "PROBLEM!!!\n";
          global $receiving_domains;
          setOrIncrement($receiving_domains, $domain);
        }
      }
    }
    else {
      //echo $xhr["data"]["url"] . "\n\n";
    }
  }
  foreach ($domains as $d => $c) {
    //if ($c < $count/2)
      //unset($domains[$d]);
  }
  arsort($domains);
  return array('count' => $count, 'size' => $size, 'domains' => $domains);
}

function setOrIncrement(&$array, $key) {
  if (isset($array[$key]))
    ++$array[$key];
  else
    $array[$key] = 1;
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
  if ($nextslashpos)
    $domain = substr($url, $colonslashslashpos+3, $nextslashpos-$colonslashslashpos-3);
  else
    $domain = substr($url, $colonslashslashpos+3);
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

<?php
function postVal($key) {
	$val = isset($_POST[$key]) ? ($_POST[$key]) : "";
  if (!empty($val) && is_string($val)) {
    return str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $val);
  } 
	return($val);
}

function urlTrim($str) {
	$url = trim($str);
	$url = str_replace("http://", "", $url);
	$url = str_replace("https://", "", $url);
	$url = str_replace("www.", "", $url);
	$len = strlen($url);
	if ($url[$len-1] == "/") {
		$url = substr($url, 0, $len-1);
	}
	
	return $url;
}

if (!function_exists('json_last_error_msg')) {
    function json_last_error_msg() {
        static $errors = array(
            JSON_ERROR_NONE             => null,
            JSON_ERROR_DEPTH            => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH   => 'Underflow or the modes mismatch',
            JSON_ERROR_CTRL_CHAR        => 'Unexpected control character found',
            JSON_ERROR_SYNTAX           => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8             => 'Malformed UTF-8 characters, possibly incorrectly encoded'
        );
        $error = json_last_error();
        return array_key_exists($error, $errors) ? $errors[$error] : "Unknown error ({$error})";
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
	// connect
	$m = new MongoClient();
	$db = $m->Tracking;
	
	$recordType = postVal("rt");
		
	if ($recordType == "1") {
		// add record
		$url = urlTrim(postVal("u"));
		$time = postVal("t");
		
		$collection = $db->sites;
		if ($collection->find(array("url" => $url))->count() < 1) {
			$doc = array('url' => $url, 'time' => $time);
			$collection->insert($doc);
		}
	}
	else if ($recordType == "2") {
		$url = urlTrim(postVal("u"));
		$time = postVal("t");
		$pt = postVal("pt");
		$data = json_decode(urldecode($_POST['d']), true);
		$err = json_last_error_msg();
		
		//get id
		$collection = $db->sites;
		$doc = $collection->findOne(array('url'=>$url), array('_id'));
		$id = $doc['_id'];
		file_put_contents("logg.txt", "\nurl: " . $url . "\njson err: " . $err . "\nsiteid: " . $id . ", time: " . $time . "\ndata: " . $_POST['d'] . "\ndataDecoded: " . urldecode($_POST['d']) . "\n", FILE_APPEND | LOCK_EX);
		
		//add record
		$collection = $db->netlog;
		$doc = array('siteId'=>$id, 'time'=> $time, 'packetType'=> $pt, 'data'=>$data);
		$collection->insert($doc);
		
		/*
		$data = array(
			"siteId" => postVal("site"),		// which site this record is for
			"type" => postVal("type"),			// type of network traffic (request or response)
			"requestId" => postVal("id"),		// sequential id associated with requests
			"method" => postVal("method"),	// POST or GET (for requests)
			"time" => postVal("time"),			// time of packet
			"url" => postVal("url"), 				// url of packet
			"size" => postVal("size"), 			// size of packet (for responses)
			"contentType" => postVal("contentType"),
			"status" => postVal("status"),
			"stage" => postVal("stage")
		);
		*/
		
		
		
		
	}
}
?>
ok
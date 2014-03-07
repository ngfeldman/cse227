<?php
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
	$db = $m->Tracking3;
	
	$stuff = explode(";;;;", $_POST['x']);
	foreach ($stuff as $item) {
		$record = json_decode(urldecode($item), true);
		
		$recordType = $record['rt'];
			
		if ($recordType == "1") {	// initial site connection
			// add record
			$url = urlTrim($record['u']);
			$time = floatval($record['t']);
			$sitenum = intval($record['d']);
			
			file_put_contents("logg.txt", "\nurl: " . $url . "\ttime: " . $time . "\n", FILE_APPEND | LOCK_EX);
			
			$collection = $db->sites;
			if ($collection->find(array("url" => $url))->count() < 1) {
				//file_put_contents("logg.txt", "adding $url to db\n", FILE_APPEND | LOCK_EX);
				$doc = array('url' => $url, 'time' => $time, 'sitenum' => $sitenum);
				$collection->insert($doc);
			}
			else {
				file_put_contents("logg.txt", "found $url match in db already\n", FILE_APPEND | LOCK_EX);
			}
		}
		else if ($recordType == "2") {	// network traffic record
			$url = urlTrim($record['u']);
			$time = floatval($record['t']);
			$pt = $record['pt'];
			$data = $record['d'];
			//$err = json_last_error_msg();
			
			//get id
			$collection = $db->sites;
			$doc = $collection->findOne(array('url'=>$url), array('_id'));
			$id = $doc['_id'];
			//file_put_contents("logg.txt", "\nurl: " . $url . "\tsiteid: " . $id . "\ttime: " . $time . "\tdataDecoded: " . substr(urldecode($_POST['d']),0,50) . "\n", FILE_APPEND | LOCK_EX);
			
			//add record
			$collection = $db->netlog;
			$doc = array('siteId'=>$id, 'time'=> $time, 'packetType'=> $pt, 'data'=>$data);
			$collection->insert($doc);
		}
		else if ($recordType == "3") {	// status message
			$url = urlTrim($record['u']);
			$time = floatval($record['t']);
			$data = intval($record['d']);
					
			//get id
			$collection = $db->sites;
			$doc = $collection->findOne(array('url'=>$url), array('_id'));
			$id = $doc['_id'];
			
			//file_put_contents("logg.txt", "\nurl: " . $url . "\tsiteid: " . $id . "\ttime: " . $time . "\tstatus: " . $data . "\n", FILE_APPEND | LOCK_EX);
			
			//add record
			$collection = $db->netlog;
			$doc = array('siteId'=>$id, 'time'=> $time, 'packetType'=> "", 'data'=>$data);
			$collection->insert($doc);
		}
	}
}
?>

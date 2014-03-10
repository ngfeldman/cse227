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

$FILE_PATH = "C:/Users/Scott.Finney/Documents/GitHub/cse227/src/crawler/log";

// connect
$m = new MongoClient();
$db = $m->Tracking5;

/* read data file into array */
$file = intval($_GET['f']);
$lines = file($FILE_PATH.$file.".txt");

echo "File: log" . $file . ".txt (<a href=\"dbFromFile.php?f=".($file+1)."\">next</a>)<br/>";
echo "Lines: " . count($lines) . "<br/>";

foreach($lines as $line) {
	$line = preg_replace('/\x{EF}\x{BB}\x{BF}/','',$line);
	//echo substr($line,0,50) . "<br/>";
	$items = explode(";;;;", $line);
	
	foreach ($items as $item) {
		$record = json_decode($item, true);
		$recordType = $record['rt'];
		
		if ($recordType == "1") {	// initial site connection
			// add record
			$url = urlTrim($record['u']);
			$time = floatval($record['t']);
			$sitenum = intval($record['d']);
			
			echo "[time] " . $time . " - url: " . $url . " (" . $sitenum . ")<br/>";
			//file_put_contents("logg2.txt", "\nurl: " . $url . " (" . $sitenum . ")\ttime: " . $time, FILE_APPEND | LOCK_EX);
			
			$collection = $db->sites;
			if ($collection->find(array("url" => $url))->count() < 1) {
				//file_put_contents("logg.txt", "adding $url to db\n", FILE_APPEND | LOCK_EX);
				$doc = array('url' => $url, 'time' => $time, 'sitenum' => $sitenum);
				$collection->insert($doc);
			}
			else {
				echo "found $url match in db already<br/>";
			}
		}
		else if ($recordType == "2") {	// network traffic record
			$url = urlTrim($record['u']);
			$time = floatval($record['t']);
			$pt = $record['pt'];
			$data = $record['d'];
			
			//echo "[time] " . $time . " - url: " . $url . " data: " . substr($data,0,50) . "<br/>";
			
			//get id
			$collection = $db->sites;
			$doc = $collection->findOne(array('url'=>$url), array('_id'));
			$id = $doc['_id'];
			//file_put_contents("logg2.txt", "\nurl: " . $url . "\tsiteid: " . $id . "\ttime: " . $time . "\tdataDecoded: " . substr($data,0,50) . "\n", FILE_APPEND | LOCK_EX);
			
			//add record
			$collection = $db->netlog;
			$doc = array('siteId' => $id, 'time'=> $time, 'packetType' => $pt, 'data' => $data);
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
			
			echo "[time] " . $time . " - url: " . $url . "(" . $id . ") - status: " . $data . "<br/>";
			//file_put_contents("logg2.txt", "\nurl: " . $url . "\tsiteid: " . $id . "\ttime: " . $time . "\tstatus: " . $data . "\n", FILE_APPEND | LOCK_EX);
			
			//add record
			$collection = $db->netlog;
			$doc = array('siteId'=>$id, 'time'=> $time, 'packetType'=> "", 'data'=>$data);
			$collection->insert($doc);
		}
	}
}

?>
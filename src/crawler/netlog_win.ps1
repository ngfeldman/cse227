$batch=50
$parallel=10
$(foreach ($i in 0..0) {
   $j= $i * $batch
   phantomjs netlog.js $parallel $j $batch keypress file
})

$batch=50
$parallel=10
$(foreach ($i in 0..4) { 
   $j= $i * $batch
   phantomjs netlog.js $parallel $j $batch
})

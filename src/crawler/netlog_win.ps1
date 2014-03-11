$batch=50
$parallel=10
$(foreach ($i in 150..159) {
   $j= $i * $batch
   phantomjs netlog.js $parallel $j $batch mousemove file > mm_log$i.txt
})

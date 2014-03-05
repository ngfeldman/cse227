#! /bin/sh
batch=30
for i in $(seq 0 1); do 
   j=`expr $i \* $batch`
   phantomjs netlog.js 10 $j $batch
done

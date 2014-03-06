var CONNECTION_TIMEOUT = 30000;
var PAGE_LOAD_TIME = 250;
var TIME_ON_PAGE = 10000 + PAGE_LOAD_TIME;
var EVENT_TYPE = "mousemove";

function dbLog(rt, url, msg, pt) {
  var xhr = new XMLHttpRequest();
  xhr.open("POST","http://localhost/Tracking/dbLog.php",true);
  xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
  xhr.onreadystatechange = function () {
    if (xhr.readyState == 4 && xhr.status == 200) {
			console.log(xhr.responseText);
    }
  }
  var time = new Date().getTime();
  if (rt == 1) {
    xhr.send("rt="+rt+"&t="+time+"&u="+encodeURIComponent(url));
    //console.log("rt="+rt+"&t="+time+"&u="+url);
  }
  else if (rt == 2) {
    xhr.send("rt="+rt+"&t="+time+"&u="+encodeURIComponent(url)+"&pt="+pt+"&d="+encodeURIComponent(JSON.stringify(msg)));
    //console.log("rt="+rt+"&t="+time+"&u="+url+"&pt="+pt+"&d="+encodeURIComponent(JSON.stringify(msg)));
  }
  else if (rt == 3) {
    xhr.send("rt="+rt+"&t="+time+"&u="+encodeURIComponent(url)+"&d="+msg);
    //console.log("rt="+rt+"&t="+time+"&u="+url+"&d="+msg);
  }
}

function getRandomInt (min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

/* setup global params */
var system = require('system');
var CONCURRENT_PAGES = parseInt(system.args[1]);
var START_INDEX = parseInt(system.args[2]);
var NUM_SITES = parseInt(system.args[3]);
var END_INDEX = START_INDEX + NUM_SITES;
var addresses;

/* load list of sites to visit */
var alexa_page = require('webpage').create();
alexa_page.open("top-1m.html", function(status) {
  addresses = alexa_page.plainText.split(' ');
  alexa_page.release();
  iHateJavaScript();
});

/* Nathan is not a fan... */
function iHateJavaScript() {
  var pagelist = new Array();
  var pagelist_opens = new Array();

  var next_i = START_INDEX + CONCURRENT_PAGES;
  var active = CONCURRENT_PAGES;
  var sites_visited = 0;
  var connections = 0;
  var successes = 0;
  
  function processNext(page, slot) {
    page.close();
    var my_next_i = next_i++; //this all needs to happen at once for concurrency reasons. this was the shortest i could make it.
    //jk this isn't an issue in javascript
    if (my_next_i < END_INDEX) {
    	var time = new Date().getTime();
      console.log("[" + time + "] slot #" + slot + " about to process site #" + my_next_i + " " + addresses[my_next_i]);
      processPage(addresses[my_next_i], pagelist, slot);
    } else {
      --active;
      if (active < 1) {
        phantom.exit();
      }
    }
  }
  
  function processPage(address, pagelist, i) {
    sites_visited++;
    var slot = i
    var time = new Date().getTime();
    console.log("[" + time + "] slot #" + slot + " processing site " + address);
    var page = require('webpage').create();
    page.onError = ( function(msg, trace) {} );
    pagelist[slot] = address;

    page.settings.userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.107 Safari/537.36';

    page.onResourceRequested = function(request) {
      var msg = JSON.stringify(request, undefined, 4);
      //console.log('Request ' + msg);
      dbLog(2, address, request, 'request');
    };
    page.onResourceReceived = function(response) {
      var msg = JSON.stringify(response, undefined, 4);
      //console.log('Response ' + msg);
      dbLog(2, address, response, 'response');
    };

    var timeout_count = 0;
    var connection_timeout_id;
    connection_timeout_id = setInterval(function() {
    	var time = new Date().getTime();
    	console.log("[" + time + "] slot #" + slot + " " + address + " reached timeout limit. (" + page.url + ", " + timeout_count + ")");
      if (page.url == "about:blank" || timeout_count > 0) {
      	console.log("[" + time + "] slot #" + slot + " " + address + " timed out.");
        dbLog(3, address, -1);
        processNext(page, slot);
      }
      timeout_count++;
    }, CONNECTION_TIMEOUT);
    
    dbLog(1, address);
    page.open('http://'+address, function(status) {
      connections++;
      clearInterval(connection_timeout_id);
      var time = new Date().getTime();
      console.log("[" + time + "] slot #" + slot + " opened " + address);
      ++pagelist_opens[slot];
      var interval_id;
      var timeout_id;
      if (status !== 'success') {
        var time = new Date().getTime();
      	console.log("[" + time + "] FAIL to load the address " + address);
        dbLog(3, address, -1);
        --pagelist_opens[slot];
        processNext(page, slot);
      }
      else {
        successes++;
        var time = new Date().getTime();
      	console.log("[" + time + "] Initial page load of " + address + " complete");
        dbLog(3, address, 0);

        var coordsx = 0;
        var coordsy = 0;
        var count = 0;
        
        function createUserEvent (iters) {
					for(i = 0; i<iters; i++)
          {
            //coordsy += getRandomInt(1,10);
            //coordsx += getRandomInt(1,10);
            //if(coordsx > 1500) coordsx = 0;
            //if(coordsy > 1500) coordsy = 0;
            
            coordsx = getRandomInt(100, 1000);
            coordsy = getRandomInt(100, 1000);
            if (count % 2 != 0) {
              coordsx = Math.floor(coordsx / 50);
              coordsy = Math.floor(coordsy / 50);
            }
            
            if (EVENT_TYPE == "mousemove") {
            	page.sendEvent('mousemove', coordsx, coordsy);
            	var time = new Date().getTime();
            	//console.log("\t"+time+"\tmouse moved to (" + coordsx + ", " + coordsy + ")");
            }
            else if (EVENT_TYPE == "mouseclick") {
            	page.sendEvent('click', coordsx, coordsy, 'left');
            }
            else if (EVENT_TYPE == "keypress") {
            	var key = page.event.key.A;
            	page.sendEvent('keypress', key, null, null, 0);
            }
            count++;
          }
				}

        timeout_id = setTimeout(function() {
        	dbLog(3, address, 1);
          interval_id = setInterval(function() {
            createUserEvent(10);
          }, 10);
        }, PAGE_LOAD_TIME);
        
        setTimeout(function(){
          var time = new Date().getTime();
      		console.log("[" + time + "] slot #" + slot + " " + address + " is done");
          dbLog(3, address, 2);
          clearTimeout(timeout_id);
          clearInterval(interval_id);
          pagelist[slot] = "";
          --pagelist_opens[slot];
          processNext(page, slot);
        }, TIME_ON_PAGE);
      }
    });
  }
  setInterval( function() {
    var time = new Date().getTime();
    console.log("[" + time + "] processed: " + sites_visited + " \t connections: " + connections + " \t successes: " + successes);
    var s = "working right now:\n";
    for(var j = 0; j<CONCURRENT_PAGES; j++) {
      //if (pagelist_opens[j]>0) {
        s = s + " slot #" + j + " (" + pagelist_opens[j] + ") " + pagelist[j] + "\n";
      //}
    }
    console.log(s);
  }, 5000);
  
  for (var i = 0; (i < CONCURRENT_PAGES )&& (START_INDEX + i < END_INDEX) && (START_INDEX + i < addresses.length); i++) {
    pagelist_opens[i] = 0;
    processPage(addresses[START_INDEX + i], pagelist, i);
  }
}

/* setup global params */
var CONNECTION_TIMEOUT = 30000;
var PAGE_LOAD_TIME = 250;
var TIME_ON_PAGE = 10000 + PAGE_LOAD_TIME;
var PAUSE_START_TIME = Math.floor(TIME_ON_PAGE / 3);
var PAUSE_END_TIME = PAUSE_START_TIME * 2;

var system = require('system');
var CONCURRENT_PAGES = parseInt(system.args[1]);
var START_INDEX = parseInt(system.args[2]);
var NUM_SITES = parseInt(system.args[3]);
var END_INDEX = START_INDEX + NUM_SITES;
var EVENT_TYPE = system.args[4];
var LOG_TYPE = system.args[5];
var addresses;

phantom.onError = function(msg, trace) { };

function dbLog(rt, url, msg, pt, send, logs) {
	if (LOG_TYPE == "file") {
		fileLog(rt, url, msg, pt, send, logs);
	}
	else {
		var time = new Date().getTime();
		var str;
		if (rt == 1) {
		  str = encodeURIComponent(JSON.stringify({"rt" : rt, "t" : time, "u" : url, "d" : msg}));
		}
		else if (rt == 2) {
		  str = encodeURIComponent(JSON.stringify({"rt" : rt, "t" : time, "u" : url, "pt": pt, "d" : msg}));
		}
		else if (rt == 3) {
		  str = encodeURIComponent(JSON.stringify({"rt" : rt, "t" : time, "u" : url, "d" : msg}));
		}
		logs[logs.length] = str;
		
		if (send == 1 || logs.length > 19) {
		  var xhr = new XMLHttpRequest();
		  xhr.open("POST","http://localhost/Tracking/dbLog.php",true);
		  xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
		  xhr.onreadystatechange = function () {
		    //if (xhr.readyState == 4 && xhr.status == 200) {
				//	console.log(xhr.response);
		    //}
		    //else if (xhr.readyState == 4) {
		    //	console.log("request url: " + url + "\t response status: " + xhr.status);
		    //}
		  }
		  xhr.timeout = 2000;
		  xhr.ontimeout = function () {
		  	//console.log("log request (url: " + url + ")\t TIMED OUT");
		  }
		  
		  var stuff = logs.join(";;;;");
		  logs.splice(0, logs.length);
		  xhr.send("x=" + stuff);
		  //console.log("x=" + stuff);
		}
	}
}
function fileLog(rt, url, msg, pt, send, logs) {
	var time = new Date().getTime();
	var str;
	if (rt == 1) {
	  str = JSON.stringify({"rt" : rt, "t" : time, "u" : url, "d" : msg});
	}
	else if (rt == 2) {
	  str = JSON.stringify({"rt" : rt, "t" : time, "u" : url, "pt": pt, "d" : msg});
	}
	else if (rt == 3) {
	  str = JSON.stringify({"rt" : rt, "t" : time, "u" : url, "d" : msg});
	}
	logs[logs.length] = str;
	
	if (send == 1 /*|| logs.length > 19*/) {
	  var stuff = logs.join(";;;;");
	  logs.splice(0, logs.length);
	  console.log(stuff);
	}
}
function getRandomInt (min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}


/* load list of sites to visit and launch crawler */
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
    var my_next_i = next_i++; 
    if (my_next_i < END_INDEX) {
    	var time = new Date().getTime();
      //console.log("[" + time + "] slot #" + slot + " about to process site #" + my_next_i + " " + addresses[my_next_i]);
      processPage(addresses[my_next_i], pagelist, slot, my_next_i);
    } else {
      --active;
      if (active < 1) {
        phantom.exit();
      }
    }
  }
  
  function processPage(address, pagelist, i, site_num) {
    sites_visited++;
    var slot = i
    var logs = new Array();
    var time = new Date().getTime();
    //console.log("[" + time + "] slot #" + slot + " processing site " + address);
    var page = require('webpage').create();
    page.onError = ( function(msg, trace) {} );
    pagelist[slot] = address;

    page.settings.userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.107 Safari/537.36';

    page.onResourceRequested = function(request) {
      var msg = JSON.stringify(request, undefined, 4);
      //console.log('Request ' + msg);
      dbLog(2, address, request, 'request', 0, logs);
    };
    page.onResourceReceived = function(response) {
      var msg = JSON.stringify(response, undefined, 4);
      //console.log('Response ' + msg);
      dbLog(2, address, response, 'response', 0, logs);
    };

    var timeout_count = 0;
    var connection_timeout_id;
    connection_timeout_id = setInterval(function() {
    	var time = new Date().getTime();
    	//console.log("[" + time + "] slot #" + slot + " " + address + " reached timeout limit");
      if (page != null && (page.url == "about:blank" || timeout_count > 0)) {
      	//console.log("[" + time + "] slot #" + slot + " " + address + " timed out.");
        dbLog(3, address, -1, null, 1, logs);
        pagelist[slot] = "";
        processNext(page, slot);
      }
      timeout_count++;
    }, CONNECTION_TIMEOUT);
    
    dbLog(1, address, site_num, null, 1, logs);
    page.open('http://'+address, function(status) {
      connections++;
      clearInterval(connection_timeout_id);
      var time = new Date().getTime();
      //console.log("[" + time + "] slot #" + slot + " opened " + address);
      ++pagelist_opens[slot];
      var interval1_id, interval2_id;
      var timeout1_id, timeout2_id, timeout3_id;
      if (status !== 'success') {
        var time = new Date().getTime();
      	//console.log("[" + time + "] FAIL to load the address " + address);
        dbLog(3, address, -1, null, 1, logs);
        pagelist[slot] = "";
        --pagelist_opens[slot];
        processNext(page, slot);
      }
      else {
        successes++;
        var time = new Date().getTime();
      	//console.log("[" + time + "] Initial page load of " + address + " complete");
        dbLog(3, address, 0, null, 0, logs);

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
            //coordsx = getRandomInt(100, 1000);
            //coordsy = getRandomInt(100, 1000);
            coordsx = 1000;
            coordsy = 200;
            if (count % 2 != 0) {
              coordsx = Math.floor(coordsx / 50);
              coordsy = Math.floor(coordsy / 50);
            }
            
            if (EVENT_TYPE == "mousemove") {
            	page.sendEvent('mousemove', coordsx, coordsy);
            }
            else if (EVENT_TYPE == "mouseclick") {
            	page.sendEvent('click', coordsx, coordsy, 'left');
            }
            else if (EVENT_TYPE == "keypress") {
            	var k = getRandomInt(0,25);
            	switch (k) {
            		case 0: var key = page.event.key.A; break; case 1: var key = page.event.key.B; break; case 2: var key = page.event.key.C; break; case 3: var key = page.event.key.D; break; case 4: var key = page.event.key.E; break; case 5: var key = page.event.key.F; break; case 6: var key = page.event.key.G; break; case 7: var key = page.event.key.H; break; case 8: var key = page.event.key.I; break; case 9: var key = page.event.key.J; break; case 10: var key = page.event.key.K; break; case 11: var key = page.event.key.L; break; case 12: var key = page.event.key.M; break; case 13: var key = page.event.key.N; break; case 14: var key = page.event.key.O; break; case 15: var key = page.event.key.P; break; case 16: var key = page.event.key.Q; break; case 17: var key = page.event.key.R; break; case 18: var key = page.event.key.S; break; case 19: var key = page.event.key.T; break; case 20: var key = page.event.key.U; break; case 21: var key = page.event.key.V; break; case 22: var key = page.event.key.W; break; case 23: var key = page.event.key.X; break; case 24: var key = page.event.key.Y; break; case 25: var key = page.event.key.Z; break;
            	}
            	page.sendEvent('keypress', key, null, null, 0);
            }
            count++;
          }
				}

        timeout1_id = setTimeout(function() {
        	dbLog(3, address, 1, null, 0, logs);
        	interval1_id = setInterval(function() {
           		createUserEvent(20);
          	}, 10);
        }, PAGE_LOAD_TIME);
				
				timeout2_id = setTimeout(function() {
        	dbLog(3, address, 2, null, 0, logs);
          clearInterval(interval1_id);
        }, PAUSE_START_TIME);
        
				timeout3_id = setTimeout(function() {
        	dbLog(3, address, 3, null, 0, logs);
          interval2_id = setInterval(function() {
          	createUserEvent(20);
          }, 10);
        }, PAUSE_END_TIME);
        
        setTimeout(function(){
          var time = new Date().getTime();
      		//console.log("[" + time + "] slot #" + slot + " " + address + " is done");
          dbLog(3, address, 4, null, 1, logs);
          clearTimeout(timeout1_id);
          clearTimeout(timeout2_id);
          clearTimeout(timeout3_id);
          clearInterval(interval1_id);
          clearInterval(interval2_id);
          pagelist[slot] = "";
          --pagelist_opens[slot];
          processNext(page, slot);
        }, TIME_ON_PAGE);
      }
    });
  }
  /*
  setInterval( function() {
    var time = new Date().getTime();
    console.log("========\n\t[" + time + "] \tprocessed: " + sites_visited + " \t connections: " + connections + " \t successes: " + successes);
    var s = "\tworking right now:\n";
    for(var j = 0; j<CONCURRENT_PAGES; j++) {
        s = s + " \t\tslot #" + j + " (" + pagelist_opens[j] + ") " + pagelist[j] + "\n";
    }
    console.log(s + "========");
  }, 10000);
  */
  
  for (var i = 0; (i < CONCURRENT_PAGES )&& (START_INDEX + i < END_INDEX) && (START_INDEX + i < addresses.length); i++) {
    pagelist_opens[i] = 0;
    processPage(addresses[START_INDEX + i], pagelist, i, START_INDEX + i);
  }
}

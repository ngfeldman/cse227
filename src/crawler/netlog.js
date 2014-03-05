var CONNECTION_TIMEOUT = 30000;
var PAGE_LOAD_TIME = 250;
var TIME_ON_PAGE = 10000 + PAGE_LOAD_TIME;

function dbLog(rt, msg, url, pt) {
  var xhr = new XMLHttpRequest();
  xhr.open("POST","http://localhost/Tracking/dbLog.php",true);
  xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
  xhr.onreadystatechange = function () {
    if (xhr.readyState == 4 && xhr.status == 200) {
			//console.log(xhr.responseText);
    }
  }
  var time = new Date().getTime();
  if (typeof url === "undefined") {
    //xhr.send("rt="+rt+"&t="+time+"&u="+encodeURIComponent(msg));
    //console.log("rt="+rt+"&t="+time+"&u="+msg);
  }
  else {
    //xhr.send("rt="+rt+"&t="+time+"&u="+encodeURIComponent(url)+"&pt="+pt+"&d="+encodeURIComponent(JSON.stringify(msg)));
    //console.log("rt="+rt+"&t="+time+"&u="+url+"&pt="+pt+"&d="+encodeURIComponent(JSON.stringify(msg)));
  }
}

function getRandomInt (min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}


var system = require('system');
var CONCURRENT_PAGES = parseInt(system.args[1]);
var START_INDEX = parseInt(system.args[2]);
var NUM_SITES = parseInt(system.args[3]);
var END_INDEX = START_INDEX + NUM_SITES;
var addresses;

var alexa_page = require('webpage').create();
alexa_page.open("top-1m.html", function(status) {
  addresses = alexa_page.plainText.split(' ');
  alexa_page.release();
  iHateJavaScript();
});

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
      console.log("slot #" + slot + " about to process site #" + my_next_i + " " + addresses[my_next_i]);
      processPage(addresses[my_next_i], pagelist, slot);
    } else {
      --active;
      if (active == 0) {
        phantom.exit();
      }
    }
  }
  
  function processPage(address, pagelist, i) {
    sites_visited++;
    var slot = i
    console.log("slot #" + slot + " processing site" + address);
    var page = require('webpage').create();
    page.onError = ( function(msg, trace) {} );
    pagelist[slot] = address;

    page.settings.userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.107 Safari/537.36';

    page.onResourceRequested = function(request) {
      var msg = JSON.stringify(request, undefined, 4);
      //console.log('Request ' + msg);
      dbLog(2, request, address, 'request');
    };
    page.onResourceReceived = function(response) {
      var msg = JSON.stringify(response, undefined, 4);
      //console.log('Response ' + msg);
      dbLog(2, response, address, 'response');
    };

    dbLog(1, address);

    var connection_timeout_id;
    connection_timeout_id = setTimeout(function() {
      if (page.url == "about:blank") {
        page.stop();
        processNext(page, slot);
        //TODO: log in database that connection never went through.
      }
    }, CONNECTION_TIMEOUT);
    
    page.open('http://'+address, function(status) {
      connections++;
      clearTimeout(connection_timeout_id);
      console.log("slot #" + slot + " opened " + address);
      ++pagelist_opens[slot];
      var interval_id;
      var timeout_id;
      if (status !== 'success') {
        console.log('FAIL to load the address ' + address);
        --pagelist_opens[slot];
        processNext(page, slot);
      }
      else {
        successes++;
        console.log('Initial page load of ' + address + ' complete');

        var coordsx = 0;
        var coordsy = 0;
        var count = 0;

        timeout_id = setTimeout(function() {
          interval_id = setInterval(function() {
            for(i = 0; i<10; i++)
            {
              /*
              coordsy += getRandomInt(1,10);
              coordsx += getRandomInt(1,10);
              if(coordsx > 1500) coordsx = 0;
              if(coordsy > 1500) coordsy = 0;
              */
              if (count % 2 == 0) {
                coordsx = 500;
                coordsy = 125;
              }
              else {
                coordsx = 5;
                coordsy = 5;
              }

              //console.log('['+count+'] moving mouse to ('+coordsx+','+coordsy+')');
              page.sendEvent('mousemove', coordsx, coordsy);

              //if(coords % 30 == 0) {
                //page.sendEvent('click', coords, coords, 'left');
              //}
              count++;
            }
          }, 10);
        }, PAGE_LOAD_TIME);
        setTimeout(function(){
          console.log("slot #" + slot + " " + address + " is done");
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
    console.log("processed: " + sites_visited + " \t successes: " + successes);
    var s = "working right now:\n";
    for(var j = 0; j<CONCURRENT_PAGES; j++) {
      //if (pagelist_opens[j]>0) {
        s = s + " slot#" + j + " (" + pagelist_opens[j] + ") " + pagelist[j] + "\n";
      //}
    }
    console.log(s);
  }, 5000);
  
  for (var i = 0; (i < CONCURRENT_PAGES )&& (START_INDEX + i < END_INDEX) && (START_INDEX + i < addresses.length); i++) {
    pagelist_opens[i] = 0;
    processPage(addresses[START_INDEX + i], pagelist, i);
  }
}

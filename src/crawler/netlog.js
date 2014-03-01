var CONCURRENT_PAGES = 50;

function dbLog(rt, msg, url, pt) {
  var xhr = new XMLHttpRequest();
  xhr.open("POST","http://localhost/Tracking/dbLog.php",true);
  xhr.setRequestHeader("Content-type","application/x-www-form-urlencoded");
  xhr.onreadystatechange = function () {
    if (xhr.readyState == 4 && xhr.status == 200) {
			console.log(xhr.responseText);
    }
  }
  var time = new Date().getTime();
  if (typeof url === "undefined") {
    xhr.send("rt="+rt+"&t="+time+"&u="+encodeURIComponent(msg));
    console.log("rt="+rt+"&t="+time+"&u="+msg);
  }
  else {
    xhr.send("rt="+rt+"&t="+time+"&u="+encodeURIComponent(url)+"&pt="+pt+"&d="+encodeURIComponent(JSON.stringify(msg)));
    console.log("rt="+rt+"&t="+time+"&u="+url+"&pt="+pt+"&d="+encodeURIComponent(JSON.stringify(msg)));
  }
}

function getRandomInt (min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}


var system = require('system');

var addresses;

var alexa_page = require('webpage').create();
alexa_page.open("top-1m.html", function(status) { 
  addresses = alexa_page.plainText.split(' ');
  alexa_page.release();
  iHateJavaScript();
});

function iHateJavaScript() {
  var pagelist = new Array();


  for (var i=0; i < CONCURRENT_PAGES && i < addresses.length; i++) {
    processPage(addresses[i], pagelist, i)
  }

  var next_i = CONCURRENT_PAGES;
  var active = CONCURRENT_PAGES;

  function processPage(address, pagelist, i) {
    var page = require('webpage').create();

    pagelist[i] = page;
    
    page.settings.userAgent = 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.107 Safari/537.36';

    page.onResourceRequested = function(request) {
      var msg = JSON.stringify(request, undefined, 4);
      console.log('Request ' + msg);
      dbLog(2, request, address, 'request');
    };
    page.onResourceReceived = function(response) {
      var msg = JSON.stringify(response, undefined, 4);
      console.log('Response ' + msg);
      dbLog(2, response, address, 'response');
    };

    dbLog(1, address);

    page.open('http://'+address, function(status) {
      if (status !== 'success') {
        console.log('FAIL to load the address ' + address);
        phantom.exit(1);
      }
      else {
        console.log('Initial page load of ' + address + ' complete');

        var coordsx = 0;
        var coordsy = 0;
        var count = 0;
        
        var interval_id;
        
        setTimeout(function() {
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
        }, 250);

        setTimeout(function(){
          var my_next_i = next_i++; //this all needs to happen at once for concurrency reasons. this was the shortest i could make it.
          clearInterval(interval_id);
          page.release();
          if (my_next_i < addresses.length) {
            processPage(addresses[i], pagelist, i);
          } else {
            --active;
            if (active == 0) {
              phantom.exit();
            }
          }
        }, 5250);
      }
    });
  }
}

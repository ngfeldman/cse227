var page = require('webpage').create();
var count = 0;
var system = require('system');
var address = system.args[1];
page.open('http://' + address, function(status) {
  ++count;
  console.log("opened! status=" + status);
  page.render(address+count+'.png');
  setTimeout(function(){phantom.exit();},10000);
});

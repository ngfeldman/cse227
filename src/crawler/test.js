var page = require('webpage').create();
var count = 0;
page.open('http://ask.com', function() {
  ++count;
  console.log("opened!");
  page.render('ask'+count+'.png');
  setTimeout(function(){phantom.exit();},10000);
});

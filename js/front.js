function log (message) {
  try {
    console.log(message);
  } catch(err) { 
    /*alert(message);*/
  }
}

log('*** front.js loading');

function ajaxRequest() {
   try
   {
	var request = new XMLHttpRequest();
   }
   catch(e1)
   {
	try
	{
	    request = new ActiveXObject("Msxml2.XMLHTTP");
	}
	catch(e2)
	{
	    try
	    {
		request = new ActiveXObject("Microsoft.XMLHTTP");
	    }
	    catch(e3)
	    {
		request = false;
	    }
	}
    }
    return request;
}
function PlaceHold(barcode) {
  var nocache = Math.random() * 1000000;
  var url = front_js.WEBLIB_BASEURL+'/PlaceHoldOnItem.php?barcode='+encodeURI(barcode)+
  		'&nocache='+nocache;
  var request = new ajaxRequest();
  request.open("GET",url,true);
  request.onreadystatechange = function()
  {
    if (this.readyState == 4)
    {
      if (this.status == 200) {
	if (this.responseXML != null)
	{
	  // hold-count-<barcode>
	  var message = this.responseXML.getElementsByTagName('message');
	  if (message.length > 0) {
	    var messageText = message[0].childNodes[0].nodeValue;
	    document.getElementById('ajax-message').innerHTML = '<p><span id="error">'+messageText+'</span></p>';
	  }
	  var result = this.responseXML.getElementsByTagName('result');
	  if (result.length > 0) {
	    var barcode = result[0].getElementsByTagName('barcode')[0].childNodes[0].nodeValue;
	    var holdcount = result[0].getElementsByTagName('holdcount')[0].childNodes[0].nodeValue;
	    var spanelt = document.getElementById('hold-count-'+barcode);
	    if (holdcount != 1) {
		spanelt.innerHTML = holdcount+' '+front_js.holds;
	    } else {
		spanelt.innerHTML = holdcount+' '+front_js.hold;
	    }
	  }
	}
	else alert(front_js.nodata);
      }
      else alert(front_js.ajaxerr+this.statusText);
    }
  }
  request.send(null);
}


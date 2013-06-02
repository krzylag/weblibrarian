function log (message) {
  try {
    console.log(message);
  } catch(err) { 
    /*alert(message);*/
  }
}

log('*** admin.js loading');

function QuoteString(s) {
    //log('*** QuoteString('+s+')');
    var result = s.replace(/"/g,'&quot;');
    //log('*** QuoteString: result ("s) is '+result);
    result = result.replace(/\\/g,'\\\\');
    //log('*** QuoteString: result \\s is '+result);
    result = result.replace(/\'/g,"\\'");
    //log('*** QuoteString: result \'s is '+result);
    return result;
}
                        
                     

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
function AWSRequest(params,ajaxCallback) {
  params['nocache'] = Math.random() * 1000000;
  var url = admin_js.WEBLIB_BASEURL+'/AWSXmlGet.php?';
  var needamp = false;
  var param;
  for (param in params) {
    if (needamp) url += '&';
    url += param+'='+params[param];
    needamp = true;
  }
  var request = new ajaxRequest();
  request.open("GET",url,true);
  request.onreadystatechange = ajaxCallback;
  request.send(null);
}

function AWSGotoFirstPage() 
{
  AWSSearch(1);
}

function AWSGotoPrevPage()
{
  var page = (document.getElementById('amazon-page-current').value*1) - 1;
  if (page < 1) page = 1;
  AWSSearch(page);
}

function AWSGotoNextPage() 
{
  var page = (document.getElementById('amazon-page-current').value*1) + 1;
  if (page > document.getElementById('amazon-page-N').value) 
	page = document.getElementById('amazon-page-N').value;
  if (page > 400) page = 400;
  AWSSearch(page);
}

function AWSGotoLastPage() 
{
  var page = document.getElementById('amazon-page-N').value;
  if (page > 400) page = 400;
  AWSSearch(page);
}

function AWSGotoPage()
{
  var page = document.getElementById('amazon-page-current').value;
  if (page < 1) page = 1;
  if (page > 400) page = 400;
  AWSSearch(page);
}

function AWSSearch(page) 
{
  var params = {"Operation": "ItemSearch",
		"ResponseGroup": "Small"};
  params["SearchIndex"] = document.getElementById('SearchIndex').value;
  params[document.getElementById('FieldName').value] = 
			document.getElementById('SearchString').value;
  params["ItemPage"] = page;

  AWSRequest(params,AWSSearchCallback);
}

function AWSSearchCallback() 
{
  var listout = '';
  if (this.readyState == 1)
  {
    document.getElementById('amazon-search-workstatus').innerHTML = '<p>'+admin_js.loading+'...</p>';
    document.getElementById('amazon-result-list').innerHTML = '';
    document.getElementById('amazon-item-lookup-display').innerHTML = '';
  } else if (this.readyState == 4)
  {
    if (this.status == 200)
    {
      if (this.responseXML != null)
      {
	var ErrorsElts = this.responseXML.getElementsByTagName('Error');
	if (ErrorsElts != null && ErrorsElts.length > 0) {
	  var ierr;
	  var WorkStatus = document.getElementById('amazon-search-workstatus');
	  WorkStatus.innerHTML = '';
	  for (ierr = 0; ierr < ErrorsElts.length; ierr++) {
	    var theError = ErrorsElts[ierr];
	    var theMessage = theError.getElementsByTagName('Message')[0].childNodes[0].nodeValue;
	    WorkStatus.innerHTML += '<p class="error">'+theMessage+'</p>';
	  }
	  return;
	}
        var CurrentPage = this.responseXML.getElementsByTagName('ItemPage')[0].childNodes[0].nodeValue;
        /*log("*** AWSSearchCallback: CurrentPage = '"+CurrentPage+"'");*/
        var TotalResults = this.responseXML.getElementsByTagName('TotalResults')[0].childNodes[0].nodeValue;
        /*log("*** AWSSearchCallback: TotalResults = '"+TotalResults+"'");*/
        var TotalPages   = this.responseXML.getElementsByTagName('TotalPages')[0].childNodes[0].nodeValue;
        /*log("*** AWSSearchCallback: TotalPages = '"+TotalPages+"'");*/

        document.getElementById('amazon-page-current').value = CurrentPage;
        document.getElementById('amazon-page-N').value = TotalPages;

        var items = this.responseXML.getElementsByTagName('Item');
        listout += '<table>';
	var j;
        for (j = 0; j < items.length; j++)
        {
	  var title = items[j].getElementsByTagName('Title')[0].childNodes[0].nodeValue;
	  var asin  = items[j].getElementsByTagName('ASIN')[0].childNodes[0].nodeValue;
	  listout += '<tr><td valign="top" width="68%">'+title+' (ASIN: '+asin+')</td>';
	  listout += '<td valign="top" width="16%"><a href="#amazon-item-lookup-display" class="button" onclick="AWSLookupItem('+
			"'"+asin+"'"+');">'+admin_js.lookupItem+'</a></td>';
	  listout += '<td valign="top" width="16%"><a href="#" class="button" onclick="AWSInsertItem('+
			"'"+asin+"'"+');">'+admin_js.insertItem+'</a></td>';
	  listout += '</tr>';
        }
        listout += '</table>';
        document.getElementById('amazon-result-list').innerHTML = listout;
        document.getElementById('amazon-search-workstatus').innerHTML = '<p>'+TotalResults+' '+admin_js.totalResultsFount+'</p>';
      } else document.getElementById('amazon-search-workstatus').innerHTML = '<p>'+admin_js.nodata+'</p>'
    } else document.getElementById('amazon-search-workstatus').innerHTML  = '<p>'+admin_js.ajaxerr+this.status+"</p>"
  }
}

function AWSLookupItem(asin)
{
  var params = {"Operation": "ItemLookup",
		"IdType": "ASIN",
		"ResponseGroup": "Large"};
  params["ItemId"] = asin;

  AWSRequest(params,AWSLookupCallback);
}

function AWSInsertItem(asin)
{
  var params = {"Operation": "ItemLookup",
		"IdType": "ASIN",
		"ResponseGroup": "Large"};
  params["ItemId"] = asin;

  AWSRequest(params,AWSInsertCallback);
}

function AWSFixName(name)
{
  var nameparts = name.split(',');
  var propername = nameparts[0];
  var extraparts = '';
  var j;
  for (j = 1; j < nameparts.length; j++)
  {
    extraparts += ', ' + nameparts[j];
  }
  var firstlast = propername.split(' ');
  switch (firstlast.length) {
    case 1: return propername+extraparts;
    case 2: return firstlast[1]+', '+firstlast[0]+extraparts;
    case 3: return firstlast[2]+', '+firstlast[0]+' '+firstlast[1]+extraparts;
  }
}

function AWSFixTitle(thetitle)
{
  if (/^the /i.test(thetitle)) 
  {
    return thetitle.replace(/^the /i,"")+", The";
  } else if (/^a  /i.test(thetitle))
  {
    return thetitle.replace(/^a /i,"")+", A";
  } else {
    return thetitle;
  }
}

function WEBLIB_WriteKeywords(name) {
  var keywordstextarea = document.getElementById(name+'-keyword-list');
  var keywordschecklist = document.getElementById(name+'-keywordchecklist');

  var keywords = keywordstextarea.value.split(',');
  keywordschecklist.innerHTML = "";
  var i;
  for (i = 0; i < keywords.length; i++) {
    var val,txt, button_id, id = name;
    val = keywords[i].trim();
    if (val == '') continue;
    button_id = id + '-check-num-' + val;
    txt = '<span><a id="' + button_id + 
	  '" class="ntdelbutton" onclick="WEBLIB_DeleteKeyword('+"'"+name+"','"+val+"'"+')">X</a>&nbsp;' + val + '</span> ';
    keywordschecklist.innerHTML += txt;
  }
} 

function AWSInsertCallback()
{
  if (this.readyState == 1) 
  {
    document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<p>'+admin_js.loading+'...</p>';
  } else if (this.readyState == 4)
  {
    if (this.status == 200)
    {
      if (this.responseXML != null)
      {
	var ErrorsElts = this.responseXML.getElementsByTagName('Error');
	if (ErrorsElts != null && ErrorsElts.length > 0) {
	  var ierr;
	  var WorkStatus = document.getElementById('amazon-search-workstatus');
	  WorkStatus.innerHTML = '';
	  for (ierr = 0; ierr < ErrorsElts.length; ierr++) {
	    var theError = ErrorsElts[ierr];
	    var theMessage = theError.getElementsByTagName('Message')[0].childNodes[0].nodeValue;
	    WorkStatus.innerHTML += '<p class="error">'+theMessage+'</p>';
	  }
	  return;
	}
	var item = this.responseXML.getElementsByTagName('Item')[0];

	var smallimage = item.getElementsByTagName('SmallImage')[0];
	if (smallimage != null)
	{
	  var smallimageURL = smallimage.getElementsByTagName('URL')[0].childNodes[0].nodeValue;
	  document.getElementById('thumburl').value = smallimageURL;
	}
	document.getElementById('title').value = '';
        document.getElementById('itemauthor').value = '';
	document.getElementById('description').value = '';
	document.getElementById('media').value = '';
	document.getElementById('publisher').value = '';
	document.getElementById('publocation').value = '';
	document.getElementById('pubdate').value = '';
	document.getElementById('edition').value = '';
	document.getElementById('isbn').value = '';
	var ItemAttributesList = item.getElementsByTagName('ItemAttributes');
	//log("*** AWSInsertCallback: ItemAttributesList.length is "+ItemAttributesList.length);
	var k;
	for (k = 0; k < ItemAttributesList.length; k++)
	{
	  var ItemAttributes = ItemAttributesList[k];
	  //log("*** AWSInsertCallback: (k="+k+"): ItemAttributes.childNodes.length is "+ItemAttributes.childNodes.length);
	  var j;
	  for (j = 0; j < ItemAttributes.childNodes.length; j++)
	  {
	    var attribute = ItemAttributes.childNodes[j];
	    var value = attribute.childNodes[0].nodeValue;
	    if (value != null)
	    {
	      //log("*** AWSInsertCallback: (j="+j+") attribute.tagName is "+attribute.tagName+" value is '"+value+"'");
	      switch (attribute.tagName) {
		case 'Editor':
		case 'Artist':
		case 'Actor':
		case 'Director':
		case 'Foreword':
		case 'Contributor':
		case 'Author':
		  var thename = AWSFixName(value);
		  if (document.getElementById('itemauthor').value == '')
		  {
		    document.getElementById('itemauthor').value = thename;
		  } else {
		    document.getElementById('itemauthor').value += ' and '+thename;
		  }
		  break;
		case 'Creator':
		  var thename = AWSFixName(value);
		  var role = attribute.attributes.getNamedItem("role");
		  if (role == null) attribute.attributes.getNamedItem("Role");
		  if (role != null) thename += ' ('+role+')';
		  if (document.getElementById('itemauthor').value == '')
		  {
		    document.getElementById('itemauthor').value = thename;
		  } else {
		    document.getElementById('itemauthor').value += ' and '+thename;
		  }
		  break;
		case 'Title':
		  if (document.getElementById('title').value == '')
		  {
		    document.getElementById('title').value = AWSFixTitle(value);
		  }
		  break;
		case 'ReleaseDate':
		case 'PublicationDate':
		  if (document.getElementById('pubdate').value == '')
		  {
		    var date;
		    //log("*** AWSInsertCallback(): value = "+value);
		    if (/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]/.test(value)) {
			var dateval = value.split('-');
			//log("*** AWSInsertCallback(): dateval is "+dateval);
			date = new Date(dateval[0],dateval[1]-1,dateval[2],0,0,0);
		    } else {
			date = new Date(value);
		    }
		    //log("*** AWSInsertCallback(): date.getMonth() = "+date.getMonth()+", date.getDate() = "+date.getDate()+", date.getYear() = "+date.getYear());
		    var months = ['Jan','Feb','Mar','Apr','May','Jun',
				 'Jul','Aug','Sep','Oct','Nov','Dec'];
		    document.getElementById('pubdate').value = 
			months[date.getMonth()]+'/'+date.getDate()+'/'+
					(date.getYear()+1900);
		  }
		  break;
		case 'Studio':
		case 'Label':
		case 'Publisher':
		  if (document.getElementById('publisher').value == '')
		  {
		    document.getElementById('publisher').value = value;
		  }
		  break;
		case 'ISBN':
		  document.getElementById('isbn').value = value;
		  break;
		case 'Edition':
		  document.getElementById('edition').value = value;
		  break;
		case 'Binding':
		case 'Format':
		case 'ProductGroup':
		  if (document.getElementById('media').value == '')
		  {
		    document.getElementById('media').value = value;
		  } else {
		    document.getElementById('media').value += ','+value;
		  }
		  break;
		case 'Height':
		case 'Width':
		case 'Length':
		case 'Weight':
		  var units = attribute.attributes.getNamedItem("units");
		  if (units == null) attribute.attributes.getNamedItem("Units");
		  if (units != 'pixels') {
		    document.getElementById('description').value += attribute.tagName+' '+value+' '+units+"\n";
		  } else {
		    document.getElementById('description').value += attribute.tagName+' '+value+"\n";
		  }
		  break;
		default:
		  document.getElementById('description').value += attribute.tagName;
		  document.getElementById('description').value += ' ';
		  var ia;
		  for (ia = 0; ia < attribute.attributes.length; ia++)
		  {
		    var attr = attribute.attributes.item(ia);
		    document.getElementById('description').value += attr.name+'="'+attr.value+'" ';
		  }
		  document.getElementById('description').value += value+"\n";
		  break;
	      }
	    }
	  }
	}
	var keywords = item.getElementsByTagName('Keywords');
	var needcomma = false;
	var keys = '';
	for (k = 0; k < keywords.length; k++)
	{
	  var keyword = keywords[0];
	  for (j = 0; j < keyword.childNodes.length; j++)
	  {
	    if (needcomma) keys += ', ';
	    keys += keyword.childNodes[j].nodeValue;
	    needcomma = true;
	  }
	}
	document.getElementById('itemedit-keyword-list').value = keys;
	WEBLIB_WriteKeywords('itemedit');
	document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<p>'+admin_js.formInsertionComplete+'</p>';
      } else document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<h1>'+admin_js.nodata+'</h1>';
    } else document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<h1>'+admin_js.ajaxerr+this.status+'</h1>';
  } 
}

function UpdatePatronID(userid) {
  var nocache = Math.random() * 1000000;
  var userid  = userid;
  var patronid = document.getElementById('patronid-'+userid).value;
  var url = admin_js.WEBLIB_BASEURL+'/UpdatePatronID.php?userid='+userid+
		'&patronid='+patronid+'&nocache='+nocache;
  var request = new ajaxRequest();
  request.open("GET",url,true);
  request.onreadystatechange = function() 
  {
    if (this.readyState == 4)
    {
      if (this.status == 200)
      {
	if (this.responseXML != null)
	{
	  userid = this.responseXML.getElementsByTagName('userid')[0].childNodes[0].nodeValue;
	  patronid = this.responseXML.getElementsByTagName('patronid')[0].childNodes[0].nodeValue;
	  document.getElementById('displayed-patronid-'+userid).innerHTML = patronid;
	} 
	else alert(admin_js.nodata);
      }
      else alert(admin_js.ajaxerr+this.statusText);
    }
  }
  request.send(null);
}

function FindPatron() {
  var nocache = Math.random() * 1000000;
  var searchname = document.getElementById('searchname').value;
  var url = admin_js.WEBLIB_BASEURL+'/FindPatron.php?searchname='+encodeURI(searchname)+
		'&nocache='+nocache;
  var request = new ajaxRequest();
  request.open("GET",url,true);
  request.onreadystatechange = function() 
  {
    if (this.readyState == 1) {
      document.getElementById('weblib-patronlist').innerHTML = '<p>'+admin_js.lookingUpPatron+'...</p>';
    } else if (this.readyState == 4)
    {
      if (this.status == 200)
      {
	document.getElementById('weblib-patronlist').innerHTML = '';
	if (this.responseXML != null)
	{
	  var patrons = this.responseXML.getElementsByTagName('patron');
	  //log("*** FindPatron -- onreadystatechange: patrons.length = "+patrons.length);
	  switch (patrons.length) {
	    case 0:
	      document.getElementById('weblib-patronlist').innerHTML = '<p>'+admin_js.noMatchingPatrons+'</p>';
	      break;
	    case 1:
	      document.getElementById('patronid').value = 
		patrons[0].getElementsByTagName('id')[0].childNodes[0].nodeValue;
	      break;
	    default:
	      //log("*** FindPatron -- onreadystatechange: patrons.length > 1");
	      var outHTML  = '<label for="searched-patronid" class="inputlab"></label>';
	      outHTML += '<select class="patrondroplist weblib-input-fill" id="searched-patronid">';
	      var j;
	      for (j = 0; j < patrons.length; j++) {
		//log("*** FindPatron -- onreadystatechange: j = "+j);
		var id = patrons[j].getElementsByTagName('id')[0].childNodes[0].nodeValue;
		var name = patrons[j].getElementsByTagName('name')[0].childNodes[0].nodeValue;
		//log("*** FindPatron -- onreadystatechange: id = "+id+", name = "+name);
		outHTML += '<option value="'+id+'">'+name+'</option>';
	      }
	      outHTML += '</select>';
	      outHTML +=
		'<input class="button" type="button" name="patronselect" value="'+admin_js.selectPatron+'" onclick="SelectPatron();" />';
	      //log("*** FindPatron -- onreadystatechange: outHTML = "+outHTML);
	      document.getElementById('weblib-patronlist').innerHTML = outHTML;
	    break;
	  }
	}
	else alert(admin_js.nodata);
      }
      else alert(admin_js.ajaxerr+this.statusText);
    }
  }
  request.send(null);
}

function SelectPatron() {
  var searched_patronid = document.getElementById('searched-patronid');
  var id = searched_patronid.options[searched_patronid.selectedIndex].value;
  //log("*** SelectPatron: id = "+id);
  var select = document.getElementById('patronid');
  /*if (select.options[select.selectedIndex].value == id) return;*/
  select.options[select.selectedIndex].selected = false;
  select.selectedIndex = 0;
  var i;
  for (i = 1; i < select.length; i++) {
    //log("*** SelectPatron: select.options["+i+"].value = "+select.options[i].value);
    if (select.options[i].value == id) {
      select.options[i].selected = true;
      select.selectedIndex = i;
      break;
    }
  }
  document.getElementById('weblib-patronlist').innerHTML = '';
}

function Renew(barcode) {
  var nocache = Math.random() * 1000000;
  var url = admin_js.WEBLIB_BASEURL+'/RenewItem.php?barcode='+encodeURI(barcode)+
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
	  
	  var message = this.responseXML.getElementsByTagName('message');
	  if (message.length > 0) {
	    messageText = message[0].childNodes[0].nodeValue;
	    document.getElementById('ajax-message').innerHTML = '<p><span id="error">'+messageText+'</span></p>';
	  }
	  var result = this.responseXML.getElementsByTagName('result');
	  if (result.length > 0) {
	    var barcode = result[0].getElementsByTagName('barcode')[0].childNodes[0].nodeValue;
	    var duedate = result[0].getElementsByTagName('duedate')[0].childNodes[0].nodeValue;
	    var spanelt = document.getElementById('due-date-'+barcode);
	    spanelt.className = spanelt.className.replace(/overdue/i,"");
	    spanelt.innerHTML = duedate;
	  }
	}
	else alert(admin_js.nodata);
      }
      else alert(admin_js.ajaxerr+this.statusText);
    }
  }
  request.send(null);
}

function PlaceHold(barcode) {
  // patronid
  var patronidSelect = document.getElementById('patronid');
  var selectedPatron = patronidSelect.selectedIndex;
  var patronid = patronidSelect.options[selectedPatron].value;
  var nocache = Math.random() * 1000000;
  var url = admin_js.WEBLIB_BASEURL+'/PlaceHoldOnItem.php?barcode='+encodeURI(barcode)+
  		'&patronid='+patronid+'&nocache='+nocache;
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
	    if (holdcount == 1) {
	      spanelt.innerHTML = holdcount+' '+admin_js.hold;
	    } else {
	      spanelt.innerHTML = holdcount+' '+admin_js.holds;
	    }
	    var brelt = document.getElementById('hold-br-'+barcode);
	    brelt.removeAttribute('style');
	    var patroninfo = document.getElementById('patron-info-'+barcode);
	    if (patroninfo.innerHTML == '') {
	      var name = result[0].getElementsByTagName('name')[0].childNodes[0].nodeValue;
	      var email = result[0].getElementsByTagName('email')[0].childNodes[0].nodeValue;
	      var telephone = result[0].getElementsByTagName('telephone')[0].childNodes[0].nodeValue;
	      var expires = result[0].getElementsByTagName('expires')[0].childNodes[0].nodeValue;
	      var ptHTML = '<a href="mailto:'+email+'">'+name+'</a>';
	      ptHTML += '<br />'+telephone;
	      ptHTML += '<br />Expires: '+expires;
	      patroninfo.innerHTML = ptHTML;
	    }
	  }
	}
	else alert(admin_js.nodata);
      }
      else alert(admin_js.ajaxerr+this.statusText);
    }
  }
  request.send(null);
}



function WEBLIB_AddKeyword(name) {
  var keydiv = document.getElementById(name+'-keyword-div');
  var keywordstextarea = document.getElementById(name+'-keyword-list');
  var keyinput = document.getElementById(name+'-new-keyword-item_keyword');
  if (keywordstextarea.value != "") keywordstextarea.value += ',';
  keywordstextarea.value += keyinput.value.toUpperCase();
  WEBLIB_WriteKeywords(name);  
}

function WEBLIB_DeleteKeyword(name,keyword) {
  var keywordstextarea = document.getElementById(name+'-keyword-list');
  var ic1 = keywordstextarea.value.indexOf(','+keyword+',');
  var ic2 = keywordstextarea.value.indexOf(','+keyword);
  var ic3 = keywordstextarea.value.indexOf(keyword+',');
  if (ic1 >= 0) {
    keywordstextarea.value = keywordstextarea.value.substr(0,ic1) + 
			     keywordstextarea.value.substr(ic1+keyword.length+1);
  } else if (ic2 >= 0 && (ic2+keyword.length+1) == keywordstextarea.value.length) {
    keywordstextarea.value = keywordstextarea.value.substr(0,ic2);
  } else if (ic3 == 0) {
    keywordstextarea.value = keywordstextarea.value.substr(ic3+keyword.length+1);
  } else if (keywordstextarea.value == keyword) {
    keywordstextarea.value = '';
  }
  WEBLIB_WriteKeywords(name);  
}


function AWSLookupCallback()
{
  var outHTML = '';
  if (this.readyState == 1) 
  {
    document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<p>'+admin_js.loading+'...</p>';
    document.getElementById('amazon-item-lookup-display').innerHTML = '';
  } else if (this.readyState == 4)
  {
    if (this.status == 200)
    {
      if (this.responseXML != null)
      {
	var ErrorsElts = this.responseXML.getElementsByTagName('Error');
	if (ErrorsElts != null && ErrorsElts.length > 0) {
	  var ierr;
	  var WorkStatus = document.getElementById('amazon-search-workstatus');
	  WorkStatus.innerHTML = '';
	  for (ierr = 0; ierr < ErrorsElts.length; ierr++) {
	    var theError = ErrorsElts[ierr];
	    var theMessage = theError.getElementsByTagName('Message')[0].childNodes[0].nodeValue;
	    WorkStatus.innerHTML += '<p class="error">'+theMessage+'</p>';
	  }
	  return;
	}
	var item = this.responseXML.getElementsByTagName('Item')[0];
	var title = item.getElementsByTagName('Title')[0].childNodes[0].nodeValue;
	var asin  = item.getElementsByTagName('ASIN')[0].childNodes[0].nodeValue;
	outHTML += '<h3>'+title;
        outHTML += '<img class="WEBLIB_AWS_addinsertbutton"';
        outHTML += ' src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16"';
        outHTML += ' alt="'+admin_js.insertTitle+'"';
        outHTML += ' title="'+admin_js.insertTitle+'"';
        outHTML += ' onclick="WEBLIB_InsertTitle('+"'"+QuoteString(title)+"'"+');" />';
        outHTML += ' ('+asin;
        outHTML += '<img class="WEBLIB_AWS_addinsertbutton"';
        outHTML += ' src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16"';
        outHTML += ' alt="'+admin_js.insertISBN+'"' ;
        outHTML += ' title="'+admin_js.insertISBN+'"' ;
        outHTML += ' onclick="WEBLIB_InsertISBN('+"'"+QuoteString(asin)+"'"+');" />';
        outHTML += ")</h3>\n";

	var smallimage = item.getElementsByTagName('SmallImage')[0];
	if (smallimage != null)
	{
	  var smallimageURL = smallimage.getElementsByTagName('URL')[0].childNodes[0].nodeValue;
	  var smallimageHeight = smallimage.getElementsByTagName('Height')[0].childNodes[0].nodeValue;
	  var smallimageWidth = smallimage.getElementsByTagName('Width')[0].childNodes[0].nodeValue;
	      
	  outHTML += '<img src="'+smallimageURL+'" height="'+smallimageHeight+
          '" width="'+smallimageWidth+'" border="0">';
          outHTML += '<img class="WEBLIB_AWS_addinsertbutton" src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertThumbnail+'" title="'+admin_js.insertThumbnail+'" onclick="WEBLIB_InsertThumb('+"'"+QuoteString(smallimageURL)+"'"+');" />';
          
                    
        }
	var ItemAttributesList = item.getElementsByTagName('ItemAttributes');
	outHTML += '<ul>';
	var k;
	for (k = 0; k < ItemAttributesList.length; k++)
	{
	  var ItemAttributes = ItemAttributesList[k];
	  outHTML += '<table>';
	  for (j = 0; j < ItemAttributes.childNodes.length; j++)
	  {
	    var attribute = ItemAttributes.childNodes[j];
	    var value = attribute.childNodes[0].nodeValue;
	    if (value != null)
	    {
	      outHTML += '<tr>';
	      outHTML += '<th valign="top" width="20%">'+attribute.tagName+'</th>';
	      outHTML += '<td valign="top" width="80%">'+attribute.childNodes[0].nodeValue+'</td>';
              outHTML += '<td>';
              switch (attribute.tagName) {
                  case 'Editor':
                  case 'Artist':
                  case 'Actor':
                  case 'Director':
                  case 'Foreword':
                  case 'Contributor':
                  case 'Author':
                    var thename = AWSFixName(value);
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToAuthor+'" title="'+admin_js.addToAuthor+'" onclick="WEBLIB_AddAuthor('+"'"+QuoteString(thename)+"'"+');" />';
                    break;
                  case  'Creator':
                    var thename = AWSFixName(value);
                    var role = attribute.attributes.getNamedItem("role");
                    if (role == null) attribute.attributes.getNamedItem("Role");
                    if (role != null) thename += ' ('+role+')';
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToAuthor+'" title="'+admin_js.addToAuthor+'" onclick="WEBLIB_AddAuthor('+"'"+QuoteString(thename)+"'"+');" />';
                    break;
                  case 'ReleaseDate':
                  case 'PublicationDate':
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertAsDate+'" title="'+admin_js.insertAsDate+'"  onclick="WEBLIB_InsertDate('+"'"+QuoteString(value)+"'"+');" />';
                    break;
                  case 'Studio':
                  case 'Label':
                  case 'Publisher':
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertAsPublisher+'" title="'+admin_js.insertAsPublisher+'" onclick="WEBLIB_InsertPublisher('+"'"+QuoteString(value)+"'"+');" />';
                    break;
                  case 'ISBN':
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertISBN+'" title="'+admin_js.insertISBN+'" " onclick="WEBLIB_InsertISBN('+"'"+QuoteString(value)+"'"+');" />';
                    break;
                  case 'Edition':
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertEdition+'" title="'+admin_js.insertEdition+'" onclick="WEBLIB_InsertEdition('+"'"+QuoteString(value)+"'"+');" />';
                    break;
                  case 'Binding':
                  case 'Format':
                  case 'ProductGroup':
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToMedia+'" title="'+admin_js.addToMedia+'" onclick="WEBLIB_AddToMedia('+"'"+QuoteString(value)+"'"+');" />';
                    break;
                  case 'Height':
                  case 'Width':
                  case 'Length':
                  case 'Weight':
                    var units = attribute.attributes.getNamedItem("units");
                    if (units != 'pixels') {
                        var temp = value+' '+units;
                    } else {
                        var temp = value;
                    }
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToDescription+'" title="'+admin_js.addToDescription+'" onclick="WEBLIB_AddToDescription('+"'"+QuoteString(temp)+"'"+');" />';
                    break;
                  case 'Title':
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.insertTitle+'" title="'+admin_js.insertTitle+'" onclick="WEBLIB_InsertTitle('+"'"+QuoteString(value)+"'"+');" />';  
                    break;
                  default:
                    outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToDescription+'" title="'+admin_js.addToDescription+'" onclick="WEBLIB_AddToDescription('+"'"+QuoteString(value)+"'"+');" />';
                    break;
                }
                outHTML += '</td></tr>';
	    }
	  }
	  outHTML += '</table>';
	}
	outHTML += '</ul>';
	var keywords = item.getElementsByTagName('Keywords');
	var needcomma = false;
	outHTML += '<p>';
	for (k = 0; k < keywords.length; k++)
	{
	  var keyword = keywords[0];
	  var j;
	  for (j = 0; j < keyword.childNodes.length; j++)
	  {
              if (needcomma) outHTML += ', ';
              outHTML += '<a href="" onclick="WEBLIB_InsertKeyword('+"'"+QuoteString(keyword.childNodes[j].nodeValue)+"'"+');return false;">';
              outHTML += keyword.childNodes[j].nodeValue;
              outHTML += '<img src="'+admin_js.WEBLIB_BASEURL+'/images/update_field.png" width="16" height="16" class="WEBLIB_AWS_addinsertbutton" alt="'+admin_js.addToKeywords+'" />';
              outHTML += '</a>';
	    needcomma = true;
	  }
	}
	outHTML += '</p>'
        document.getElementById('amazon-item-lookup-display').innerHTML = outHTML;
        document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<p>'+admin_js.lookupComplete+'</p>';
      } else document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<h1>'+admin_js.nodata+'</h1>';
    } else document.getElementById('amazon-item-lookup-workstatus').innerHTML = '<h1>'+admin_js.ajaxerr+this.status+'</h1>';
  } 
}

function WEBLIB_InsertTitle(title) {
    document.getElementById('title').value = title;
}

function WEBLIB_InsertISBN(isbn) {
    document.getElementById('isbn').value = isbn;
}

function WEBLIB_InsertThumb(url) {
    document.getElementById('thumburl').value = url;
}

function WEBLIB_AddAuthor(thename) {
    if (document.getElementById('itemauthor').value == '')
    {
        document.getElementById('itemauthor').value = thename;
    } else {
        document.getElementById('itemauthor').value += ' and '+thename;
    }
}

function WEBLIB_InsertDate(value) {
    var date;
    //log("*** AWSInsertCallback(): value = "+value);
    if (/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]/.test(value)) {
        var dateval = value.split('-');
        //log("*** AWSInsertCallback(): dateval is "+dateval);
        date = new Date(dateval[0],dateval[1]-1,dateval[2],0,0,0);
    } else {
        date = new Date(value);
    }
    //log("*** AWSInsertCallback(): date.getMonth() = "+date.getMonth()+", date.getDate() = "+date.getDate()+", date.getYear() = "+date.getYear());
    var months = ['Jan','Feb','Mar','Apr','May','Jun',
                  'Jul','Aug','Sep','Oct','Nov','Dec'];
    document.getElementById('pubdate').value = 
    months[date.getMonth()]+'/'+date.getDate()+'/'+
    (date.getYear()+1900);
}

function WEBLIB_InsertPublisher(value) {
    document.getElementById('publisher').value = value;
}

function WEBLIB_InsertEdition(value) {
    document.getElementById('edition').value = value;
}

function WEBLIB_AddToMedia(value) {
    if (document.getElementById('media').value == '')
    {
        document.getElementById('media').value = value;
    } else {
        document.getElementById('media').value += ','+value;
    }
}

function WEBLIB_AddToDescription(value) {
    document.getElementById('description').value += value+"\n";
}

function WEBLIB_InsertKeyword(keyword) {
    if (document.getElementById('itemedit-keyword-list').value != '') 
    {
        document.getElementById('itemedit-keyword-list').value += ',';
    }
    document.getElementById('itemedit-keyword-list').value += keyword;
}

log('*** admin.js: loading JQuery functions');

jQuery(function(jQuery) {
       jQuery('#SearchString').on('keydown',
        function (event) {
            if (event.which == 13 /* Return */) {
                AWSSearch(1);
                if (event.stopPropagation) {
                    event.stopPropagation();
                }
                //IE8 and Lower
                else {
                    event.cancelBubble = true;
                }
            }
        });
       
   });
   

<?php
	/* AWS Ajax support code: Sign an AWS Amazon Product Advertising API
	   request and issue a request and send the XML back out to the 
	   JavaScript */

/*
Modified to use CURL : Sameer Borate
Original code Copyright (c) 2009 Ulrich Mierendorff

Permission is hereby granted, free of charge, to any person obtaining a
copy of this software and associated documentation files (the "Software"),
to deal in the Software without restriction, including without limitation
the rights to use, copy, modify, merge, publish, distribute, sublicense,
and/or sell copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
DEALINGS IN THE SOFTWARE.
*/


/*
  
  More information on the authentication process can be found here:
  http://docs.amazonwebservices.com/AWSECommerceService/latest/DG/BasicAuthProcess.html
  
*/



function  aws_signed_request($region,$params,$public_key,$private_key)
{

    $method = "GET";
    $host = "webservices.amazon.".$region; // must be in small case
    $uri = "/onca/xml";
    
    
    $params["Service"]          = "AWSECommerceService";
    $params["AWSAccessKeyId"]   = $public_key;
    $params["Timestamp"]        = gmdate("Y-m-d\TH:i:s\Z");
    $params["Version"]          = "2009-03-31";

    /* The params need to be sorted by the key, as Amazon does this at
      their end and then generates the hash of the same. If the params
      are not in order then the generated hash will be different thus
      failing the authetication process.
    */
    ksort($params);
    
    $canonicalized_query = array();

    foreach ($params as $param=>$value)
    {
        $param = str_replace("%7E", "~", rawurlencode($param));
        $value = str_replace("%7E", "~", rawurlencode($value));
        $canonicalized_query[] = $param."=".$value;
    }
    
    $canonicalized_query = implode("&", $canonicalized_query);

    $string_to_sign = $method."\n".$host."\n".$uri."\n".$canonicalized_query;
    
    /* calculate the signature using HMAC with SHA256 and base64-encoding.
       The 'hash_hmac' function is only available from PHP 5 >= 5.1.2.
    */
    $signature = base64_encode(hash_hmac("sha256", $string_to_sign, $private_key, True));
    
    /* encode the signature for the request */
    $signature = str_replace("%7E", "~", rawurlencode($signature));
    
    /* create request */
    $request = "http://".$host.$uri."?".$canonicalized_query."&Signature=".$signature;

    /* I prefer using CURL */
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$request);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $xml_response = curl_exec($ch);
    
    /* If cURL doesn't work for you, then use the 'file_get_contents'
       function as given below.
    */
    //$xml_response = file_get_contents($request);
    
    if ($xml_response === False)
    {
        return False;
    }
    else
    {	
	/* Return the raw XML -- we will be sending it off to the JavaScript
	   which will deal with parsing it.
	 */
	return $xml_response;
    }
}

/* Minimal WP set up -- we are called directly, not through the normal WP
 * process.  We won't be displaying full fledged WP pages either.
 */
$wp_root = dirname(__FILE__) .'/../../../';
if(file_exists($wp_root . 'wp-load.php')) {
      require_once($wp_root . "wp-load.php");
} else if(file_exists($wp_root . 'wp-config.php')) {
      require_once($wp_root . "wp-config.php");
} else {
      exit;
}

@error_reporting(0);
  
global $wp_db_version;
if ($wp_db_version < 8201) {
	// Pre 2.6 compatibility (BY Stephen Rider)
	if ( ! defined( 'WP_CONTENT_URL' ) ) {
		if ( defined( 'WP_SITEURL' ) ) define( 'WP_CONTENT_URL', WP_SITEURL . '/wp-content' );
		else define( 'WP_CONTENT_URL', get_option( 'url' ) . '/wp-content' );
	}
	if ( ! defined( 'WP_CONTENT_DIR' ) ) define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
	if ( ! defined( 'WP_PLUGIN_URL' ) ) define( 'WP_PLUGIN_URL', WP_CONTENT_URL. '/plugins' );
	if ( ! defined( 'WP_PLUGIN_DIR' ) ) define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

require_once(ABSPATH.'wp-admin/admin.php');

if (!current_user_can('manage_collection')) {
  wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
}

/* Make sure we are first and only program */
if (headers_sent()) {
  @header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
  wp_die(__('The headers have been sent by another plugin - there may be a plugin conflict.','web-librarian'));
}

$params = $_REQUEST;
unset($params['nocache']);

//file_put_contents("php://stderr","*** AWSXmlGet.php: params = ".print_r($params,true)."\n");

$region      = get_option('weblib_aws_regiondom');
if ($region == 'jp') {
  $region = 'co.jp';
} else if ($region == 'uk') {
  $region = 'co.uk';
}
$public_key  = get_option('weblib_aws_public_key');
$private_key = get_option('weblib_aws_private_key');
$params['AssociateTag'] = get_option('weblib_associate_tag');

//file_put_contents("php://stderr","*** AWSXmlGet.php: region = $region, public_key = $public_key, private_key = $private_key \n");

$xml_response = aws_signed_request($region,$params,$public_key,$private_key);

//file_put_contents("php://stderr","*** AWSXmlGet.php: xml_response = '$xml_response'\n");

if ($xml_response) {
  /* http Headers */
  @header('Content-Type: text/xml');
  @header('Content-Length: '.strlen($xml_response));
  @header("Pragma: no-cache");
  @header("Expires: 0");
  @header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
  @header("Robots: none");
  echo "";	/* End of headers */
  echo $xml_response;
} else {
  @header('Status: 500 Request Failed');
  @header('Content-Type: text/html');
  @header("Pragma: no-cache");
  @header("Expires: 0");
  @header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
  @header("Robots: none");
  echo "";	/* End of headers */
  ?><html><head><title>500 Request Failed</title></head><body>
  <h1>500 Request Failed</h1></body></html>
  <?php
}
?>


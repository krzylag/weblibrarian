<?php
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

/* Make sure we are first and only program */
if (headers_sent()) {
  @header('Content-Type: ' . get_option('html_type') . '; charset=' . get_option('blog_charset'));
  wp_die(__('The headers have been sent by another plugin - there may be a plugin conflict.','web-librarian'));
}

define('WEBLIB_FILE', basename(__FILE__));
define('WEBLIB_DIR' , dirname(__FILE__));
define('WEBLIB_INCLUDES', WEBLIB_DIR . '/includes');
require_once(WEBLIB_INCLUDES . '/database_code.php');

$barcode = $_REQUEST['barcode'];
$xml_response = '<?xml version="1.0" ?>';

$outitem = WEBLIB_OutItem::OutItemByBarcode($barcode);
if ($outitem != null) {
  $numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($outitem->barcode());
  if ($numberofholds > 0) {
    $xml_response .= '<message>Someone else has a hold on this item!</message>';
  } else if ($outitem->patronid() == 
		get_user_meta(wp_get_current_user()->ID,'PatronID',true) ||
	     current_user_can('manage_circulation') ) {
    $type = new WEBLIB_Type($outitem->type());
    $currentdue = strtotime($outitem->datedue());
    $originalout = strtotime($outitem->dateout());
    $newduedate = $currentdue + ($type->loanperiod() * 24 * 60 * 60);
    $totalloandays = ($newduedate - $originalout) / (24 * 60 * 60);
    $renewals = $totalloandays / $type->loanperiod();
    unset($type);
    if ($renewals > 3) {
      $xml_response .= '<message>Maximum number of renewals reached.</message>';
    } else {
      $duedate = date('Y-m-d',$newduedate);
      $outitem->set_datedue($duedate);
      $outitem->store();
      $xml_response .= '<result><barcode>'.$barcode.'</barcode><duedate>'.
			mysql2date('F j, Y',$duedate).'</duedate></result>';
    }
  } else {
    $xml_response .= '<message>You do not have enough priviledge to do this!</message>';
  }
} else {
  $xml_response .= '<message>Item is not checked out!</message>';
}

/* http Headers */
@header('Content-Type: text/xml');
@header('Content-Length: '.strlen($xml_response));
@header("Pragma: no-cache");
@header("Expires: 0");
@header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
@header("Robots: none");
echo "";	/* End of headers */
echo $xml_response;


  


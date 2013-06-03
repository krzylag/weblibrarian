<?php
/** Load WordPress Administration Bootstrap */
if(file_exists('../../../wp-load.php')) {
	require_once("../../../wp-load.php");
} else if(file_exists('../../wp-load.php')) {
	require_once("../../wp-load.php");
} else if(file_exists('../wp-load.php')) {
	require_once("../wp-load.php");
} else if(file_exists('wp-load.php')) {
	require_once("wp-load.php");
} else if(file_exists('../../../../wp-load.php')) {
	require_once("../../../../wp-load.php");
} else if(file_exists('../../../../wp-load.php')) {
	require_once("../../../../wp-load.php");
} else {

	if(file_exists('../../../wp-config.php')) {
		require_once("../../../wp-config.php");
	} else if(file_exists('../../wp-config.php')) {
		require_once("../../wp-config.php");
	} else if(file_exists('../wp-config.php')) {
		require_once("../wp-config.php");
	} else if(file_exists('wp-config.php')) {
		require_once("wp-config.php");
	} else if(file_exists('../../../../wp-config.php')) {
		require_once("../../../../wp-config.php");
	} else if(file_exists('../../../../wp-config.php')) {
		require_once("../../../../wp-config.php");
	} else {
		echo '<p>Failed to load bootstrap.</p>';
		exit;
	}

}

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

require_once(dirname(__FILE__) . '/WebLibrarian.php');

load_plugin_textdomain('web-librarian',WEBLIB_BASEURL.'/languages/',
                       basename(WEBLIB_DIR).'/languages/');
$version = WebLibrarian::_getVersion();
################################################################################
// REPLACE ADMIN URL
################################################################################

if (function_exists('admin_url')) {
	wp_admin_css_color('classic', __('Blue'), admin_url("css/colors-classic.css"), array('#073447', '#21759B', '#EAF3FA', '#BBD8E7'));
	wp_admin_css_color('fresh', __('Gray'), admin_url("css/colors-fresh.css"), array('#464646', '#6D6D6D', '#F1F1F1', '#DFDFDF'));
} else {
	wp_admin_css_color('classic', __('Blue'), get_bloginfo('wpurl').'/wp-admin/css/colors-classic.css', array('#073447', '#21759B', '#EAF3FA', '#BBD8E7'));
	wp_admin_css_color('fresh', __('Gray'), get_bloginfo('wpurl').'/wp-admin/css/colors-fresh.css', array('#464646', '#6D6D6D', '#F1F1F1', '#DFDFDF'));
}

wp_enqueue_script( 'common' );
wp_enqueue_script( 'jquery-color' );

wp_enqueue_style('weblib-front-style',WEBLIB_CSSURL . '/front.css',
                 null,$version);
wp_enqueue_style('weblib-admin-style',WEBLIB_CSSURL . '/admin.css',
                 array('weblib-front-style'),$version);

wp_enqueue_script('front_js',WEBLIB_JSURL . '/front.js', array(), $version);
wp_localize_script( 'front_js','front_js',WebLibrarian::localize_vars_front());
wp_enqueue_script('jquery-ui-resizable');
wp_enqueue_script('admin_js',WEBLIB_JSURL . '/admin.js', array('front_js','jquery-ui-resizable'), $version);
wp_localize_script( 'admin_js','admin_js',WebLibrarian::localize_vars_admin());
wp_enqueue_script('AWSFunctions_js',WEBLIB_JSURL . '/AWSFunctions.js', 
                  array('admin_js'), $version);

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php do_action('admin_xml_ns'); ?> <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo get_option('blog_charset'); ?>" />
	<title><?php bloginfo('name') ?> &rsaquo; <?php _e('Uploads'); ?> &#8212; <?php _e('WordPress'); ?></title>
	<?php
		wp_enqueue_style( 'global' );
		wp_enqueue_style( 'wp-admin' );
		wp_enqueue_style( 'colors' );
		wp_enqueue_style( 'media' );
	?>
	<script type="text/javascript">
	//<![CDATA[
		function addLoadEvent(func) {if ( typeof wpOnload!='function'){wpOnload=func;}else{ var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}}
	//]]>
	</script>
	<?php
	do_action('admin_print_styles');
	do_action('admin_print_scripts');
	do_action('admin_head');
	if ( isset($content_func) && is_string($content_func) )
		do_action( "admin_head_{$content_func}" );
	$tab = isset($_REQUEST['tab'])?$_REQUEST['tab']:'links';
	
	?>
</head>
<body id="aws-form" class="wp-admin branch-3-3 version-3-3-2 admin-color-fresh">
<div id="item-aws">
  <span id="amazon-search-workstatus"></span><br clear="all" />
  <div id="amazon-result-list"></div>
  <span id="amazon-page-buttons">
    <input type="text" id="amazon-page-1" class="page-label" 
     value="1" readonly="readonly" size="1"/>
    <input type="button" id="amazon-goto-page-1" 
     value="<<" class="page-button" 
       onclick="AWSGotoFirstPage();" />
      <input type="button" id="amazon-goto-previous-page"
       value="<" class="page-button"
        onclick="AWSGotoPrevPage();" />
       <input type="button" id="amazon-goto-page"
        value="<?php _e('Goto Page:','web-librarian'); ?>" class="page-button"
        onclick="AWSGotoPage();" />
       <input type="text" id="amazon-page-current" 
        class="page-label"
        value="  "  size="3"/>
       <input type="button" id="amazon-goto-next-page"
        value=">" class="page-button"
       onclick="AWSGotoNextPage();" />
      <input type="button" id="amazon-goto-last-page"
       value=">>" class="page-button"
     onclick="AWSGotoLastPage();" />
    <input type="text" id="amazon-page-N" class="page-label"
     value="  " readonly="readonly" size="3" />
    <br /></span>
  <span id="amazon-search-box">
    <label for="SearchIndex"><?php _e('Search In:','web-librarian'); ?></label>
    <select id="SearchIndex">
      <option value="Books" selected="selected"><?php _e('Books','web-librarian'); ?></option>
      <option value="DVD"><?php _e('DVD','web-librarian'); ?></option>
      <option value="Music"><?php _e('Music','web-librarian'); ?></option>
      <option value="Video"><?php _e('Video','web-librarian'); ?></option>
    </select>
    <label for="FieldName"><?php _e('for','web-librarian'); ?></label>
    <select id="FieldName">
      <option value="Title" selected="selected"><?php _e('Title','web-librarian'); ?></option>
      <option value="Artist"><?php _e('Artist','web-librarian'); ?></option>
      <option value="Author"><?php _e('Author','web-librarian'); ?></option>
      <option value="Keywords"><?php _e('Keywords','web-librarian'); ?></option>
    </select>
    <input id="SearchString" type='text' value="" />
    <input type="button" id="Go" onclick="AWSSearch(1);" value="<?php _e('Go','web-librarian'); ?>" />
  </span>
</div>
<a name="amazon-item-lookup-display"></a>
<div id="amazon-item-lookup-display"></div>
<span id="amazon-item-lookup-workstatus"></span><br clear="all" />
</body></html>

<?php
	/* Admin page classes */

class WEBLIB_Contextual_Help {
  private $help_map = array();

  function __construct() {
    add_filter('contextual_help', array($this,'provide_contextual_help'), 10, 3);
  }
  function provide_contextual_help($contextual_help, $screen_id, $screen) {
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::provide_contextual_help('$contextual_help','$screen_id',".print_r($screen,true).")\n");
    $helptext = @$this->help_map[$screen_id];
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::provide_contextual_help: helptext = '$helptext'\n");
    if ($helptext) {
      $contextual_help = $helptext;
    }
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::provide_contextual_help: contextual_help = '$contextual_help'\n");
    return $contextual_help;
  }

  function add_contextual_help($sid,$page) {
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::add_contextual_help('$sid','$page')\n");
    $helpfile = WEBLIB_CONTEXTUALHELP.'/'.$page.'.html';
    //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::add_contextual_help: helpfile = '$helpfile'\n");
    $helptext = file_get_contents($helpfile);
    if ($helptext) {
      $helptext = preg_replace("/\n/",' ',$helptext);
      $this->help_map[$sid] = $helptext.
	  '<p><a href="'.WEBLIB_DOCURL.
	  '/user_manual.pdf">'.__('Web Librarian User Manual (PDF)','web-librarian').'</a></p>';
      //file_put_contents("php://stderr","*** WEBLIB_Contextual_Help::add_contextual_help: this->help_map is ".print_r($this->help_map,true)."\n");
    }
  }
}

global $weblib_contextual_help;
$weblib_contextual_help = new WEBLIB_Contextual_Help();

require_once (WEBLIB_INCLUDES . '/../../../../wp-admin/includes/class-wp-list-table.php');

class WEBLIB_AdminPages {

    private $user_admin_pages;
    private $patron_admin_pages;
    private $types_database_pages;
    private $statistics_pages;
    private $collection_pages;
    private $circulation_pages;
    private $patron_holdrecord_page;
    private $patron_outrecord_page;

    function __construct() {
	global $weblib_contextual_help;

	load_plugin_textdomain('web-librarian',WEBLIB_BASEURL.'/languages/',
                                          basename(WEBLIB_DIR).'/languages/');
	$this->patron_admin_pages = new WEBLIB_Patrons_Admin();
	$this->user_admin_pages = new WEBLIB_Users_Admin($this->patron_admin_pages);
	$this->collection_pages = new WEBLIB_Collection_Admin();
	$this->types_database_pages = new WEBLIB_Types_Database_Admin();
	$this->circulation_pages = new WEBLIB_Circulation_Admin();
	$this->statistics_pages = new WEBLIB_Statistics_Admin();
	$patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
	if ($patronid != '' && WEBLIB_Patron::ValidPatronID($patronid)) {
	  $this->patron_holdrecord_page = new WEBLIB_PatronHoldRecord_Admin();
	  $this->patron_outrecord_page = new WEBLIB_PatronOutRecord_Admin();
	}
	$screen_id = add_submenu_page('options-general.php',
			__('Web Librarian Configuration','web-librarian'),__('Web Librarian','web-librarian'),
			'manage_options','web-librarian-options',
			array($this,'configuration_page'));
	$weblib_contextual_help->add_contextual_help($screen_id,'web-librarian-options');
    }
    function configuration_page() {
      //must check that the user has the required capability
      if (!current_user_can('manage_options'))
      {
	wp_die( __('You do not have sufficient permissions to access this page.','web-librarian' ));
      }
      if ( get_option('weblib_debugdb') != 'off' && isset($_REQUEST['makedb']) ) {
	global $wpdb;
	$olderror = $wpdb->show_errors(true);
	WEBLIB_make_tables();
	$weblib_tables = $wpdb->get_results("SHOW TABLES LIKE '" . $wpdb->prefix . "weblib_%'",'ARRAY_A');
	?><pre><?php
	echo "weblib_tables = ";
	print_r($weblib_tables);
	?></pre><?php
	$wpdb->show_errors($olderror);
      }
      if ( get_option('weblib_debugdb') != 'off' && isset($_REQUEST['dumpdb']) ) {
	WEBLIB_dump_tables();
      } 
      if ( isset($_REQUEST['saveoptions']) ) {
        $new_public_key = (isset($_REQUEST['aws_public_key']))?$_REQUEST['aws_public_key']:'';
        $new_private_key = (isset($_REQUEST['aws_private_key']))?$_REQUEST['aws_private_key']:'';
        $new_regiondom = (isset($_REQUEST['aws_regiondom']))?$_REQUEST['aws_regiondom']:'';
        $new_associate_tag = (isset($_REQUEST['associate_tag']))?$_REQUEST['associate_tag']:'';
	$message = ''; $valid = true;
	if ($new_public_key == '') {
	  $message .= '<p class="error">'.__('Public Key missing!','web-librarian').'</p>';
	  $valid = false;
	}
	if ($new_private_key == '') {
	  $message .= '<p class="error">'.__('Private Key missing!','web-librarian').'</p>';
	  $valid = false;
	}
	if ($new_regiondom == '') {
	  $message .= '<p class="error">'.__('Region Domain missing!','web-librarian').'</p>';
	  $valid = false;
	}
	if ($new_associate_tag == '') {
	  $message .= '<p class="error">'.__('Associate Tag missing!','web-librarian').'</p>';
	  $valid = false;
	}
	if ($valid) {	
	  update_option('weblib_aws_public_key',$_REQUEST['aws_public_key']);
	  update_option('weblib_aws_private_key',$_REQUEST['aws_private_key']);
	  update_option('weblib_aws_regiondom',$_REQUEST['aws_regiondom']);
	  update_option('weblib_associate_tag',$_REQUEST['associate_tag']);
	  update_option('weblib_debugdb',$_REQUEST['debugdb']);
	  $message = '<p>'.__('Options Saved','web-librarian').'</p>';
	}
	?><div id="message" class="updated fade"><?php echo $message; ?></p></div><?php
	
      }
      $aws_public_key = get_option('weblib_aws_public_key');
      $aws_private_key = get_option('weblib_aws_private_key');
      $aws_regiondom = get_option('weblib_aws_regiondom');
      $associate_tag = get_option('weblib_associate_tag');
      $debugdb = get_option('weblib_debugdb');
      ?><div class="wrap"><div id="icon-weblib-options" class="icon32"><br /></div><h2><?php _e('Configure Options','web-librarian'); ?></h2>
        <form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="web-librarian-options" />
	<table class="form-table">
	  <tr valign="top">
	     <th scope="row">
		<label for="aws_public_key" style="width:20%;"><?php _e('AWS Public Key:','web-librarian'); ?></label></th>
	     <td><input type="text" id="aws_public_key" 
			name="aws_public_key" 
			value="<?php echo $aws_public_key; ?>"
			 style="width:75%" /></td></tr>
	  <tr valign="top">
	     <th scope="row">
		<label for="aws_private_key" style="width:20%;"><?php _e('AWS Private Key:','web-librarian'); ?></label></th>
	     <td><input type="text" id="aws_private_key" 
			name="aws_private_key" 
			value="<?php echo $aws_private_key; ?>"
			 style="width:75%" /></td></tr>
	  <tr valign="top">
	     <th scope="row">
		<label for="aws_regiondom" style="width:20%;"><?php _e('AWS Region:','web-librarian'); ?></label></th>
	     <td><select id="aws_regiondom" 
			 name="aws_regiondom" 
			 style="width:75%">
		   <option value="com"<?php 
			if ($aws_regiondom == 'com') 
			  echo 'selected="selected"'; ?>><?php _e('United States','web-librarian'); ?></option>
		   <option value="ca"<?php
			if ($aws_regiondom == 'ca')
			  echo 'selected="selected"'; ?>><?php _e('Canada','web-librarian'); ?></option>
		   <option value="de"<?php
			if ($aws_regiondom == 'de')
			  echo 'selected="selected"'; ?>><?php _e('Germany','web-librarian'); ?></option>
		   <option value="fr"<?php
			if ($aws_regiondom == 'fr')
			  echo 'selected="selected"'; ?>><?php _e('France','web-librarian'); ?></option>
		   <option value="jp"<?php
			if ($aws_regiondom == 'jp')
			  echo 'selected="selected"'; ?>><?php _e('Japan','web-librarian'); ?></option>
		   <option value="uk"<?php
			if ($aws_regiondom == 'uk')
			  echo 'selected="selected"'; ?>><?php _e('United Kingdom','web-librarian'); ?></option>
		</select></td></tr>
	  <tr valign="top">  
	     <th scope="row">
		<label for="associate_tag" style="width:20%;"><?php _e('Amazon Associate Tag:','web-librarian'); ?></label></th>
	     <td><input type="text" id="associate_tag"
			name="associate_tag"
			value="<?php echo $associate_tag; ?>"
			style="width:75%" /></td></tr>
	  <tr valign="top">
	     <th scope="row">
		<label for="debugdb" style="width:20%;"><?php _e('Debug Database:','web-librarian'); ?></label></th>
	     <td><select id="debugdb" name="debugdb" style="width:75%">
		 <option value="on"<?php
			if ($debugdb == 'on') echo 'selected="selected"'; ?>><?php _e('On','web-librarian'); ?></option>
		 <option value="off"<?php
			if ($debugdb == 'off') echo 'selected="selected"'; ?>><?php _e('Off','web-librarian'); ?></option>
		 </select></td></tr>
	</table>
	<p>
	  <input type="submit" name="saveoptions" class="button-primary" 
			value="<?php _e('Save Options','web-librarian'); ?>" />
	  <?php if ( get_option('weblib_debugdb') != 'off' ) {
		?><input type="submit" name="makedb" class="button-primary"
			value="<?php _e('Make Database','web-librarian'); ?>" />
		  <input type="submit" name="dumpdb" class="button-primary"
			value="<?php _e('Dump Database','web-librarian'); ?>" /><?php
		} ?>
	</p></form></div><?php
    }
    function wp_dashboard_setup() {
	wp_add_dashboard_widget('weblib-quick-stats', 
				__('Circulation Quick Stats','web-librarian'),
				array($this, 'QuickStats'));
    }
    function user_wp_dashboard_setup() {
	$patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
	if ($patronid != '' && WEBLIB_Patron::ValidPatronID($patronid)) {
	  wp_add_dashboard_widget('weblib-user-stats',
				  __('Patron Circulation Stats','web-librarian'),
				  array($this, 'PatronCirculationStats'));
	}
    }
    function QuickStats() {
	$thisyear = date('Y',time());
	$thismonth = date('m',time());
	$monthlyTotal = WEBLIB_Statistic::MonthTotal($thisyear,$thismonth);
	$types = WEBLIB_Type::AllTypes();
	$typetotals = array();
	foreach ($types as $type) {
	  $typetotals[$type] = WEBLIB_Statistic::TypeCount($type,$thisyear,$thismonth);
	}
	?><div class="table">
	    <h3>For <?php echo date('F, Y',time()); ?></h3>
	    <table class="weblib-quick-stats" width="80%">
	    <thead><tr><th width="80%" align="left"><?php _e('Circulation Type','web-librarian'); ?></th>
	    	       <th width="20%" align="right"><?php _e('Count','web-librarian'); ?></th></tr></thead>
	    <tbody><?php
		foreach ($types as $type) {
		  ?><tr><td><?php echo $type;
		  ?></td><td align="right"><?php echo $typetotals[$type];
		  ?></td></tr><?php
		}
		?><tr><td><?php _e('Total','web-librarian'); ?></td><td align="right"><?php echo $monthlyTotal;
		?></td></tr></tbody></table></div><?php
    }
    function PatronCirculationStats() {
      $patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
      $myholds = WEBLIB_HoldItem::HeldItemsOfPatron($patronid);
      $myouts  = WEBLIB_OutItem::OutItemsOfPatron($patronid);
      $name = WEBLIB_Patron::NameFromId($patronid);
      ?><div class="table">
	<h3><?php printf(__('Circulation Statistics for %s','web-librarian'),$name); ?></h3>
	<table class="weblib-user-record-stats" width="80%">
	<tbody>
	<tr><td><?php _e('Items on hold:','web-librarian'); ?></td><td><?php echo count($myholds); ?></td></tr>
	<tr><td><?php _e('Items checked out:','web-librarian'); ?></td><td><?php echo count($myouts); ?></td></tr>
	</tbody></table></div><?php
    }
}

class WEBLIB_Users_Admin extends WP_List_Table {

  private $patron_admin_pages;

  function __construct($pa_pages) {
    global $weblib_contextual_help;

    $this->patron_admin_pages = $pa_pages;

    $screen_id = add_submenu_page('users.php',__('Edit your Patron info','web-librarian'),
			__('Edit Patron info','web-librarian'),'read','edit-your-patron-info',
			array($this,'edit_your_patron_info'));

    $weblib_contextual_help->add_contextual_help($screen_id,'edit-your-patron-info');
    $screen_id =  add_submenu_page('users.php',__('Add Patron ID to a user','web-librarian'),
			__('Add Patron ID','web-librarian'),'edit_users',
			'add-patron-id-to-a-user',array($this,'add_patron_id'));
    $weblib_contextual_help->add_contextual_help($screen_id,'add-patron-id-to-a-user');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    parent::__construct(array(
		'singular' => __('user','web-librarian'),
		'plural' => __('users','web-librarian')
    ) );
  }


  function add_per_page_option() {
    add_screen_option('per_page',array('label' => __('Users','web-librarian') ));
  }

  function edit_your_patron_info() {
    $error = '';
    $patron = WEBLIB_Patron::PatronFromCurrentUser($error);
    $formtype = 'none';
    if ($patron != null) {
	$message = $this->patron_admin_pages->prepare_one_item(
			array('mode' => 'edit',
			      'id' => $patron->ID(),
			      'self' => true));
      $formtype = 'edit';
    } else {
      $formtype = 'setid';
      $patronid = 0;
      if (isset($_REQUEST['patronid']) ) {
	$patronid = $_REQUEST['patronid'];
	$patron = new WEBLIB_Patron($patronid);
	$error = '';
	if (! $patron->StorePatronIDWithCurrentUser($error) ) {
	  $message = '<p><span id="error">'.$error.'</span></p>';
	} else {
	  $message = '<p>'.__('Your Patron ID has been set. Thank you.','web-librarian').'</p>';
	  $formtype = 'none';
	}
      }
    }      
    ?><div class="wrap"><div id="icon-users" class="icon32"><br /></div><h2><?php _e('Edit your patron info','web-librarian'); ?></h2><?php
    if ($message != '') {
      ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
    }
    ?><form action="<?php echo admin_url('admin.php'); ?>" method="get">
	<input type="hidden" name="page" value="edit-your-patron-info" /><?php
    switch ($formtype) {
      case 'none': break;
      case 'edit': $this->patron_admin_pages->display_one_item_form(null); 
		   break;
      case 'setid' :
	WEBLIB_Patron::PatronIdDropdown($patronid,array('onlyunassoc' => true));
	?><input type="submit" value="<?php _e('Set Your Patron Id','web-librarian'); ?>" /><?php
	break;
    }
    ?></form></div><?php
  }

  function add_patron_id() {
    //file_put_contents("php://stderr","*** WEBLIB_Users_Admin::add_patron_id: current_screen is '".print_r(get_current_screen(),true)."'\n");
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-users" class="icon32"><br /></div>
	<h2><?php _e('Add Patron Ids to Users','web-librarian'); ?></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="add-patron-id-to-a-user" />
	<?php $this->search_box(__( 'Search Users','web-librarian' ), 'user' ); ?>
	<?php $this->display(); ?></form></div><?php
  }


  function set_row_actions($racts) { $this->row_actions = $racts; }

  function get_columns() {
	return array('username' => __('Username','web-librarian'),
		     'patronid' => __('Patron ID','web-librarian'),
		     'patronname' => __('Patron Name','web-librarian'),
		     'update' => __('Update','web-librarian') );
  }

  function get_items_per_page ($option, $default = 20) {
    if ( isset($_REQUEST['screen-options-apply']) &&
	 $_REQUEST['wp_screen_options']['option'] == $option ) {
      $per_page = (int) $_REQUEST['wp_screen_options']['value'];
    } else {
      $per_page = $default;
    }
    return (int) apply_filters( $option, $per_page );
  }

  function check_permissions() {
    if (!current_user_can('edit_users')) {
      wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
    }
  }

  function prepare_items() {
    $this->check_permissions();
    $message = '';
    if ( isset( $_REQUEST['setid']) ) {
      $patronid = $_REQUEST['patronid'];
      $user_id  = $_REQUEST['user_id'];
      $user = get_userdata($user_id);
      $patron = new WEBLIB_Patron($patronid);
      $error = '';
      if (! $patron->StorePatronIDWithSelectedUser($user_id,$error,true)) {
	$message = $error;
      } else {
	$message = '<p>'.sprintf(__('Patron ID %d (%s) has been set for user %d (%s).',
					'web-librarian'),
				$patronid,WEBLIB_Patron::NameFromId($patronid),
				$user_id,$user->user_login).'</p>';
      }
    }
    global $usersearch;
    $usersearch = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';

    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);
    $paged = $this->get_pagenum();

    $args = array(
		'number' => $per_page,
		'offset' => ( $paged-1 ) * $per_page,
		'search' => $usersearch,
		'fields' => array('id','user_login') );

    $wp_user_search = new WP_User_Query( $args );

    $this->items = $wp_user_search->get_results();

    $this->set_pagination_args( array(
	'total_items' => $wp_user_search->get_total(),
	'per_page'    => $per_page
    ) );
    return $message;
  }
  function no_items() {
    _e( 'No matching users were found.','web-librarian' );
  }

  function get_sortable_columns() {return array();}

  function get_column_info() {
    if ( isset($this->_column_headers) ) {return $this->_column_headers;}
    $this->_column_headers =
	array( $this->get_columns(),
		array(),
		$this->get_sortable_columns() );
    return $this->_column_headers;
  }

  function column_username($item) {
    return $item->user_login;
  }
  function column_patronid($item) {
    $patron_id = get_user_meta($item->id,'PatronID',true);
    if ($patron_id == '') { $patron_id = 0; }
    return '<span id="displayed-patronid-'.$item->id.'">'.$patron_id.'</span>';
  }
  function column_patronname($item) {
    $patron_id = get_user_meta($item->id,'PatronID',true);
    if ($patron_id == '') {
      $patron_name = '';
    } else {
      $patron_name = WEBLIB_Patron::NameFromId($patron_id);
    }
    return $patron_name;
    
  }
  function column_update($item) {
    $patron_id = get_user_meta($item->id,'PatronID',true);
    if ($patron_id == '') { $patron_id = 0; }
    ?><?php
      WEBLIB_Patron::PatronIdDropdown($patron_id,
			array('onlyunassoc' => false,
			      'name' => 'patronid-'.$item->id));
    ?><input type="button" class="button" name="setid" value="<?php _e('Update Id','web-librarian'); ?>" 
	     onClick="UpdatePatronID(<?php echo $item->id; ?>);" />
      <?php
    return "";
  }

}

class WEBLIB_Patrons_Admin extends WP_List_Table {

  var $row_actions = array();
  var $viewmode = 'add';
  var $viewid   = 0;
  var $viewitem;

  function __construct() {
     global $weblib_contextual_help;

     $screen_id =  add_menu_page(__('Library Patrons','web-librarian'),
			__('Patrons','web-librarian'),'manage_patrons',
			'list-patrons',array($this,'list_patrons'),
			WEBLIB_IMAGEURL.'/Patron_Menu.png');
     $weblib_contextual_help->add_contextual_help($screen_id,'list-patrons');
     add_action("load-$screen_id", array($this,'add_per_page_option'));
     $screen_id =  add_submenu_page('list-patrons',__('Add Library Patron','web-librarian'),
			__('Add New','web-librarian'),'manage_patrons',
			'add-patron',array($this,'add_patron'));
     $weblib_contextual_help->add_contextual_help($screen_id,'add-patron');
     $screen_id =  add_submenu_page('list-patrons',__('Add Bulk Library Patrons','web-librarian'),
				    __('Add New Bulk','web-librarian'),'manage_patrons',
				    'add-patron-bulk',
				    array($this,'add_patron_bulk'));
     $weblib_contextual_help->add_contextual_help($screen_id,'add-patron-bulk');

     $this->set_row_actions(array(
	__('Edit','web-librarian') => add_query_arg(
			array('page' => 'add-patron', 'mode' => 'edit'),
			admin_url('admin.php')),
	__('View','web-librarian') => add_query_arg(
			array('page' => 'add-patron', 'mode' => 'view'),
			admin_url('admin.php')),
	__('Delete','web-librarian') => add_query_arg(
			array('page' => 'list-patrons', 'action' => 'delete'),
			admin_url('admin.php'))));


     parent::__construct(array(
		'singular' => __('Patron','web-librarian'),
		'plural' =>  __('Patrons','web-librarian')
     ) );
  }

  function add_per_page_option() {
    add_screen_option('per_page',array('label' => __('Patrons','web-librarian') ));
  }

  function list_patrons() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-patrons" class="icon32"><br /></div
	<h2><?php _e('Library Patrons','web-librarian'); ?> <a href="<?php
	   echo add_query_arg( array('page' => 'add-patron',
				     'mode' => 'add',
				     'patronid' => false),
				admin_url('admin.php'));
	?>" class="button add-new-h2"><?php _e('Add New','web-librarian');
	?></a> <a href="<?php
	   echo add_query_arg( array('page' => 'add-patron-bulk' ),
				admin_url('admin.php'));
	?>" class="button add-new-h2"><?php _e('Add New Bulk','web-librarian');
	?></a> <a href="<?php
	   echo add_query_arg( array('dataselection' => 'patrons'),
				WEBLIB_BASEURL.'/ExportLibraryData.php');
	?>" class="button add-new-h2"><?php _e('Export as CSV','web-librarian');
	?></a></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="list-patrons" />
	<?php $this->search_box(__( 'Search Patrons','web-librarian' ), 'user' ); ?>
	<?php $this->display(); ?></form></div><?php
  }

  function add_patron() {
    $message = $this->prepare_one_item();
    ?><div class="wrap"><div id="<?php echo $this->add_item_icon(); ?>" class="icon32"><br />
    </div><h2><?php echo $this->add_item_h2(); ?></h2>
    <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	} ?>
    <form action="<?php echo admin_url('admin.php'); ?>" method="get">
	<input type="hidden" name="page" value="add-patron" />
    <?php $this->display_one_item_form(
		add_query_arg(array('page' => 'list-patrons', 
				    'mode' => false, 
				    'patronid' => false))); 
	?></form></div><?php
	
  }

  function set_row_actions($racts) { $this->row_actions = $racts; }
  function get_columns() {
	return array('cb' => '<input type="checkbox" />',
		     'patronid' => __('Patron Id','web-librarian'),
		     'patronname' => __('Patron Name','web-librarian'),
		     'telephone' => __('Telephone Number','web-librarian'),
		     'username' => __('Username','web-librarian'));
  }
  function get_items_per_page ($option, $default = 20) {
    if ( isset($_REQUEST['screen-options-apply']) &&
	 $_REQUEST['wp_screen_options']['option'] == $option ) {
      $per_page = (int) $_REQUEST['wp_screen_options']['value'];
    } else {
      $per_page = $default;
    }
    return (int) apply_filters( $option, $per_page );
  }

  function check_permissions() {
    if (!current_user_can('manage_patrons')) {
      wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
    }
  }

  function prepare_items() {
    $this->check_permissions();
    $message = '';
    if ( isset($_REQUEST['action']) && $_REQUEST['action'] != -1 ) {
      $theaction = $_REQUEST['action'];
    } else if ( isset($_REQUEST['action2']) && $_REQUEST['action2'] != -1 ) {
      $theaction = $_REQUEST['action2'];
    } else {
      $theaction = 'none';
    }
    switch ($theaction) {
      case 'delete':
        if ( isset($_REQUEST['patronid']) ) {
	  WEBLIB_Patron::DeletePatronByID($_REQUEST['patronid']);
	} else {
	  foreach ( $_REQUEST['checked'] as $theitem ) {
	    WEBLIB_Patron::DeletePatronByID($theitem);
	  }
	}
	break;
    }
    $search = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';

    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);

    if ($search == '') {
      $all_items = WEBLIB_Patron::AllPatrons();
    } else {
      $all_items = WEBLIB_Patron::FindPatronByName($search . '%');
    }
    $total_items = count($all_items);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    $this->items = array_slice( $all_items,$start,$per_page );
    return $message;
  }


  function get_bulk_actions() {
    return array ('delete' => __('Delete','web-librarian') );
  }

  function get_column_info() {
    if ( isset($this->_column_headers) ) {return $this->_column_headers;}
    $this->_column_headers =
	array( $this->get_columns(),
	       array(), 
	       $this->get_sortable_columns() );
    return $this->_column_headers;
  }

  function column_cb ($item) {
    return '<input type="checkbox" name="checked[]" value="'.$item['patronid'].'" />';
  }
  function column_patronid ($item) {
    return $item['patronid'];
  }
  function column_patronname ($item) {
    echo $item['name'];
    echo '<br />';
    $paged = $this->get_pagenum();
    $option = str_replace( '-', '_', 
			get_current_screen()->id . '_per_page' );
    $per_page = $this->get_pagination_arg('per_page');
    foreach ($this->row_actions as $label => $url) {
	?><a href="<?php echo add_query_arg(
			array('paged'   => $paged,
			      'screen-options-apply' => 'Apply',
			      'wp_screen_options[option]' => $option,
			      'wp_screen_options[value]' => $per_page,
			      'patronid' => $item['patronid'] ), $url);
		   ?>"><?php echo $label; ?></a>&nbsp;<?php
    }
    return '';
  }
  function column_telephone ($item) {
    return WEBLIB_Patrons_Admin::addtelephonedashes(WEBLIB_Patron::TelephoneFromId($item['patronid']));
  }
  function column_username ($item) {
    $userid = WEBLIB_Patron::UserIDFromPatronID($item['patronid']);
    if ($userid == -1) {
      return '';
    } else {
      return get_userdata($userid)->user_login;
    }
  }
  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
  }

  /* Add/View/Edit page */
  function prepare_one_item($args = array()) {
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item(".print_r($args,true).")\n");
    extract (wp_parse_args($args, 
			array('mode' => 'add', 'id' => 0, 'self' => false)));
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item:: self = $self\n");
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item:: mode = $mode\n");
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item:: id = $id\n");
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::prepare_one_item:: _REQUEST = ".print_r($_REQUEST,true)."\n");
    if ($self) {
      $error = '';
      $patron = WEBLIB_Patron::PatronFromCurrentUser($error);
      if ($patron == null) {
	wp_die( $error );
      }
      if ($mode != 'edit' && $id != $patron->ID() ) {
	wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
      }
    } else {
      $this->check_permissions();
    }
      
    $message = '';
    if ( isset($_REQUEST['addpatron']) ) {
      $message = $this->checkiteminform(0);
      $item    = $this->getitemfromform(0);
      if ($message == '') {
	$pid = isset($_REQUEST['patronid']) ? $_REQUEST['patronid'] : 0;
	$newid = $item->store($pid);
	$message = '<p>'.sprintf(__('%s, %s %s inserted with Patron Id %d.','web-librarian'), 
				$item->lastname(),$item->firstname(),
				$item->extraname(),$newid).'</p>';
	$this->viewmode = 'edit';
	$this->viewid   = $newid;
	$this->viewitem = $item;
      } else {
	$this->viewmode = 'add';
	$this->viewid   = 0;
	$this->viewitem = $item;
      }
    } else if ( isset($_REQUEST['updatepatron']) && 
		isset($_REQUEST['patronid']) ) {
      $message = $this->checkiteminform($_REQUEST['patronid']);
      $item    = $this->getitemfromform($_REQUEST['patronid']);
      if ($message == '') {
	$item->store();
	$message = '<p>'.sprintf(__('%s, %s %s updated.','web-librarian'),
				$item->lastname(),$item->firstname(),
				$item->extraname()).'</p>';
      }
      $this->viewmode = 'edit';
      $this->viewid   = $item->ID();
      $this->viewitem = $item;
    } else {
      $this->viewmode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : $mode;
      $this->viewid   = isset($_REQUEST['patronid']) ? $_REQUEST['patronid'] : $id;
      switch ($this->viewmode) {
	case 'edit':
	case 'view':
	  if ($this->viewid == 0) {$this->viewmode = 'add';}
	  break;
	case 'add':
	  $this->viewid   = 0;
	  break;
	default:
	  $this->viewmode = 'add';
	  $this->viewid   = 0;
	  break;
      }
      $this->viewitem = new WEBLIB_Patron($this->viewid);
    }
    return $message;
  }
  function checkiteminform($id) {
    $result = '';
    $newtelephone = WEBLIB_Patrons_Admin::striptelephonedashes($_REQUEST['telephone']);
    if (!preg_match('/^\d+$/',$newtelephone) && strlen($newtelephone) != 10) {
      $result .= '<br /><span id="error">Telephone invalid</span>';
    }
    $newlastname = $_REQUEST['lastname'];
    if ($newlastname == '') {
      $result .= '<br /><span id="error">Last name is invalid</span>';
    }
    $newfirstname = $_REQUEST['firstname'];
    if ($newfirstname == '') {
      $result .= '<br /><span id="error">First name is invalid</span>';
    }
    $newaddress1 = $_REQUEST['address1'];
    if ($newaddress1 == '') {
      $result .= '<br /><span id="error">Address 1 is invalid</span>';
    }
    $newcity = $_REQUEST['city'];
    if ($newcity == '') {
      $result .= '<br /><span id="error">City is invalid</span>';
    }
    $newstate = $_REQUEST['state'];
    if ($newstate == '' || strlen($newstate) != 2) {
      $result .= '<br /><span id="error">State is invalid</span>';
    }
    $newzip = $_REQUEST['zip'];
    if (!($newzip != '' && (strlen($newzip) == 5 || strlen($newzip) == 10) &&
        preg_match('/\d+(-\d+)?/',$newzip) )) {
      $result .= '<br /><span id="error">Zip is invalid</span>';
    }
    $newoutstandingfines = $_REQUEST['outstandingfines'];
    if (!is_numeric($newoutstandingfines)) {
      $result .= '<br /><span id="error">Outstanding fines invalid</span>';
    }
    $newexpiration = $_REQUEST['expiration'];
    WEBLIB_Patrons_Admin::ValidHumanDate($newexpiration,$theexpiration,'Expiration',$result);
    return $result;
  }
  function getitemfromform($id) {
    $patron = new WEBLIB_Patron($id);
    $patron->set_telephone(WEBLIB_Patrons_Admin::striptelephonedashes($_REQUEST['telephone']));
    $patron->set_lastname($_REQUEST['lastname']);
    $patron->set_firstname($_REQUEST['firstname']);
    $patron->set_extraname($_REQUEST['extraname']);
    $patron->set_address1($_REQUEST['address1']);
    $patron->set_address2($_REQUEST['address2']);
    $patron->set_city($_REQUEST['city']);
    $patron->set_state($_REQUEST['state']);
    $patron->set_zip($_REQUEST['zip']);
    $patron->set_outstandingfines($_REQUEST['outstandingfines']);
    $dummy = '';
    WEBLIB_Patrons_Admin::ValidHumanDate($_REQUEST['expiration'],$theexpiration,'Expiration',$dummy);
    $patron->set_expiration($theexpiration);
    return $patron;
  }
  function add_item_icon() {
    switch ($this->viewmode) {
      case 'edit': return 'icon-patron-edit';
      case 'view': return 'icon-patron-view';
      default:
      case 'add': return 'icon-patron-add';
    }
  }
  function add_item_h2() {
    switch ($this->viewmode) {
      case 'edit': return 'Edit Patron Info';
      case 'view': return 'View Patron Info';
      default:
      case 'add': return 'Add new Patron';
    }
  }

  static function ValidHumanDate($datestring,&$mysqldate,$label,&$error) {
    $Months = array('jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
		      'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8,
		      'sep' => 9, 'oct' =>10, 'nov' =>11, 'dec' =>12);
    $datearry=split("/",$datestring); // splitting the array
    if (count($datearry) == 2) {/* only month and year given (presumed) */
      $month = $datearry[0];
      $year  = $datearry[1];
      $date  = 1; /* assume first of the month */
    } elseif (count($datearry) == 3) {
      $month = $datearry[0];
      $date  = $datearry[1];
      $year  = $datearry[2];
    } else {
      $error .= '<br /><span id="error">Invalid '.$label.' date ('.$datestring.'). Should be mm/yyyy or mm/dd/yyyy.</span>';
      return false;
    }
    if (!is_int($month)) {
      $lowmonth = strtolower($month);
      if (strlen($lowmonth) > 3) {$lowmonth = substr($lowmonth,0,3);}
      if (isset($Months[$lowmonth])) {
	$month = $Months[$lowmonth];
      } else {
	$error .= '<br /><span id="error">Invalid '.$label.' date ('.$datestring.'): illegal month ('.$month.'). Should be one of ';
	$comma='';
	foreach ($Months as $k => $dummy) {
	  $error .= $comma.$k;
	  $comma = ', ';
	}
	$error .= '.</span>';
	return false;
      }
    }
    if (!checkdate($month,$date,$year)) {
      $error .= '<br /><span id="error">Invalid '.$label.' date ('.$datestring.'). Out of range</span>';
      return false;
    }
    $mysqldate = sprintf("%04d-%02d-%02d",$year,$month,$date);
    return true;
  }
  static function striptelephonedashes($telephone) {
    $telephone = preg_replace('/\((\d+)\)[[:space:]]*/','$1-',$telephone);
    return preg_replace('/(\d+)-(\d+)-(\d+)/',
		      '$1$2$3',$telephone);
  }
  static function addtelephonedashes($telephone) {
     return preg_replace('/(\d\d\d)(\d\d\d)(\d\d\d\d)/',
	'$1-$2-$3',$telephone);
  }

  function display_one_item_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      ?><input type="hidden" name="paged" value="<?php echo $_REQUEST['paged'] ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      ?><input type="hidden" name="screen-options-apply" value="<?php echo $_REQUEST['screen-options-apply'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo $_REQUEST['wp_screen_options']['option'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo $_REQUEST['wp_screen_options']['value'] ?>" /><?php
    }
    if ($this->viewmode == 'view') {
      $ro = ' readonly="readonly"';
      $ro_admin = $ro;
    } else {
      $ro = '';
      if (!current_user_can('manage_patrons')) {
	$ro_admin = ' readonly="readonly"';
      }
    }
    ?><table class="form-table">
      <tr valign="top">
	<th scope="row"><label for="patronid" style="width:20%;"><?php _e('Patron ID:','web-librarian'); ?></label></th>
	<td><input id="patronid" 
		   name="patronid" 
		   style="width:75%;"
		   value="<?php echo $this->viewid; ?>"<?php
	if ($this->viewmode != 'add') {
	  echo ' readonly="readonly"';
	} ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="telephone" style="width:20%;"><?php _e('Telephone:','web-librarian'); ?></label></th>
	<td><input id="telephone"
		   name="telephone"
		   style="width:75%;"
		   maxlength="20"
		   value="<?php echo WEBLIB_Patrons_Admin::addtelephonedashes($this->viewitem->telephone()); 
		?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="lastname" style="width:20%;"><?php _e('Last Name:','web-librarian'); ?></label></th>
	<td><input id="lastname"
		   name="lastname"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->lastname()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="firstname" style="width:20%;"><?php _e('First Name:','web-librarian'); ?></label></th>
	<td><input id="firstname"
		   name="firstname"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->firstname()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="extraname" style="width:20%;"><?php _e('Extra Name:','web-librarian'); ?></label></th>
	<td><input id="extraname"
		   name="extraname"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->extraname()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="address1" style="width:20%;"><?php _e('Address 1:','web-librarian'); ?></label></th>
	<td><input id="address1"
		   name="address1"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->address1()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="address2" style="width:20%;"><?php _e('Address 2:','web-librarian'); ?></label></th>
	<td><input id="address2"
		   name="address2"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->address2()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="city" style="width:20%;"><?php _e('City:','web-librarian'); ?></label></th>
	<td><input id="city"
		   name="city"
		   style="width:75%;"
		   maxlength="32"
		   value="<?php echo stripslashes($this->viewitem->city()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="state" style="width:20%;"><?php _e('State:','web-librarian'); ?></label></th>
	<td><input id="state"
		   name="state"
		   style="width:75%;"
		   maxlength="2"
		   value="<?php echo stripslashes($this->viewitem->state()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="zip" style="width:20%;"><?php _e('Zip:','web-librarian'); ?></label></th>
	<td><input id="zip"
		   name="zip"
		   style="width:75%;"
		   maxlength="10"
		   value="<?php echo $this->viewitem->zip(); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="outstandingfines" style="width:20%;"><?php _e('Outstanding Fines: $','web-librarian'); ?></label></th>
	<td><input id="outstandingfines"
		   name="outstandingfines"
		   style="width:75%;"
		   value="<?php echo $this->viewitem->outstandingfines(); ?>"<?php echo $ro_admin; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="expiration" style="width:20%;"><?php _e('Expires on:','web-librarian'); ?></label></th>
	<td><input id="expiration"
		   name="expiration"
		   style="width:75%;"
		   value="<?php echo mysql2date('M/j/Y',$this->viewitem->expiration()); ?>"<?php echo $ro_admin; ?> /></td></tr>
      </table>
      <p>
	<?php switch($this->viewmode) {
		case 'add':
		  ?><input type="submit" name="addpatron" class="button-primary" value="<?php  _e('Add Patron','web-librarian'); ?>" /><?php
		  break;
		case 'edit':
		  ?><input type="submit" name="updatepatron" class="button-primary" value="<?php  _e('Update Patron','web-librarian'); ?>" /><?php
		  break;
	      }
	      if ($returnURL != '') {
		?><a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','web-librarian'); ?></a><?php
	      } ?>
      </p><?php
  }
  function add_patron_bulk() {
    $message = $this->process_bulk_upload();
    ?><div class="wrap"><div id="icon-patron-add-bulk" class="icon32"><br />
      </div><h2><?php _e('Add Library Patrons in bulk','web-librarian'); ?></h2>
      <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
        } ?>
      <form method="post" action=""  
	    enctype="multipart/form-data" >
      <input type="hidden" name="page" value="add-patron-bulk" />
      <?php $this->display_bulk_upload_form(
			add_query_arg(
				array('page' => 'list-patrons'))); 
	?></form></div><?php
  }
  function process_bulk_upload() {
    $this->check_permissions();
    //file_put_contents("php://stderr","*** WEBLIB_Patrons_Admin::process_bulk_upload: _REQUEST is ".print_r($_REQUEST,true)."\n");
    if (!isset($_REQUEST['doupload']) ) return '';
    $filename = $_FILES['file_name']['tmp_name'];
    $use_csv_headers = $_REQUEST['use_csv_header'];
    $field_sep = stripslashes($_REQUEST['field_sep']);
    $enclose_char = stripslashes($_REQUEST['enclose_char']);
    /*$escape_char = stripslashes($_REQUEST['escape_char']);*/
    $result = WEBLIB_Patron::upload_csv($filename,$use_csv_headers,$field_sep,
				$enclose_char/*,$escape_char*/);
    return $result;
  }
  function display_bulk_upload_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      ?><input type="hidden" name="paged" value="<?php echo $_REQUEST['paged'] ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      ?><input type="hidden" name="screen-options-apply" value="<?php echo $_REQUEST['screen-options-apply'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo $_REQUEST['wp_screen_options']['option'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo $_REQUEST['wp_screen_options']['value'] ?>" /><?php
    }
    ?><p><label for="file_name"><?php _e('CSV File:','web-librarian'); ?></label>
	 <input type="file" id="file_name" name="file_name" 
		value="<?php echo $_REQUEST['file_name']; ?>" /></p>
      <p><label for="use_csv_header"><?php _e('Use CSV Header?','web-librarian'); ?></label>
	 <input type="checkbox" name="use_csv_header" id="use_csv_header" 
		value="1" <?php 
		if ($_REQUEST['use_csv_header']) echo ' checked="checked"'; ?> /></p>
      <p><label for="field_sep"><?php _e('Field Separater Character:','web-librarian'); ?></label>
	 <select id="field_sep" name="field_sep">
	 <option value="," <?php if (!isset($_REQUEST['field_sep']) ||
				     $_REQUEST['field_sep'] == ',') {
				   echo 'selected="selected"'; 
				 } ?>>,</option>
	 <option value="<?php echo "\t"; ?>" <?php 
		if (isset($_REQUEST['field_sep']) && 
		    $_REQUEST['field_sep'] == "\t") {
		  echo 'selected="selected"'; 
		} ?>><?php _e('TAB','web-librarian'); ?></option>
	 </select></p>
      <p><label for="enclose_char"><?php _e('Enclosure Character:','web-librarian'); ?></label>
	 <select id="enclose_char" name="enclose_char">
	 <option value='<?php echo '"'; ?>' <?php
		if (!isset($_REQUEST['enclose_char']) ||
		    $_REQUEST['enclose_char'] == '"') {
		  echo 'selected="selected"'; 
		} ?>>&quot;</option>
	 <option value="'" <?php
		if (isset($_REQUEST['enclose_char']) &&
		    $_REQUEST['enclose_char'] == "'") {
		  echo 'selected="selected"';
		} ?>>'</option>
	 </select></p>
      <?php /*
      <p><label for="escape_char"><?php _e('Escape Character:','web-librarian'); ?></label>
	 <input type="text" id="escape_char" name="escape_char"
		maxlength="1" size="1" value="<?php 
		if (isset($_REQUEST['escape_char'])) {
		  echo $_REQUEST['escape_char'];
		} else {
		  echo "\\";
		} ?>" /></p> */ ?>
      <p><input class="button-primary" type="submit" name="doupload" value="<?php _e('Upload File','web-librarian'); ?>" />
	 <a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','web-librarian'); ?></a></p><?php
  }

}


class WEBLIB_Types_Database_Admin extends WP_List_Table {
  var $row_actions = array();
  var $viewmode = 'add';
  var $viewtypename = '';
  var $viewloanperiod = 14;

  function __construct() {
    global $weblib_contextual_help;

    $screen_id =  add_menu_page(__('Circulation Types Database','web-librarian'), __('Circulation Types','web-librarian'),
				'manage_collection', 
				'item-types-database',
				array($this,'item_types_database'),
			WEBLIB_IMAGEURL.'/CircType_Menu.png');
    $weblib_contextual_help->add_contextual_help($screen_id,'item-types-database');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $screen_id =  add_submenu_page('item-types-database', 
				   __('Add New Item Type','web-librarian'), __('Add New','web-librarian'),
				   'manage_collection', 
				   'add-item-type',
				   array($this,'add_item_type'));
    $weblib_contextual_help->add_contextual_help($screen_id,'add-item-type');

    $this->set_row_actions(array(
	__('Edit','web-librarian') => add_query_arg(
			array('page' => 'add-item-type', 
				'mode' => 'edit'),
			admin_url('admin.php'))));

     parent::__construct(array(
		'singular' => __('Type','web-librarian'),
		'plural' => __('Types','web-librarian')
	));
  }
  function add_per_page_option() {
    add_screen_option('per_page',array('label' => __('Types','web-librarian') ));
  }

  function item_types_database() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-types" class="icon32"><br /></div
	<h2><?php _e('Circulation Types','web-librarian'); ?> <a href="<?php
		echo add_query_arg( array('page' => 'add-item-type',
					  'mode' => 'add',
					  'id' => false),
				    admin_url('admin.php'));
	?>" class="button add-new-h2"><?php _e('Add New','web-librarian');
	?></a></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="item-types-database" />
	<?php $this->display(); ?></form></div><?php
  }

  function add_item_type() {
    $message = $this->prepare_one_item();
    ?><div class="wrap"><div id="<?php echo $this->add_item_icon(); ?>" class="icon32"><br />
    </div><h2><?php echo $this->add_item_h2(); ?></h2>
    <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	} ?>
    <form action="<?php echo admin_url('admin.php'); ?>" method="get">
	<input type="hidden" name="page" value="add-item-type" />
    <?php $this->display_one_item_form(
		add_query_arg(array('page' => 'item-types-database', 
				    'mode' => false, 
				    'id' => false))); 
	?></form></div><?php
	
  }
  function set_row_actions($racts) { $this->row_actions = $racts; }
  function get_columns() {
	return array('type' => __("Type",'web-librarian'),
		     'loanperiod' => __("Loan Period (days)",'web-librarian'));
  }
  function get_items_per_page ($option, $default = 20) {
    if ( isset($_REQUEST['screen-options-apply']) &&
	 $_REQUEST['wp_screen_options']['option'] == $option ) {
      $per_page = (int) $_REQUEST['wp_screen_options']['value'];
    } else {
      $per_page = $default;
    }
    return (int) apply_filters( $option, $per_page );
  }

  function check_permissions() {
    if (!current_user_can('manage_collection')) {
      wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
    }
  }
  function prepare_items() {
    $this->check_permissions();
    $message = '';
    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);
    $all_items = WEBLIB_Type::AllTypes();
    $total_items = count($all_items);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    $this->items = array_slice( $all_items,$start,$per_page );
    return $message;
  }

  function get_column_info() {
    if ( isset($this->_column_headers) ) {return $this->_column_headers;}
    $this->_column_headers =
	array( $this->get_columns(),
	       array(), 
	       $this->get_sortable_columns() );
    return $this->_column_headers;
  }

  function column_type($item) {
    echo $item;
    echo '<br />';
    $paged = $this->get_pagenum();
    $option = str_replace( '-', '_', 
			get_current_screen()->id . '_per_page' );
    $per_page = $this->get_pagination_arg('per_page');
    foreach ($this->row_actions as $label => $url) {
	?><a href="<?php echo add_query_arg(
			array('paged'   => $paged,
			      'screen-options-apply' => 'Apply',
			      'wp_screen_options[option]' => $option,
			      'wp_screen_options[value]' => $per_page,
			      'typename' => urlencode($item) ), $url);
		   ?>"><?php echo $label; ?></a>&nbsp;<?php
    }
    return '';
  }
  function column_loanperiod($item) {
    $thetype = new WEBLIB_Type($item);
    return $thetype->loanperiod();
  }
  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
  }
  function checkiteminform() {
    $result = '';
    if ($this->viewmode == 'add') {
      if (WEBLIB_Type::KnownType($_REQUEST['typename'])) {
	$result .= '<br /><span id="error">Duplicate typename.</span>';
      }
    }
    if (!preg_match('/^\d+$/',$_REQUEST['loanperiod'])) {
      $result .= '<br /><span id="error">Loan period not a whole number</span>';
    }
    return $result;
  }
  function getitemfromform() {
    $this->viewtypename = $_REQUEST['typename'];
    $this->viewloanperiod = $_REQUEST['loanperiod'];
  }
  function add_item_icon() {
    switch ($this->viewmode) {
      case 'edit': return 'icon-type-edit';
      default:
      case 'add': return 'icon-type-add';
    }
  }
  function add_item_h2() {
    switch ($this->viewmode) {
      case 'edit': return "Edit A Circulation Type's Loan Period";
      default:
      case 'add': return 'Add new Circulation Type';
    }
  }

  function prepare_one_item() {
    $this->check_permissions();
    //file_put_contents("php://stderr","*** WEBLIB_Types_Database_Admin::prepare_one_item:: _REQUEST = ".print_r($_REQUEST,true)."\n");    $message = '';
    if ( isset($_REQUEST['addtype']) ) {
      $this->viewmode = 'add';
      $message = $this->checkiteminform();
      $this->getitemfromform();
      if ($message == '') {
	$item = new WEBLIB_Type($this->viewtypename);
	$item->set_loanperiod($this->viewloanperiod);
	$item->store();
	$message = '<p>'.sprintf(__('%s inserted, with a loan period of %d.','web-librarian'),
				$item->type(),$item->loanperiod()).'</p>';
	$this->viewmode = 'edit';
	$this->viewtypename = $item->type();
	$this->viewloanperiod = $item->loanperiod();
      } else {
	$this->viewmode = 'add';
      }
    } else if ( isset($_REQUEST['updatetype']) ) {
      $this->viewmode = 'edit';
      $message = $this->checkiteminform();
      $this->getitemfromform();
      if ($message == '') {
	$item = new WEBLIB_Type($this->viewtypename);
	$item->set_loanperiod($this->viewloanperiod);
	$item->store();
	$message = '<p>'.sprintf(__('%s updated, with a loan period of %d.','web-librarian'),
				$item->type(),$item->loanperiod()).'</p>';
      }
      $this->viewmode = 'edit';
    } else {
      $this->viewmode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'add';
      $this->viewtypename = isset($_REQUEST['typename']) ? $_REQUEST['typename'] : '';
      //file_put_contents("php://stderr","*** WEBLIB_Types_Database_Admin::prepare_one_item: this->viewtypename = '".$this->viewtypename."'\n");
      switch ($this->viewmode) {
	case 'edit':
	  if ($this->viewtypename == '') {
	    $this->viewmode = 'add';
	    $this->viewtypename = '';
	    $this->viewloanperiod = 14;
	  } else if (WEBLIB_Type::KnownType($this->viewtypename)) {
	    $item = new WEBLIB_Type($this->viewtypename);
	    $this->viewloanperiod = $item->loanperiod();
	  } else {
	    $this->viewmode = 'add';
	    $this->viewloanperiod = 14;
	  }
	  break;
	case 'add':
	  $this->viewtypename = '';
	  $this->viewloanperiod = 14;
	  break;
	default:
	  $this->viewmode = 'add';
	  $this->viewtypename = '';
	  $this->viewloanperiod = 14;
      }
    }
    return $message;
  }
  function display_one_item_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      ?><input type="hidden" name="paged" value="<?php echo $_REQUEST['paged'] ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      ?><input type="hidden" name="screen-options-apply" value="<?php echo $_REQUEST['screen-options-apply'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo $_REQUEST['wp_screen_options']['option'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo $_REQUEST['wp_screen_options']['value'] ?>" /><?php
    }
    ?><table class="form-table">
      <tr valign="top">
	<th scope="row"><label for="typename" style="width:20%;"><?php _e('Type name:','web-librarian'); ?></label></th>
	<td><input id="typename"
		   name="typename"
		   style="width:75%;"
		   maxlength="16"
		   value="<?php echo stripslashes($this->viewtypename); ?>"<?php
	if ($this->viewmode != 'add') {
	  echo ' readonly="readonly"';
	} ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="loanperiod" style="width:20%;"><?php _e('Loan Period:','web-librarian'); ?></label></th>
	<td><input id="loanperiod"
		   name="loanperiod"
		   style="width:75%;"
		   value="<?php echo $this->viewloanperiod; ?>" /></td></tr>
      </table>
      <p>
	<?php switch($this->viewmode) {
		case 'add':
		  ?><input type="submit" name="addtype" class="button-primary" value="<?php  _e('Add New Type','web-librarian'); ?>" /><?php
		  break;
		case 'edit':
		  ?><input type="submit" name="updatetype" class="button-primary" value="<?php  _e('Update Type','web-librarian'); ?>" /><?php
		  break;
	      }
	      ?><a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','web-librarian'); ?></a>
	</p><?php
  }

}

class WEBLIB_Collection_Shared extends WP_List_Table {

  function __construct() {
	parent::__construct(array(
		'singular' => __('Item','web-librarian'),
		'plural' => __('Items','web-librarian')
	) );

  }

  function add_per_page_option() {
    add_screen_option('per_page',array('label' => __('Items','web-librarian') ));
  }

  function search_box($text, $input_id) {
    if ( empty( $_REQUEST['s'] ) && !$this->has_items() ) return;

    $input_id = $input_id . '-search-input';

    if ( ! empty( $_REQUEST['orderby'] ) )
      echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
    if ( ! empty( $_REQUEST['order'] ) )
      echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
    $field = isset ($_REQUEST['f']) ? $_REQUEST['f'] : 'title';
?>
<p class="search-box">
	<label class="screen-reader-text" for="<?php echo $input_id; ?>"><?php echo $text; ?>:</label>
	<input type="text" id="<?php echo $input_id; ?>" name="s" value="<?php _admin_search_query(); ?>" />
	<select name="f">
	<?php foreach (array('Title' => 'title',
			 'Author' => 'author',
			 'Subject' => 'subject',
			 'ISBN'  => 'isbn',
			 'Keyword' => 'keyword') as $l => $f) {
		?><option value="<?php echo $f; ?>"<?php
		  if ($f == $field) {echo ' selected="selected"';}
		?>><?php echo $l; ?></option>
		<?php
	      } ?>
	<?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
</p>
<?php
  }

  function get_items_per_page ($option, $default = 20) {
    if ( isset($_REQUEST['screen-options-apply']) &&
	 $_REQUEST['wp_screen_options']['option'] == $option ) {
      $per_page = (int) $_REQUEST['wp_screen_options']['value'];
    } else {
      $per_page = $default;
    }
    return (int) apply_filters( $option, $per_page );
  }


  function get_column_info() {
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: (entry) this->_column_headers = ".print_r($this->_column_headers,true)."\n");
    if ( isset( $this->_column_headers ) ) return $this->_column_headers;

    $columns = $this->get_columns( );
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: columns is ".print_r($columns,true)."\n");
    $hidden = get_hidden_columns( $screen );
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: hidden is ".print_r($hidden,true)."\n");

    $_sortable = apply_filters( "manage_{$screen->id}_sortable_columns", $this->get_sortable_columns() );
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: _sortable is ".print_r($_sortable,true)."\n");

    $sortable = array();
    foreach ( $_sortable as $id => $data ) {
	if ( empty( $data ) )
		continue;

	$data = (array) $data;
	if ( !isset( $data[1] ) )
		$data[1] = false;

	$sortable[$id] = $data;
    }

    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: sortable is ".print_r($sortable,true)."\n");

    $this->_column_headers = array( $columns, $hidden, $sortable );

    return $this->_column_headers;
  }

  function get_sortable_columns() {
	return array('barcode' => __('barcode','web-librarian'), 
		     'title' => __('title','web-librarian'), 
		     'author' => __('author','web-librarian'));
  }  

  function column_barcode ($item) {
    return $item;
  }

  function column_author ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return $theitem->author();
  }
  function column_type ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return $theitem->type();
  }

  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
  }

}

class WEBLIB_Collection_Admin extends WEBLIB_Collection_Shared {
  var $row_actions = array();
  var $viewmode = 'add';
  var $viewbarcode   = '';
  var $viewitem;
  var $viewkeywords = array();

  function __construct() {
    global $weblib_contextual_help;

    $screen_id =  add_menu_page('Collection Database', 'Collection',
				'manage_collection','collection-database',
				array($this,'collection_database'),
			WEBLIB_IMAGEURL.'/Collection_Menu.png');
    $weblib_contextual_help->add_contextual_help($screen_id,'collection-database');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $screen_id =  add_submenu_page('collection-database',
				'Add Item to Collection','Add New',
				'manage_collection','add-item-collection',
				array($this,'add_item'));
    $weblib_contextual_help->add_contextual_help($screen_id,'add-item-collection');
    $screen_id =  add_submenu_page('collection-database',
				'Add Bulk Items to Collection','Add New Bulk',
				'manage_collection','add-item-collection-bulk',
				array($this,'add_item_bulk'));
    $weblib_contextual_help->add_contextual_help($screen_id,'add-item-collection-bulk');

    $this->set_row_actions(array(
	__('Edit','web-librarian') => add_query_arg(
			array('page' => 'add-item-collection',
			      'mode' => 'edit'),
			admin_url('admin.php')),
	__('View','web-librarian') => add_query_arg(
			array('page' => 'add-item-collection',
			      'mode' => 'view'),
			admin_url('admin.php')),
	__('Delete','web-librarian') => add_query_arg(
			array('page' => 'collection-database',
			      'action' => 'delete'),
			admin_url('admin.php'))));

   parent::__construct(); 

  }
  function set_row_actions($racts) { $this->row_actions = $racts; }

  function collection_database() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-collection" class="icon32"><br /></div
	<h2>Library Collection <a href="<?php
		echo add_query_arg( array('page' => 'add-item-collection',
					  'mode' => 'add',
					  'barcode' => false));
	?>" class="button add-new-h2"><?php _e('Add New','web-librarian');?></a> <a href="<?php
	   echo add_query_arg( array('page' => 'add-item-collection-bulk'));
	?>" class="button add-new-h2"><?php _e('Add New Bulk','web-librarian');
	?></a> <a href="<?php
	  echo add_query_arg( array('dataselection' => 'collection'),
			      WEBLIB_BASEURL.'/ExportLibraryData.php');
	?>" class="button add-new-h2"><?php _e('Export as CSV','web-librarian');
	?></a></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>"> 
	<input type="hidden" name="page" value="collection-database" />
	<?php $this->search_box(__( 'Search Collection' ,'web-librarian'), 'collection' ); ?>
	<?php submit_button(__( 'Fix Broken Barcodes','web-librarian' ),  'secondary', 
			'fixbrokenbarcodes', false, 
			array( 'id' => 'post-query-submit') ); ?>
	<?php $this->display(); ?></form></div><?php
  }
	
  function check_permissions() {
    if (!current_user_can('manage_collection')) {
      wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
    }
  }

  function get_bulk_actions() {
    return array ('delete' => __('Delete','web-librarian') );
  }

  function get_columns() {
	return array('cb' => '<input type="checkbox" />',
		     'barcode' => __('Barcode','web-librarian'),
		     'title' => __('Title','web-librarian'),
		     'author' => __('Author','web-librarian'),
		     'type' => __('Type','web-librarian'));
  }

  function column_cb ($item) {
    return '<input type="checkbox" name="checked[]" value="'.$item.'" />';
  }

  function column_title ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    echo $theitem->title();
    echo '<br />';
    $paged = $this->get_pagenum();
    $option = str_replace( '-', '_', 
			get_current_screen()->id . '_per_page' );
    $per_page = $this->get_pagination_arg('per_page');
    foreach ($this->row_actions as $label => $url) {
	?><a href="<?php echo add_query_arg(
			array('paged'   => $paged,
			      'screen-options-apply' => 'Apply',
			      'wp_screen_options[option]' => $option,
			      'wp_screen_options[value]' => $per_page,
			      'barcode' => $item ), $url);
		   ?>"><?php echo $label; ?></a>&nbsp;<?php
    }
    return '';
    
  }

  function prepare_items() {
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::prepare_items:: _REQUEST = ".print_r($_REQUEST,true)."\n");
    $this->check_permissions();
    $message = '';
    if ( isset($_REQUEST['fixbrokenbarcodes']) ) {
      $n = WEBLIB_ItemInCollection::fixBrokenBarcodes();
      $message .= sprintf(__('Broken barcodes fixed: %d','web-librarian'),$n);
    }
    if ( isset($_REQUEST['action']) && $_REQUEST['action'] != -1 ) {
      $theaction = $_REQUEST['action'];
    } else if ( isset($_REQUEST['action2']) && $_REQUEST['action2'] != -1 ) {
      $theaction = $_REQUEST['action2'];
    } else {
      $theaction = 'none';
    }
    switch ($theaction) {
      case 'delete':
        if ( isset($_REQUEST['barcode']) ) {
	  WEBLIB_ItemInCollection::DeleteItemByBarCode($_REQUEST['barcode']);
	  WEBLIB_ItemInCollection::DeleteKeywordsByBarCode($_REQUEST['barcode']);
	} else {
	  foreach ( $_REQUEST['checked'] as $thebarcode ) {
	    WEBLIB_ItemInCollection::DeleteItemByBarCode($thebarcode);
	    WEBLIB_ItemInCollection::DeleteKeywordsByBarCode($thebarcode);
	  }
	}
	break;
    }
    $search = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';
    $field  = isset( $_REQUEST['f'] ) ? $_REQUEST['f'] : 'title';
    $orderby = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';

    

    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);
    
    if ($search == '') {
      $all_items = WEBLIB_ItemInCollection::AllBarCodes($orderby,$order);
    } else {
      switch($field) {
	case 'title':
	  $all_items = WEBLIB_ItemInCollection::FindItemByTitle('%'.$search.'%',$orderby,$order);
	  break;
	case 'author':
	  $all_items = WEBLIB_ItemInCollection::FindItemByAuthor('%'.$search.'%',$orderby,$order);
	  break;
	case 'subject':
	  $all_items = WEBLIB_ItemInCollection::FindItemBySubject('%'.$search.'%',$orderby,$order);
	  break;
	case 'isbn':
	  $all_items = WEBLIB_ItemInCollection::FindItemByISBN('%'.$search.'%',$orderby,$order);
	  break;
	case 'keyword':
	  $all_items = WEBLIB_ItemInCollection::FindItemByKeyword('%'.$search.'%',$orderby,$order);
	  break;
      }
    }

    $total_items = count($all_items);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    $this->items = array_slice( $all_items,$start,$per_page );
    return $message;
  }

  /* Add/View/Edit page */
  function add_item() {
    $message = $this->prepare_one_item();
    ?><div class="wrap"><div id="<?php echo $this->add_item_icon(); ?>" class="icon32"><br />
    </div><h2><?php echo $this->add_item_h2(); ?></h2>
    <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	} ?>
    <form action="<?php echo admin_url('admin.php'); ?>" method="get">
	<input type="hidden" name="page" value="add-item-collection" />
    <?php $this->display_one_item_form(
		add_query_arg(array('page' => 'collection-database', 
				    'mode' => false, 
				    'barcode' => false))); 
	?></form></div><?php
	
  }

  function prepare_one_item() {
    $message = '';
    if ( isset($_REQUEST['additem']) ) {
      $message = $this->checkiteminform('');
      $item    = $this->getitemfromform('');
      if ($message == '') {
	$barcode = isset($_REQUEST['barcode']) ? $_REQUEST['barcode'] : '';
	$newbarcode  = $item->store($barcode);
	$keywords = $this->getkeywordsfromform();
	foreach ($keywords as $keyword) {
	  $item->addkeywordto($keyword);
	}
	$message = '<p>'.sprintf(__('%s inserted with barcode %s.','web-librarian'),
				 $item->title(),$newbarcode).'</p>';
	$this->viewmode = 'edit';
	$this->viewbarcode = $newbarcode;
	$this->viewitem = $item;
	$this->viewkeywords = $keywords;
      } else {
	$this->viewmode = 'add';
	$this->viewbarcode = $barcode;
	$this->viewitem = $item;
	$this->viewkeywords = $this->getkeywordsfromform();
      }
    } else if ( isset($_REQUEST['updateitem']) && 
		isset($_REQUEST['barcode']) ) {
      $message = $this->checkiteminform($_REQUEST['barcode']);
      $item    = $this->getitemfromform($_REQUEST['barcode']);
      if ($message == '') {
	$item->store();
	$keywords = $this->getkeywordsfromform();
	$oldkeywords = $item->keywordsof();
	$removedkeywords = array_diff($oldkeywords,$keywords);
	foreach ($removedkeywords as $keyword) {
	  $item->removekeywordfrom($keyword);
	}
	$newkeywords = array_diff($keywords,$oldkeywords);
	foreach ($newkeywords as $keyword) {
	  $item->addkeywordto($keyword);
	}
	$message = '<p>'.sprintf(__('%s updated.','web-librarian'),
				$item->title()).'</p>';
      }
      $this->viewmode = 'edit';
      $this->viewbarcode   = $item->BarCode();
      $this->viewitem = $item;
      $this->viewkeywords = $item->keywordsof();
    } else {
      $this->viewmode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'add';
      $this->viewbarcode = isset($_REQUEST['barcode']) ? $_REQUEST['barcode'] : '';
      switch ($this->viewmode) {
	case 'edit':
	case 'view':
	  if ($this->viewbarcode == '') {$this->viewmode = 'add';}
	  break;
	case 'add':
	  $this->viewbarcode = '';
	  break;
	default:
	  $this->viewmode = 'add';
	  $this->viewbarcode = '';
	  break;
      }
      $this->viewitem = new WEBLIB_ItemInCollection($this->viewbarcode);
      if ($this->viewbarcode == '') {
	$this->viewkeywords = array();
      } else {
	$this->viewkeywords = $this->viewitem->keywordsof();
      }
    }
    return $message;
  }

  function add_item_icon() {
    switch ($this->viewmode) {
      case 'view': return 'icon-item-view';
      case 'edit': return 'icon-item-edit';
      default:
      case 'add': return 'icon-item-add';
    }
  }
  function add_item_h2() {
    switch ($this->viewmode) {
      case 'view': return "View an item in the collection";
      case 'edit': return "Edit an item in the collection";
      default:
      case 'add': return 'Add a new item to the collection';
    }
  }

  function display_one_item_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      ?><input type="hidden" name="paged" value="<?php echo $_REQUEST['paged'] ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      ?><input type="hidden" name="screen-options-apply" value="<?php echo $_REQUEST['screen-options-apply'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo $_REQUEST['wp_screen_options']['option'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo $_REQUEST['wp_screen_options']['value'] ?>" /><?php
    }
    if ($this->viewmode == 'view') {
      $ro = ' readonly="readonly"';
    } else {
      $ro = '';
    }
    ?><table class="form-table">
      <tr valign="top">
	<th scope="row"><label for="barcode" style="width:20%;"><?php _e('Barcode:','web-librarian'); ?></label></th>
	<td><input id="barcode"
		   name="barcode"
		   style="width:75%;"
		   maxlength="16"
		   value="<?php echo stripslashes($this->viewbarcode); ?>"<?php
	if ($this->viewmode != 'add') {
	  echo ' readonly="readonly"';
	} ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="title" style="width:20%;"><?php _e('Title:','web-librarian'); ?></label></th>
	<td><input id="title"
		   name="title"
		   style="width:75%;"
		   maxlength="128"
		   value="<?php echo stripslashes($this->viewitem->title()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="itemauthor" style="width:20%;"><?php _e('Author:','web-librarian'); ?></label></th>
	<td><input id="itemauthor"
		   name="itemauthor"
		   style="width:75%;"
		   maxlength="64"
		   value="<?php echo stripslashes($this->viewitem->author()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="subject" style="width:20%;"><?php _e('Subject:','web-librarian'); ?></label></th>
	<td><input id="subject"
		   name="subject"
		   style="width:75%;"
		   maxlength="128"
		   value="<?php echo stripslashes($this->viewitem->subject()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="description" style="width:20%;"><?php _e('Description:','web-librarian'); ?></label></th>
	<td><textarea id="description"
		      name="description"
		      style="width:75%);"
		      rows="5" cols="64"
		      <?php echo $ro; ?>><?php echo stripslashes($this->viewitem->description()); ?></textarea></td></tr>
      <tr valign="top">
	<th scope="row"><label for="itemcategory" style="width:20%;"><?php _e('Category:','web-librarian'); ?></label></th>
	<td><input id="itemcategory"
		   name="itemcategory"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo stripslashes($this->viewitem->category()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="media" style="width:20%;"><?php _e('Media:','web-librarian'); ?></label></th>
	<td><input id="media"
		   name="media"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo stripslashes($this->viewitem->media()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="publisher" style="width:20%;"><?php _e('Publisher:','web-librarian'); ?></label></th>
	<td><input id="publisher"
		   name="publisher"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo stripslashes($this->viewitem->publisher()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="publocation" style="width:20%;"><?php _e('Publisher Location:','web-librarian'); ?></label></th>
	<td><input id="publocation"
		   name="publocation"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo stripslashes($this->viewitem->publocation()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="pubdate" style="width:20%;"><?php _e('Publish Date:','web-librarian'); ?></label></th>
	<td><input id="pubdate"
		   name="pubdate"
		   style="width:75%;"
		   maxlength="40"
		   value="<?php echo mysql2date('M/j/Y',$this->viewitem->pubdate()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="edition" style="width:20%;"><?php _e('Edition:','web-librarian'); ?></label></th>
	<td><input id="edition"
		   name="edition"
		   style="width:75%;"
		   maxlength="36"
		   value="<?php echo stripslashes($this->viewitem->edition()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="isbn" style="width:20%;"><?php _e('ISBN:','web-librarian'); ?></label></th>
	<td><input id="isbn"
		   name="isbn"
		   style="width:75%;"
		   maxlength="20"
		   value="<?php echo stripslashes($this->viewitem->isbn()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<th scope="row"><label for="type" style="width:20%;"><?php _e('Type:','web-librarian'); ?></label></th>
	<td><?php
	  if ($this->viewmode == 'view') {
	    ?><input id="type" 
		     name="type" 
		     style="width:75%;" 
		     value="<?php echo stripslashes($this->viewitem->type()); ?>"
		     readonly="readonly" /><?php
	  } else {
	    ?><select id="type" name="type" style="width:75%;"><?php
	    $alltypes = WEBLIB_Type::AllTypes();
	    $existingtype = $this->viewitem->type();
	    if ($existingtype == '') $existingtype = $alltypes[0];
	    foreach ($alltypes as $atype) {
	      ?><option value="<?php echo $atype; ?>"<?php
		if ($atype == $existingtype) echo ' selected="selected"';
	      ?>><?php echo $atype; ?></option><?php
	    }
	    ?></select><?php
	  } ?></td></tr>
      <tr valign="top">
	<th scope="row"><label for="thumburl" style="width:20%;"><?php _e('Thumbnail URL:','web-librarian'); ?></label></th>
	<td><input id="thumburl"
		   name="thumburl"
		   style="width:75%;"
		   maxlength="256"
		   value="<?php echo stripslashes($this->viewitem->thumburl()); ?>"<?php echo $ro; ?> /></td></tr>
      <tr valign="top">
	<td colspan="2" width="100%">
	<div id="itemedit-keyword-div">
	<?php if ($this->viewmode != 'view') {
		?><div class="jaxkeyword">
		  <div class="nojs-keywords hide-if-js">
		  <p>Add or remove keywords</p><?php
	      } else {
		?><label for="itemedit-keyword-list"><?php _e('Keywords:','web-librarian'); ?></label><br /><?php
	      }
		?><textarea id="itemedit-keyword-list" name="keywordlist" 
			    rows="3" cols="20" class="the-keywords"<?php echo $ro; ?> ><?php
	      echo implode(',',$this->viewkeywords); ?></textarea><?php
	   if ($this->viewmode != 'view') {
	  ?></div><div class="hide-if-no-js">
		<label class="screen-reader-text" 
		       for="itemedit-new-keyword-item_keyword"><?php _e('Item Keywords','web-librarian'); ?></label>
		<div class="keywordhint">Add New Keyword</div>
	    <p><input type="text" id="itemedit-new-keyword-item_keyword" 
		      name="newkeyword" class="newkeyword form-input-tip" 
		      size="16" autocomplete="off" value="" />
	       <input type="button" class="button" value="<?php _e('Add','web-librarian'); ?>" 
			onclick="WEBLIB_AddKeyword('itemedit');" /></p>
	    <p class="howto">Separate keywords with commas</p></div> 
		<div id="itemedit-keywordchecklist" class="keywordchecklist">
		<script type="text/javascript">
			WEBLIB_WriteKeywords('itemedit');</script></div><?php
	    } ?></div></td></tr>
	 <?php 
	   if ($this->viewmode != 'view') {
	     ?><tr valign="top"><td colspan="2" width="100%">
	     <div id="item-aws">
		<div id="amazon-logo"><br /></div>
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
		    <option value="Artist"><?php _e('Artist','web-librarian'); ?></option>
		    <option value="Author"><?php _e('Author','web-librarian'); ?></option>
		    <option value="Keywords" selected="selected"><?php _e('Keywords','web-librarian'); ?></option>
		    <option value="Title"><?php _e('Title','web-librarian'); ?></option>
		  </select>
		  <input id="SearchString" type='text' value="" />
		  <input type="button" id="Go" onclick="AWSSearch(1);" value="<?php _e('Go','web-librarian'); ?>" />
		</span>
	     </div>
	     <a name="amazon-item-lookup-display"></a>
	     <div id="amazon-item-lookup-display"></div>
	     <span id="amazon-item-lookup-workstatus"></span><br clear="all" />
	     </td></tr><?php
	   }
	 ?>
      </table>
      <p>
	<?php switch($this->viewmode) {
		case 'add':
		  ?><input type="submit" name="additem" class="button-primary" value="<?php  _e('Add New Item','web-librarian'); ?>" /><?php
		  break;
		case 'edit':
		  ?><input type="submit" name="updateitem" class="button-primary" value="<?php  _e('Update Item','web-librarian'); ?>" /><?php
		  break;
	      }
	      ?><a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','web-librarian'); ?></a>
	</p><?php
  }

  function checkiteminform($barcode)
  {
    $result = '';
    if ($this->viewmode == 'add') {
      $newbarcode = $_REQUEST['barcode'];
      if ($newbarcode != '') {
	if (!preg_match('/^[a-zA-Z0-9]+$/',$newbarcode) || strlen($barcode) > 16) {
	  $result .= '<br /><span id="error">Bad barcode.  Must be alphanumerical and not more than 16 characters long</span>';
	}
      }
    }
    if ($_REQUEST['title'] == '') {
      $result .= '<br /><span id="error">Title is invalid</span>';
    }
    if ($_REQUEST['itemauthor'] == '') {
      $result .= '<br /><span id="error">Author is invalid</span>';
    }
    if ($_REQUEST['subject'] == '') {
      $result .= '<br /><span id="error">Subject is invalid</span>';
    }
    WEBLIB_Patrons_Admin::ValidHumanDate($_REQUEST['pubdate'],$dummy,'Publication Date',$result);
    if ($_REQUEST['type'] == '') {
      $result .= '<br /><span id="error">Type is invalid</span>';
    }
    return $result;    
  }

  function getitemfromform($barcode)
  {
    $item = new WEBLIB_ItemInCollection($barcode);
    $item->set_title($_REQUEST['title']);
    $item->set_author($_REQUEST['itemauthor']);
    $item->set_subject($_REQUEST['subject']);
    $item->set_description($_REQUEST['description']);
    $item->set_category($_REQUEST['itemcategory']);
    $item->set_media($_REQUEST['media']);
    $item->set_publisher($_REQUEST['publisher']);
    $item->set_publocation($_REQUEST['publocation']);
    if (WEBLIB_Patrons_Admin::ValidHumanDate($_REQUEST['pubdate'],$thepubdate,'Publication Date',$error)) {
      $item->set_pubdate($thepubdate);
    }
    $item->set_edition($_REQUEST['edition']);
    $item->set_isbn($_REQUEST['isbn']);
    $item->set_type($_REQUEST['type']);
    $item->set_thumburl($_REQUEST['thumburl']);
    return $item;
  }

  function getkeywordsfromform()
  {
    return explode(',',$_REQUEST['keywordlist']);
  }

  function add_item_bulk() {
    $message = $this->process_bulk_upload();
    ?><div class="wrap"><div id="icon-item-add-bulk" class="icon32"><br />
      </div><h2><?php _e('Add Items to the collection in bulk','web-librarian'); ?></h2>
      <?php if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
        } ?>
      <form method="post" action=""  enctype="multipart/form-data" >
      <input type="hidden" name="page" value="add-item-collection-bulk" />
      <?php $this->display_bulk_upload_form(
			add_query_arg(
				array('page' => 'collection-database'))); 
	?></form></div><?php
  }
  function process_bulk_upload() {
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::process_bulk_upload: _REQUEST is ".print_r($_REQUEST,true)."\n");
    $this->check_permissions();
    if (!isset($_REQUEST['doupload']) ) return '';
    $filename = $_FILES['file_name']['tmp_name'];
    $use_csv_headers = $_REQUEST['use_csv_header'];
    $field_sep = stripslashes($_REQUEST['field_sep']);
    $enclose_char = stripslashes($_REQUEST['enclose_char']);
    /*$escape_char = stripslashes($_REQUEST['escape_char']);*/
    $result = WEBLIB_ItemInCollection::upload_csv($filename,$use_csv_headers,
						  $field_sep,$enclose_char
						  /*,$escape_char*/);
    return $result;
  }
  function display_bulk_upload_form($returnURL) {
    if ( isset($_REQUEST['paged']) ) {
      ?><input type="hidden" name="paged" value="<?php echo $_REQUEST['paged'] ?>" /><?php
    }
    if ( isset($_REQUEST['screen-options-apply']) ) {
      ?><input type="hidden" name="screen-options-apply" value="<?php echo $_REQUEST['screen-options-apply'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['option']) ) {
      ?><input type="hidden" name="wp_screen_options[option]" value="<?php echo $_REQUEST['wp_screen_options']['option'] ?>" /><?php
    }
    if ( isset($_REQUEST['wp_screen_options']['value']) ) {
      ?><input type="hidden" name="wp_screen_options[value]" value="<?php echo $_REQUEST['wp_screen_options']['value'] ?>" /><?php
    }
    ?><p><label for="file_name"><?php _e('CSV File:','web-librarian'); ?></label>
	 <input type="file" id="file_name" name="file_name" 
		value="<?php echo $_REQUEST['file_name']; ?>" /></p>
      <p><label for="use_csv_header"><?php _e('Use CSV Header?','web-librarian'); ?></label>
	 <input type="checkbox" name="use_csv_header" id="use_csv_header" 
		value="1"<?php 
                if ($_REQUEST['use_csv_header']) echo ' checked="checked"'; ?> /></p>
      <p><label for="field_sep"><?php _e('Field Separater Character:','web-librarian'); ?></label>
	 <select id="field_sep" name="field_sep">
	 <option value="," <?php if (!isset($_REQUEST['field_sep']) ||
				     $_REQUEST['field_sep'] == ',') {
				   echo 'selected="selected"'; 
				 } ?>>,</option>
	 <option value="<?php echo "\t"; ?>" <?php 
		if (isset($_REQUEST['field_sep']) && 
		    $_REQUEST['field_sep'] == "\t") {
		  echo 'selected="selected"'; 
		} ?>><?php _e('TAB','web-librarian'); ?></option>
	 </select></p>
      <p><label for="enclose_char"><?php _e('Enclosure Character:','web-librarian'); ?></label>
	 <select id="enclose_char" name="enclose_char">
	 <option value='<?php echo '"'; ?>' <?php
		if (!isset($_REQUEST['enclose_char']) ||
		    $_REQUEST['enclose_char'] == '"') {
		  echo 'selected="selected"'; 
		} ?>>&quot;</option>
	 <option value="'" <?php
		if (isset($_REQUEST['enclose_char']) &&
		    $_REQUEST['enclose_char'] == "'") {
		  echo 'selected="selected"';
		} ?>>'</option>
	 </select></p>
      <?php /*
      <p><label for="escape_char"><?php _e('Escape Character:','web-librarian'); ?></label>
	 <input type="text" id="escape_char" name="escape_char"
		maxlength="1" size="1" value="<?php 
		if (isset($_REQUEST['escape_char'])) {
		  echo $_REQUEST['escape_char'];
		} else {
		  echo "\\";
		} ?>" /></p> */ ?>
      <p><input class="button-primary" type="submit" name="doupload" value="<?php _e('Upload File','web-librarian'); ?>" />
	 <a href="<?php echo $returnURL; ?>" class="button-primary"><?php _e('Return','web-librarian'); ?></a></p><?php
  }
}

class WEBLIB_Circulation_Admin extends WEBLIB_Collection_Shared {

  var $mode = 'circulationdesk';
  var $checkinlist = array();
  var $barcode = '';
  var $patronid = 0;
  var $searchname = '';

  function __construct() {
    global $weblib_contextual_help;

    $screen_id =  add_menu_page('Circulation Desk','Circulation Desk',
				'manage_circulation','circulation-desk',
				array($this,'circulation_desk'),
			WEBLIB_IMAGEURL.'/Circulation_Menu.png');
    $weblib_contextual_help->add_contextual_help($screen_id,'circulation-desk');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    parent::__construct();
  }

  function circulation_desk() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-circulation" class="icon32"><br /></div
	<h2>Library Circulation Desk<?php
	  switch ($this->mode) {
	    case 'checkinpage': echo ' -- Check Items In'; break;
	    case 'holdlist':    echo ' -- Items with Holds'; break;
	    case 'outlist':     echo ' -- Items Checked out'; break;
	    case 'patroncircrecord': echo ' -- '.
			WEBLIB_Patron::NameFromId($this->patronid).
			' Circulation Record'; break;
	    case 'itemcircrecord': echo ' -- Circulation Record for '.
			$this->barcode; break;
	    default: break;
	  }
	?></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<? echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="circulation-desk" />
	<?php if ($this->mode != 'checkinpage')
		$this->search_box(__( 'Search Collection','web-librarian' ), 'collection' ); ?>
	<?php $this->display(); ?></form></div><?php
  }

  function check_permissions() {
    if (!current_user_can('manage_circulation')) {
      wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
    }
  }

  function get_columns() {
    if ($this->mode == 'patroncircrecord') {
      return array('barcode' => __('Barcode','web-librarian'),
		   'title' => __('Title','web-librarian'),
		   'author' => __('Author','web-librarian'),
		   'type' => __('Type','web-librarian'),
		   'status' => __('Status','web-librarian'));
    } else {
      return array('barcode' => __('Barcode','web-librarian'),
		   'title' => __('Title','web-librarian'),
		   'author' => __('Author','web-librarian'),
		   'type' => __('Type','web-librarian'),
		   'status' => __('Status','web-librarian'),
		   'patron' => __('Patron','web-librarian'));
    }
  }

  function column_title ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    echo $theitem->title();
    echo '<br />';
    if ($this->mode != 'checkinpage') {
      ?><input class="button" type="button" value="<?php _e('Select','web-librarian'); ?>"
		onClick="document.location.href='<?php 
		echo add_query_arg( array('barcode' => $item,
					  'barcodelookup' => 'yes',
					  'page' => 'circulation-desk'),
				    admin_url('admin.php')); ?>';" /><?php
    }
  }
  function column_status ($item) {
    $outitem = WEBLIB_OutItem::OutItemByBarcode($item);
    $numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($item);
    $status = '';
    $brattr = ' style="display:none;"';
    $this->patroninfo = '';
    if ($outitem != null) {
      $status = 'Due ';
      $duedate = $outitem->datedue();
      if (mysql2date('U',$duedate) < time()) {
        $status .= '<span id="due-date-'.$item.'" class="overdue" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
      } else {
	$status .= '<span id="due-date-'.$item.'" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
      }
      $status .= '<br /><input class="button" type="button" value="'.__('Renew','web-librarian').'" onClick="Renew('."'".$item."'".')" />';
      $patronid = $outitem->patronid();
      $telephone = WEBLIB_Patron::TelephoneFromId($patronid);
      $userid = WEBLIB_Patron::UserIDFromPatronID($patronid);
      $email = get_userdata( $userid )->user_email;
      $this->patroninfo = '<a href="mailto:'.$email.'">'.WEBLIB_Patron::NameFromID($patronid).'</a>';
      $this->patroninfo .= '<br />'.WEBLIB_Patrons_Admin::addtelephonedashes($telephone);
      unset($outitem);
    } else {
      $status .= 'Check Shelves';
    }
    $status .= '<br />';
    $status .= '<span id="hold-count-'.$item.'">';
    if ($numberofholds > 0) {
      $status .= $numberofholds.' Hold';
      if ($numberofholds > 1) $status .= 's';
      $brattr = '';
      if ($this->patroninfo == '') {
	$holds = WEBLIB_HoldItem::HeldItemsByBarcode($item);
	$firsthold = new WEBLIB_HoldItem($holds[0]);
	$patronid = $firsthold->patronid();
	$telephone = WEBLIB_Patron::TelephoneFromId($patronid);
	$userid = WEBLIB_Patron::UserIDFromPatronID($patronid);
	$email = get_userdata( $userid )->user_email;
	$this->patroninfo = '<a href="mailto:'.$email.'">'.WEBLIB_Patron::NameFromID($patronid).'</a>';
	$this->patroninfo .= '<br />'.WEBLIB_Patrons_Admin::addtelephonedashes($telephone);
	$this->patroninfo .= '<br />Expires: '.strftime('%x',mysql2date('U',$firsthold->dateexpire()));
	unset($firsthold);
      }
    }
    $status .= '</span>';
    $status .= '<br id="hold-br-'.$item.'" '.$brattr.' /><input class="button" type="button" value="'.__('Place Hold','web-librarian').'" onClick="PlaceHold('."'".$item."');".'" />';
    return $status;
  }

  function column_patron($item) {
    return '<span id="patron-info-'.$item.'">'.$this->patroninfo.'</span>';
  }

  function extra_tablenav ( $which ) {
    if ('top' != $which) return;

    ?><input type="hidden" name="mode" value="<?php echo $this->mode; ?>" /><?php
    if ($this->mode == 'checkinpage') {
      foreach ($this->checkinlist as $index => $bc) {
	?><input type="hidden" name="checkinlist[<?php echo $index; ?>]" value="<? echo stripslashes($bc); ?>" /><?php
      }
    }
    // barcode entry / dropdown + mode selection
    ?><div id="ajax-message"></div>
	<div class="circulation-desk"><div class="weblib-row"><div id="weblib-inputs"><div class="weblib-inputitem"><label for="barcode" class="inputlab"><?php _e('Scanned Barcode','web-librarian'); ?></label><input id="barcode" name="barcode" value="<?php echo stripslashes($this->barcode); ?>" class="weblib-input-fill" /><?php
    switch ($this->mode) {
      case 'checkinpage':
	?><input class="button" type="submit" name="checkinitem" value="<?php _e('Check in Item','web-librarian'); ?>" /><?php
	break;
      case 'patroncircrecord':
	?><input class="button" type="submit" name="checkoutitem" value="<?php _e('Checkout Item','web-librarian'); ?>" /><?php
	break;
      default:
	?><input class="button" type="submit" name="barcodelookup" value="<?php _e('Lookup Barcode','web-librarian'); ?>" /><?php
	break;
    }
    ?></div><?php
    if ($this->mode != 'checkinpage') {
	// patron search and dropdown
	?><div class="weblib-inputitem"><label for="searchname" class="inputlab"><?php _e('Find Patron:','web-librarian'); ?></label>
	<input id="searchname" name="searchname" value="<?php 
		echo $this->searchname;
	?>" class="weblib-input-fill" /><input class="button" type="button" name="patronfind" value="<?php _e('Find Patron','web-librarian'); ?>" onclick="FindPatron();" /></div>
	<div class="weblib-inputitem"><?php 
		WEBLIB_Patron::PatronIdDropdown(
			$this->patronid,
			array('selectclass' => 'weblib-input-fill',
			      'labelclass' => 'inputlab' ));
	?><input class="button" type="submit" name="patronlookup" value="<?php _e('Lookup Patron','web-librarian'); ?>" /></div>
	<div class="weblib-inputitem" id="weblib-patronlist"></div>
	<?php
    }
    ?></div><div id="weblib-buttons"><?php
    if ($this->mode != 'checkinpage') {
      ?><div class="weblib-inputitem-button"><input class="button" type="submit" name="checkin" value="<?php _e('Check in','web-librarian'); ?>" /></div>
        <div class="weblib-inputitem-button"><input class="button" type="submit" name="listholds" value="<?php _e('List Holds','web-librarian'); ?>" /></div>
	<div class="weblib-inputitem-button"><input class="button" type="submit" name="listouts" value="<?php _e('List Checked Out Items','web-librarian'); ?>" /></div><?php
    }      
    if ($this->mode != 'circulationdesk') {
      ?><div class="weblib-inputitem-button"><input class="button" type="submit" name="resetmode" value="<?php _e('Back to Main Circulation','web-librarian'); ?>" /></div><?php
    }
    ?></div></div></div><br clear="all" /><?php
  }

	/**
	 * Generate the table navigation above or below the table
	 *
	 * @since 3.1.0
	 * @access protected
	 */
	function display_tablenav( $which ) {
		if ( 'top' == $which )
			wp_nonce_field( 'bulk-' . $this->_args['plural'], 
					"_wpnonce", false );
?>
	<div class="tablenav <?php echo esc_attr( $which ); 
		  if ( 'top' == $which ) echo ' '.esc_attr('weblib-circdesk'); 
		?>">

		<div class="alignleft actions">
			<?php $this->bulk_actions( $which ); ?>
		</div>
<?php
		$this->extra_tablenav( $which );
		$this->pagination( $which );
?>

		<br class="clear" />
	</div>
<?php
	}

  


  function search_box($text, $input_id) {
    /*if ( empty( $_REQUEST['s'] ) && !$this->has_items() ) return;*/

    $input_id = $input_id . '-search-input';

    if ( ! empty( $_REQUEST['orderby'] ) )
      echo '<input type="hidden" name="orderby" value="' . esc_attr( $_REQUEST['orderby'] ) . '" />';
    if ( ! empty( $_REQUEST['order'] ) )
      echo '<input type="hidden" name="order" value="' . esc_attr( $_REQUEST['order'] ) . '" />';
    $field = isset ($_REQUEST['f']) ? $_REQUEST['f'] : 'title';
?>
<p class="search-box">
	<label class="screen-reader-text" for="<?php echo $input_id; ?>"><?php echo $text; ?>:</label>
	<input type="text" id="<?php echo $input_id; ?>" name="s" value="<?php _admin_search_query(); ?>" />
	<select name="f">
	<?php foreach (array('Title' => 'title',
			 'Author' => 'author',
			 'Subject' => 'subject',
			 'ISBN'  => 'isbn',
			 'Keyword' => 'keyword') as $l => $f) {
		?><option value="<?php echo $f; ?>"<?php
		  if ($f == $field) {echo ' selected="selected"';}
		?>><?php echo $l; ?></option>
		<?php
	      } ?>
	<?php submit_button( $text, 'button', false, false, array('id' => 'search-submit') ); ?>
</p>
<?php
  }

  function prepare_items() {
    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::prepare_items: _REQUEST = ".print_r($_REQUEST,true)."\n");
    $this->check_permissions();
    $message = '';

    // get: patron id, mode, current barcode etc.

    $this->mode = isset($_REQUEST['mode']) ? $_REQUEST['mode'] : 'circulationdesk';
    if (isset($_REQUEST['checkin'])) {
      $this->mode = 'checkinpage';
      unset($this->_column_headers);
    }
    if (isset($_REQUEST['listholds'])) {
      $this->mode = 'holdlist';
      unset($this->_column_headers);
    }
    if (isset($_REQUEST['listouts'])) {
      $this->mode = 'outlist';
      unset($this->_column_headers);
    }
    if (isset($_REQUEST['resetmode'])) {
      $this->mode = 'circulationdesk';
      unset($this->_column_headers);
    }
    $this->checkinlist = isset($_REQUEST['checkinlist']) ? $_REQUEST['checkinlist'] : array();
    $this->barcode = isset($_REQUEST['barcode']) ? $_REQUEST['barcode'] : '';
    $this->patronid = isset($_REQUEST['patronid']) ? $_REQUEST['patronid'] : 0;
    if (isset($_REQUEST['barcodelookup']) && $this->mode != 'checkinpage') {
      $this->mode = 'itemcircrecord';
      unset($this->_column_headers);
    } else if (isset($_REQUEST['patronlookup']) && $this->mode != 'checkinpage') {
      $this->mode = 'patroncircrecord';
      unset($this->_column_headers);
    } else if ($this->mode == 'patroncircrecord' && 
		isset($_REQUEST['checkoutitem'])) {
      $outitem = WEBLIB_OutItem::OutItemByBarcode($this->barcode);
      $checkouttrans = 0;
      if ($outitem != null) {
	$message .= '<p><span id="error">Item already checked out!</span></p>';
      } else {
	$holds = WEBLIB_HoldItem::HeldItemsByBarcode($this->barcode);
	$hasholds = (! empty($holds));
	if (! empty($holds) ) {
	  foreach ($holds as $trans) {
	    $hold = new WEBLIB_HoldItem($trans);
	    if ($hold->patronid() == $this->patronid) {
	      $type = new WEBLIB_Type($hold->type());
	      $duedate = date('Y-m-d',time() + ($type->loanperiod() * 24 * 60 * 60));
	      unset($type);
	      $checkouttrans = $hold->checkout($duedate);
	      $hasholds = false;
	      unset($hold);
	      $hasholds = false;
	      break;
	    }
	  }
	}
	if ($hasholds) {
	  $message .= '<p><span id="error">Someone else has a hold on this item!</span></p>';
	} else if ($checkouttrans == 0) {
	  if (WEBLIB_ItemInCollection::IsItemInCollection($this->barcode)) {
	    $item = new WEBLIB_ItemInCollection($this->barcode);
	    $type = new WEBLIB_Type($item->type());
	    $duedate = date('Y-m-d',time() + ($type->loanperiod() * 24 * 60 * 60));
	    unset($type);
	    $checkouttrans = $item->checkout($this->patronid, 'Local', $duedate);
	    unset($item);
	  } else {/* item not in collection */
	    $message .= '<p><span id="error">Item is not in the collection!</span></p>';
	  }
	}
	if ($checkouttrans > 0) {
	  $message .= '<p>Item checked out, transaction is '.$checkouttrans.
				', due: '.strftime('%x',mysql2date('U',$duedate)).".</p>\n";
	} else {
	  $message .= '<p><span id="error">Error checking out!  Result code is '.$checkouttrans.'.</span></p>';
	}
      }
    } else if ($this->mode == 'checkinpage' && 
		isset( $_REQUEST['checkinitem'] ) &&
		$this->barcode != '') {
      $outitem = WEBLIB_OutItem::OutItemByBarcode($this->barcode);
      if ($outitem == null) {
	$message .= '<p><span id="error">Item not checked out!</span></p>';
      } else {
	$outitem->checkin(0.10);
	$this->checkinlist[] = $this->barcode;
      }
    } else if ( ! empty($_REQUEST['s']) ) {
      $this->mode = 'circulationdesk';
    }
    $this->searchname = isset($_REQUEST['searchname']) ? $_REQUEST['searchname'] : '';

    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::prepare_items: this->mode = $this->mode\n");
    $search = isset( $_REQUEST['s'] ) ? $_REQUEST['s'] : '';
    $field  = isset( $_REQUEST['f'] ) ? $_REQUEST['f'] : 'title';
    $orderby = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';

    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);
    
    // subset all_items -- by items checked out [by patron] / on hold [by patron]
    switch ($this->mode) {
      case 'checkinpage':
	$all_items = $this->checkinlist;
	$this->sort_items($all_items,$orderby,$order);
	break;
      case 'holdlist':
	$all_holds = WEBLIB_HoldItem::AllHeldItems();
	$all_items = array();
	foreach ($all_holds as $hold) {
	  $helditem = new WEBLIB_HoldItem($hold);
	  $all_items[] = $helditem->barcode();
	}
	$this->sort_items($all_items,$orderby,$order);
	break;
      case 'outlist':
	$all_outs = WEBLIB_OutItem::AllOutItems();
	$all_items = array();
	foreach ($all_outs as $out) {
	  $outitem = new WEBLIB_OutItem($out);
	  $all_items[] = $outitem->barcode();
	}
	$this->sort_items($all_items,$orderby,$order);
	break;
      case 'patroncircrecord':
	$outitems = WEBLIB_OutItem::OutItemsOfPatron($this->patronid);
	$helditems = WEBLIB_HoldItem::HeldItemsOfPatron($this->patronid);
	$all_items = array();
	foreach ($outitems as $transaction) {
	  $outitem = new WEBLIB_OutItem($transaction);
	  $all_items[] = $outitem->barcode();
	}
	foreach ($helditems as $transaction) {
	  $helditem = new WEBLIB_HoldItem($transaction);
	  $all_items[] = $helditem->barcode();
	}	
	$this->sort_items($all_items,$orderby,$order);
	break;
      case 'itemcircrecord':
	if ( isset($_REQUEST['barcodelookup']) && $this->barcode != '') {
	  
	  if (WEBLIB_ItemInCollection::IsItemInCollection($this->barcode)) {
	    $all_items = array($this->barcode);
	  } else {
	    $all_items = array();
	  }
	}
	break;
      case 'circulationdesk':
	//file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::prepare_items (circulationdesk): search = $search, field = $field\n");
	if ($search == '') {
	  $all_items = WEBLIB_ItemInCollection::AllBarCodes($orderby,$order);
	} else {
	  switch($field) {
	    case 'title':
	      $all_items = WEBLIB_ItemInCollection::FindItemByTitle('%'.$search.'%',$orderby,$order);
	      break;
	    case 'author':
	      $all_items = WEBLIB_ItemInCollection::FindItemByAuthor('%'.$search.'%',$orderby,$order);
	      break;
	    case 'subject':
	      $all_items = WEBLIB_ItemInCollection::FindItemBySubject('%'.$search.'%',$orderby,$order);
	      break;
	    case 'isbn':
	      $all_items = WEBLIB_ItemInCollection::FindItemByISBN('%'.$search.'%',$orderby,$order);
	      break;
	    case 'keyword':
	      $all_items = WEBLIB_ItemInCollection::FindItemByKeyword('%'.$search.'%',$orderby,$order);
	      break;
	  }
	}
	break;
      default:
	break;
    } 

    //file_put_contents("php://stderr","*** WEBLIB_Circulation_Admin::prepare_items (circulationdesk): ".print_r($all_items,true)."\n");
    if ($all_items == null) $all_items = array();

    $total_items = count($all_items);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    if ($total_items == 0) {
      $this->items = array();
    } else {
      $this->items = array_slice( $all_items,$start,$per_page );
    }
    return $message;
  }
  private $sortfield = 'barcode';
  private $sortorder = 'ASC';
  function sort_items(&$items,$orderby,$order) {
    $this->sortfield = $orderby;
    $this->sortorder = $order;
    usort($items,array($this,'sort_cmp'));
  }
  function sort_cmp ($a, $b) {
    $aitem = new WEBLIB_ItemInCollection($a);
    $bitem = new WEBLIB_ItemInCollection($b);
    switch ($this->sortfield) {
      case 'barcode': $akey = $a; $bkey = $b; break;
      case 'title':   $akey = $aitem->title(); $bkey = $bitem->title(); break;
      case 'author':  $akey = $aitem->author(); $bkey = $bitem->author(); break;
    }
    unset($aitem); unset($bitem);
    if ($akey == $bkey) return 0;
    if ($akey > $bkey) {
      if ($this->sortorder == 'ASC') {
	return 1;
      } else {
	return -1;
      }
    } else {
      if ($this->sortorder == 'ASC') {
	return -1;
      } else {
	return 1;
      }
    }
  }
}

class WEBLIB_Statistics_Admin extends WP_List_Table {

  private $MonthNames = 
	array('Month Totals','January','February','March','April','May','June',
	      'July','August','September', 'October','November','December');

  private $mode = 'typecount';

  private $year;
  private $month;

  function __construct() {
    global $weblib_contextual_help;

    $screen_id =  add_menu_page('Circulation Statsistics', 'Circulation Stats',
				'manage_circulation', 
				'circulation-statistics',
				array($this,'circulation_statistics'),
			WEBLIB_IMAGEURL.'/CircStats_Menu.png');
    $weblib_contextual_help->add_contextual_help($screen_id,'circulation-statistics');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $screen_id =  add_submenu_page('circulation-statistics',
				   'Export Circulation Stats', 'Export',
				   'manage_circulation',
				   'export-circulation-statistics',
				   array($this,
					 'export_circulation_statistics'));
    $weblib_contextual_help->add_contextual_help($screen_id,'export-circulation-statistics');

    $this->year = date('Y',time());
    $this->month = date('m',time());

    parent::__construct(array());
  }

  function add_per_page_option() {
    add_screen_option('per_page',array('label' => __('Rows','web-librarian') ));
  }

  function check_permissions() {
    if (!current_user_can('manage_circulation')) {
      wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
    }
  }

  function circulation_statistics () {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-statistics" class="icon32"><br /></div>
      <h2>Library Circulation Statistics <a href="<?php
		echo add_query_arg( 
		       array('page' => 'export-circulation-statistics')); 
	?>" class="button add-new-h2"><?php _e('Export Stats','web-librarian'); ?></a></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="circulation-statistics" />
	<?php $this->display(); ?></form></div><?php
  }

  function export_circulation_statistics () {
    ?><div class="wrap"><div id="icon-export-statistics" class="icon32"><br /></div>
      <h2>Export Library Circulation Statistics</h2><?php
      ?><form method="get" action="<?php 
	echo WEBLIB_BASEURL.'/ExportLibraryStats.php'; ?>"><?php
	$year = date('Y',time());
	$month = date('m',time());
      ?><p><label for="year"><?php _e('Year','web-librarian'); ?></label><select id="year" name="year"><?php
 	$allyears = WEBLIB_Statistic::AllYears();
	if ( empty($allyears) ) {$allyears[] = $year;}
	foreach ($allyears as $y) {
	  ?><option value="<?php echo $y; ?>"<?php
	  if ($y == $year) echo ' selected="selected"';
	  ?>><?php echo $y; ?></option><?php
	}
      ?></select></p>
	<p><label for="month"><?php _e('Month','web-librarian'); ?></label><select id="month" name="month"><?php
	foreach ($this->MonthNames as $m => $mtext) {
	  ?><option value="<?php echo $m; ?>"<?php
	  if ($m == $month) echo ' selected="selected"';
	  ?>><?php echo $mtext; ?></option><?php
	}
      ?></select></p><p><?php
      submit_button(__( 'Export','web-librarian'), 'secondary', 'export',false,
			array( 'id' => 'post-query-submit') );
      ?></p><?php
  }

  function prepare_items() {
    //file_put_contents("php://stderr","*** WEBLIB_Statistics_Admin::prepare_items: _REQUEST = ".print_r($_REQUEST,true)."\n");
    $this->check_permissions();
    $message = '';
    $this->year = isset($_REQUEST['year']) ? $_REQUEST['year'] : date('Y',time());
    $this->month = isset($_REQUEST['month']) ? $_REQUEST['month'] : date('m',time());

    if ( isset($_REQUEST['filter_top']) ) {
      $this->year = isset($_REQUEST['year_top']) ? $_REQUEST['year_top'] : $this->year;
      $this->month = isset($_REQUEST['month_top']) ? $_REQUEST['month_top'] : $this->month;
    } else if ( isset($_REQUEST['filter_bottom']) ) {
      $this->year = isset($_REQUEST['year_bottom']) ? $_REQUEST['year_bottom'] : $this->year;
      $this->month = isset($_REQUEST['month_bottom']) ? $_REQUEST['month_bottom'] : $this->month;
    }
    //file_put_contents("php://stderr","*** WEBLIB_Statistics_Admin::prepare_items: this->year = $this->year\n");
    //file_put_contents("php://stderr","*** WEBLIB_Statistics_Admin::prepare_items: this->month = $this->month\n");

    if ($this->month == 0) {
      /* Monthy totals */
      $rowdata = array();
      for ($imonth = 1; $imonth < 13; $imonth++) {
	$rowdata[] = (object) array('label' => $this->MonthNames[$imonth],
				    'value' => WEBLIB_Statistic::MonthTotal($this->year,$imonth));
      }
      $rowdata[] = (object) array('label' => 'Total',
				  'value' => WEBLIB_Statistic::AnnualTotal($this->year));
      if ($this->mode != 'monthtotal') {
	unset($this->_column_headers);
	$this->mode = 'monthtotal';
      }
    } else {
      /* Month count by type */
      $types = WEBLIB_Type::AllTypes();
      $rowdata = array();
      foreach ($types as $type) {
	$rowdata[] = (object) array('label' => $type,
				    'value' => WEBLIB_Statistic::TypeCount($type,$this->year,$this->month));
      }
      $rowdata[] = (object) array('label' => 'Total',
				  'value' => WEBLIB_Statistic::MonthTotal($this->year,$this->month));
      if ($this->mode != 'typecount') {
	unset($this->_column_headers);
	$this->mode = 'typecount';
      }
    }

    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);

    $total_items = count($rowdata);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    if ($total_items == 0) {
      $this->items = array();
    } else {
      $this->items = array_slice( $rowdata,$start,$per_page );
    }
    return $message;
  }

  function get_sortable_columns() {return array();}

  function get_column_info() {
    if ( isset($this->_column_headers) ) {return $this->_column_headers;}
    $this->_column_headers =
	array( $this->get_columns(),
		array(),
		$this->get_sortable_columns() );
    return $this->_column_headers;
  }

  function get_columns() {
    if ($this->mode == 'monthtotal') {
      return array('label' => __('Month','web-librarian'), 
		   'value' => __('Count','web-librarian'));
    } else {
      return array('label' => __('Type','web-librarian'), 
		   'value' => __('Count','web-librarian'));
    }
  }

  function column_label($item) {
    return $item->label;
  }
  function column_value($item) {
    return $item->value;
  }

  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
  }

  function get_items_per_page ($option, $default = 20) {
    if ( isset($_REQUEST['screen-options-apply']) &&
	 $_REQUEST['wp_screen_options']['option'] == $option ) {
      $per_page = (int) $_REQUEST['wp_screen_options']['value'];
    } else {
      $per_page = $default;
    }
    return (int) apply_filters( $option, $per_page );
  }

  function extra_tablenav( $which ) {
    if ($which == 'top') {
      ?><input type="hidden" name="year" value="<?php echo $this->year; ?>" />
	<input type="hidden" name="month" value="<?php echo $this->month; ?>" /><?php
    }

    ?><div class="alignleft actions">
    <label for="year_<?php echo $which; ?>"><?php _e('Year','web-librarian'); ?></label>
    <select id="year_<?php echo $which; ?>" name="year_<?php echo $which; ?>"><?php
    $allyears = WEBLIB_Statistic::AllYears();
    if ( empty($allyears) ) {$allyears[] = $year;}
    foreach ($allyears as $y) {
      ?><option value="<?php echo $y; ?>"<?php
      if ($y == $this->year) echo ' selected="selected"';
      ?>><?php echo $y; ?></option><?php
    }
    ?></select>&nbsp;
    <label for="month_<?php echo $which; ?>"><?php _e('Month','web-librarian'); ?></label>
    <select id="month_<?php echo $which; ?>" name="month_<?php echo $which; ?>"><?php
    foreach ($this->MonthNames as $m => $mtext) {
      ?><option value="<?php echo $m; ?>"<?php
      if ($m == $this->month) echo ' selected="selected"';
      ?>><?php echo $mtext; ?></option><?php
    }
    ?></select>&nbsp;<?php
    submit_button(__( 'Filter','web-librarian'), 'secondary', 'filter_'.$which,false, 
		     array( 'id' => 'post-query-submit') );
  }
}

class WEBLIB_PatronRecord_Common extends WP_List_Table {

  function __construct() {
	parent::__construct(array(
		'singular' => __('Item','web-librarian'),
		'plural' => __('Items','web-librarian')
	) );

  }

  function add_per_page_option() {
    add_screen_option('per_page',array('label' => __('Items','web-librarian') ));
  }

  function check_permissions() {
    $patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);
    if ($patronid == '' || !WEBLIB_Patron::ValidPatronID($patronid)) {
      wp_die( __('You do not have a patron ID yet.','web-librarian') );
    }
  }

  function get_items_per_page ($option, $default = 20) {
    if ( isset($_REQUEST['screen-options-apply']) &&
	 $_REQUEST['wp_screen_options']['option'] == $option ) {
      $per_page = (int) $_REQUEST['wp_screen_options']['value'];
    } else {
      $per_page = $default;
    }
    return (int) apply_filters( $option, $per_page );
  }


  function get_column_info() {
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: (entry) this->_column_headers = ".print_r($this->_column_headers,true)."\n");
    if ( isset( $this->_column_headers ) ) return $this->_column_headers;

    $columns = $this->get_columns( );
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: columns is ".print_r($columns,true)."\n");
    $hidden = get_hidden_columns( $screen );
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: hidden is ".print_r($hidden,true)."\n");

    $_sortable = apply_filters( "manage_{$screen->id}_sortable_columns", $this->get_sortable_columns() );
    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: _sortable is ".print_r($_sortable,true)."\n");

    $sortable = array();
    foreach ( $_sortable as $id => $data ) {
	if ( empty( $data ) )
		continue;

	$data = (array) $data;
	if ( !isset( $data[1] ) )
		$data[1] = false;

	$sortable[$id] = $data;
    }

    //file_put_contents("php://stderr","*** WEBLIB_Collection_Admin::get_column_info: sortable is ".print_r($sortable,true)."\n");

    $this->_column_headers = array( $columns, $hidden, $sortable );

    return $this->_column_headers;
  }

  function get_sortable_columns() {
	return array('barcode' => __('barcode','web-librarian'), 
		     'title' => __('title','web-librarian'), 
		     'author' => __('author','web-librarian'));
  }  

  function column_barcode ($item) {
    return $item;
  }

  function column_author ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return $theitem->author();
  }
  function column_type ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return $theitem->type();
  }

  function column_default($item, $column_name) {
    return apply_filters( 'manage_items_custom_column','',$column_name,$item['patronid']);
  }

  function get_columns() {
    return array('cb' => '<input type="checkbox" />',
		 'barcode' => __('Barcode','web-librarian'),
		 'title' => __('Title','web-librarian'),
		 'author' => __('Author','web-librarian'),
		 'type' => __('Type','web-librarian'),
		 'status' => __('Status','web-librarian'));
  }
  function column_cb ($item) {
    return '<input type="checkbox" name="checked[]" value="'.$item.'" />';
  }

  function column_title ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    return ($theitem->title());
  }
  function column_status ($item) {
    $outitem = WEBLIB_OutItem::OutItemByBarcode($item);
    $numberofholds = WEBLIB_HoldItem::HoldCountsOfBarcode($item);
    $status = '';
    if ($outitem != null) {
      $status = 'Due ';
      $duedate = $outitem->datedue();
      if (mysql2date('U',$duedate) < time()) {
        $status .= '<span id="due-date-'.$item.'" class="overdue" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
      } else {
	$status .= '<span id="due-date-'.$item.'" >'.strftime('%x',mysql2date('U',$duedate)).'</span>';
      }
      $status .= '<br /><input class="button" type="button" value="'.__('Renew','web-librarian').'" onClick="Renew('."'".$item."'".')" />';
      unset($outitem);
    }
    $status .= '<br />';
    $status .= '<span id="hold-count-'.$item.'">';
    if ($numberofholds > 0) {
      $status .= $numberofholds.' Hold';
      if ($numberofholds > 1) $status .= 's';
    }
    $status .= '</span>';
    return $status;
  }

  function extra_tablenav ( $which ) {
    if ('top' != $which) return;

    ?><div id="ajax-message"></div><?php
  }
  private $sortfield = 'barcode';
  private $sortorder = 'ASC';
  function sort_items(&$items,$orderby,$order) {
    $this->sortfield = $orderby;
    $this->sortorder = $order;
    usort($items,array($this,'sort_cmp'));
  }
  function sort_cmp ($a, $b) {
    $aitem = new WEBLIB_ItemInCollection($a);
    $bitem = new WEBLIB_ItemInCollection($b);
    switch ($this->sortfield) {
      case 'barcode': $akey = $a; $bkey = $b; break;
      case 'title':   $akey = $aitem->title(); $bkey = $bitem->title(); break;
      case 'author':  $akey = $aitem->author(); $bkey = $bitem->author(); break;
    }
    unset($aitem); unset($bitem);
    if ($akey == $bkey) return 0;
    if ($akey > $bkey) {
      if ($this->sortorder == 'ASC') {
	return 1;
      } else {
	return -1;
      }
    } else {
      if ($this->sortorder == 'ASC') {
	return -1;
      } else {
	return 1;
      }
    }
  }
}

class WEBLIB_PatronHoldRecord_Admin extends WEBLIB_PatronRecord_Common {
  private $patronid;

  function __construct() {
    global $weblib_contextual_help;

    $screen_id = add_submenu_page('users.php','Your Items on Hold','Holds',
				  'read','patron-holdlist',
				  array($this,'patron_holds'));
    $weblib_contextual_help->add_contextual_help($screen_id,'patron-holdlist');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $this->patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);

    parent::__construct();
  }
  function patron_holds() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-users" class="icon32"><br /></div>
      <h2>Your Items on Hold</h2><?php
      if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
      }
      ?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="patron-holdlist" />
	<?php $this->display(); ?></form></div><?php
  }

  function get_bulk_actions() {
    return array ('removehold' => __('Release Selected Holds','web-librarian') );
  }

  function prepare_items() {
    $message = '';
    if ( isset($_REQUEST['action']) && $_REQUEST['action'] != -1 ) {
      $theaction = $_REQUEST['action'];
    } else if ( isset($_REQUEST['action2']) && $_REQUEST['action2'] != -1 ) {
      $theaction = $_REQUEST['action2'];
    } else {
      $theaction = 'none';
    }
    switch ($theaction) {
      case 'removehold':
	if ( isset($_REQUEST['barcode']) ) {
	  WEBLIB_HoldItem::DeleteHeldItemByBarcodeAndPatronId(
		$_REQUEST['barcode'],$this->patronid);
	} else {
	  foreach ( $_REQUEST['checked'] as $barcode ) {
	    WEBLIB_HoldItem::DeleteHeldItemByBarcodeAndPatronId(
				$barcode,$this->patronid);
	  }
	}
	break;
    }
    $orderby = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';

    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);

    $helditems = WEBLIB_HoldItem::HeldItemsOfPatron($this->patronid);
    $all_items = array();
    foreach ($helditems as $transaction) {
      $outitem = new WEBLIB_HoldItem($transaction);
      $all_items[] = $outitem->barcode();
    }
    $this->sort_items($all_items,$orderby,$order);
    
    if ($all_items == null) $all_items = array();

    $total_items = count($all_items);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    if ($total_items == 0) {
      $this->items = array();
    } else {
      $this->items = array_slice( $all_items,$start,$per_page );
    }
    return $message;
  }
}

class WEBLIB_PatronOutRecord_Admin extends WEBLIB_PatronRecord_Common {
  private $patronid;

  function __construct() {
    global $weblib_contextual_help;

    $screen_id = add_submenu_page('users.php','My Checked out Items',
				  'Checkouts','read','patron-outlist',
				  array($this,'patron_outs'));
    $weblib_contextual_help->add_contextual_help($screen_id,'patron-outlist');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    $this->patronid = get_user_meta(wp_get_current_user()->ID,'PatronID',true);

    parent::__construct();
  }
  function patron_outs() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-users" class="icon32"><br /></div>
      <h2>Your Checked out Items</h2><?php
      if ($message != '') {
	?><div id="message" class="update fade"><?php echo $message; ?></div><?php
      }
      ?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
        <input type="hidden" name="page" value="patron-outlist" />
	<?php $this->display(); ?></form></div><?php
  }

  function get_bulk_actions() {
    return array ('renew' => __('Renew Selected Items','web-librarian') );
  }

  function prepare_items() {
    $message = '';
    if ( isset($_REQUEST['action']) && $_REQUEST['action'] != -1 ) {
      $theaction = $_REQUEST['action'];
    } else if ( isset($_REQUEST['action2']) && $_REQUEST['action2'] != -1 ) {
      $theaction = $_REQUEST['action2'];
    } else {
      $theaction = 'none';
    }
    switch ($theaction) {
      case 'renew':
	if ( isset($_REQUEST['barcode']) ) {
	  $m = WEBLIB_OutItem::RenewByBarcodeAndPatronID(
				$_REQUEST['barcode'],$this->patronid);
	  if (preg_match('/ Renewed\.$/',$m)) {
	    $message .= '<p>'.$m.'</p>';
	  } else {
	    $message .= '<p><span id="error">'.$m.'</span></p>';
	  }
	} else {
	  foreach ( $_REQUEST['checked'] as $barcode ) {
	    $m = WEBLIB_OutItem::RenewByBarcodeAndPatronID(
				$barcode,$this->patronid);
	    if (preg_match('/ Renewed\.$/',$m)) {
	      $message .= '<p>'.$m.'</p>';
	    } else {
	      $message .= '<p><span id="error">'.$m.'</span></p>';
	    }
	  }
	}
	break;
    }
    $orderby = isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'barcode';
    if ( empty( $orderby ) ) $orderby = 'barcode';
    $order = isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'ASC';
    if ( empty( $order ) ) $order = 'ASC';

    $screen = get_current_screen();
    $option = str_replace( '-', '_', $screen->id . '_per_page' );
    $per_page = $this->get_items_per_page($option);

    $outitems = WEBLIB_OutItem::OutItemsOfPatron($this->patronid);
    $all_items = array();
    foreach ($outitems as $transaction) {
      $outitem = new WEBLIB_OutItem($transaction);
      $all_items[] = $outitem->barcode();
    }
    $this->sort_items($all_items,$orderby,$order);
    
    if ($all_items == null) $all_items = array();

    $total_items = count($all_items);
    $this->set_pagination_args( array (
	'total_items' => $total_items,
	'per_page'    => $per_page ));
    $total_pages = $this->get_pagination_arg( 'total_pages' );
    $pagenum = $this->get_pagenum();
    if ($pagenum < 1) {
      $pagenum = 1;
    } else if ($pagenum > $total_pages && $total_pages > 0) {
      $pagenum = $total_pages;
    }
    $start = ($pagenum-1)*$per_page;
    if ($total_items == 0) {
      $this->items = array();
    } else {
      $this->items = array_slice( $all_items,$start,$per_page );
    }
    return $message;
  }
}



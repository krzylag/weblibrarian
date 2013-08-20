<?php

require_once(dirname(__FILE__) . '/WEBLIB_Collection_Admin.php');




class WEBLIB_Circulation_Admin extends WEBLIB_Collection_Shared {

  var $mode = 'circulationdesk';
  var $checkinlist = array();
  var $barcode = '';
  var $patronid = 0;
  var $searchname = '';

  static $my_per_page = 'weblib_circulationdesk_per_page';


  function __construct() {
    global $weblib_contextual_help;

    $screen_id =  add_menu_page(__('Circulation Desk','web-librarian'),__('Circulation Desk','web-librarian'),
				'manage_circulation','weblib-circulation-desk',
				array($this,'circulation_desk'),
			WEBLIB_IMAGEURL.'/Circulation_Menu.png');
    $weblib_contextual_help->add_contextual_help($screen_id,'weblib-circulation-desk');
    add_action("load-$screen_id", array($this,'add_per_page_option'));
    parent::__construct();
  }

  function add_per_page_option() {
    $args['option'] = WEBLIB_Circulation_Admin::$my_per_page;
    $args['label'] = __('Items','web-librarian');
    $args['default'] = 20;
    add_screen_option('per_page', $args);
  }

  function get_per_page() {
    $user = get_current_user_id();
    $screen = get_current_screen();
    $option = $screen->get_option('per_page','option');
    $v = get_user_meta($user, $option, true);
    if (empty($v)  || $v < 1) {
      $v = $screen->get_option('per_page','default');
    }
    return $v;
  }

  function column_title ($item) {
    $theitem = new WEBLIB_ItemInCollection($item);
    //echo $theitem->title();
    //echo '<br />';
    if ($this->mode != 'checkinpage') {
      $actions = array(
	'select' => '<input class="button" type="button" value="'.__('Select','web-librarian').'"
		onClick="document.location.href=\''. 
			add_query_arg( array('barcode' => $item,
					  'barcodelookup' => 'yes',
					  'page' => 'weblib-circulation-desk'),
				    admin_url('admin.php')).'\';" />');
    } else {
      $actions = array();
    }
    return $theitem->title().$this->row_actions($actions);
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
      $status .= __('Check Shelves', 'web-librarian');
    }
    $status .= '<br />';
    $status .= '<span id="hold-count-'.$item.'">';
    if ($numberofholds > 0) {
      if ($numberofholds > 1) {
        $status .= sprintf(__('%d Holds','web-librarian'),$numberofholds);
      } else {
        $status .= sprintf(__('%d Hold','web-librarian'),$numberofholds);
      }
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

  function get_columns() {
    if ($this->mode == 'patroncircrecord') {
      return array('barcode' => __('Barcode','web-librarian'),
		   'title' => __('Title','web-librarian'),
		   'author' => __('Author','web-librarian'),
		   'type' => __('Type','web-librarian'),
		   'callnumber'  => __('Call Number','web-librarian'),
		   'status' => __('Status','web-librarian'));
    } else {
      return array('barcode' => __('Barcode','web-librarian'),
		   'title' => __('Title','web-librarian'),
		   'author' => __('Author','web-librarian'),
		   'type' => __('Type','web-librarian'),
		   'callnumber'  => __('Call Number','web-librarian'),
		   'status' => __('Status','web-librarian'),
		   'patron' => __('Patron','web-librarian'));
    }
  }

  function extra_tablenav ( $which ) {
    if ('top' != $which) return;

    ?><input type="hidden" name="mode" value="<?php echo $this->mode; ?>" /><?php
    if ($this->mode == 'checkinpage') {
      foreach ($this->checkinlist as $index => $bc) {
	?><input type="hidden" name="checkinlist[<?php echo $index; ?>]" value="<?php echo stripslashes($bc); ?>" /><?php
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
	<?php foreach (array(__('Title','web-librarian') => 'title',
			 __('Author','web-librarian') => 'author',
			 __('Subject','web-librarian') => 'subject',
			 __('ISBN','web-librarian')  => 'isbn',
			 __('Keyword','web-librarian') => 'keyword') as $l => $f) {
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

    // Deal with columns
    $columns = $this->get_columns();    // All of our columns
    $hidden  = array();         // Hidden columns [none]
    $sortable = $this->get_sortable_columns(); // Sortable columns
    $this->_column_headers = array($columns,$hidden,$sortable); // Set up columns

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
	$message .= '<p><span id="error">'.__('Item is already checked out!','web-librarian').'</span></p>';
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
	  $message .= '<p><span id="error">';
          $message .= __('Someone else has a hold on this item!','web-librarian');
          $message .= '</span></p>';
	} else if ($checkouttrans == 0) {
	  if (WEBLIB_ItemInCollection::IsItemInCollection($this->barcode)) {
	    $item = new WEBLIB_ItemInCollection($this->barcode);
	    $type = new WEBLIB_Type($item->type());
	    $duedate = date('Y-m-d',time() + ($type->loanperiod() * 24 * 60 * 60));
	    unset($type);
	    $checkouttrans = $item->checkout($this->patronid, __('Local','web-librarian'), $duedate);
	    unset($item);
	  } else {/* item not in collection */
	    $message .= '<p><span id="error">';
            $message .= __('Item is not in the collection!','web-librarian');
            $message .= '</span></p>';
	  }
	}
	if ($checkouttrans > 0) {
	  $message .= '<p>';
          $message .= sprintf(__('Item checked out, transaction is %d, due: %s.',
                                 'web-librarian'),
                                 $checkouttrans,
                                 strftime('%x',mysql2date('U',$duedate)));
          $message .= "</p>\n";
	} else {
	  $message .= '<p><span id="error">';
          $message .= sprintf(__('Error checking out!  Result code is %d.',
                                 'web-librarian'),$checkouttrans);
          $message .= '</span></p>';
	}
      }
    } else if ($this->mode == 'checkinpage' && 
		isset( $_REQUEST['checkinitem'] ) &&
		$this->barcode != '') {
      $outitem = WEBLIB_OutItem::OutItemByBarcode($this->barcode);
      if ($outitem == null) {
	$message .= '<p><span id="error">'.__('Item not checked out!','web-librarian').'</span></p>';
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

    $per_page = $this->get_per_page();
    
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


  function circulation_desk() {
    $message = $this->prepare_items();
    ?><div class="wrap"><div id="icon-circulation" class="icon32"><br /></div>
	<h2><?php
         switch ($this->mode) {
            case 'circulationdesk': _e( 'Library Circulation Desk','web-librarian'); break;
	    case 'checkinpage': _e( 'Library Circulation Desk -- Check Items In','web-librarian'); break;
	    case 'holdlist':    _e( 'Library Circulation Desk -- Items with Holds','web-librarian'); break;
	    case 'outlist':     _e( 'Library Circulation Desk -- Items Checked out','web-librarian'); break;
	    case 'patroncircrecord': echo sprintf(__("Library Circulation Desk -- %s's Circulation Record",'web-librarian'),WEBLIB_Patron::NameFromId($this->patronid)); break;
	    case 'itemcircrecord': echo sprintf(__('Library Circulation Desk -- Circulation Record for %s','web-librarian'),$this->barcode); break;
	    default: break;
	  }
	?></h2><?php
	if ($message != '') {
	  ?><div id="message" class="update fade"><?php echo $message; ?></div><?php
	}
	?><form method="get" action="<?php echo admin_url('admin.php'); ?>">
	<input type="hidden" name="page" value="weblib-circulation-desk" />
	<?php if ($this->mode != 'checkinpage')
		$this->search_box(__( 'Search Collection','web-librarian' ), 'collection' ); ?>
	<?php $this->display(); ?></form></div><?php
  }

  function check_permissions() {
    if (!current_user_can('manage_circulation')) {
      wp_die( __('You do not have sufficient permissions to access this page.','web-librarian') );
    }
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


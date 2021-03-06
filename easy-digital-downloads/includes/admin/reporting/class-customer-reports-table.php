<?php
/**
 * Customer Reports Table Class
 *
 * @package     EDD
 * @subpackage  Admin/Reports
 * @copyright   Copyright (c) 2013, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.5
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * EDD_Customer_Reports_Table Class
 *
 * Renders the Customer Reports table
 *
 * @since 1.5
 */
class EDD_Customer_Reports_Table extends WP_List_Table {
	/**
	 * Number of items per page
	 *
	 * @var int
	 * @since 1.5
	 */
	public $per_page = 30;

	/**
	 * Get things started
	 *
	 * @access public
	 * @since 1.5
	 * @see WP_List_Table::__construct()
	 * @return void
	 */
	public function __construct() {
		global $status, $page;

		// Set parent defaults
		parent::__construct( array(
			'singular'  => __( 'Customer', 'edd' ),     // Singular name of the listed records
			'plural'    => __( 'Customers', 'edd' ),    // Plural name of the listed records
			'ajax'      => false             			// Does this table support ajax?
		) );

	}

	/**
	 * This function renders most of the columns in the list table.
	 *
	 * @access public
	 * @since 1.5
	 *
	 * @param array $item Contains all the data of the customers
	 * @param string $column_name The name of the column
	 *
	 * @return string Column Name
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'name' :
				return '<a href="' .
						admin_url( '/edit.php?post_type=download&page=edd-payment-history&user=' . urlencode( $item['email'] )
					) . '">' . esc_html( $item[ $column_name ] ) . '</a>';

			case 'amount_spent' :
				return edd_currency_filter( edd_format_amount( $item[ $column_name ] ) );

			case 'file_downloads' :
					return '<a href="' . admin_url( '/edit.php?post_type=download&page=edd-reports&tab=logs&user=' . urlencode( ! empty( $item['ID'] ) ? $item['ID'] : $item['email'] ) ) . '" target="_blank">' . $item['file_downloads'] . '</a>';

			default:
				$value = isset( $item[ $column_name ] ) ? $item[ $column_name ] : null;
				return apply_filters( 'edd_report_column_' . $column_name, $value, $item['ID'] );
		}
	}

	/**
	 * Retrieve the table columns
	 *
	 * @access public
	 * @since 1.5
	 * @return array $columns Array of all the list table columns
	 */
	public function get_columns() {
		$columns = array(
			'name'     		=> __( 'Name', 'edd' ),
			'email'     	=> __( 'Email', 'edd' ),
			'num_purchases' => __( 'Purchases', 'edd' ),
			'amount_spent'  => __( 'Total Spent', 'edd' ),
			'file_downloads'=> __( 'Files Downloaded', 'edd' )
		);

		return apply_filters( 'edd_report_customer_columns', $columns );
	}

	/**
	 * Outputs the reporting views
	 *
	 * @access public
	 * @since 1.5
	 * @return void
	 */
	public function bulk_actions() {
		// These aren't really bulk actions but this outputs the markup in the right place
		edd_report_views();
	}

	/**
	 * Retrieve the current page number
	 *
	 * @access public
	 * @since 1.5
	 * @return int Current page number
	 */
	public function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}

	/**
	 * Retrieve the total customers from the database
	 *
	 * @access public
	 * @since 1.5
	 * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @return int $count The number of customers from the database
	 */
	public function get_total_customers() {
		global $wpdb;
		$count = $wpdb->get_col( "SELECT COUNT(DISTINCT meta_value) FROM $wpdb->postmeta WHERE meta_key = '_edd_payment_user_email'" );
		return $count[0];
	}

	/**
	 * Build all the reports data
	 *
	 * @access public
	 * @since 1.5
	  * @global object $wpdb Used to query the database using the WordPress
	 *   Database API
	 * @return array $reports_data All the data for customer reports
	 */
	public function reports_data() {
		global $wpdb;

		$reports_data = array();
		$paged        = $this->get_paged();
		$offset       = $this->per_page * ( $paged - 1 );
		$customers    = $wpdb->get_col( "SELECT DISTINCT meta_value FROM $wpdb->postmeta WHERE meta_key = '_edd_payment_user_email' ORDER BY meta_id DESC LIMIT $this->per_page OFFSET $offset" );

		if ( $customers ) {
			foreach ( $customers as $customer_email ) {
				$wp_user = get_user_by( 'email', $customer_email );

				$user_id = $wp_user ? $wp_user->ID : 0;

				$reports_data[] = array(
					'ID' 			=> $user_id,
					'name' 			=> $wp_user ? $wp_user->display_name : __( 'Guest', 'edd' ),
					'email' 		=> $customer_email,
					'num_purchases'	=> edd_count_purchases_of_customer( $customer_email ),
					'amount_spent'	=> edd_purchase_total_of_user( $customer_email ),
					'file_downloads'=> edd_count_file_downloads_of_user( ! empty( $user_id ) ? $user_id : $customer_email )
				);
			}
		}

		return $reports_data;
	}

	/**
	 * Setup the final data for the table
	 *
	 * @access public
	 * @since 1.5
	 * @uses EDD_Customer_Reports_Table::get_columns()
	 * @uses WP_List_Table::get_sortable_columns()
	 * @uses EDD_Customer_Reports_Table::get_pagenum()
	 * @uses EDD_Customer_Reports_Table::get_total_customers()
	 * @return void
	 */
	public function prepare_items() {
		$columns = $this->get_columns();

		$hidden = array(); // No hidden columns

		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$current_page = $this->get_pagenum();

		$total_items = $this->get_total_customers();

		//$data = array_slice( $data,( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->items = $this->reports_data();

		$this->set_pagination_args( array(
			'total_items' => $total_items,                  	// WE have to calculate the total number of items
			'per_page'    => $this->per_page,                     	// WE have to determine how many items to show on a page
			'total_pages' => ceil( $total_items / $this->per_page )   // WE have to calculate the total number of pages
		) );
	}
}
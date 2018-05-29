<?php
/**
 * Payments Export Class
 *
 * This class handles payment export in batches
 *
 * @package     EDD
 * @subpackage  Admin/Reporting/Export
 * @copyright   Copyright (c) 2018, Easy Digital Downloads, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       2.4
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * EDD_Batch_Payments_Export Class
 *
 * @since 2.4
 */
class EDD_Batch_Payments_Export extends EDD_Batch_Export {

	/**
	 * Our export type. Used for export-type specific filters/actions.
	 *
	 * @var string
	 * @since 2.4
	 */
	public $export_type = 'payments';

	/**
	 * Set the CSV columns
	 *
	 * @since 2.4
	 *
	 * @return array $cols All the columns
	 */
	public function csv_cols() {
		$cols = array(
			'id'           => __( 'Payment ID',   'easy-digital-downloads' ), // unaltered payment ID (use for querying)
			'seq_id'       => __( 'Payment Number',   'easy-digital-downloads' ), // sequential payment ID
			'email'        => __( 'Email', 'easy-digital-downloads' ),
			'customer_id'  => __( 'Customer ID', 'easy-digital-downloads' ),
			'first'        => __( 'First Name', 'easy-digital-downloads' ),
			'last'         => __( 'Last Name', 'easy-digital-downloads' ),
			'address1'     => __( 'Address', 'easy-digital-downloads' ),
			'address2'     => __( 'Address (Line 2)', 'easy-digital-downloads' ),
			'city'         => __( 'City', 'easy-digital-downloads' ),
			'state'        => __( 'State', 'easy-digital-downloads' ),
			'country'      => __( 'Country', 'easy-digital-downloads' ),
			'zip'          => __( 'Zip / Postal Code', 'easy-digital-downloads' ),
			'products'     => __( 'Products (Verbose)', 'easy-digital-downloads' ),
			'products_raw' => __( 'Products (Raw)', 'easy-digital-downloads' ),
			'skus'         => __( 'SKUs', 'easy-digital-downloads' ),
			'amount'       => __( 'Amount', 'easy-digital-downloads' ) . ' (' . html_entity_decode( edd_currency_filter( '' ) ) . ')',
			'tax'          => __( 'Tax', 'easy-digital-downloads' ) . ' (' . html_entity_decode( edd_currency_filter( '' ) ) . ')',
			'discount'     => __( 'Discount Code', 'easy-digital-downloads' ),
			'gateway'      => __( 'Payment Method', 'easy-digital-downloads' ),
			'trans_id'     => __( 'Transaction ID', 'easy-digital-downloads' ),
			'key'          => __( 'Purchase Key', 'easy-digital-downloads' ),
			'date'         => __( 'Date', 'easy-digital-downloads' ),
			'user'         => __( 'User', 'easy-digital-downloads' ),
			'currency'     => __( 'Currency', 'easy-digital-downloads' ),
			'ip'           => __( 'IP Address', 'easy-digital-downloads' ),
			'mode'         => __( 'Mode (Live|Test)', 'easy-digital-downloads' ),
			'status'       => __( 'Status', 'easy-digital-downloads' ),
			'country_name' => __( 'Country Name', 'easy-digital-downloads' ),
		);

		if ( ! edd_use_skus() ){
			unset( $cols['skus'] );
		}

		if ( ! edd_get_option( 'enable_sequential' ) ) {
			unset( $cols['seq_id'] );
		}

		return $cols;
	}

	/**
	 * Get the Export Data
	 *
	 * @since 2.4
	 * @since 3.0 Updated to use new query methods.
	 *
	 * @return array $data The data for the CSV file
	 */
	public function get_data() {
		$data = array();

		$args = array(
			'number'  => 30,
			'offset'  => ( $this->step * 30 ) - 30,
			'status'  => $this->status,
			'order'   => 'ASC',
			'orderby' => 'date',
		);

		if ( ! empty( $this->start ) || ! empty( $this->end ) ) {
			$args['date_query'] = array(
				array(
					'after'     => date( 'Y-n-d 00:00:00', strtotime( $this->start ) ),
					'before'    => date( 'Y-n-d 23:59:59', strtotime( $this->end ) ),
					'inclusive' => true
				)
			);
		}

		$orders = edd_get_orders( $args );

		foreach ( $orders as $order ) {
			/** @var EDD\Orders\Order $order */

			$user_info    = $order->get_user_info();
			$total        = $order->get_total();
			$user_id      = $order->get_id() && $order->get_id() != - 1 ? $order->get_id() : $user_info['email'];
			$products     = '';
			$products_raw = '';
			$skus         = '';

			$items = $order->get_items();
			$adjustments = $order->get_adjustments();

			foreach ( $items as $key => $item ) {
				/** @var EDD\Orders\Order_Item $item */
				
				// Setup item information.
				$id       = $item->get_product_id();
				$qty      = $item->get_quantity();
				$price    = $item->get_amount();
				$tax      = $item->get_tax();
				$price_id = $item->get_price_id();
				$skus     = '';

				/* Set up verbose product column */

				$products .= html_entity_decode( get_the_title( $id ) );

				if ( $qty > 1 ) {
					$products .= html_entity_decode( ' (' . $qty . ')' );
				}

				$products .= ' - ';

				if ( edd_use_skus() ) {
					$sku = edd_get_download_sku( $id );

					if ( ! empty( $sku ) ) {
						$skus .= $sku;
					}
				}

				if ( 0 < $item->get_price_id() ) {
					$products .= html_entity_decode( edd_get_price_option_name( $id, $item->get_price_id(), $order->get_id() ) ) . ' - ';
				}

				$products .= html_entity_decode( edd_currency_filter( edd_format_amount( $price ) ) );

				if ( $key != ( count( $items ) -1 ) ) {
					$products .= ' / ';

					if ( edd_use_skus() ) {
						$skus .= ' / ';
					}
				}

				/* Set up raw products column - Nothing but product names */
				$products_raw .= html_entity_decode( get_the_title( $id ) ) . '|' . $price . '{' . $tax . '}';

				// if we have a Price ID, include it.
				if ( false !== $price_id ) {
					$products_raw .= '{' . $price_id . '}';
				}

				if ( $key != ( count( $items ) -1 ) ) {
					$products_raw .= ' / ';
				}
			}

			if ( is_numeric( $user_id ) ) {
				$user = get_userdata( $user_id );
			} else {
				$user = false;
			}

			$data[] = array(
				'id'           => $order->get_id(),
				'seq_id'       => $order->get_number(),
				'email'        => $order->get_number(),
				'customer_id'  => $order->get_customer_id(),
				'first'        => $user_info['first_name'],
				'last'         => $user_info['last_name'],
				'address1'     => isset( $user_info['address']['line1'] )   ? $user_info['address']['line1']   : '',
				'address2'     => isset( $user_info['address']['line2'] )   ? $user_info['address']['line2']   : '',
				'city'         => isset( $user_info['address']['city'] )    ? $user_info['address']['city']    : '',
				'state'        => isset( $user_info['address']['state'] )   ? $user_info['address']['state']   : '',
				'country'      => isset( $user_info['address']['country'] ) ? $user_info['address']['country'] : '',
				'zip'          => isset( $user_info['address']['zip'] )     ? $user_info['address']['zip']     : '',
				'products'     => $products,
				'products_raw' => $products_raw,
				'skus'         => $skus,
				'amount'       => html_entity_decode( edd_format_amount( $total ) ), // The non-discounted item price
				'tax'          => html_entity_decode( edd_format_amount( $order->get_tax() ) ),
				'discount'     => isset( $user_info['discount'] ) && $user_info['discount'] != 'none' ? $user_info['discount'] : __( 'none', 'easy-digital-downloads' ),
				'gateway'      => edd_get_gateway_admin_label( $order->get_gateway() ),
				'trans_id'     => $payment->transaction_id,
				'key'          => $order->get_payment_key(),
				'date'         => $order->get_date_created(),
				'user'         => $user ? $user->display_name : __( 'guest', 'easy-digital-downloads' ),
				'currency'     => $order->get_currency(),
				'ip'           => $order->get_ip(),
				'mode'         => $order->get_mode(),
				'status'       => ( 'publish' === $order->get_status() ) ? 'complete' : $order->get_status(),
				'country_name' => isset( $user_info['address']['country'] ) ? edd_get_country_name( $user_info['address']['country'] ) : '',
			);

		}

		$data = apply_filters( 'edd_export_get_data', $data );
		$data = apply_filters( 'edd_export_get_data_' . $this->export_type, $data );

		return ! empty( $data )
			? $data
			: false;
	}

	/**
	 * Return the calculated completion percentage
	 *
	 * @since 2.4
	 * @since 3.0 Updated to use new query methods.
	 *
	 * @return int
	 */
	public function get_percentage_complete() {
		$status = $this->status;

		$args = array(
			'start-date' => date( 'n/d/Y', strtotime( $this->start ) ),
			'end-date'   => date( 'n/d/Y', strtotime( $this->end ) ),
		);

		if ( 'any' === $status ) {
			$total = array_sum( (array) edd_count_payments( $args ) );
		} else {
			$total = edd_count_payments( $args )->$status;
		}

		$percentage = 100;

		if ( $total > 0 ) {
			$percentage = ( ( 30 * $this->step ) / $total ) * 100;
		}

		if ( $percentage > 100 ) {
			$percentage = 100;
		}

		return $percentage;
	}

	/**
	 * Set the properties specific to the payments export
	 *
	 * @since 2.4.2
	 *
	 * @param array $request The Form Data passed into the batch processing
	 */
	public function set_properties( $request ) {
		$this->start  = isset( $request['start'] )  ? sanitize_text_field( $request['start'] )  : '';
		$this->end    = isset( $request['end']  )   ? sanitize_text_field( $request['end']  )   : '';
		$this->status = isset( $request['status'] ) ? sanitize_text_field( $request['status'] ) : 'complete';
	}
}
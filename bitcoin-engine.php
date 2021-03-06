<?php
/*
 * Plugin Name: Bitcoin Engine
 * Plugin URI:  https://bitbucket.org/wikitopian/bitcoin-engine
 * Description: General purpose bitcoin library
 * Text Domain: bitcoin-engine
 * Version:     0.1
 * Author:      @wikitopian
 * Author URI:  http://www.github.com/wikitopian
 * License:     LGPLv3
 */

require_once( 'inc/bitcoin-engine-menu.php' );
require_once( 'inc/bitcoin-engine-db.php' );
require_once( 'inc/bitcoin-engine-rpc.php' );

class Bitcoin_Engine {

	protected $db;
	protected $rpc;

	public function __construct() {

		$settings = array(
			'version'     => '0.1',
			'debug'       => false,
			'rpctimeout'  => 2,
			'list_tx_max' => 999,
			'lastblock'   => false,
			'fx_rate_url' => 'https://blockchain.info/ticker?cors=true',
			'min_conf'    => 1,
			'commission'  => '0.0',
			'comm_label'  => Bitcoin_Engine_Rpc::get_account_label( 0 ),
		);

		$settings = get_option( 'bitcoin-engine', $settings );
		update_option( 'bitcoin-engine', $settings );

		// user interface
		$settings_menu = array(
			'rpcconnect'  => 'rpc.blockchain.info',
			'rpcssl'      => true,
			'rpcport'     => 443,
			'rpcuser'     => null,
			'rpcpassword' => null,
			'rpcwallet'   => null,
			'fx'          => 'USD',
			'decimals'    => 1,
		);

		$this->settings = get_option( 'bitcoin-engine_menu', $settings_menu );
		update_option( 'bitcoin-engine_menu', $this->settings );

		$this->menu = new Bitcoin_Engine_Menu();

		// database functionality
		$this->db = new Bitcoin_Engine_Db();

		// bitcoin functionality
		$this->rpc = new Bitcoin_Engine_Rpc();

		register_activation_hook(
			__FILE__,
			array( &$this->db, 'create_transactions_table' )
		);

		register_activation_hook(
			__FILE__,
			array( &$this->db, 'create_addresses_table' )
		);

		add_filter(
			'plugin_action_links',
			array( &$this, 'do_settings_link' ),
			10,
			2
		);

		add_action(
			'wp_enqueue_scripts',
			array( &$this, 'do_scripts_and_styles' )
		);

		$this->refresh_tx_history();

		$this->do_payouts();

	}

	public function do_settings_link( $links, $file ) {

		if ( $file == 'bitcoin-engine/bitcoin-engine.php' ) {

			$url  = menu_page_url( 'bitcoin-engine/inc/bitcoin-engine-menu.php', false );

			$link = sprintf( '<a href="%s">%s</a>', esc_url( $url ), __( 'Settings' ) );

			array_unshift( $links, $link );

		}

		return $links;
	}

	public function do_scripts_and_styles() {

		wp_enqueue_style(
			'bitcoin-engine',
			plugins_url( '/styles/bitcoin-engine.css', __FILE__ )
		);

		wp_enqueue_script(
			'bitcoin_engine',
			plugins_url( '/scripts/bitcoin-engine.js', __FILE__ ),
			array(
				'jquery',
				'jquery-ui-core',
				'jquery-ui-button',
				'jquery-ui-dialog',
			),
			false,
			true
		);

		wp_localize_script(
			'bitcoin_engine',
			'bitcoin_engine',
			array(
				'decimals' => $this->menu->settings['decimals'],
			)
		);

		wp_enqueue_script(
			'bitcoin-engine_formatCurrency',
			plugins_url( '/scripts/jquery-formatcurrency/jquery.formatCurrency.js', __FILE__ ),
			array(
				'jquery',
			),
			false,
			true
		);

		wp_enqueue_script(
			'bitcoin_engine_fx',
			plugins_url( '/scripts/bitcoin-engine-fx.js', __FILE__ ),
			array(
				'jquery',
				'jquery-ui-core',
				'jquery-ui-button',
				'jquery-ui-dialog',
			),
			false,
			true
		);

		$settings = get_option( 'bitcoin-engine' );
		$settings_menu = get_option( 'bitcoin-engine_menu' );

		wp_localize_script(
			'bitcoin_engine_fx',
			'bitcoin_engine_fx',
			array(
				'url' => $settings['fx_rate_url'],
				'fx'  => $settings_menu['fx'],
			)
		);

	}

	public function set_debug( $debug = true ) {

		$settings = get_option( 'bitcoin-engine' );

		$settings['debug'] = $debug;

		update_option( 'bitcoin-engine', $settings );

	}

	public function set_commission( $commission ) {

		$settings = get_option( 'bitcoin-engine' );

		$settings['commission'] = $commission;

		update_option( 'bitcoin-engine', $settings );

	}


	private function refresh_tx_history() {

		if ( false === ( $tx = get_transient( 'bitcoin-engine_tx_data' ) ) ) {
			$tx = $this->rpc->get_tx_history();

			set_transient( 'bitcoin-engine_tx_data', $tx, 10 );
		}

		$this->db->update_tx_history( $tx );

	}

	private function do_payouts() {

		$unpaids = $this->db->get_payouts_unpaid();

		$payees = array();
		foreach( $unpaids as &$unpaid ) {

			$withdrawal = get_user_meta(
				$unpaid->rx_id,
				'bitcoin-engine_withdrawal',
				true
			);

			if( empty( $withdrawal ) ) {
				continue;
			}

			if( $unpaid->amount < 0.001 || $unpaid->amount < $unpaid->fee ) {
				continue;
			}

			$paid = $this->rpc->do_payout( $unpaid->account, $unpaid->rx_id, $unpaid->amount );

			if( $paid ) {

				$this->db->confirm_paid_out( $unpaid->account );

			}

		}

	}

	public function get_post_html() {

		$post_array = $this->get_post_array();

		echo "\n\n<!-- get_post_html BEGIN --><br />\n\n";

		echo "<span class=\"bitcoin-engine_format\">\n";
		echo $post_array['amount'];
		echo "\n</span>";

		echo "\n\n<!-- get_post_HTML END -->";

	}

	public function get_post_array() {

		$post_history = $this->get_post_history_array();

		$post_array = array();

		$post_array['history'] = $post_history;

		$amount = 0.0;
		foreach( $post_array['history'] as $transaction ) {

			$amount += $transaction->amount;

		}

		global $post;

		if( $post->post_type == 'reply' ) {
		$post_array['amount'] = -$amount;
		} else {
			$post_array['amount'] = $amount;
		}

		global $post;

		$post_array['post'] = $post;

		$user_id = get_current_user_id();

		$post_array['address'] = $this->rpc->get_post_address(
			$post->ID,
			$post->post_author,
			$user_id
		);
		
		$label = "btc_{$post->post_author}_{$post->ID}_{$user_id}";
		$post_array['qr'] = $this->get_qr_url( $post_array['address'], $label );

		return $post_array;

	}

	public function get_post_history_html() {

		$post_history = $this->get_post_history_array();

		echo "\n\n<!-- get_post_history BEGIN -->\n\n";

		foreach( $post_history as $transaction ) {

			print_r( $transaction );

		}

		echo "\n\n<!-- get_post_history END -->\n\n";

	}

	public function get_post_history_array() {

		global $post;

		if( $post->post_type == 'reply' ) {

			$post_id = $post->post_parent;
			$reply_id = $post->post_author;
			$post_history = $this->db->get_post_history( $post_id, $reply_id );

		} else {

			$post_id = $post->ID;
			$post_history = $this->db->get_post_history( $post_id );

		}

		return $post_history;

	}

	public function get_user_history_html( $user_id = null ) {

		$user_history = $this->get_user_history_array( $user_id );

		echo "\n\n<!-- get_user_history BEGIN -->\n\n";

		foreach( $user_history as $transaction ) {

			print_r( $transaction );

		}

		echo "\n\n<!-- get_user_history END -->\n\n";

	}

	public function get_user_history_array( $user_id = null ) {

		if( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}

		$user_history = $this->db->get_user_history( $user_id );

		foreach( $user_history as &$tip ) {

			$dt = new DateTime( $tip->timestamp );

			$tip->date = $dt->format( 'Y-m-d' );

			$user = get_user_by( 'id', $tip->user );

			$tip->user_name = $user->user_login;

			$tip->tip = $this->get_tip_span( $tip->amount );

			$tip->story = get_the_title( $tip->post_id );

			$tip->story_url = get_permalink( $tip->post_id );

		}

		return $user_history;

	}

	public function get_qr_url( $address, $label ) {
		require_once( 'lib/phpqrcode/qrlib.php' );

		$filename = "{$label}.png";
		$path_url = plugins_url( '/lib/phpqrcode/cache/codes/', __FILE__ );
		$path     = plugin_dir_path( __FILE__ ) . 'lib/phpqrcode/cache/codes/';

		if ( !file_exists( $path . $filename ) ) {
			QRcode::png(
				"bitcoin:{$address}?label={$label}",
				$path . $filename,
				QR_ECLEVEL_H,
				20
			);
		}

		return $path_url . $filename;
	}

	public function validate_address( $address ) {
		return $this->rpc->validate_address( $address );
	}

	public function get_top_posts( $post_type, $days_ago, $count ) {
		$top_posts = $this->db->get_top_posts( $post_type, $days_ago, $count );

		foreach( $top_posts as &$top_post ) {

			$top_post_obj = get_post( $top_post->ID );

			$top_post->name = $top_post_obj->post_title;

			$top_post->link = get_permalink( $top_post->ID );

		}

		return $top_posts;
	}

	public function get_amount_span( $post_id = null ) {

		$amount = $this->get_amount( $post_id );

		return $this->get_tip_span( $amount );

	}

	public function get_tip_span( $amount ) {

		$html  = "<span class=\"bitcoin-engine_format\">";
		$html .= $amount;
		$html .= "</span>";

		return $html;
	}

	public function get_amount( $post_id = null ) {

		global $post;

		if( empty( $post_id ) ) {
			$post_id = $post->ID;
		}

		$post_history = $this->db->get_post_history( $post_id );

		$amount = 0.0;
		foreach( $post_history as $transactions ) {
			$amount += $transactions->amount;
		}

		return $amount;

	}

	public function refresh_post_meta_amounts( $post_type ) {

		$post_amounts = $this->db->get_post_amounts( $post_type );

		foreach( $post_amounts as $post_amount ) {

			$amount = $post_amount->amount;

			if( empty( $amount ) ) {
				$amount = 0.0;
			}

			update_post_meta( $post_amount->ID, '_bitcoin-engine_amount', $amount );

		}

	}

}

$bitcoin_engine = new Bitcoin_Engine();

/* EOF */

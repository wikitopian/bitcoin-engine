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

class Bitcoin_Engine {

	protected $db;
	protected $rpc;

	public $settings;

	public function __construct() {

		$settings = array(
			'version'     => '0.1',
			'debug'       => false,
			'rpctimeout'  => 2,
			'list_tx_max' => 999,
			'lastblock'   => false,
			'fx_rate_url' => 'https://blockchain.info/ticker?cors=true',
		);

		$this->settings = get_option( 'bitcoin-engine', $settings );
		update_option( 'bitcoin-engine', $this->settings );

		// user interface
		$settings_menu = array(
			'rpcconnect'  => 'rpc.blockchain.info',
			'rpcssl'      => true,
			'rpcport'     => 443,
			'rpcuser'     => null,
			'rpcpassword' => null,
			'rpcwallet'   => null,
			'fx'          => 'USD',
			'decimals'    => 5,
		);

		$this->settings = get_option( 'bitcoin-engine_menu', $settings_menu );
		update_option( 'bitcoin-engine_menu', $this->settings );

		require_once( 'inc/bitcoin-engine-menu.php' );
		$this->menu = new Bitcoin_Engine_Menu();

		// database functionality
		require_once( 'inc/bitcoin-engine-db.php' );
		$this->db = new Bitcoin_Engine_Db();

		// bitcoin functionality
		require_once( 'inc/bitcoin-engine-rpc.php' );
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
			'bitcoin-engine',
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

	private function refresh_tx_history() {

		$tx = $this->rpc->get_tx_history();

		$this->db->update_tx_history( $tx );

	}

}

$bitcoin_engine = new Bitcoin_Engine();

/* EOF */

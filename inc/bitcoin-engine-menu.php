<?php

class Bitcoin_Engine_Menu {

	public $settings;

	public function __construct() {

		$this->settings = get_option( 'bitcoin-engine_menu' );

		add_action( 'admin_menu', array( &$this, 'menu' ) );
		add_action( 'admin_init', array( &$this, 'menu_settings' ) );

	}

	public function menu() {

		add_options_page(
			'Bitcoin Engine',
			'Bitcoin Engine',
			'manage_options',
			__FILE__,
			array( &$this, 'menu_page' )
		);

	}

	public function menu_settings() {
		register_setting( 'bitcoin-engine_menu', 'bitcoin-engine_menu' );
	}

	public function menu_page() {

		echo '<div class="wrap">';
		echo '<h2>Bitcoin Engine Settings</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields( 'bitcoin-engine_menu' );
		do_settings_fields( 'bitcoin-engine_menu', 'bitcoin-engine_menu' );
		echo '<table class="form-table">';

		$this->menu_page_item(
			'rpcssl',
			'Secure Socket Layer'
		);

		$this->menu_page_item(
			'rpcconnect',
			'Address'
		);

		$this->menu_page_item(
			'rpcport',
			'Port'
		);

		$this->menu_page_item(
			'rpcuser',
			'Username'
		);

		$this->menu_page_item(
			'rpcpassword',
			'Password'
		);

		$this->menu_page_item(
			'rpcwallet',
			'Wallet Password'
		);

		$this->menu_page_item(
			'fx',
			'Conversion Currency'
		);

		$this->menu_page_item(
			'decimals',
			'Bitcoin Decimals'
		);

		echo '</table>';
		submit_button();
		echo '</form></div>';
	}

	private function menu_page_item( $item, $label ) {

		echo '<tr valign="top">';
		echo '<th scope="row"><label for="bitcoin-engine_menu[' . $item . ']">' . $label . '</label></th>';
		echo '<td>';

		if ( $item == 'rpcssl' ) {
			echo '<input type="checkbox" class="regular-text" ';
			echo 'name="bitcoin-engine_menu[' . $item . ']" id="bitcoin-engine_menu[' . $item . ']" ';
			if ( !empty( $this->settings[$item] ) ) {
				checked( $this->settings[$item] );
			}
			echo 'value="1" />';
		} else {
			echo '<input type="text" class="regular-text" ';
			echo 'name="bitcoin-engine_menu[' . $item . ']" id="bitcoin-engine_menu[' . $item . ']" ';
			echo 'value="' . $this->settings[$item] . '" />';
		}

		echo '</td>';
		echo '</tr>';

	}

}

/* EOF */

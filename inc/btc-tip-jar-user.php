<?php

class Btc_Tip_Jar_User {
	protected $tip_jar;

	private $summary;
	private $deposit;
	private $withdraw;
	private $history;

	public function __construct( $tip_jar ) {
		$this->tip_jar = $tip_jar;

		require_once( 'btc-tip-jar-user-summary.php' );
		$this->summary = new Btc_Tip_Jar_User_Summary( $this );

		require_once( 'btc-tip-jar-user-deposit.php' );
		$this->deposit = new Btc_Tip_Jar_User_Deposit( $this );

		require_once( 'btc-tip-jar-user-withdraw.php' );
		$this->withdraw = new Btc_Tip_Jar_User_Withdraw( $this );

		require_once( 'btc-tip-jar-user-history.php' );
		$this->history = new Btc_Tip_Jar_User_History( $this );

		add_action( 'admin_menu', array( &$this, 'do_menu' ) );

		add_action( 'admin_init', array( &$this, 'do_scripts_and_styles' ) );
	}
	public function do_menu() {

		add_menu_page(
			'Bitcoin Tip Jar',
			'Tip Jar',
			'read',
			get_class(),
			array( &$this->summary, 'do_page' ),
			plugins_url( '../images/btc-tip-jar-16.png', __FILE__ ),
			71
		);

		$this->do_page( 'Bitcoin Tip Jar - Overview', 'Overview', '' );
		$this->do_page( 'Bitcoin Tip Jar - Deposit Bitcoins', 'Deposit', '_deposit' );
		$this->do_page( 'Bitcoin Tip Jar - Withdraw Bitcoins', 'Withdraw', '_withdraw' );
		$this->do_page( 'Bitcoin Tip Jar - Transaction History', 'History', '_history' );
	}
	private function do_page( $title, $menu_title, $page_slug ) {
		$page = add_submenu_page(
			get_class(),
			$title,
			$menu_title,
			'read',
			get_class() . $page_slug,
			array( &$this->summary, 'do_page' )
		);

		add_action(
			'admin_print_scripts-' . $page,
			array( &$this, 'print_script' )
		);

		add_action(
			'admin_print_styles-' . $page,
			array( &$this, 'print_style' )
		);
	}
	public function do_scripts_and_styles() {
		wp_register_style(
			get_class(),
			plugins_url( '/../styles/btc-tip-jar-admin.css', __FILE__ )
		);

		wp_register_script(
			get_class(),
			plugins_url( '/../scripts/btc-tip-jar-admin.js', __FILE__ )
		);
	}
	public function print_script() {
		wp_enqueue_script( get_class() );
	}
	public function print_style() {
		wp_enqueue_style( get_class() );
	}
}

?>

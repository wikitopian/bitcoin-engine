<?php
/*
 * Plugin Name: Bitcoin Tip Jar
 * Plugin URI:  https://bitbucket.org/wikitopian/btc-tip-jar
 * Description: Easily tip specific posts with bitcoins.
 * Version:     0.1
 * Author:      @wikitopian
 * Author URI:  http://www.swarmstrategies.com/matt
 * License:     LGPLv3
 * */

class Btc_Tip_Jar {
	private $menu;
	private $btc;

	public function __construct() {

		$defaults = array(
			'rpcconnect'  => 'rpc.blockchain.info',
			'rpcssl'      => true,
			'rpcport'     => 443,
			'rpcuser'     => null,
			'rpcpassword' => null,
			'rpcwallet'   => null,
			'rpctimeout'  => 5.0,
		);

		// admin menu functionality
		require_once( 'inc/btc-tip-jar-menu.php' );
		$this->menu = new Btc_Tip_Jar_Menu( $defaults );

		// bitcoin functionality
		require_once( 'inc/btc-tip-jar-btc.php' );
		$this->btc = new Btc_Tip_Jar_Btc(
			$this->menu->settings['rpcconnect'],
			$this->menu->settings['rpcssl'],
			$this->menu->settings['rpcport'],
			$this->menu->settings['rpcuser'],
			$this->menu->settings['rpcpassword'],
			$this->menu->settings['rpcwallet'],
			$this->menu->settings['rpctimeout']
		);

		add_action( 'wp_enqueue_scripts', array( &$this, 'do_scripts_and_styles' ) );

		add_filter( 'the_content', array( &$this, 'add_post_tip_jar' ) );
	}
	public function do_scripts_and_styles() {

		global $wp_scripts;
		$jquery   = $wp_scripts->query( 'jquery-ui-core' );
		$protocol = is_ssl() ? 'https' : 'http';
		$url = "$protocol://ajax.googleapis.com/ajax/libs/jqueryui/{$jquery->ver}/themes/smoothness/jquery-ui.min.css";
		wp_enqueue_style( 'jquery-ui-smoothness', $url, false, null );

		wp_enqueue_style(
			get_class(),
			plugins_url( '/styles/btc-tip-jar.css', __FILE__ )
		);

		wp_enqueue_script(
			get_class(),
			plugins_url( '/scripts/btc-tip-jar.js', __FILE__ ),
			array(
				'jquery',
				'jquery-ui-core',
				'jquery-ui-button',
				'jquery-ui-dialog',
			),
			false,
			true
		);
	}
	public function add_post_tip_jar( $content = '' ) {

		if ( !is_single() ) {
			return $content;
		}

		global $post;

		if ( !is_user_logged_in() ) {
			$address = $this->btc->get_post_address_anonymous( $post->post_author, $post->ID );
		} else {
			// replace with more complex options
			$address = $this->btc->get_post_address_anonymous( $post->post_author, $post->ID );
		}

		$total_donated = 0.0;

		$label = "Bitcoins Donated: {$total_donated}";

		$qr_url = $this->get_qr_url( $address, 'donation-' . $post->post_name );

		if ( is_user_logged_in() ) {
			global $current_user;
			get_currentuserinfo();

			$logout = wp_logout_url( get_permalink() );

			$before = "Donating as {$current_user->display_name}...";
			$after  = "<a href=\"{$logout}\">Log out</a> first to donate anonymously.";
		} else {
			$login = wp_login_url( get_permalink() );

			$before = 'Donating anonymously';
			$after  = "<a href=\"{$login}\">Log in</a> first to take credit!";
		}

		$tip_jar = <<<HTML
<input type="button" id="Btc_Tip_Jar_tip_jar" name="Btc_Tip_Jar_tip_jar" value="{$label}" />
<div id="Btc_Tip_Jar_dialog" title="Bitcoin Tip Jar">
{$before}
<hr />
		<img src="{$qr_url}" />
<hr />
{$after}
</div>
HTML;

		$content .= "<br />\n" . $tip_jar;

		return $content;
	}
	public function get_qr_url( $address, $label ) {
		require_once( 'lib/phpqrcode/qrlib.php' );

		$filename = 'btc-tip-jar-' . md5( $address . $label ) . '.png';
		$path_url = plugins_url( '/lib/phpqrcode/cache/codes/', __FILE__ );
		$path     = plugin_dir_path( __FILE__ ) . 'lib/phpqrcode/cache/codes/';

		if ( !file_exists( $path . $filename ) ) {
			QRcode::png( "bitcoin:{$address}?label={$label}", $path . $filename, QR_ECLEVEL_H );
		}

		return $path_url . $filename;
	}
}
$btc_tip_jar = new Btc_Tip_Jar();

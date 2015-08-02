<?php

class Bitcoin_Engine_Rpc {

	public $settings;

	public $settings_menu;

	private $connect_string;

	public function __construct() {
		$this->settings      = get_option( 'bitcoin-engine' );
		$this->settings_menu = get_option( 'bitcoin-engine_menu' );

		if ( $this->settings_menu['rpcssl'] ) {
			$schema = 'https';
		} else {
			$schema = 'http';
		}

		$this->connect_string  = "{$schema}://";
		$this->connect_string .= "{$this->settings_menu['rpcuser']}:";
		$this->connect_string .= "{$this->settings_menu['rpcpassword']}@";
		$this->connect_string .= "{$this->settings_menu['rpcconnect']}:";
		$this->connect_string .= "{$this->settings_menu['rpcport']}";

	}
	public function connect() {

		require_once(
			plugin_dir_path( __FILE__ )
			.
			'../lib/json-rpc-php/jsonRPCClient.php'
		);

		if (
			empty( $this->settings_menu['rpcuser'] )
			||
			empty( $this->settings_menu['rpcpassword'] )
			||
			empty( $this->settings_menu['rpcwallet'] )
		) {
			return false;
		}

		try {
			$debug = $this->settings['debug'];
			$connection = new jsonRPCClient( $this->connect_string, $debug );

			$connection->walletpassphrase(
				$this->settings_menu['rpcwallet'],
				intval( $this->settings['rpctimeout'] )
			);

			return $connection;
		} catch( Exception $e ) {
			error_log( $e->getMessage() );
			return false;
		}

	}

	public function get_tx_history() {
		$connection = $this->connect();

		if ( !$connection ) {
			return false;
		}

		try {
			if ( !empty( $this->settings['lastblock'] ) ) {
				$history = $connection->listsinceblock( $this->settings['lastblock'] );
			} else {
				$history = $connection->listtransactions(
					'',
					$this->settings['list_tx_max'],
					0
				);
			}
		} catch( Exception $e ) {
			error_log( $e->getMessage() );
		}

		$this->settings['lastblock'] = $history['lastblock'];
		update_option( 'bitcoin-engine', $this->settings );

		if ( !empty( $history['transactions'] ) ) {

			foreach( $history['transactions'] as &$transaction ) {

				$transaction['confirmations'] = abs( $transaction['confirmations'] );

				if( $transaction['account'] ) {
					$transaction['rx_id'] = preg_replace( '/^.*user_/i', '', $transaction['account'] );

					if( !is_numeric( $transaction['rx_id'] ) ) {
						$transaction['rx_id'] = 0;
					}

				} else {
					$transaction['rx_id'] = 0;
				}

			}

			return $history['transactions'];
		}
	}

	public static function get_account_label( $user ) {
		$label  = home_url( '/' );
		$label .= 'bitcoin-engine/user_' . $user;

		return $label;
	}

	public function get_user_address( $user ) {

		$label = $this->get_account_label( $user );

		$user_address = get_user_meta(
			$user, 'bitcoin-engine_account', true
		);

		if ( empty( $user_address ) ) {
			$rpc = $this->connect();
			try {
				$getaccountaddress = $rpc->getaccountaddress( $label );
				$user_address = array();
				$user_address['label']   = $label;
				$user_address['address'] = $getaccountaddress;

				update_user_meta(
					$user, 'bitcoin-engine_account', $user_address
				);

			} catch( Exception $e ) {
				error_log( $e->getMessage() );
			}
		} else {
			return $user_address;
		}
	}

	public function get_post_address( $post_id, $author_id, $user_id ) {

		if( $user_id ) {
			return $this->get_post_address_user( $post_id, $author_id, $user_id );
		} else {
			return $this->get_post_address_anonymous( $post_id, $author_id );
		}

	}

	private function get_post_address_user( $post_id, $author_id, $user_id ) {

		$db = new Bitcoin_Engine_Db();

		$author_account = $this->get_user_address( $author_id );

		$address = $db->get_user_address_query(
			$post_id,
			$author_id,
			$user_id
		);

		if ( !empty( $address ) ) {
			return $address;
		} else {
			$rpc = $this->connect();
			try {
				$getnewaddress = $rpc->getnewaddress( $author_account['label'] );

				$db->insert_post_address_user(
					$post_id,
					$author_id,
					$user_id,
					$getnewaddress
				);

				return $getnewaddress;
			} catch( Exception $e ) {
				error_log( $e->getMessage() );
				return false;
			}
		}
	}

	private function get_post_address_anonymous( $post_id, $author ) {

		$author_account = $this->get_user_address( $author );

		$anonymous_address = get_post_meta(
			$post_id, 'bitcoin-engine_anonymous', true
		);

		if ( empty( $anonymous_address ) ) {
			$rpc = $this->connect();
			try {
				$getnewaddress = $rpc->getnewaddress( $author_account['label'] );
			} catch( Exception $e ) {
				error_log( $e->getMessage() );
			}
			$anonymous_address = $getnewaddress;

			update_post_meta(
				$post_id, 'bitcoin-engine_anonymous', $anonymous_address
			);

			Bitcoin_Engine_Db::insert_post_address_user( $post_id, $author, 0, $anonymous_address );
		}

		return $anonymous_address;
	}

	public function get_user_balance( $user_id ) {
		$label = $this->get_account_label( $user_id );

		$rpc = $this->connect();

		try {
			$user_balance = $rpc->getbalance( $label, 0 );
			return $user_balance;
		} catch( Exception $e ) {
			error_log( $e->getMessage() );
			return 0.0;
		}
	}

	public function do_withdrawal( $user, $address, $amount ) {
		$rpc = $this->connect();

		try {

			if( !$this->validate_address( $address ) ) {
				return 'INVALID_ADDRESS';
			}

			$label = $this->get_account_label( $user );

			$getbalance = $rpc->getbalance( $label );

			if ( $amount > $getbalance ) {
				return 'OVERDRAFT';
			}

			$sendfrom = $rpc->sendfrom(
				$label,
				$address,
				$amount,
				1,
				$label,
				'bitcoin-engine'
			);

			$transaction = $sendfrom['result'];

			return 'WITHDRAWN';

		} catch( Exception $e ) {
			error_log( $e->getMessage() );
			return 'CONNECTION';
		}
	}

	public function validate_address( $address ) {
		$rpc = $this->connect();

		try {

			$validateaddress = $rpc->validateaddress( $address );

			if( $validateaddress['isvalid'] ) {
				return true;
			} else {
				return false;
			}

		} catch( Exception $e ) {

			error_log( $e->getMessage() );

			return false;
		}

	}

	public function do_payout( $account, $rx_id, $amount ) {
		$rpc = $this->connect();

		$settings = get_option( 'bitcoin-engine' );

		$commission = floatval( $settings['commission'] );

		$comm_amount = $amount * $commission;

		$payout_amount = $amount - $comm_amount;

		try {

			$comm_account = $rpc->getaccountaddress( $settings['comm_label'] );

			$payout_address = get_user_meta( $rx_id, 'bitcoin-engine_withdrawal', true );

			if( $commission > 0.0 ) {
				$rpc->move( $account, $comm_account, $comm_amount );
			}

			$rpc = $this->connect();
			$rpc->sendfrom( $account, $payout_address, $payout_amount );

			return true;


		} catch( Exception $e ) {

			error_log( $e->getMessage() );

			return false;
		}



	}

}

/* EOF */

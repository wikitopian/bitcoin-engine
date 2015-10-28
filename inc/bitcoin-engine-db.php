<?php

class Bitcoin_Engine_Db {

	private $wpdb;

	private $trx_table;
	private $adr_table;

	public function __construct() {
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		global $wpdb;
		$this->wpdb = $wpdb;

		$this->trx_table = "{$this->wpdb->base_prefix}bitcoin_engine_transactions";
		$this->adr_table = "{$this->wpdb->base_prefix}bitcoin_engine_addresses";

	}

	public function create_transactions_table() {

		$transactions_sql = <<<SQL
CREATE TABLE {$this->trx_table} (
	fee       DECIMAL(16,8) DEFAULT 0.0,
	amount    DECIMAL(16,8) DEFAULT 0.0,
	blockindex VARCHAR(64) NOT NULL,
	category  VARCHAR(64)  NOT NULL,
	confirmations mediumint(9) DEFAULT 0,
	address   VARCHAR(64)  NOT NULL,
	txid      VARCHAR(64)  NOT NULL,
	blockhash VARCHAR(64)  NOT NULL,
	account   VARCHAR(64)  NULL,
	rx_id     MEDIUMINT(9) DEFAULT 0,
	payout    VARCHAR(64)  DEFAULT 'UNPAID',
	time      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
	UNIQUE KEY (txid)
);
SQL;

		DbDelta( $transactions_sql );

	}

	public function create_addresses_table() {

		$addresses_table_sql = <<<SQL
CREATE TABLE {$this->adr_table} (
	id        mediumint(9) NOT NULL AUTO_INCREMENT,
	type      VARCHAR(16)  NOT NULL,
	tx_id     mediumint(9) NOT NULL,
	rx_id     mediumint(9) NOT NULL,
	post_id   mediumint(9) NOT NULL,
	address   VARCHAR(64)  NOT NULL,
	UNIQUE KEY (id)
);
SQL;

		dbDelta( $addresses_table_sql );
	}

	public function update_tx_history( $transactions ) {

		if( empty( $transactions ) ) {
			return;
		}

		$duplicates_query_raw = <<<DUP

SELECT trx.txid FROM {$this->trx_table} AS trx WHERE trx.txid = '%s';

DUP;

		foreach ( $transactions as $transaction ) {

			$duplicates_query = $this->wpdb->prepare(
				$duplicates_query_raw,
				$transaction['txid']
			);

			$duplicates = $this->wpdb->get_results( $duplicates_query );

			if( empty( $duplicates ) ) {

			$this->wpdb->insert(

				$this->trx_table,

				array(
					'fee'           => $transaction['fee'],
					'amount'        => $transaction['amount'],
					'blockindex'    => $transaction['blockindex'],
					'category'      => $transaction['category'],
					'confirmations' => $transaction['confirmations'],
					'address'       => $transaction['address'],
					'txid'          => $transaction['txid'],
					'blockhash'     => $transaction['blockhash'],
					'account'       => $transaction['account'],
					'rx_id'         => $transaction['rx_id'],
				)

			);

			} else {

			$this->wpdb->update(

				$this->trx_table,

				array(
					'fee'           => $transaction['fee'],
					'amount'        => $transaction['amount'],
					'blockindex'    => $transaction['blockindex'],
					'category'      => $transaction['category'],
					'confirmations' => $transaction['confirmations'],
					'address'       => $transaction['address'],
					'blockhash'     => $transaction['blockhash'],
					'account'       => $transaction['account'],
					'rx_id'         => $transaction['rx_id'],
				),

				array( 'txid'       => $transaction['txid'] )

			);



			}

		}

	}

	public function get_user_address_query( $post_id, $rx_id, $tx_id ) {

		$sql = <<<SQL
SELECT
	address
	FROM {$this->adr_table}
	WHERE post_id   = {$post_id}
	  AND rx_id     = {$rx_id}
	  AND tx_id     = {$tx_id}
	LIMIT 1;
SQL;

		$results = $this->wpdb->get_results( $sql );

		if ( !empty( $results[0] ) ) {
			return $results[0]->address;
		} else {
			return false;
		}
	}

	public static function insert_post_address_user( $post_id, $rx_id, $tx_id, $address ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'bitcoin_engine_addresses',
			array(
				'type'      => 'tip',
				'post_id'   => $post_id,
				'rx_id'     => $rx_id,
				'tx_id'     => $tx_id,
				'address'   => $address,
			)
		);
	}

	public function get_post_history( $post_id, $reply_id = null ) {

		if( empty( $reply_id ) ) {
			$reply_id = 'adr.tx_id';
		}

		$anon_address = get_post_meta( $post_id, 'bitcoin-engine_anonymous', true );

		$post_history_query = <<<SQL

SELECT
	trx.amount AS amount,
	trx.fee AS fee,
	adr.tx_id AS sender,
	trx.txid AS transaction,
	trx.payout AS payout,
	trx.time AS timestamp
	FROM {$this->adr_table} AS adr
	INNER JOIN {$this->trx_table} AS trx
	ON  trx.address  = adr.address
	AND adr.type     = 'tip'
	AND trx.category = 'receive'
	AND adr.tx_id = {$reply_id}
	WHERE adr.post_id = {$post_id}
UNION
SELECT
	trx.amount AS amount,
	trx.fee AS fee,
	0 AS sender,
	trx.txid AS transaction,
	trx.payout AS payout,
	trx.time AS timestamp
	FROM {$this->trx_table} AS trx
	WHERE trx.address  = '{$anon_address}'
	  AND trx.category = 'receive';

SQL;

		$post_history = $this->wpdb->get_results( $post_history_query );

		return $post_history;
	}

	public function get_user_history( $user_id ) {

		$user_history_query = <<<SQL

SELECT
	'TX' AS type,
	-trx.amount AS amount,
	adr.rx_id AS user,
	adr.post_id AS post_id,
	trx.txid AS transaction,
	trx.time AS timestamp
	FROM {$this->adr_table} AS adr
	INNER JOIN {$this->trx_table} AS trx
	ON  trx.address  = adr.address
	AND adr.type     = 'tip'
	AND trx.category = 'receive'
	WHERE adr.tx_id = {$user_id}
UNION
SELECT
	'RX' AS type,
	trx.amount AS amount,
	adr.tx_id AS user,
	adr.post_id AS post_id,
	trx.txid AS transaction,
	trx.time AS timestamp
	FROM {$this->adr_table} AS adr
	INNER JOIN {$this->trx_table} AS trx
	ON  trx.address  = adr.address
	AND adr.type     = 'tip'
	AND trx.category = 'receive'
	WHERE adr.rx_id = {$user_id}
	ORDER BY timestamp DESC;

SQL;

		$user_history = $this->wpdb->get_results( $user_history_query );

		return $user_history;

	}

	public function get_payouts_unpaid() {

		$payouts_unpaid_query = <<<UNPAID

SELECT
	trx.rx_id,
	trx.account,
	SUM(trx.amount) AS amount,
	SUM(trx.fee) AS fee
	FROM {$this->trx_table} AS trx
	WHERE trx.category = 'receive'
	  AND trx.payout = 'UNPAID'
	  AND trx.account <> ''
	  AND trx.confirmations >= '%d'
	GROUP BY
		trx.rx_id,
		trx.account


UNPAID;

		$settings = get_option( 'bitcoin-engine' );

		if( !is_int( $settings['min_conf'] ) || $settings['min_conf'] <= 0 ) {
			$settings['min_conf'] = 1;
		}

		$payouts_unpaid_query = $this->wpdb->prepare(
			$payouts_unpaid_query,
			$settings['min_conf']
		);

		$payouts_unpaid = $this->wpdb->get_results( $payouts_unpaid_query );

		return $payouts_unpaid;

	}

	public function confirm_paid_out( $account ) {

		$this->wpdb->update(
			$this->trx_table,
			array(
				'payout' => 'PAID',
			),
			array(
				'account' => $account,
			)
		);

	}

	public function get_top_posts( $post_type, $days_ago, $count ) {

		$settings = get_option( 'bitcoin-engine' );

		$get_top_posts_query = <<<TOP

SELECT
	pst.ID,
	SUM( trx.amount ) AS amount
	FROM {$this->wpdb->prefix}posts AS pst
	INNER JOIN {$this->adr_table} AS adr
	ON  adr.post_id = pst.ID
	INNER JOIN {$this->trx_table} AS trx
	ON  trx.address = adr.address
	WHERE pst.post_date >= DATE_ADD( CURRENT_TIMESTAMP(), INTERVAL -%d DAY )
	  AND pst.post_type = '%s'
	  AND pst.post_status = 'publish'
	  AND trx.category = 'receive'
	  AND trx.confirmations >= %d
	  AND adr.type = 'tip'
	GROUP BY pst.ID
	HAVING   SUM( trx.amount ) > 0
	ORDER BY SUM( trx.amount ) DESC
	LIMIT %d;

TOP;

		$get_top_posts_query = $this->wpdb->prepare(
			$get_top_posts_query,
			array(
				$days_ago,
				$post_type,
				$settings['min_conf'],
				$count
			)
		);

		return $this->wpdb->get_results( $get_top_posts_query );

	}

public function get_post_amounts( $post_type ) {

		$settings = get_option( 'bitcoin-engine' );

		$get_post_amounts_query = <<<TOP

SELECT
	pst.ID,
	SUM( trx.amount ) AS amount
	FROM {$this->wpdb->prefix}posts AS pst
	LEFT JOIN {$this->adr_table} AS adr
	ON  adr.post_id = pst.ID
	AND adr.type = 'tip'
	LEFT JOIN {$this->trx_table} AS trx
	ON  trx.address = adr.address
	AND trx.category = 'receive'
	AND trx.confirmations >= %d
	WHERE pst.post_type = '%s'
	  AND pst.post_status = 'publish'
	GROUP BY pst.ID

TOP;

		$get_post_amounts_query = $this->wpdb->prepare(
			$get_post_amounts_query,
			array(
				$settings['min_conf'],
				$post_type,
			)
		);

		return $this->wpdb->get_results( $get_post_amounts_query );

	}

}

/* EOF */

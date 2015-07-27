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
	account   varchar(64)  NULL,
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

	public function insert_post_address_user( $post_id, $rx_id, $tx_id, $address ) {
		$this->wpdb->insert(
			$this->adr_table,
			array(
				'type'      => 'tip',
				'post_id'   => $post_id,
				'rx_id'     => $rx_id,
				'tx_id'     => $tx_id,
				'address'   => $address,
			)
		);
	}

	public function get_post_history( $post_id ) {

		$anon_address = get_post_meta( $post_id, 'bitcoin-engine_anonymous', true );

		$post_history_query = <<<SQL

SELECT
    trx.amount AS amount,
    trx.fee AS fee,
    adr.tx_id AS sender,
    trx.txid AS transaction,
    trx.time AS timestamp
	FROM {$this->adr_table} AS adr
	INNER JOIN {$this->trx_table} AS trx
	ON  trx.address  = adr.address
	AND adr.type     = 'tip'
	AND trx.category = 'receive'
	WHERE adr.post_id = {$post_id}
UNION
SELECT
	trx.amount AS amount,
	trx.fee AS fee,
	0 AS sender,
	trx.txid AS transaction,
	trx.time AS timestamp
	FROM {$this->trx_table} AS trx
	WHERE trx.address  = '{$anon_address}'
	  AND trx.category = 'receive';

SQL;

		$post_history = $this->wpdb->get_results( $post_history_query );

		return $post_history;
	}

}

/* EOF */

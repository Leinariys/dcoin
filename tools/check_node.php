<?php

define( 'DC', TRUE);

define( 'ABSPATH', dirname(dirname(__FILE__)) . '/' );

set_time_limit(0);

require_once( ABSPATH . 'db_config.php' );
require_once( ABSPATH . 'includes/autoload.php' );

$db = new MySQLidb(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: host_");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

$variables = ParseData::get_all_variables($db);

$block_data = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
		SELECT `time`, `block_id`
		FROM `".DB_PREFIX."info_block`
		", 'fetch_array');

if ( isset($_REQUEST['block_id']) ) {

	$block_id = intval($_REQUEST['block_id']);
	$hash = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SELECT `hash`
			FROM `".DB_PREFIX."block_chain`
			WHERE `id`= {$block_id}
			", 'fetch_one');
	print $hash;

} else if ( isset($_REQUEST['nodes']) ) {

	$nodes = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SELECT `host`,
						 count(`user_id`) as count
			FROM `".DB_PREFIX."miners_data`
			WHERE `status`= 'miner'
			GROUP BY `host`
			LIMIT 100
			", 'all_data');
	echo json_encode($nodes);

} else if ( isset($_REQUEST['col'], $_REQUEST['row'], $_REQUEST['table']) ) {

	$table = filter_var($_REQUEST['table'], FILTER_SANITIZE_STRING);
	$row = filter_var($_REQUEST['row'], FILTER_SANITIZE_STRING);
	$col = filter_var($_REQUEST['col'], FILTER_SANITIZE_STRING);
	$all_tables = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SHOW TABLES
			", 'array');

	if ( !in_array($table, $all_tables) )
		die ('error table');

	if (preg_match('/(my_|_my|config|_refs)/i', $table))
		die ('error table');

	if (!preg_match('/^\-?[0-9]{1,10}$/D', $row))
		die ('error row');

	if ( !check_input_data ($col, 'bigint') )
		die ('error col');

	if ($row==-1) {
		$data = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
				SHOW TABLE STATUS LIKE '{$table}'
				", 'fetch_array');
		$row = $data['Rows'] -1 ;
	}

	$res = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SELECT *
			FROM `".DB_PREFIX."{$table}`
			LIMIT $row, 1
			");
	$data =  $db->fetchArrayNum($res);
	print $data[$col];
}
else {

	$all_counts = array();
	$data = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SELECT `current_version`,
						 `block_id`
			FROM `".DB_PREFIX."info_block`
			", 'fetch_array');
	$all_counts['time'] = time();
	$all_counts['block_id'] = $data['block_id'];
	$all_counts['db_version'] = $data['current_version'];
	$all_counts['file_version'] = file_get_contents( ABSPATH . 'version' );
	$data = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SELECT sum(`amount`) as amount,
						 sum(`tdc_amount`) as tdc_amount
			FROM `".DB_PREFIX."promised_amount`
			WHERE `del_block_id`=0
			", 'fetch_array');
	$all_counts['sum_promised_amount'] = $data['amount'];
	$all_counts['sum_promised_tdc_amount'] = $data['tdc_amount'];
	$all_counts['sum_wallets_amount'] = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SELECT sum(`amount`)
			FROM `".DB_PREFIX."wallets`
			", 'fetch_one');

	$tables_array = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SHOW TABLES
			", 'array');

	foreach($tables_array as $table) {

		if (preg_match('/(my_|_my|config|_refs)/i', $table))
			continue;

		$sql_where = '';
		$order_by = '';
		if (preg_match('/log_time_(.*)/i', $table, $t_name) && $table!='log_time_money_orders'){
			$sql_where = " WHERE `time` > ".($block_data['time'] - @$variables['limit_'.$t_name[1].'_period'])."";
			$order_by = "`user_id`, `time`";
		}
		else if (preg_match('/^(log_transactions)$/i', $table) ) {
			$sql_where = " WHERE `time` > ".($block_data['time'] - 86400*3);
		}
		else if (preg_match('/^(log_votes)$/i', $table) ) {
			$sql_where = " WHERE `del_block_id` > ".($block_data['block_id'] - $variables['rollback_blocks_2']);
			$order_by = "`user_id`, `voting_id`";
		}
		else if (preg_match('/^(log_time_money_orders)$/i', $table) ) {
			$sql_where = " WHERE `del_block_id` > ".($block_data['block_id'] - $variables['rollback_blocks_2']);
		}
		else if (preg_match('/^(log_forex_orders|log_forex_orders_main)$/i', $table) ) {
			$sql_where = " WHERE `block_id` > ".($block_data['block_id'] - $variables['rollback_blocks_2'])."";
		}
		else if (preg_match('/^(log_commission|log_faces|log_miners|log_miners_data|log_points|log_promised_amount|log_recycle_bin|log_spots_compatibility|log_users|log_votes_max_other_currencies|log_votes_max_promised_amount|log_votes_miner_pct|log_votes_reduction|log_votes_user_pct|log_wallets)$/i', $table) ) {
			$sql_where = " WHERE `block_id` > ".($block_data['block_id'] - $variables['rollback_blocks_2'])."";
			$order_by = "`log_id`";
		}
		else if (preg_match('/^(votes_miners|cf_comments|cf_currency|cf_funding|cf_lang|cf_projects|cf_projects_data)$/i', $table) ) {
			$order_by = "`id`";
		}
		else if (preg_match('/^(wallets_buffer)$/i', $table) ) {
			$sql_where = " WHERE `del_block_id` > ".($block_data['block_id'] - $variables['rollback_blocks_2']);
			$order_by = "`user_id`, `del_block_id`";
		}
		else if (preg_match('/^(arbitration_trust_list)$/i', $table) ) {
			$order_by = "`user_id`, `arbitrator_user_id`";
		}
		else if (preg_match('/^(points_status)$/i', $table) ) {
			$order_by = "`block_id`, `time_start`";
		}

		$count = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
				SELECT count(*)
				FROM `".DB_PREFIX."{$table}`
				{$sql_where}
				", 'fetch_one');
		$all_counts[$table] = $count;
		$all_counts['_hash_'.$table] = substr(hash_table_data($db, $table, $sql_where, $order_by), 0, 6);
	}

	print json_encode($all_counts);
}
?>
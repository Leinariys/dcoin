<?php
if (!defined('DC')) die("!defined('DC')");

$tpl['block_id'] = intval($_REQUEST['parameters']['block_id']);
$tpl['start'] = intval(@$_REQUEST['parameters']['start']);

$tpl['data'] = '';

if ($tpl['start'] || (!$tpl['start'] && !$tpl['block_id'])) {
	if (!$tpl['start'] && !$tpl['block_id']) {

		$tpl['data'].= '<h3>Latest Blocks</h3>';
		$sql = "SELECT `data`,  `hash`
				FROM `".DB_PREFIX."block_chain`
				ORDER BY `id` DESC
				LIMIT 15";
	}
	else {
		$sql = "SELECT `data`,  `hash`
				FROM `".DB_PREFIX."block_chain`
				ORDER BY `id` ASC
				LIMIT ".($tpl['start']-1).", 100";
	}
	$tpl['data'].= '<table class="table"><tr><th>Block</th><th>Hash</th><th>Time</th><th>User id</th><th>Level</th><th>Transactions</th></tr>';
	$res = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, $sql);
	$bin_to_hex_array = array('sign', 'public_key', 'encrypted_message', 'comment', 'bin_public_keys');
	while ( $row = $db->fetchArray( $res ) ) {
		//$hash = substr(bin2hex($row['hash']), 0, 8);
		$hash = bin2hex($row['hash']);
		$binary_data = $row['data'];
		$parsedata = new ParseData($binary_data, $db);
		$parsedata->ParseData_tmp();
		$block_data = $parsedata->block_data;
		$tx_array = $parsedata->tx_array;
		$block_data['sign'] = bin2hex($block_data['sign']);
		$tpl['data'].= "<tr><td><a href=\"#\" onclick=\"fc_navigate('block_explorer', {'block_id':{$block_data['block_id']}})\">{$block_data['block_id']}</a></td><td>{$hash}</td><td>".date('d-m-Y H:i:s', $block_data['time'])."</td><td>{$block_data['user_id']}</td><td>{$block_data['level']}</td><td>";
		if ($tx_array) {
			$tpl['data'].= sizeof($tx_array);
		}
		else
			$tpl['data'].= '0';
		$tpl['data'].= "</td>";
		//$tpl['data'].= "<td><div style=\"width: 300px; height: 40px; overflow: auto; background-color: #f2dede\">{$block_data['sign']}</div></td>";
		$tpl['data'].= "</tr>";

	}
	$tpl['data'].= '</table>';
}
else if ($tpl['block_id']) {
	$tpl['data'].= '<table class="table">';
	$row = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SELECT `data`,
						 `hash`
			FROM `".DB_PREFIX."block_chain`
			WHERE `id` = {$tpl['block_id']}
			LIMIT 1
			", 'fetch_array');
	$bin_to_hex_array = array('sign', 'public_key', 'encrypted_message', 'comment', 'bin_public_keys');
	//$hash = substr(bin2hex($row['hash']), 0, 8);
	$hash = bin2hex($row['hash']);
	$binary_data = $row['data'];
	$parsedata = new ParseData($binary_data, $db);
	$parsedata->ParseData_tmp();
	$block_data = $parsedata->block_data;
	$tx_array = $parsedata->tx_array;
	$block_data['sign'] = bin2hex($block_data['sign']);

	$tpl['data'].= "<tr><td><strong>Raw&nbsp;data</strong></strong></td><td><a href='get_block.php?id={$block_data['block_id']}' target='_blank'>Download</a></td></tr>";
	$tpl['data'].= "<tr><td><strong>Block_id</strong></strong></td><td>{$block_data['block_id']}</td></tr>";
	$tpl['data'].= "<tr><td><strong>Hash</strong></td><td>{$hash}</td></tr>";
	$tpl['data'].= "<tr><td><strong>Time</strong></td><td>".date('d-m-Y H:i:s', $block_data['time'])." / {$block_data['time']}</td></tr>";
	$tpl['data'].= "<tr><td><strong>User_id</strong></td><td>{$block_data['user_id']}</td></tr>";
	$tpl['data'].= "<tr><td><strong>Level</strong></td><td>{$block_data['level']}</td></tr>";
	$tpl['data'].= "<tr><td><strong>Sign</strong></td><td>".chunk_split($block_data['sign'], 130)."</td></tr>";
	if ($tx_array) {
		//$tpl['data'].= sizeof($tx_array);
		$tpl['data'].= "<tr><td><strong>Transactions</strong></td><td><div><pre style='width: 700px'>";
		for ($i=0; $i<sizeof($tx_array); $i++) {
			foreach ($tx_array[$i] as $k=>$v) {
				if (in_array($k, $bin_to_hex_array))
					$tx_array[$i][$k] = bin2hex($v);
				if ($k=='file')
					$tx_array[$i][$k] = 'file size: '.strlen($v);
				if ($k=='code')
					$tx_array[$i][$k] = ParseData::dsha256($v);

			}
		}
		$tpl['data'].=print_r($tx_array, true);
		$tpl['data'].= "</pre></div></td></tr>";
	}
	//else
	//	$tpl['data'].= '0';
	//$tpl['data'].= "</td>";
	//$tpl['data'].= "<td><div style=\"width: 300px; height: 40px; overflow: auto; background-color: #f2dede\">{$block_data['sign']}</div></td>";
	//$tpl['data'].= "</tr>";
	$tpl['data'].= '</table>';
}

require_once( ABSPATH . 'templates/block_explorer.tpl' );

?>
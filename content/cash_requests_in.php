<?php
if (!defined('DC')) die("!defined('DC')");

$cash_requests_status =
	array(
		'my_pending'=> $lng['local_pending'],
		'pending' => $lng['pending'],
		'approved'=> $lng['approved'],
		'rejected'=> $lng['rejected']
	);

// валюты
$res = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, '
		SELECT `id`,
					 `name`
		FROM `'.DB_PREFIX.'currency`
		ORDER BY `name`
		');
while ($row = $db->fetchArray($res)) 
	$tpl['currency_list'][$row['id']] = $row['name'];

// Узнаем свой user_id
$tpl['user_id'] = get_my_user_id($db);

$variables = ParseData::get_all_variables($db);
// актуальный запрос к нам на получение налички. Может быть только 1.
$tpl['data'] = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
		SELECT `".DB_PREFIX.MY_PREFIX."my_cash_requests`.`cash_request_id`,
					 `".DB_PREFIX.MY_PREFIX."my_cash_requests`.`id`,
					 `".DB_PREFIX.MY_PREFIX."my_cash_requests`.`comment_status`,
					 `".DB_PREFIX.MY_PREFIX."my_cash_requests`.`comment`,
					 `".DB_PREFIX."cash_requests`.`amount`,
					 `".DB_PREFIX."cash_requests`.`currency_id`,
					 `".DB_PREFIX."cash_requests`.`from_user_id`,
					 LOWER(HEX(`".DB_PREFIX."cash_requests`.`hash_code`)) as `hash_code`
		FROM `".DB_PREFIX.MY_PREFIX."my_cash_requests`
		LEFT JOIN `".DB_PREFIX."cash_requests` ON `".DB_PREFIX."cash_requests`.`id` = `".DB_PREFIX.MY_PREFIX."my_cash_requests`.`cash_request_id`
		WHERE `".DB_PREFIX."cash_requests`.`to_user_id` = {$tpl['user_id']} AND
					 `".DB_PREFIX."cash_requests`.`status` = 'pending' AND
					 `".DB_PREFIX."cash_requests`.`time` > ".(time()-$variables['cash_request_time'])." AND
					 `".DB_PREFIX."cash_requests`.`del_block_id` = 0 AND
					 `".DB_PREFIX."cash_requests`.`for_repaid_del_block_id` = 0
		ORDER BY `cash_request_id` DESC
		LIMIT 1
		", 'fetch_array' );

// список ранее отправленных ответов на запросы.
$res = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
		SELECT *
		FROM `".DB_PREFIX.MY_PREFIX."my_cash_requests`
		WHERE `to_user_id` = {$tpl['user_id']}
		");
while ($row = $db->fetchArray($res))
	$tpl['my_cash_requests'][] = $row;


$tpl['data']['type'] = 'cash_request_in';
$tpl['data']['type_id'] = ParseData::findType($tpl['data']['type']);
$tpl['data']['time'] = time();
$tpl['data']['user_id'] = $user_id;

require_once( ABSPATH . 'templates/cash_requests_in.tpl' );

?>
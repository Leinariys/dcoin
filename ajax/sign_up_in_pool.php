<?php
session_start();

if ( empty($_SESSION['user_id']) )
	die('!user_id');
$user_id = intval($_SESSION['user_id']);

define( 'DC', TRUE);

define( 'ABSPATH', dirname(dirname(__FILE__)) . '/' );

set_time_limit(0);

//require_once( ABSPATH . 'includes/errors.php' );
require_once( ABSPATH . 'includes/fns-main.php' );
require_once( ABSPATH . 'db_config.php' );
require_once( ABSPATH . 'includes/class-mysql.php' );
require_once( ABSPATH . 'phpseclib/Math/BigInteger.php');
require_once( ABSPATH . 'phpseclib/Crypt/Random.php');
require_once( ABSPATH . 'phpseclib/Crypt/Hash.php');
require_once( ABSPATH . 'phpseclib/Crypt/RSA.php');
require_once( ABSPATH . 'phpseclib/Crypt/AES.php');

$db = new MySQLidb(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);

$email = $_REQUEST['email'];
if(!filter_var($email, FILTER_VALIDATE_EMAIL))
	die(json_encode(array('error'=>'incorrect email')));

$lang = get_lang();
require_once( ABSPATH . 'lang/'.$lang.'.php' );

// если мест в пуле нет, то просто запишем юзера в очередь
$pool_max_users = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
			SELECT `pool_max_users`
			FROM `".DB_PREFIX."config`
			", 'fetch_one' );
if (sizeof($community) >= $pool_max_users) {
	$db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__,  "
			INSERT IGNORE INTO `".DB_PREFIX."pool_waiting_list` (
				`email`,
				`time`,
				`user_id`
			)
			VALUES (
					'{$email}',
					".time().",
					{$user_id}
			)");
	die(json_encode(array('error'=>'К сожалению, пул переполнен. Мы сообщим Вам, когда будет запущен новый пул.')));
}

// регистрируем юзера в пуле
// вначале убедитмся, что такой user_id у нас уже не зареган
$community = $db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
		SELECT `user_id`
		FROM `".DB_PREFIX."community`
		WHERE `user_id` = {$user_id}
		", 'fetch_one' );
if ($community) {
	die(json_encode(array('error'=>'Ваш user_id уже зарегистрирован в нашем пуле. Пожалуйста, свяжитесь с администрацией.')));
}

$db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__,  "
		INSERT IGNORE INTO `".DB_PREFIX."community` (
			`user_id`
		)
		VALUES (
			{$user_id}
		)");

$rsa = new Crypt_RSA();
$key = array();
$key['e'] = new Math_BigInteger($_POST['e'], 16);
$key['n'] = new Math_BigInteger($_POST['n'], 16);
$rsa->setPublicKey($key, CRYPT_RSA_PUBLIC_FORMAT_RAW);
$PublicKey = clear_public_key($rsa->getPublicKey());

// если таблы my для этого юзера уже есть в БД, то они перезапишутся.
$mysqli_link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
$db_name = DB_NAME;
$prefix = DB_PREFIX;
include ABSPATH.'schema.php';
mysqli_query($mysqli_link, 'SET NAMES "utf8" ');
pool_add_users ("{$user_id};{$PublicKey}\n", $my_queries, $mysqli_link, DB_PREFIX, false);

define('MY_PREFIX', $user_id.'_');
$db->query( __FILE__, __LINE__,  __FUNCTION__,  __CLASS__, __METHOD__, "
		UPDATE `".DB_PREFIX.MY_PREFIX."my_table`
		SET `email` = '{$email}'
		");
print json_encode(array('success'=>'Поздравляем, теперь Вы зарегистрированы на нашем пуле. Обновите страницу и заново введите свой ключ'));
?>
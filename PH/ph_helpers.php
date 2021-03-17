<?php
	require_once 'ph_config.php';
	
	class Helpers {
		private static $connection = NULL;
		
		public static function create_connection() {
			try {
				$settings = $GLOBALS['settings'];
				self::$connection = new PDO('mysql:host=' . $settings['db_host'] . ';dbname=' . $settings['db_name'], $settings['db_user'], $settings['db_password'],[PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
			} catch (PDOException $e) {}
		}
		
		public static function is_connected() {
			return (bool)self::$connection;
		}
		
		public static function get_user_usergroups($user) {
			if (empty($user) || !self::$connection)
				return NULL;
			
			$res_user = self::$connection->prepare('SELECT permissions FROM users WHERE discord_id= :username LIMIT 1');
			$res_user->bindValue(':username', $user, PDO::PARAM_STR);
			
			if ($res_user->execute() && $res_user->rowCount() > 0) {
				$row_groups = $res_user->fetch(PDO::FETCH_ASSOC);
				return array('primary' => $row_groups['permissions'], 'secondary' => array());
			}
			
			return NULL;
		}
		
		public static function get_usergroups() {
			$groups = array(
				array(
					'name' => 'Banned',
					'id' => -1
				),
				array(
					'name' => 'User',
					'id' => 0
				),
				array(
					'name' => 'Subscribed',
					'id' => 1
				),
				array(
					'name' => 'Beta',
					'id' => 2
				),
				array(
					'name' => 'Support',
					'id' => 3
				),
				array(
					'name' => 'Moderator',
					'id' => 4
				),
				array(
					'name' => 'Developer',
					'id' => 5
				),
				array(
					'name' => 'Administrator',
					'id' => 6
				)
			);
			
			return $groups;
		}
		
		public static function is_banned($user) {
			if (empty($user) || !self::$connection)
				return false;
			
			$res_is_banned = self::$connection->prepare('SELECT id FROM users WHERE discord_id= :id AND permissions= -1 LIMIT 1');
			$res_is_banned->bindValue(':id', $user, PDO::PARAM_STR);

			return (bool)($res_is_banned->execute() && $res_is_banned->rowCount() > 0);
		}
		
		public static function unban($user) {
			if (empty($user) || !self::$connection)
				return false;
			
			$res_unban_user = self::$connection->prepare('UPDATE users SET permissions=0 WHERE discord_id= :username LIMIT 1');
			$res_unban_user->bindValue(':username', $user, PDO::PARAM_STR);
			
			return $res_unban_user->execute();
		}
		
		public static function ban_user($user) {
			if (empty($user) || !self::$connection)
				return false;
			
			$res_ban_user = self::$connection->prepare('UPDATE users SET permissions= -1 WHERE discord_id= :username LIMIT 1');
			$res_ban_user->bindValue(':username', $user, PDO::PARAM_STR);
			
			return $res_ban_user->execute();
		}
		
		private static function get_ip() {
			$ipaddress = '';			
			if (getenv('HTTP_CLIENT_IP'))
				$ipaddress = getenv('HTTP_CLIENT_IP');
			else if(getenv('HTTP_X_FORWARDED_FOR'))
				$ipaddress = getenv('HTTP_X_FORWARDED_FOR');
			else if(getenv('HTTP_X_FORWARDED'))
				$ipaddress = getenv('HTTP_X_FORWARDED');
			else if(getenv('HTTP_FORWARDED_FOR'))
				$ipaddress = getenv('HTTP_FORWARDED_FOR');
			else if(getenv('HTTP_FORWARDED'))
				$ipaddress = getenv('HTTP_FORWARDED');
			else if(getenv('REMOTE_ADDR'))
				$ipaddress = getenv('REMOTE_ADDR');
			else
				$ipaddress = 'UNKNOWN';

			return $ipaddress;
		}
		
		public static function random_string($length = 10) {
			$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$charactersLength = strlen($characters);
			$randomString = '';
			for ($i = 0; $i < $length; $i++) {
				$randomString .= $characters[rand(0, $charactersLength - 1)];
			}
			return $randomString;
		}
		
		public static function download($user) {
			if (empty($user) || !self::$connection)
				return '';
			
			$groups = self::get_user_usergroups($user);
			if (empty($groups) || !isset($groups))
				return '';
			
			$settings = $GLOBALS['settings'];
			
			$post_data = json_encode(array(
				'user' => $user,
				'ip' => self::get_ip(),
				'client_id' => $settings['ph_client_id'],
				'groups' => $groups
			));
			
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, $settings['ph_download_url']);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json')); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$content = curl_exec($ch);

			curl_close($ch);
			
			return $content;
		}
	}
?>
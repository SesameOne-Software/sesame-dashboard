<?php
	require_once 'ph_helpers.php';
	require_once 'ph_config.php';

	// Useragent Check
	if ($_SERVER['HTTP_USER_AGENT'] !== 'Project Hades POST')
		exit();

	$client_id = $_POST['client_id'];
	$user = $_POST['user'];
	
	// Make sure source is Project Hades
	if ($client_id !== $settings['ph_client_id'])
		exit();
	
	Helpers::create_connection();
	if (!Helpers::is_connected())
		die(json_encode(array('success' => false, 'error' => 'Failed to establish connection to forums database.')));
	
	$banned_user = Helpers::ban_user($user);
	if (!$banned_user)
		die(json_encode(array('success' => false, 'error' => 'Failed to ban user on forums.')));
	
	die(json_encode(array('success' => true, 'msg' => 'Successfully banned user on forums.')));
?>
<?php
	require_once 'ph_helpers.php';
	require_once 'ph_config.php';

	// Useragent Check
	if ($_SERVER['HTTP_USER_AGENT'] !== 'Project Hades POST')
		exit();

	$client_id = $_POST['client_id'];
	
	// Make sure source is Project Hades
	if ($client_id !== $settings['ph_client_id'])
		exit();
	
	Helpers::create_connection();
	if (!Helpers::is_connected())
		exit();
	
	$groups = Helpers::get_usergroups();
	if (!isset($groups) || empty($groups))
		exit();
	
	die(json_encode($groups));
?>
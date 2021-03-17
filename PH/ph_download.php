<?php
	require_once 'ph_helpers.php';
	require_once 'ph_config.php';
	
	session_start();
	
	$discord_id = $_SESSION['user']['id'];

	if(!$discord_id)
		die('You are not logged in.'); // Not logged in
	
	Helpers::create_connection();
	if (!Helpers::is_connected())
		die('Failed to retrieve data from our forums.');
	
	if (empty($discord_id))
		die('Failed to retrieve user information.');
	
	if (Helpers::is_banned($discord_id))
		die('You are banned.');
	
	$download = Helpers::download($discord_id);
	$result_json = json_decode($download, true);
	
	if (empty($download) || !isset($result_json))
		die('Failed to get dependencies from Project Hades.');
	
	if (!$result_json['success'])
		die($result_json['error']);
	
	$file = base64_decode($result_json['file']);
	$ui = base64_decode($result_json['ui']);
	
	$zip = new ZipArchive();
	$filename = "./loader_". Helpers::random_string() . ".zip";

	if ($zip->open($filename, ZipArchive::CREATE)!==TRUE) {
		die('Failed to prepare loader files.');
	}

	if (empty($settings['ph_ui_file_name']))
		$zip->addFromString(Helpers::random_string(20) . '.exe', $ui);
	else
		$zip->addFromString($settings['ph_ui_file_name'] . '.exe', $ui);
	
	$zip->addFromString('ph.dll', $file); // DO NOT RENAME THIS. Doing so will stop auto-update from working and may cause security breaches.
	$zip->close();
	
	if (empty($settings['ph_download_zip_name']))
		header('Content-Disposition: attachment; filename="'. Helpers::random_string() . '.zip' . '"');
	else
		header('Content-Disposition: attachment; filename="'. $settings['ph_download_zip_name'] . '.zip' . '"');
	
	header('Content-Type: application/zip');
	header('Connection: close');
	ob_clean();
	flush();	
	readfile($filename);
	ignore_user_abort(true);
	unlink($filename);
?>
<?php

	global $wpdb;
	$table_name = $wpdb->prefix . "chatbox";
	
	$sql = "DROP TABLE IF EXISTS " . $table_name . ";";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

?>
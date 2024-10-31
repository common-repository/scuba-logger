<?php
/**
 * Plugin Name: Scuba Logger
 * Plugin URI: http://www.am-process.org/scuba/?page_id=974
 * Description: Store a dive log so that it can be easily displayed in a wordpress blog, and searched.
 * Version: 0.1.8
 * Author: Aengus Martin
 * Author URI: http://www.am-process.org
 * License: GPL3
 */

/*
 * The following is plagiarised directly from the Shashin wp plugin.
 * It sets up automatic class loading, then creates a ScubaLoggerWp object
 * and calls the run() method which sets up all the hooks, etc.
 */
$scubaLoggerPath = dirname(__FILE__);
$scubaLoggerParentDir = basename($scubaLoggerPath);
$scubaLoggerAutoLoaderPath = $scubaLoggerPath . '/ScubaLoggerAutoLoader.php';

register_activation_hook(__FILE__, 'scubalogger_activate');
register_deactivation_hook(__FILE__, 'scubalogger_deactivate');

if (file_exists($scubaLoggerAutoLoaderPath)) {
    require_once($scubaLoggerAutoLoaderPath);
    new ScubaLoggerAutoLoader('/scuba-logger');
    $scubaLogger = new ScubaLoggerWp();
    $scubaLogger->run();
}

/*
 * Create the scubalogger tables to store dive information.
 */
function scubalogger_activate() {
	global $wpdb;

	$table_name = $wpdb->prefix . "scubalogger_types";
	$sql = "CREATE TABLE $table_name (
		typeid int NOT NULL,
		type varchar(30),
		PRIMARY KEY  (typeid)
	);";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	$table_name = $wpdb->prefix . "scubalogger";
	// ensure that in the command below, there are 2 SPACES betweeen 'PRIMARY KEY' and '(divenumber)'
	$sql = "CREATE TABLE $table_name (
		divenumber int NOT NULL,
		divedate date,
		sitename varchar(100),
		location varchar(100),
		objective varchar(100),
		timedown time,
		maxdepth decimal(6,3),
		avdepth decimal(6,3),
		divetime int,
		watertemp decimal(6,3),
		airtemp decimal(6,3),
		weather varchar(100),
		seaconditions varchar(100),
		visibility int,
		buddy varchar(100),
		boatname varchar(100),
		notes text,
		PRIMARY KEY  (divenumber)
	);";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	$table_name = $wpdb->prefix . "scubalogger_type_records";
	$sql = "CREATE TABLE $table_name (
		divenumber int NOT NULL,
		typeid int NOT NULL
	);";
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);

	scubalogger_insert_types();
	// scubalogger_insert_test_dives();
}

/*
 * Delete the scubalogger table when the plugin is deactivated.
 */
function scubalogger_deactivate() {
	// The following code drops all tables which is useful during development. Uncomment at your own risk.
	/*
	global $wpdb;
	$table_name = $wpdb->prefix . "scubalogger";
	$sql = "DROP TABLE $table_name;";
	$wpdb->query($sql);
	$table_name = $wpdb->prefix . "scubalogger_types";
	$sql = "DROP TABLE $table_name;";
	$wpdb->query($sql);
	$table_name = $wpdb->prefix . "scubalogger_type_records";
	$sql = "DROP TABLE $table_name;";
	$wpdb->query($sql);
	*/
}

/**
 * Insert types if the table is empty.
 */
function scubalogger_insert_types() {
	global $wpdb;
	$table_name = $wpdb->prefix . "scubalogger_types";
	$sql = "SELECT typeid FROM " . $table_name;
	$results = $wpdb->get_col($sql);
	if (count($results) == 0) {
		$wpdb->insert($table_name, array('typeid' => 0, 'type' => "Boat"));
		$wpdb->insert($table_name, array('typeid' => 1, 'type' => "Shore"));
		$wpdb->insert($table_name, array('typeid' => 2, 'type' => "Drift"));
		$wpdb->insert($table_name, array('typeid' => 3, 'type' => "Night"));
		$wpdb->insert($table_name, array('typeid' => 4, 'type' => "Cave"));
		$wpdb->insert($table_name, array('typeid' => 5, 'type' => "Wreck"));
		$wpdb->insert($table_name, array('typeid' => 6, 'type' => "Decompression"));
		$wpdb->insert($table_name, array('typeid' => 7, 'type' => "Nitrox"));
		$wpdb->insert($table_name, array('typeid' => 8, 'type' => "Freshwater"));
		$wpdb->insert($table_name, array('typeid' => 9, 'type' => "Dive Course"));
	}
}

/**
 * Useful during development: a function to add a bunch of test entries to the log.
 */
function scubalogger_insert_test_dives() {
	global $wpdb;
	$table_name = $wpdb->prefix . "scubalogger";
	$table_name2 = $wpdb->prefix . "scubalogger_type_records";
	for ($i = 1; $i <= 9; $i++) {
		$wpdb->insert($table_name,
			array(
				'divenumber' => $i,
				'divedate' => "2014-01-$i",
				'sitename' => "testsite",
				'location' => "Sydney",
				'objective' => "tourism",
				'timedown' => "13:$i:00",
				'maxdepth' => 10.0 * $i,
				'avdepth' => 5.0 * $i,
				'divetime' => 5 * $i,
				'watertemp' => $i + 0.23,
				'airtemp' => $i + 6.82,
				'weather' => "sunny",
				'seaconditions' => "rough",
				'visibility' => $i * 3,
				'buddy' => testbuddy
				)
			);
		$wpdb->insert($table_name2,array('divenumber' => $i, 'typeid' => $i % 9));
		$wpdb->insert($table_name2,array('divenumber' => $i, 'typeid' => ($i+2) % 9));
	}
}
?>

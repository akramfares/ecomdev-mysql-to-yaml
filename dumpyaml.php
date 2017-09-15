#!/usr/bin/env php
<?php
/* ========================================================
 * File           : dumpyaml.php
 * Date           : July 15, 2010, Edited December 5, 2014
 * Author         : Jonathan Franzone, Edited by: Ben Robie and Akram Fares
 * Author Website : Original Script: http://www.franzone.com/2010/07/16/dump-a-mysql-database-to-yaml-using-php/
 *
 * Script to generate YAML (http://www.yaml.org/) from
 * a MySQL database. Will generate YAML for either all
 * tables in the specified database, only the specified
 * tables in the specified database or only the given
 * SQL query.
 *
 * Edited to create yaml in Ecomdev format for Magento
 *
 * ========================================================
 */

/* ========================================================
 * Configuration (read from command-line)
 * ========================================================
 */

$DBHOST = '';
$DBUSER = '';
$DBPASS = '';
$DBNAME = '';
$TABLES = array();
$DBQUERY = '';
$QUERYNAME = '';
$CONVERT_NAME = false;

/**
 * Prints the usage for this script
 */
function usage() {
	echo "
MySQLToYaml

  Script to generate YAML (http://www.yaml.org/) from
  a MySQL database. Will generate YAML for either all
  tables in the specified database, only the specified
  tables in the specified database or only the given
  SQL query.

Usage : dumpyaml.php [options] -d <database>
        dumpyaml.php [options] -d <database> -t table1,table2
        dumpyaml.php [options] -d <database> -q \"SELECT * FROM `mytable`\" -qn custom_name

  -h <host>       MySQL database host
  -u <user>       MySQL username
  -p <pass>       MySQL password
  -d <database>   Name of the MySQL database to dump
  -t <table(s)>   Comma delimited list of tables to dump from database
  -q \"<query>\"    SQL query to dump
  -qn <query name>  Name of the \"table\" when using the -q option
  -n              Convert table names to class names

";
}

/**
 * Reads/parses the command-line arguments
 */
function read_args() {

	global $argc, $argv, $DBHOST, $DBUSER, $DBPASS, $DBNAME, $TABLES, $DBQUERY, $QUERYNAME, $CONVERT_NAMES;

	while(!empty($argv)) {

		$arg = array_shift($argv);
		switch($arg) {
			case '-h':
				$DBHOST = array_shift($argv);
				break;
			case '-u':
				$DBUSER = array_shift($argv);
				break;
			case '-p':
				$DBPASS = array_shift($argv);
				break;
			case '-d':
				$DBNAME = array_shift($argv);
				break;
			case '-t':
				$TABLES = explode(",", array_shift($argv));
				break;
			case '-q':
				$DBQUERY = array_shift($argv);
				break;
			case '-qn':
				$QUERYNAME = array_shift($argv);
				break;
			case '-n':
				$CONVERT_NAMES = true;
				break;
		}
	}

	// Validate Command-Line Arguments
	$retVal = true;
	if (empty($DBHOST)) {
		echo "-h <host> is required\n";
		$retVal = false;
	}
	if (empty($DBUSER)) {
		echo "-u <user> is required\n";
		$retVal = false;
	}
	if (empty($DBPASS)) {
		echo "-p <pass> is required\n";
		$retVal = false;
	}
	if (empty($DBNAME)) {
		echo "-d <database> is required\n";
		$retVal = false;
	}
	if (!empty($TABLES) && !empty($DBQUERY)) {
		echo "-t <table(s)> and -q \"<query>\" are mutually exclusive options\n";
		$retVal = false;
	}
	if (!empty($DBQUERY) && empty($QUERYNAME)) {
		echo "-qn <query name> is required when using -q \"<query>\"\n";
		$retVal = false;
	}
	return $retVal;
}

/**
 * Takes a table name with underscores and converts it to something
 * like a class name (CAML case and remove the underscores).
 */
function convert_name_to_class($string) {

	// Return Value
	$retVal = '';

	$tokens = explode('_', $string);
	foreach($tokens as $token) {
		$retVal .= strtoupper(substr($token, 0, 1));
		if (strlen($token) > 1) {
			$retVal .= substr($token, 1);
		}
	}

	// Return Value
	return $retVal;
}

/**
 * Takes a SQL query and dumps YAML
 */
function sql_to_yaml($link, $sql, $table) {

	global $CONVERT_NAMES;

	// Run the query
	$result = mysqli_query($link, $sql);

	if ($result) {

		// Output the table name
		echo ($CONVERT_NAMES === true) ? convert_name_to_class($table) . ":\n" : "{$table}:\n";

		// Loop over the result set
		while ($row = mysqli_fetch_assoc($result)) {
			$first = true;
			// Output the row/collection indicator
			echo "  - ";

			// Loop over the columns output names and values
			foreach ($row as $key => $value) {

				// Do have any newlines or line feeds?
				$literalFlag = (strpos($value, "\r") !== FALSE || strpos($value, "\n") !== FALSE) ? "| " : "";

				// Output the key/value pair
				if ($first){
					echo "{$key}: {$literalFlag}{$value}\n";
					$first = false;
				} else {
					echo "      {$key}: {$literalFlag}{$value}\n";
				}
			}
		}
	}

	// Free the result resources
	mysqli_free_result($result);
}

/**
 * Retrieves the database tables from the database and puts them into the $TABLES array
 */
function get_database_tables($link) {

	global $TABLES;

	// Run the query
	$result = mysqli_query('SHOW TABLES', $link);

	if ($result) {

		while ($row = mysqli_fetch_row($result)) {
			$TABLES[] = $row[0];
		}
	}

	// Free the result resources
	mysqli_free_result($result);
}

/**
 * Main program loop
 */
if (read_args()) {

	// Open database connection
	$link = mysqli_connect($DBHOST, $DBUSER, $DBPASS, $DBNAME, 6603);
	if (!$link) {
		die("Could not connect to DB [{$DBUSER}:{$DBPASS}@{$DBHOST}]\n");
	}

	// Select the database
	if (mysqli_select_db($link, $DBNAME)) {

		// Query Mode
		if (!empty($DBQUERY)) {
			sql_to_yaml($link, $DBQUERY, $QUERYNAME);
		}

		else {

			// If the user has not specified tables then query for them
			if (empty($TABLES)) {
				get_database_tables($link);
			}

			// Loop over tables and output YAML
			foreach ($TABLES as $tbl) {
				sql_to_yaml($link, "SELECT * FROM {$tbl}", $tbl);
				echo "\n";
			}
		}
	}
	else {
		echo "Could not select database [{$DBNAME}] :: " . mysqli_error() . "\n";
	}

	// Close Database
	mysqli_close($link);
}
else
{
	usage();
}
?>

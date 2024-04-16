<?php
/**
 * Copyright (c) 1999-2024 Paul-Erik Törrönen, poltsi@poltsi.fi
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation version 2 and provided that the above
 *  copyright and permission notice is included with all distributed
 *  copies of this or derived software.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 */

/**
 * Log level settings. They are additive
 *
 */

const V_LOG_NONE    = 0; /* Do not print anything, in any case */
const V_LOG_ERROR   = 1; /* Print only errors */
const V_LOG_RUNTIME = 2; /* Print useful information, including warnings */
const V_LOG_DEBUG   = 3; /* Print everything */

$LOG_LEVEL_NAME = [V_LOG_NONE => 'NONE', V_LOG_ERROR => 'ERROR', V_LOG_RUNTIME => 'RUNTIME', V_LOG_DEBUG => 'DEBUG'];

/**
 * readEnvVars: reads the arguments from the post or request-string
 */
function readEnvVars(): array {
	global $ARGS;
	foreach ($_GET as $key => $val) {
		$ARGS[$key] = $val;
	}

	foreach ($_POST as $key => $val) {
		$ARGS[$key] = $val;
	}

	foreach ($_SERVER as $key => $val) {
		$ARGS[$key] = $val;
	}

	return ($ARGS);
}

/**
 * writeToLog: Writes the given message to log file if the log-level is met
 */
function writeToLog(int $level, string $message): bool {
	global $LOG_FILE;
	global $LOG_LEVEL;
	global $LOG_LEVEL_NAME;

	/* At the given log level, should we add the message? */
	if (($LOG_LEVEL - $level) < 0) {
		return (true);
	}

	if ((string)$LOG_FILE === '') {
		error_log('Vempain log file not defined');
		return (false);
	}

	$fp = fopen($LOG_FILE, 'a');

	if (!$fp) {
		error_log('Failed to open vempain log file at: ' . $LOG_FILE);
		return (false);
	}

	// Get the call stack, the first entry should be the calling function
	$call_stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

	$location = basename($call_stack[0]['file']) . '(' . $call_stack[0]['line'] . '):' .
	            (array_key_exists('class', $call_stack[0]) &&
	             strlen($call_stack[0]['class']) ? $call_stack[0]['class'] . ':' : '') .
	            (array_key_exists(1, $call_stack) ? $call_stack[1]['function'] : 'main');

	$ux_time   = time();
	$dt_time   = date('Y.m.d H:i:s', $ux_time);
	$dt_string = microtime(true) . ":" . $dt_time;

	if (!fwrite($fp, $dt_string . " " . $LOG_LEVEL_NAME[$level] . ': ' . $location . ': ' . $message . "\n")) {
		error_log('Unable to write to vempain file: ' . $LOG_FILE);
	}

	fclose($fp);

	return (true);
}

/**
 * connectDB: Creates a db-connection, sets the charset and places it
 * into the global DBHANDLE-array
 */
function connectDB(string $handle, string $dbhost, int $dbport, string $dbase, string $dbuser, string $dbpassword, string $charset): bool {
	global $DB_HANDLE;
	$DB_HANDLE[$handle] = mysqli_connect($dbhost, $dbuser, $dbpassword, $dbase, $dbport);

	if (!$DB_HANDLE[$handle]) {
		return (false);
	}

	if (!mysqli_set_charset($DB_HANDLE[$handle], $charset)) {
		return (false);
	}

	return (true);
}

/**
 * Converts the string to a DB-friendly format
 */
function convertDBString(string $req_string): string {
	writeToLog(V_LOG_DEBUG, "Called.");

	$req_string = preg_replace("#\'#", "\\'", $req_string);

	return (preg_replace('#"#', "\\\"", $req_string));
}

/**
 * Fetch the page by the given path
 */

function fetchPage(string $handle = '', string $page_path = ''): array|bool {
	global $DB_HANDLE;
	$sqlString = 'SELECT p.id, p.body, p.cache, p.indexlist, p.`path`, p.secure, p.header, p.title, p.creator, p.created, p.modifier, p.modified, p.published  
				  FROM   page p
				  WHERE  BINARY path = ?';

	// Prepare the statement
	$stmt = mysqli_prepare($DB_HANDLE[$handle], $sqlString);

	if ($stmt) {
		mysqli_stmt_bind_param($stmt, 's', $page_path);
		mysqli_stmt_execute($stmt);
		$result    = mysqli_stmt_get_result($stmt);
		$recordset = [];

		while ($row = mysqli_fetch_assoc($result)) {
			$recordset[] = $row;
		}

		mysqli_free_result($result);
		mysqli_stmt_close($stmt);

		if (count($recordset) == 0) {
			return false;
		}

		if (!array_key_exists('body', $recordset[0])) {
			return false;
		}

		return $recordset[0];
	} else {
		// Handle the error if the statement preparation fails
		return false;
	}
}

function cachePage(string $handle, int $id, string $cache) {
	global $DB_HANDLE;

	// Prepare the SQL statement
	$sqlString = 'UPDATE page SET cache = ? WHERE id = ?';
	$stmt      = mysqli_prepare($DB_HANDLE[$handle], $sqlString);

	if ($stmt) {
		$minifiedCache = minifyOutput($cache);
		// Bind parameters
		mysqli_stmt_bind_param($stmt, 'si', $minifiedCache, $id);

		// Execute the statement
		$success = mysqli_stmt_execute($stmt);

		// Close the statement
		mysqli_stmt_close($stmt);

		return $success;
	} else {
		// Handle the error if the statement preparation fails
		return false;
	}
}

#[NoReturn] function redirectToFrontendPage(): void {
	global $CONFIG;
	// Replace 'frontend-page-url' with the actual URL of your frontend page
	$frontendPageUrl = 'http://example.com/frontend-page-url';

	// Perform the redirect
	header('Location: ' . $CONFIG['site_url'] . '/');
	exit(); // Ensure that subsequent code is not executed after the redirect
}

/**
 *
 * getSQLArray: Executes the given sqlstring to the given db-handle and returns the
 *              resultset as a numeric 2d array.
 * @param string $handle
 * @param string $sqlString
 */
function getSQLArray(string $handle = '', string $sqlString = ''): bool|array {
	global $DB_HANDLE;

	if ($handle === '') {
		return (false);
	}

	if ($sqlString === '') {
		return (false);
	}

	$recordset = [];

	$db_result = mysqli_query($DB_HANDLE[$handle], $sqlString);

	if ($db_result) {
		$no_rows = mysqli_num_rows($db_result);

		for ($idx = 0; $idx < $no_rows; ++$idx) {
			$recordset[$idx] = mysqli_fetch_row($db_result);
		}

		mysqli_free_result($db_result);
		return ($recordset);
	}

	return (false);
}

/**
 * Execute an SQL query with parameters and return the result set as an array.
 *
 * @param mysqli $connection The database connection object.
 * @param string $sqlQuery The SQL query with placeholders for parameters.
 * @param array $params An array of parameters to bind to the prepared statement.
 * @return array The result set as an array.
 */
function getSQLArrayWithParams(mysqli $connection, string $sqlQuery, array $params): array {
	// Prepare the SQL statement
	$statement = $connection->prepare($sqlQuery);

	if (!$statement) {
		// Handle errors, return empty array for simplicity
		return [];
	}

	// Bind parameters to the prepared statement
	if (!empty($params)) {
		$types      = ''; // String containing parameter types ('s' for string, 'i' for integer, etc.)
		$bindParams = [];

		foreach ($params as $param) {
			// Determine the type of each parameter (assuming strings for simplicity)
			$types        .= 's'; // Assuming all parameters are strings
			$bindParams[] = &$param;
		}

		// Bind parameters dynamically
		$statement->bind_param($types, ...$bindParams);
	}

	// Execute the prepared statement
	$statement->execute();

	// Get the result set
	$result = $statement->get_result();

	// Fetch the result set as an array
	$resultSet = $result->fetch_all(MYSQLI_ASSOC);

	// Close the statement
	$statement->close();

	return $resultSet;
}

function minifyOutput(string $input) {
	// Go through the input line by line, splitting it by newline
	$lines = explode("\n", $input);
	// Then remove all white space characters from the beginning of each line
	$lines = array_map('trim', $lines);

	// Go through each line. If the line begins with a // then we remove it
	$lines = array_map(fn($line) => preg_replace('/^\/\/.*/', '', $line), $lines);

	// Finally, join the lines back together with a space between them
	$output = implode(' ', $lines);
	return $output;
}

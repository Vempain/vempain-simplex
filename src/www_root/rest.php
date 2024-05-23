<?php

$CONFIG    = [];
$DB_HANDLE = [];

include_once("../lib/config.php");
include_once("../lib/lib_vempain.php");

$LOG_LEVEL = $CONFIG['log_level'];
$LOG_FILE  = $CONFIG['log_file'];

if (connectDB($CONFIG['database'],
              $CONFIG['db_host'],
              $CONFIG['db_port'],
              $CONFIG['database'],
              $CONFIG['db_user'],
              $CONFIG['db_password'])) {
	$ARGS = readEnvVars();

	if ($ARGS['rest'] === 'gallery') {
		include_once('../lib/lib_gallery.php');
		if (is_numeric($ARGS['id'])) {
			getGalleryJson($ARGS['id']);
		} else {
			writeToLog(V_LOG_ERROR, 'REST: Invalid gallery id: ' . $ARGS['id']);
		}
	}
} else {
	writeToLog(V_LOG_DEBUG, 'REST: Could not connect to database, please inform the maintainer of this site');
}
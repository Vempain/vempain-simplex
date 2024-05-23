<?php
$PAGE_INFO = [];
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

	$requestUri = urldecode((string)$ARGS['REQUEST_URI']);

	$requestDir = preg_replace('|^' . $CONFIG['home_dir'] . '|u', '', $requestUri);

	$pagePath = str_replace($CONFIG['file_descriptor'], '', $requestDir);
	[$pagePath,] = explode('?', $pagePath, 2);

	if (preg_match("#/$#", $pagePath) || !$pagePath) {
		$pagePath .= 'index';
	}

	$pageEntry = fetchPage($CONFIG['database'], $pagePath);

	if ($pageEntry) {
		/* Save the page information in a global variable to be perused by the modules */
		$PAGE_INFO['body']      = $pageEntry['body'];
		$PAGE_INFO['cache']     = $pageEntry['cache'];
		$PAGE_INFO['created']   = $pageEntry['created'];
		$PAGE_INFO['creator']   = $pageEntry['creator'];
		$PAGE_INFO['header']    = $pageEntry['header'];
		$PAGE_INFO['id']        = $pageEntry['id'];
		$PAGE_INFO['modified']  = $pageEntry['modified'];
		$PAGE_INFO['modifier']  = $pageEntry['modifier'];
		$PAGE_INFO['path']      = $pagePath;
		$PAGE_INFO['published'] = $pageEntry['published'];
		$PAGE_INFO['secure']    = $pageEntry['secure'];
		$PAGE_INFO['title']     = $pageEntry['title'];

		// We use buffering to capture all output
		// If we have cached output, then we just print that, otherwise we will eval the body, push it out and store it to cache
		if ($PAGE_INFO['cache'] != null) {
			print($PAGE_INFO['cache']);
		} else {
			ob_start();
			$output = "";

			try {
				$evalRes = eval($PAGE_INFO['body']);
				$output  = ob_get_clean();
			} catch (Throwable $e) { // Catch any throwable, including errors
				$errorMessage = "Error occurred: " . $e->getMessage();
				// Log the error message
				writeToLog(V_LOG_DEBUG, '===================================================================');
				writeToLog(V_LOG_DEBUG, $errorMessage);
				writeToLog(V_LOG_DEBUG, '===================================================================');
				writeToLog(V_LOG_DEBUG, "\n" . $PAGE_INFO['body']);
				writeToLog(V_LOG_DEBUG, '===================================================================');
				// Print or handle the error message as needed
				writeToLog(V_LOG_DEBUG, $errorMessage);
				redirectToFrontendPage(); // Assuming this is a defined function
			}

			if ($evalRes !== null) {
				writeToLog(V_LOG_ERROR, '===================================================================');
				writeToLog(V_LOG_ERROR, $pageEntry);
				writeToLog(V_LOG_ERROR, '===================================================================');

				redirectToFrontendPage();
			} else {
				print($output);
				cachePage($CONFIG['database'], $PAGE_INFO['id'], $output);
			}
		}
	} else {
		writeToLog(V_LOG_ERROR, "Failed to find page for path: " . $pagePath);
	}
} else {
	writeToLog(V_LOG_ERROR, 'Could not connect to database, please inform the maintainer of this site');
}

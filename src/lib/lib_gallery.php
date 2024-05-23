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

include_once('lib_subject.php');
include_once('lib_coordinates.php');

function showGallery(int $galleryId = 0) {
	$templateFile = __DIR__ . '/template/gallery_template.html';

	if (!file_exists($templateFile)) {
		writeToLog(V_LOG_ERROR, 'Template file not found: ' . $templateFile);
		return '';
	}

	$templateContent = file_get_contents($templateFile);

	$output = str_replace('{$galleryId}', $galleryId, $templateContent);
	print $output;
}

function getGalleryJson(int $id) {
	$galleryData = getGalleryData($id);

    if ($galleryData === false) {
        header('Content-Type: application/json');
        echo json_encode([]);
        return;
    }

	for ($i = 0; $i < count($galleryData); $i++) {
		$data[] = [
			'src'    => $galleryData[$i]['path'],
			'width'  => $galleryData[$i]['width'],
			'height' => $galleryData[$i]['height'],
			'alt'    => ((
				array_key_exists('comment', $galleryData[$i])
				&& $galleryData[$i]['comment'] != null
				&& strlen(trim($galleryData[$i]['comment'])) > 0) ? $galleryData[$i]['comment'] : '')
		];
	}

	header('Content-Type: application/json');
	echo json_encode($data);
}

function getGalleryData(int $id) {
	global $CONFIG;
	global $DB_HANDLE;

	$sqlString = 'SELECT f.path, f.width, f.height, f.comment FROM file f, gallery_file gf, gallery g WHERE g.gallery_id = $1 AND gf.gallery_id = g.id AND gf.file_id = f.id ORDER BY gf.sort_order ASC';
	$result      = pg_query_params($DB_HANDLE[$CONFIG['database']], $sqlString, [$id]);

	if ($result) {
		$recordset = [];

		while ($row = pg_fetch_assoc($result)) {
			$recordset[] = $row;
		}

		pg_free_result($result);

		if (count($recordset) == 0) {
			return false;
		}

		if (!array_key_exists('path', $recordset[0])) {
			return false;
		}

		return $recordset;
	} else {
		// Handle the error if the statement preparation fails
		return false;
	}
}

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
 * getSubjectByFileID takes an array of one or several file_id and returns an
 *                    array of subject names
 */
function getSubjectByFileID(array $fileIDArr = []): bool|array {
	global $CONFIG;

	writeToLog(V_LOG_DEBUG, 'Called');

	if (is_array($fileIDArr) && count($fileIDArr)) {
		$sqlString = 'SELECT DISTINCT s.subject ' .
		             'FROM file_subject fs, subject s ' .
		             'WHERE fs.subject_id = s.subject_id AND fs.file_id IN (' . implode(',', $fileIDArr) . ')';
		$rs        = getSQLArray($CONFIG['database'], $sqlString);

		if ((is_countable($rs) ? count($rs) : 0) === 0) {
			writeToLog(V_LOG_DEBUG, 'No subjects found for the files: ' . implode(', ', $fileIDArr));
			return (false);
		}

		$arr = [];

		foreach ($rs as $r) {
			$arr[] = $r[0];
		}

		return ($arr);
	}

	writeToLog(V_LOG_DEBUG, 'Called without an array as argument');
	return (false);
}

/**
 * subjectSearch receives a list (1-n) of subjects, and returns a list if file_id
 * which have all the given subjects. There are some magic involved since 1
 * subject word handling requires that is treated as a 2 word search with only
 * one subject
 */
function getFileIdBySubject(array|string $subjects): array {
	writeToLog(V_LOG_DEBUG, 'Called');
	writeToLog(V_LOG_DEBUG, 'Subjects are: ' . implode(' ', $subjects));

	global $CONFIG;

	$subjectList = [];
	$fileIdList  = [];
	$subCount    = 2;

	if (is_array($subjects)) {
		$subjectList = $subjects;
		$subCount    = count($subjects);

		/* Special handling when we have only one subject, treat it as if
		 * we had 2 */
		if ($subCount < 2) {
			$subCount = 2;
		}
	} else {
		/* If, however, the argument is not an array, then insert it into our array */
		$subjectList[] = $subjects;
	}

	/* Our collector arrays which are used to create certain parts of the request */
	$joinTables     = [];
	$joinJoinTables = [];
	$joinJoinOn     = [];
	$joinWhere      = [];
	$bindParams     = [];

	for ($idx = 0; $idx < $subCount; ++$idx) {
		writeToLog(V_LOG_DEBUG, 'Round ' . $idx);
		$joinTables[] = 'subject s' . $idx;

		// Skip the first round on these
		if ($idx !== 0) {
			$joinJoinTables[] = 'file_subject fs' . $idx;
			$joinJoinOn[]     = 'fs0.file_id = fs' . $idx . '.file_id';
		} else {
			writeToLog(V_LOG_DEBUG, 'Skipping on ' . $idx);
		}

		if (array_key_exists($idx, $subjectList)) {
			$joinWhere[] = 's' . $idx . '.subject = ?';
			$bindParams[] = $subjectList[$idx];
		}

		$joinWhere[] = 's' . $idx . '.subject_id = fs' . $idx . '.subject_id';
	}

	$sqlString = 'SELECT ' .
	             'DISTINCT ' .
	             'fc.id AS fcfid ' .
	             'FROM ' .
	             'file fc, ' .
	             implode(', ', $joinTables) . ', ' .
	             'file_subject fs0 LEFT JOIN ( ' .
	             implode(', ', $joinJoinTables) . ' ) ON ' .
	             implode(' AND ', $joinJoinOn) . ' ' .
	             'WHERE ' .
	             implode(' AND ', $joinWhere) . ' ' .
	             'AND fc.id = fs0.file_id ' .
	             'ORDER BY fcfid ASC';

	$rs = getSQLArrayWithParams($CONFIG['database'], $sqlString, $bindParams);

	/* Deflate the rs to an array */
	foreach ($rs as $r) {
		$fileIdList[] = $r[0];
	}

	writeToLog(V_LOG_DEBUG, 'Got ' . count($fileIdList) . ' rows');

	return $fileIdList;
}

/**
 * getFileIdBySubjectId: Takes an array of subject indexes and returns an array
 *                       of file id connected to the subjects
 */
function getFileIdBySubjectId(array $subject): array {
	global $CONFIG;
	$arr = [];

	writeToLog(V_LOG_DEBUG, 'Called');

	/* Do the search */
	$sqlString = 'SELECT DISTINCT file_id FROM file_subject WHERE subject_id IN ( ' . implode(',', $subject) . ' )';

	$rs = getSQLArray($CONFIG['database'], $sqlString);
	foreach ($rs as $r) {
		$arr[] = (int)$r[0];
	}

	return ($arr);
}

/**
 * getSubjectRequest: Takes an array of subject indexes and returns the request
 *                    request-string snippet for a URL
 */
function getSubjectRequest(array|null $subject): string {
	writeToLog(V_LOG_DEBUG, 'Called');

	if ($subject == null) {
		return "";
	}

	$buf = [];

	foreach ($subject as $val) {
		$buf[] = 'subject_id[]=' . $val;
	}

	return (implode('&', $buf));
}

/**
 * getSubjectId: Takes a single string or an array of subject strings and returns the
 *               corresponding id values in an array
 */
function getSubjectId(bool|array|string $subject = false): bool|array {
	global $CONFIG;

	writeToLog(V_LOG_DEBUG, 'Called with subject: ' . print_r($subject, true));

	if (is_array($subject)) {
		/* First neutralize them */
		foreach ($subject as $key => $val) {
			$subject[$key] = convertDBString($val);
		}

		$sqlString = 'SELECT DISTINCT subject_id FROM subject WHERE subject IN ( "' . implode('", "', $subject) . '" )';
	} elseif (is_string($subject) && trim($subject) !== '') {
		$sqlString = 'SELECT DISTINCT subject_id FROM subject WHERE subject = "' . convertDBString($subject) . '"';
	} else {
		writeToLog(V_LOG_ERROR, 'Received something that is not a string or array: "' . $subject . '"');
		return (false);
	}

	$rs = getSQLArray($CONFIG['database'], $sqlString);

	if ((is_countable($rs) ? count($rs) : 0) > 0) {
		$arr = [];

		foreach ($rs as $r) {
			$arr[] = $r[0];
		}

		return ($arr);
	}

	return (false);
}


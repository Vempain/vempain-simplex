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
 * get_nearest_timezone: Get the nearest timezone based on the coordinates and country
 *
 * Copied from: http://stackoverflow.com/questions/3126878/get-php-timezone-name-from-latitude-and-longitude
 * @param double $cur_lat
 * @param double $cur_long
 * @param string $country_code
 * @return mixed|string
 */

function get_nearest_timezone(float $cur_lat, float $cur_long, string $country_code = ''): mixed {
	writeToLog(V_LOG_DEBUG, 'Called');
	$timezone_ids = ($country_code !== '' && $country_code !== '0') ? DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country_code)
		: DateTimeZone::listIdentifiers();

	if ($timezone_ids &&
	    is_array($timezone_ids) &&
	    isset($timezone_ids[0])) {
		$time_zone   = '';
		$tz_distance = 0;

		//only one identifier?
		if (count($timezone_ids) == 1) {
			$time_zone = $timezone_ids[0];
		} else {
			foreach ($timezone_ids as $timezone_id) {
				$timezone = new DateTimeZone($timezone_id);
				$location = $timezone->getLocation();
				$tz_lat   = $location['latitude'];
				$tz_long  = $location['longitude'];

				$theta    = $cur_long - $tz_long;
				$distance = (sin(deg2rad($cur_lat)) * sin(deg2rad($tz_lat)))
				            + (cos(deg2rad($cur_lat)) * cos(deg2rad($tz_lat)) * cos(deg2rad($theta)));
				$distance = acos($distance);
				$distance = abs(rad2deg($distance));

				if (!$time_zone || $tz_distance > $distance) {
					$time_zone   = $timezone_id;
					$tz_distance = $distance;
				}
			}
		}

		return ($time_zone);
	}

	return 'unknown';
}

/**
 * convertToDecGPS: Takes in degree, minutes and seconds-format coordinates and returns
 *                  the constituents in numeric format as well as calculated
 *                  decimal format coordinates
 */
function convertToDecGPS(bool|string $lat = FALSE, bool|string $long = FALSE): array|bool {
	writeToLog(V_LOG_DEBUG, 'Called');

	if ($lat && $long) {
		/* First trim the values since they may have whitespaces */
		$lat    = trim($lat);
		$long   = trim($long);
		$tmpGPS = ['lat' => ['deg' => 0, 'min' => 0, 'sec' => 0, 'dec' => 0], 'long' => ['deg' => 0, 'min' => 0, 'sec' => 0, 'dec' => 0]];

		/* format is "68 deg 30' 7.01" N" and "16 deg 5' 43.04" E" */
		preg_match('#^(\d+) deg .*#', $lat, $tmpGPS['lat']['deg']);
		preg_match("#\d+ deg (\d+)'.*#", $lat, $tmpGPS['lat']['min']);
		preg_match("/\d+ deg \d+' (.*)\"/", $lat, $tmpGPS['lat']['sec']);
		/* Flatten the values, which are arrays when they come out of preg_match */
		$tmpGPS['lat']['deg'] = $tmpGPS['lat']['deg'][1];
		$tmpGPS['lat']['min'] = $tmpGPS['lat']['min'][1];
		$tmpGPS['lat']['sec'] = $tmpGPS['lat']['sec'][1];

		$tmpGPS['lat']['dec'] = $tmpGPS['lat']['deg'] +
		                        ((($tmpGPS['lat']['min'] * 60) +
		                          ($tmpGPS['lat']['sec'])) / 3600);
		/* If we are on the southside, then the latitude should be negative */
		if (str_ends_with($lat, 'S')) {
			$tmpGPS['lat']['dec'] *= -1;
		}

		preg_match('#^(\d+) deg .*#', $long, $tmpGPS['long']['deg']);
		preg_match("#\d+ deg (\d+)'.*#", $long, $tmpGPS['long']['min']);
		preg_match("/\d+ deg \d+' (.*)\"/", $long, $tmpGPS['long']['sec']);

		$tmpGPS['long']['deg'] = $tmpGPS['long']['deg'][1];
		$tmpGPS['long']['min'] = $tmpGPS['long']['min'][1];
		$tmpGPS['long']['sec'] = $tmpGPS['long']['sec'][1];

		$tmpGPS['long']['dec'] = $tmpGPS['long']['deg'] +
		                         ((($tmpGPS['long']['min'] * 60) +
		                           ($tmpGPS['long']['sec'])) / 3600);

		/* If we are on the westside, then the longitude should be negative */
		if (str_ends_with($long, 'W')) {
			$tmpGPS['long']['dec'] *= -1;
		}

		return ($tmpGPS);
	}

	writeToLog(V_LOG_RUNTIME, 'Failed with values (lat:long): (' . $lat . ':' . $long . ')');
	return (false);
}

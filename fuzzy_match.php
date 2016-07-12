<?php
/**
 * Script for Fuzzy Matching address between two tables
 *
 * Script uses "levenshtein" function to get this done
 *
 * PHP versions 4 and 5
 *
 * Amit Yadav :  http://amityadav.name
 * Copyright 2010, Amit Yadav
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 */

set_time_limit(0);

$db = mysql_connect('localhost', 'root', '');
$srmSiteRecords = array();
if($db){
	if(mysql_select_db('rnd')){
		
		$resultSet = mysql_query('SELECT id, address1, address2, city, state, country, zipcode FROM srm_sites');
		
		//ADD ALL THE SITE ADDRESS TO AN ARRAY SO THAT WE NEED NOT HIT THE DATABASE EVERY TIME
		while ($rowSite = mysql_fetch_assoc($resultSet)) {
			$siteStr = '';
			$siteStr .= (trim($rowSite['address1']) != '')? trim(addslashes($rowSite['address1'])) . ' ' : '';
			$siteStr .= (trim($rowSite['address2']) != '')? trim(addslashes($rowSite['address2'])) . ' ' : '';
			$siteStr .= (trim($rowSite['city']) != '')? trim(addslashes($rowSite['city'])) . ' ' : '';
			$siteStr .= (trim($rowSite['state']) != '')? trim(addslashes($rowSite['state'])) . ' ': '';
			$siteStr .= (trim($rowSite['country']) != '')? trim(addslashes($rowSite['country'])) . ' ' : '';
			$siteStr .= (trim($rowSite['zipcode']) != '')? trim(addslashes($rowSite['zipcode'])) : '';
			$srmSiteRecords[$rowSite['id']] = $siteStr;
		}

		//GET ALL THE STUDENTS RECORDS THAT DO NOT HAVE A MATCHING SITE
		$resultSet = mysql_query('SELECT id, division, department, address1, address2, city, state, country, zipcode FROM srm_students WHERE srm_site_id IS NULL');
		
		while ($rowStudent = mysql_fetch_assoc($resultSet)) {
			
				// INPUT STUDENT ADDRESS TO MATCH
				$srmStudentRec = '';
				$srmStudentRec .= (trim($rowStudent['address1']) != '')? trim(addslashes($rowStudent['address1'])) . ' ' : '';
				$srmStudentRec .= (trim($rowStudent['address2']) != '')? trim(addslashes($rowStudent['address2'])) . ' ' : '';
				$srmStudentRec .= (trim($rowStudent['city']) != '')? trim(addslashes($rowStudent['city'])) . ' ' : '';
				$srmStudentRec .= (trim($rowStudent['state']) != '')? trim(addslashes($rowStudent['state'])) . ' ': '';
				$srmStudentRec .= (trim($rowStudent['country']) != '')? trim(addslashes($rowStudent['country'])) . ' ' : '';
				$srmStudentRec .= (trim($rowStudent['zipcode']) != '')? trim(addslashes($rowStudent['zipcode'])) : '';
				
				//MATCH THE STUDENT ADDRESS TO THE SITES ADDRESS ARRAY CREATED AT THE START OF THE FILE
				$keyMatched = get_matching_address($srmStudentRec, $srmSiteRecords, $rowStudent['id']);

				if(is_numeric($keyMatched)){
					mysql_query('UPDATE srm_students SET srm_site_id = ' . $keyMatched . ' WHERE id = ' . $rowStudent['id'] . ' AND srm_site_id IS NULL',$db);
				}elseif($keyMatched == true){
					mysql_query("INSERT INTO srm_sites SET division=" . trim(addslashes($rowStudent['division'])) . ", department=" . trim(addslashes($rowStudent['department'])) . ",address1=" . trim(addslashes($rowStudent['address1'])) . ", address2=" . trim(addslashes($rowStudent['address2'])) . ", city=" . trim(addslashes($rowStudent['city'])) . ", state=" . trim(addslashes($rowStudent['state'])) . ", country=" . trim(addslashes($rowStudent['country'])) . ", zipcode=" . trim(addslashes($rowStudent['zipcode'])));
					
					
					mysql_query('UPDATE srm_students SET srm_site_id = ' . @mysql_insert_id() . ' WHERE id = ' . $rowStudent['id'] . ' AND srm_site_id IS NULL',$db);
				}else{
					mysql_query('UPDATE srm_students SET srm_site_id = 0 WHERE id = ' . $rowStudent['id'] . ' AND srm_site_id IS NULL',$db);
				}
			}
			
		}else{
		echo 'No database with the name specified found';
	}
}else{
	echo "Cannot establish connection to localhost";
}




	function get_matching_address($srmStudentRec, $srmSiteRecords, $srmStudentRecId){
		//IF THE STUDENT ADDRESS IS NULL OR N/A THEN RETURN,
		//IN THIS CASE A DYMMY SITE IS ASSIGNED TO THE STUDENT
		if(trim($srmStudentRec) == '' || $srmStudentRec == 'N/A'){
			return false;
		}

		// NO SHORTEST DISTANCE FOUND, YET
		$shortest = -1;
		$keyMatched = 0;

		// loop through sites addresses to find the matching address
		foreach ($srmSiteRecords as $srmSiteId => $srmSiteRecord) {

			// CALCULATE THE DISTANCE BETWEEN THE STUDENT ADDRESS,
			// AND THE SITES ADDRESS
			$lev = levenshtein($srmStudentRec, $srmSiteRecord);

			// CHECK FOR AN EXACT MATCH
			if ($lev == 0) {

				// CLOSEST ADDRESS IS THIS ONE (EXACT MATCH)
				$closest = $srmSiteRecord;
				$shortest = 0;
				$keyMatched = $srmSiteId;

				// BREAK OUT OF THE LOOP; WE'VE FOUND AN EXACT MATCH
				break;
			}

			// IF THIS DISTANCE IS LESS THAN THE NEXT FOUND SHORTEST
			// DISTANCE, OR IF A NEXT SHORTEST WORD HAS NOT YET BEEN FOUND
			if ($lev <= $shortest || $shortest < 0) {
				// SET THE CLOSEST MATCH, SHORTEST DISTANCE, AND THE MATCHED SITE ID
				$closest  = $srmSiteRecord;
				$shortest = $lev;
				$keyMatched = $srmSiteId;
			}
		}

		if($keyMatched){
			echo "Input word: $srmStudentRec ==> ";
			if ($shortest == 0) {
				echo " $closest, $keyMatched\n\n";
				return $keyMatched;
			} else {
				echo "  $closest?, $keyMatched\n\n";
				return $keyMatched;
			}
		}else
			return true;
	}
?>
<?php
	/*
	 * OWNER: Amit Yadav
	 * Dated: 13th April 2010
	 * This script matches the addresses from two different database tables
	 * This script uses the "levenshtein" algorithm to calculate the difference 
	 * between the two strings, the lesser the distance the more exact the match is
	 * if the disatance calculated b/w the two strings is zero then there is an exact match
	 *
	 * The script needs the DB connection parameter to be passed through the command lines
	 */
	set_time_limit(0);
	
	/*$host = 'localhost';
	$userName = 'root';
	$pass = '';
	$port = '3306';
	$host = ($port)? 'localhost:' . $port : 'localhost';
	$database = 'rnd';*/

	$userName = $argv[2];
	$pass = (strtolower($argv[3]) == 'nil')? '' : $argv[3];
	$port = (strtolower($argv[4]) == 'nil')? '' : $argv[4];
	$host = ($port != 'nil')? $argv[1] . ':' . $port : $argv[1];
	$database = $argv[5];

	$db = mysql_connect($host, $userName, $pass);
	$srmSiteRecords = array();
	$dummyCompanyId =''; 
	$dummySiteId = '';

	if($db){
		if(mysql_select_db($database)){
			// CHECK IF THE DUMMY COMPANY EXISTS
			echo "\r\nSELECT DUMMY COMPANY => SELECT id FROM srm_companies WHERE customer_name = 'Unknown' AND customer_number = 0";
			$resultSet = mysql_query("SELECT id FROM srm_companies WHERE customer_name = 'Unknown' AND customer_number = 0");
			
			// IF THE DUMMY COMPANY DOES NOT EXISTS THEN INSERT THE DUMMY COMPANY
			if(!mysql_num_rows($resultSet)){
				echo "\r\nINSERTING DUMMY ==> COMPANY INSERT INTO srm_companies SET customer_name='Unknown', updated_at=now()";
				mysql_query("INSERT INTO srm_companies SET customer_name='Unknown', updated_at=now()");
				$dummyCompanyId = @mysql_insert_id();
			}else{
				$temp = mysql_fetch_assoc($resultSet);
				$dummyCompanyId = $temp['id'];
			}
			
			//CHECK IF THE DUMMY SITE IS THERE IN THE DB
			echo "\r\nSELECTING DUMMY SITE ==> SELECT id FROM srm_sites WHERE address1 = 'Unknown' AND siebel_site_id = 0 and customer_number = 0";
			$resultSet = mysql_query("SELECT id FROM srm_sites WHERE address1 = 'Unknown' AND siebel_site_id = 0 AND customer_number = 0 AND srm_company_id = 0");
			
			// IF DUMMY SITE NOT FOUND THEN INSERT A DUMMY SITE IN THE DATABASE
			if(!mysql_num_rows($resultSet)){
				echo "\r\nINSERTING DUMMY SITE ==> INSERT INTO srm_sites SET srm_company_id =0, siebel_site_id = 0, customer_number = 0, address1='Unknown', updated_at=now()";

				mysql_query("INSERT INTO srm_sites SET srm_company_id =0, siebel_site_id = 0, customer_number = 0, address1='Unknown', updated_at=now()");
				$dummySiteId = @mysql_insert_id();
			}else{
				$temp = mysql_fetch_assoc($resultSet);
				$dummySiteId = $temp['id'];
			}
			

			$srmSiteRecords = array();
			echo "\r\n\r\n SELECTING THE SITES == > SELECT id, address1, address2, city FROM srm_sites ORDER BY siebel_site_id ASC";
			$resultSet = mysql_query('SELECT id, address1, address2, city FROM srm_sites ORDER BY siebel_site_id ASC');
				
			// ADD ALL THE SITE ADDRESS TO AN ARRAY SO THAT WE NEED NOT HIT THE DATABASE EVERY TIME
			while ($rowSite = mysql_fetch_assoc($resultSet)) {
				$siteStr = '';
				$siteStr .= (trim($rowSite['address1']) != '')? trim(addslashes($rowSite['address1'])) . ' ' : '';
				$siteStr .= (trim($rowSite['address2']) != '')? trim(addslashes($rowSite['address2'])) . ' ': '';
				$siteStr .= (trim($rowSite['city']) != '')? trim(addslashes($rowSite['city'])) . ' ': '';
				$srmSiteRecords[$rowSite['id']] = $siteStr;
			}

						
			//GET ALL THE STUDENTS RECORDS THAT DO NOT HAVE A MATCHING SITE
			$resultSet = mysql_query('SELECT id, customer_number, siebel_site_id, division, department, address1, address2, city, state, country, zipcode FROM srm_students WHERE address_matched =0');
			
			while ($rowStudent = mysql_fetch_assoc($resultSet)) {
					$srmStudentRec = '';
					print_r($rowStudent);
					
					/***********
					 * CASE - 1
					 ***********/
					if(!is_null($rowStudent['siebel_site_id'])) 
					{
								echo "\r\n\r\n <<<<< CASE 1 STARTS >>>>>> @ student_id = " . $rowStudent['id'];
								address_match_case_1($rowStudent['id'], $rowStudent['siebel_site_id'], $rowStudent, $rowStudent['customer_number']);
								echo "\r\n\r\n <<<<< CASE 1 ENDS >>>>>> @ student_id = " . $rowStudent['id'];
					}
					/***********
					 * CASE - 2
					 ***********/
					elseif(is_null($rowStudent['siebel_site_id']) &&
							!is_null($rowStudent['customer_number']) &&
							(!is_null($rowStudent['address1']) ||
							!is_null($rowStudent['address2']))
						    )
					{
								$srmStudentRec .= (trim($rowStudent['division']) != '')? trim(addslashes($rowStudent['division'])) . ' ' : '';
								$srmStudentRec .= (trim($rowStudent['department']) != '')? trim(addslashes($rowStudent['department'])) . ' ': '';
								$srmStudentRec .= (trim($rowStudent['address1']) != '')? trim(addslashes($rowStudent['address1'])) . ' ' : '';
								$srmStudentRec .= (trim($rowStudent['address2']) != '')? trim(addslashes($rowStudent['address2'])) . ' ' : '';
								echo "\r\n\r\n <<<<< CASE 2 STARTS >>>>>> @ student_id = " . $rowStudent['id'];
								address_match_case_2($rowStudent['id'], $rowStudent['customer_number'], $srmStudentRec, $rowStudent);
								echo "\r\n\r\n <<<<< CASE 2 ENDS >>>>>> @ student_id = " . $rowStudent['id'];
					}
					
					/***********
					 * CASE - 3
					 ***********/
					elseif(is_null($rowStudent['siebel_site_id']) &&
							is_null($rowStudent['customer_number']) &&
							(!is_null($rowStudent['address1']) ||
							!is_null($rowStudent['address2']))
						    )
					{
								$srmStudentRec .= (trim($rowStudent['address1']) != '')? trim(addslashes($rowStudent['address1'])) . ' ' : '';
								$srmStudentRec .= (trim($rowStudent['address2']) != '')? trim(addslashes($rowStudent['address2'])) . ' ' : '';
								$srmStudentRec .= (trim($rowStudent['city']) != '')? trim(addslashes($rowStudent['city'])) . ' ' : '';
								
								echo "\r\n\r\n <<<<< CASE 3 STARTS >>>>>> @ student_id = " . $rowStudent['id'];
								address_match_case_3($rowStudent['id'], $srmStudentRec, $rowStudent, $rowStudent['customer_number']);
								echo "\r\n\r\n <<<<< CASE 3 ENDS >>>>>> @ student_id = " . $rowStudent['id'];
					}
					
					/***********
					 * CASE - 4
					 ***********/
					elseif(is_null($rowStudent['siebel_site_id']) &&
							!is_null($rowStudent['customer_number']) &&
							(is_null($rowStudent['address1']) ||
							is_null($rowStudent['address2']))
						    )
					{
								echo "\r\n\r\n <<<<< CASE 4 STARTS >>>>>> @ student_id = " . $rowStudent['id'];
								address_match_case_4($rowStudent['id'], $rowStudent['customer_number']);
								echo "\r\n\r\n <<<<< CASE 4 ENDS >>>>>> @ student_id = " . $rowStudent['id'];
					}
					
					/***********
					 * CASE - 5
					 ***********/
					elseif(is_null($rowStudent['siebel_site_id']) &&
							is_null($rowStudent['customer_number']) &&
							(is_null($rowStudent['address1']) ||
							is_null($rowStudent['address2']))
						    ){
								echo "\r\n\r\n <<<<< CASE 5 STARTS >>>>>> @ student_id = " . $rowStudent['id'];
								mysql_query("UPDATE srm_students SET address_matched =1, srm_site_id = $dummySiteId WHERE id = " . $rowStudent['id'], $db);
								echo "\r\n\r\n <<<<< CASE 5 ENDS >>>>>> @ student_id = " . $rowStudent['id'];
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
		//IN THIS CASE A DUMMY SITE IS ASSIGNED TO THE STUDENT
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
				echo "\n" . $keyMatched = $srmSiteId;

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

		if($shortest == 0 /*$keyMatched*/){ // A MATCH HAS BEEN FOUND FOR THE STUDENT ADDRESS
			echo "Input word: $srmStudentRec ==> ";
			/*if ($shortest == 0) {*/
				echo " $closest, $keyMatched\n\n";
				return $keyMatched;
			/*} else {
				echo "  $closest?, $keyMatched\n\n";
				return $keyMatched;
			}*/
		}else
			// NO MATCH FOUND FOR THE STUDENT ADDRESS IN THIS CASE A NEW SITE ENTRY WILL BE CREATED
			// WITH THE ADDRESS AS OF THE STUDENT AND ASSIGNED TO THE STUDENT, THE COMPANY WILL BE DUMMY 
			// IN THIS CASE
			return -1;
	}







	//Case 1 - SRM student is assigned to Oracle site
	function address_match_case_1($studentId, $siebelSiteId, $rowStudent, $customerNumber) {
		global $db, $dummyCompanyId, $dummySiteId, $srmSiteRecords;
		
		//SELECT THE SITE WITH THE GIVEN siebel_site_id
		echo "\r\n SELECT THE SITE RECORD ==> SELECT id FROM srm_sites WHERE siebel_site_id = $siebelSiteId ORDER BY siebel_site_id ASC";
		$resultSet = mysql_query("SELECT id FROM srm_sites WHERE siebel_site_id = $siebelSiteId ORDER BY siebel_site_id ASC");
		
		if(isset($resultSet) && mysql_num_rows($resultSet)){
			$rowSite = mysql_fetch_assoc($resultSet);

			//UPDATE THE STUDENT RECORD WITH THE SITE ID
			echo "UPDATE STUDENT RECORD ==> UPDATE srm_students SET srm_site_id = " . $rowSite['id'] . " WHERE id = $studentId";
			mysql_query("UPDATE srm_students SET address_matched =1, srm_site_id = " . $rowSite['id'] . " WHERE id = $studentId");		
		}else{
			$custNum = $dummyCompanyId . "', customer_number='" . $customerNumber;
			echo "\r\n ADDRESS NOT MATCHED = > INSERT INTO srm_sites SET srm_company_id='" . $custNum ."', division='" . trim(addslashes($rowStudent['division'])) . "', department='" . trim(addslashes($rowStudent['department'])) . "',address1='" . trim(addslashes($rowStudent['address1'])) . "', address2='" . trim(addslashes($rowStudent['address2'])) . "', city='" . trim(addslashes($rowStudent['city'])) . "', state='" . trim(addslashes($rowStudent['state'])) . "', country='" . trim(addslashes($rowStudent['country'])) . "', zipcode='" . trim(addslashes($rowStudent['zipcode'])) . "', updated_at=now()";
			
			if(mysql_query("INSERT INTO srm_sites SET srm_company_id='" . $custNum ."', division='" . trim(addslashes($rowStudent['division'])) . "', department='" . trim(addslashes($rowStudent['department'])) . "',address1='" . trim(addslashes($rowStudent['address1'])) . "', address2='" . trim(addslashes($rowStudent['address2'])) . "', city='" . trim(addslashes($rowStudent['city'])) . "', state='" . trim(addslashes($rowStudent['state'])) . "', country='" . trim(addslashes($rowStudent['country'])) . "', zipcode='" . trim(addslashes($rowStudent['zipcode'])) . "', updated_at=now()")){
				echo "\r\nDummy Site Insert Successful";
				$tmpSiteId = @mysql_insert_id();


				//ADD THE NEW SITE TO THE SITE ARRAY FOR CASE - 3
				$siteStr = '';
				$siteStr .= (trim($rowStudent['address1']) != '')? trim(addslashes($rowStudent['address1'])) . ' ' : '';
				$siteStr .= (trim($rowStudent['address2']) != '')? trim(addslashes($rowStudent['address2'])) . ' ': '';
				$siteStr .= (trim($rowStudent['city']) != '')? trim(addslashes($rowStudent['city'])) . ' ': '';
				$srmSiteRecords[$tmpSiteId] = $siteStr;

				echo '\r\nUPDATE srm_students SET address_matched =1, srm_site_id = ' . $tmpSiteId . ' WHERE id = ' . $studentId;
				if(mysql_query('UPDATE srm_students SET address_matched =1, srm_site_id = ' . $tmpSiteId . ' WHERE id = ' . $studentId,$db)){
						echo "\r\nUpdate Successful successful";
				}else{
					echo mysql_errno($db) . ": " . mysql_error($db);
				}
			}else{
				echo mysql_errno($db) . ": " . mysql_error($db);
			}
		}
	}






	//Case 2 - SRM student has no Siebel site, has at least a partial address, has a customer number
	function address_match_case_2($studentId, $customerNumber, $srmStudentRec, $rowStudent) {
		global $db, $dummyCompanyId, $dummySiteId, $srmSiteRecords;

		$srmSiteRecords = array();
		echo "\r\n\r\n SELECTING THE SITES == > SELECT id, division, department, address1, address2 FROM srm_sites WHERE siebel_site_id NOT NULL AND customer_number = $customerNumber ORDER BY siebel_site_id ASC";

		if($resultSet = mysql_query('SELECT id, division, department, address1, address2 FROM srm_sites WHERE siebel_site_id NOT NULL AND customer_number = $customerNumber ORDER BY siebel_site_id ASC'))
			
		// ADD ALL THE SITE ADDRESS TO AN ARRAY SO THAT WE NEED NOT HIT THE DATABASE EVERY TIME
		while ($rowSite = mysql_fetch_assoc($resultSet)) {
			$siteStr = '';
			$siteStr .= (trim($rowSite['division']) != '')? trim(addslashes($rowSite['division'])) . ' ' : '';
			$siteStr .= (trim($rowSite['department']) != '')? trim(addslashes($rowSite['department'])) . ' ' : '';
			$siteStr .= (trim($rowSite['address1']) != '')? trim(addslashes($rowSite['address1'])) . ' ' : '';
			$siteStr .= (trim($rowSite['address2']) != '')? trim(addslashes($rowSite['address2'])) . ' ': '';
			$srmSiteRecords[$rowSite['id']] = $siteStr;
		}

		//MATCH THE STUDENT ADDRESS TO THE SITES ADDRESS ARRAY CREATED AT THE START OF THE FILE
		$keyMatched = get_matching_address($srmStudentRec, $srmSiteRecords, $studentId);
		
		if(((int)$keyMatched) > 0){
			echo "\r\n Address matched == > UPDATE srm_students SET address_matched =1, srm_site_id = ' . $keyMatched . ' WHERE id = ' . $studentId";
			if(mysql_query('UPDATE srm_students SET address_matched =1, srm_site_id = ' . $keyMatched . ' WHERE id = ' . $studentId, $db)){
					echo "\r\nUpdate Successful successful";
				}else{
					echo mysql_errno($db) . ": " . mysql_error($db);
				}
		}elseif($keyMatched === -1){
			$custNum = $dummyCompanyId . "', customer_number='" . $customerNumber;
			echo "\r\n ADDRESS NOT MATCHED = > INSERT INTO srm_sites SET srm_company_id='" . $custNum ."', division='" . trim(addslashes($rowStudent['division'])) . "', department='" . trim(addslashes($rowStudent['department'])) . "',address1='" . trim(addslashes($rowStudent['address1'])) . "', address2='" . trim(addslashes($rowStudent['address2'])) . "', city='" . trim(addslashes($rowStudent['city'])) . "', state='" . trim(addslashes($rowStudent['state'])) . "', country='" . trim(addslashes($rowStudent['country'])) . "', zipcode='" . trim(addslashes($rowStudent['zipcode'])) . "', updated_at=now()";
			
			if(mysql_query("INSERT INTO srm_sites SET srm_company_id='" . $custNum ."', division='" . trim(addslashes($rowStudent['division'])) . "', department='" . trim(addslashes($rowStudent['department'])) . "',address1='" . trim(addslashes($rowStudent['address1'])) . "', address2='" . trim(addslashes($rowStudent['address2'])) . "', city='" . trim(addslashes($rowStudent['city'])) . "', state='" . trim(addslashes($rowStudent['state'])) . "', country='" . trim(addslashes($rowStudent['country'])) . "', zipcode='" . trim(addslashes($rowStudent['zipcode'])) . "', updated_at=now()")){
				echo "\r\nDummy Site Insert Successful";
				$tmpSiteId = @mysql_insert_id();

				//ADD THE NEW SITE TO THE SITE ARRAY FOR CASE - 3
				$siteStr = '';
				$siteStr .= (trim($rowStudent['address1']) != '')? trim(addslashes($rowStudent['address1'])) . ' ' : '';
				$siteStr .= (trim($rowStudent['address2']) != '')? trim(addslashes($rowStudent['address2'])) . ' ': '';
				$siteStr .= (trim($rowStudent['city']) != '')? trim(addslashes($rowStudent['city'])) . ' ': '';
				$srmSiteRecords[$tmpSiteId] = $siteStr;

				echo '\r\nUPDATE srm_students SET address_matched =1, srm_site_id = ' . $tmpSiteId . ' WHERE id = ' . $studentId;
				if(mysql_query('UPDATE srm_students SET address_matched =1, srm_site_id = ' . $tmpSiteId . ' WHERE id = ' . $studentId,$db)){
						echo "\r\nUpdate Successful successful";
				}else{
					echo mysql_errno($db) . ": " . mysql_error($db);
				}
			}else{
				echo mysql_errno($db) . ": " . mysql_error($db);
			}
			
		}
	}




	
	//Case 3 - SRM student has no Siebel site, has at least a partial address, does not have a customer number
	function address_match_case_3($studentId, $srmStudentRec, $rowStudent, $customerNumber) {
		global $db, $dummyCompanyId, $dummySiteId, $srmSiteRecords;

		/*$srmSiteRecords = array();
		echo "\r\n\r\n SELECTING THE SITES == > SELECT id, address1, address2, city FROM srm_sites ORDER BY siebel_site_id ASC";
		$resultSet = mysql_query('SELECT id, address1, address2, city FROM srm_sites ORDER BY siebel_site_id ASC');
			
		// ADD ALL THE SITE ADDRESS TO AN ARRAY SO THAT WE NEED NOT HIT THE DATABASE EVERY TIME
		while ($rowSite = mysql_fetch_assoc($resultSet)) {
			$siteStr = '';
			$siteStr .= (trim($rowSite['address1']) != '')? trim(addslashes($rowSite['address1'])) . ' ' : '';
			$siteStr .= (trim($rowSite['address2']) != '')? trim(addslashes($rowSite['address2'])) . ' ': '';
			$siteStr .= (trim($rowSite['city']) != '')? trim(addslashes($rowSite['city'])) . ' ': '';
			$srmSiteRecords[$rowSite['id']] = $siteStr;
		}*/

		//MATCH THE STUDENT ADDRESS TO THE SITES ADDRESS ARRAY CREATED AT THE START OF THE FILE
		$keyMatched = get_matching_address($srmStudentRec, $srmSiteRecords, $studentId);

		if(((int)$keyMatched) > 0){
			if(mysql_query('UPDATE srm_students SET address_matched =1, srm_site_id = ' . $keyMatched . ' WHERE id = ' . $studentId, $db)){
					echo "\r\nUpdate Successful successful";
				}else{
					echo mysql_errno($db) . ": " . mysql_error($db);
				}
		}elseif($keyMatched === -1){
			$custNum = $dummyCompanyId . "', customer_number='" . $customerNumber;
			
			echo "\r\n ADDRESS NOT MATCHED = > INSERT INTO srm_sites SET srm_company_id='" . $custNum ."', division='" . trim(addslashes($rowStudent['division'])) . "', department='" . trim(addslashes($rowStudent['department'])) . "',address1='" . trim(addslashes($rowStudent['address1'])) . "', address2='" . trim(addslashes($rowStudent['address2'])) . "', city='" . trim(addslashes($rowStudent['city'])) . "', state='" . trim(addslashes($rowStudent['state'])) . "', country='" . trim(addslashes($rowStudent['country'])) . "', zipcode='" . trim(addslashes($rowStudent['zipcode'])) . "', updated_at=now()";

			if(mysql_query("INSERT INTO srm_sites SET srm_company_id='" . $custNum ."', division='" . trim(addslashes($rowStudent['division'])) . "', department='" . trim(addslashes($rowStudent['department'])) . "',address1='" . trim(addslashes($rowStudent['address1'])) . "', address2='" . trim(addslashes($rowStudent['address2'])) . "', city='" . trim(addslashes($rowStudent['city'])) . "', state='" . trim(addslashes($rowStudent['state'])) . "', country='" . trim(addslashes($rowStudent['country'])) . "', zipcode='" . trim(addslashes($rowStudent['zipcode'])) . "', updated_at=now()")){
				echo "\r\nDummy Site Insert Successful";
				$tmpSiteId = @mysql_insert_id();

				//ADD THE NEW SITE TO THE SITE ARRAY FOR CASE - 3
				$siteStr = '';
				$siteStr .= (trim($rowStudent['address1']) != '')? trim(addslashes($rowStudent['address1'])) . ' ' : '';
				$siteStr .= (trim($rowStudent['address2']) != '')? trim(addslashes($rowStudent['address2'])) . ' ': '';
				$siteStr .= (trim($rowStudent['city']) != '')? trim(addslashes($rowStudent['city'])) . ' ': '';
				$srmSiteRecords[$tmpSiteId] = $siteStr;

				echo '\r\nUPDATE srm_students SET address_matched =1, srm_site_id = ' . $tmpSiteId . ' WHERE id = ' . $studentId;
				if(mysql_query('UPDATE srm_students SET address_matched =1, srm_site_id = ' . $tmpSiteId . ' WHERE id = ' . $studentId,$db)){
						echo "\r\nUpdate Successful successful";
				}else{
					echo mysql_errno($db) . ": " . mysql_error($db);
				}
			}else{
				echo mysql_errno($db) . ": " . mysql_error($db);
			}
			
		}
	}





	//Case 4 - SRM student has no Siebel site, has no address information, has a customer number
	function address_match_case_4($studentId, $customerNumber) {
		global $db, $dummyCompanyId, $dummySiteId;

		//CHECK IF THE COMPANY WITH THE CUSTOMER NUMBER EXISTS
		$resultSet = mysql_query("SELECT id FROM srm_companies WHERE customer_number = $customerNumber ORDER BY customer_number ASC",$db);
			
		// IF THE COMPANY WITH THE CUSTOMER NUMBER DOES NOT EXISTS THEN INSERT THE COMPANY
		if($resultSet){
			$temp = mysql_fetch_assoc($resultSet);
			$companyId = $temp['id'];
		}else{
			mysql_query("INSERT INTO srm_companies SET customer_number = $customerNumber, customer_name='Unknown', updated_at=now()",$db);
			$companyId = @mysql_insert_id();
		}
		
		//CHECK IF THE SITE WITH THE COMPANY ID EXISTS
		$resultSet = mysql_query("SELECT id FROM srm_sites WHERE siebel_site_id =0 address1 = 'Unknown' AND srm_company_is = $companyId");

		//IF THE SITE DOES NOT EXISTS THEN INSERT A NEW SITE WITH THE GIVEN CUSTOMER NUMBER
		if($resultSet){
			$temp = mysql_fetch_assoc($resultSet);
			$siteId = $temp['id'];
		}else{
			mysql_query("INSERT INTO srm_sites SET srm_company_id = $companyId, siebel_site_id = 0, customer_number = $customerNumber, address1='Unknown', updated_at=now()");
			$siteId = @mysql_insert_id();
		}
		
		mysql_query('UPDATE srm_students SET address_matched =1, srm_site_id = ' . $siteId . ' WHERE id = ' . $studentId, $db);

	}




	//Case 5 - SRM student has no Siebel site, has no address information, does not have a customer number
	function address_match_case_5() {
	}

?>
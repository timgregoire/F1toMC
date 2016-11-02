<?php

session_start();
require_once('src/fellowshipone/api.php');
include 'src/mailchimp/MailChimp.php';
include 'login.php';



// Log in to both services
$f1 = new F1\API($settings);
$f1->login2ndParty($settings['username'], $settings['password']);
$MailChimp = new MailChimp($MCKey);


/*
$Data = $f1->people()->search(array(
    //'attribute' => $AttID,
    //'recordsPerPage' => 100,
    'searchFor' => 'Anne Gaushell',
    'include'=> 'communications,attributes',
  ))->get();

//$t = $f1->people()->attributegroups('60606')->attributes()->list()->get();
//var_dump($t);
print_r($Data);

*/


$ChimpRawData = $MailChimp->get("lists/$list_id/members?offset=0&count=10000");
$ChimpDownload = array();
$F1DownloadedData = array();
$FamilyCompare = array();

$indivByEmail = downloadF1Data(761700);
$indivByBoth = downloadF1Data(761699);
$family = downloadF1Data(761697);
$familyByBoth = downloadF1Data(769054);

buildCompareList($indivByEmail);
buildCompareList($indivByBoth);
buildFamilyCompareList($family);
buildFamilyCompareList($familyByBoth);

//print_r($F1DownloadedData);
//print_r($family);


//buildChimpList($ChimpRawData);

//print_r($ChimpDownload);

//print_r($F1DownloadedData);


compareAndUpload($F1DownloadedData,$ChimpDownload);


function downloadF1Data($AttID)
  {
    global $f1;
    $AllData = array();

    $numRecords = $f1->people()->search(array(
        'attribute' => $AttID,
        'recordsPerPage' => 1  ))->get();

    $pagesToProcess = 1 + (int)($numRecords['results']['@totalRecords']/100);
    $condensedData = array();

    for ($i = 1; $i<=$pagesToProcess; $i++)
      {
          $Data = $f1->people()->search(array(
          	  'attribute' => $AttID,
          //  'searchFor' => 'Tim Gregoire',
          		'recordsPerPage' => 100,
              'page' => $i,
          		'include'=> 'communications,attributes',
          	))->get();
          //  print_r($Data);
          $AllData[] = $Data;
      }

      $counter = 0;

    for($i=0; $i<$pagesToProcess; $i++)
      {
        for($j=0;$j<100;$j++)
        {
          if(isset($AllData[$i]['results']['person'][$j]))
            {
              $condensedData[$counter] = $AllData[$i]['results']['person'][$j];
              $counter++;
            }
        }

      }


    return $condensedData;
  }

function buildCompareList($unprocessedNames)
  {
    global $F1DownloadedData;

    foreach($unprocessedNames as $person)
    	{

        $first_name = $person['firstName'];
        $last_name = $person['lastName'];
    		$id = $person["@id"];
    		//$household_ID = $person['@householdID'];
        $communications = $person['communications'];
        $communication = $communications['communication'];
        foreach($communication as $comm)
    				{
    	        if($comm['communicationType']['name'] == "Email" || $comm['communicationGeneralType']  == "Email")
    						{
    		            $email = $comm['communicationValue'];
                    $email = str_replace(' ','',$email);
    	        	}
        		}

    				$F1DownloadedData[$id] = array('firstname' => $first_name,
    																		   'lastname' => $last_name,
    																		   'Email' => $email,
    																	   	'F1ID' => $id,
    																		'F1Verify' => 'False',
    																		'ExistsInChimp' => 'False'
    																	  );
    				$first_name = "";
    		    $last_name = "";
    		    $id = "";
    		    $email = "";


    }
  }

function buildFamilyCompareList($family)
{
  global $F1DownloadedData;
  global $FamilyCompare;

  foreach($family as $familyMember)
  {

    //  print_r($familyMember);
      $household_status = $familyMember['householdMemberType']['name'];
      $first_name = $familyMember['firstName'];
      $last_name = $familyMember['lastName'];
      $household_ID = $familyMember['@householdID'];
      $id = $familyMember["@id"];


      foreach($familyMember['communications']['communication'] as $person)
        {
          if($person['communicationGeneralType'] == "Email")
            {
                $email = $person['communicationValue'];
                $email = str_replace(' ','',$email);
                $FamilyCompare[$household_ID][] = array(
                                            'householdStatus' => $household_status,
                                            'firstname' => $first_name,
                                            'lastname' => $last_name,
                                             'Email' => $email,
                                             'F1ID' => $id,
                                              );
            }

        }

        $first_name = "";
        $last_name = "";
        $id = "";
        $email = "";

  }


      foreach($FamilyCompare as $Family)
        {
          if(sizeOf($Family) == 1)
            {
                  $email = $Family[0]['Email'];
                  $email = str_replace(' ','',$email);
    				      $F1DownloadedData[$Family[0]['F1ID']] = array('firstname' => $Family[0]['firstname'],
          																		'lastname' => $Family[0]['lastname'],
          																    'Email' => $email,
          																   	'F1ID' => $Family[0]['F1ID'],
          																		'F1Verify' => 'False',
          																		'ExistsInChimp' => 'False'
                                            );
            }

          if(sizeOf($Family) > 1)
            {
              $status = False;
              foreach($Family as $Person)
                {
                  if($Person['householdStatus'] == 'Head' && $status == False)
                    {
                      $email = $Person['Email'];
                      $email = str_replace(' ','',$email);
                      $F1DownloadedData[$Family[0]['F1ID']] = array('firstname' => $Person['firstname'],
              																		'lastname' => $Person['lastname'],
              																    'Email' => $email,
              																   	'F1ID' => $Person['F1ID'],
              																		'F1Verify' => 'False',
              																		'ExistsInChimp' => 'False'
                                                );
                      $status = True;


                    }
                    else
                      {
                        if($Person['householdStatus'] == 'Spouse' && $status == False)
                          {
                            $email = $Person['Email'];
                            $email = str_replace(' ','',$email);
                            $F1DownloadedData[$Family[0]['F1ID']] = array('firstname' => $Person['firstname'],
                                                        'lastname' => $Person['lastname'],
                                                        'Email' => $email,
                                                        'F1ID' => $Person['F1ID'],
                                                        'F1Verify' => 'False',
                                                        'ExistsInChimp' => 'False'
                                                      );
                            $status = True;
                          }

                      }
                }
            }

        }




}

function buildChimpList($ChimpRawData)
{
  global $ChimpDownload;
  foreach($ChimpRawData["members"] as $email_contact)
    {
      $first_name = $email_contact['merge_fields']['FNAME'];
      $last_name = $email_contact['merge_fields']['LNAME'];
      $F1ID = $email_contact['merge_fields']['F1ID'];
      $email_address = $email_contact['email_address'];

      $ChimpDownload[$F1ID] = array('firstname' => $first_name,
                               'lastname' => $last_name,
                                'Email' => $email_address,
                                'F1ID' => $F1ID,
                                'ExistsInF1' => 'False'
                                    );
      $first_name = "";
      $last_name = "";
      $F1ID = "";
      $email_address = "";
    }

}

function compareAndUpload($F1DownloadedData, $ChimpDownload)
  {
    global $F1DownloadedData;
    global $ChimpDownload;
    global $MailChimp;
    global $list_id;

    //Iterate through the collected emails from FellowshipOne and upload to MailChimp
    foreach($F1DownloadedData as &$current_indiv)
    	{

    		if(array_key_exists($current_indiv['F1ID'], $ChimpDownload))
    		{
    				$current_indiv['ExistsInChimp'] = 'True';
    				$ChimpDownload[$current_indiv['F1ID']]['ExistsInF1'] = 'True';

    				if($current_indiv['firstname'] == $ChimpDownload[$current_indiv['F1ID']]['firstname'])
    				{
    					if($current_indiv['lastname'] == $ChimpDownload[$current_indiv['F1ID']]['lastname'])
    					{
    						if($current_indiv['Email'] == $ChimpDownload[$current_indiv['F1ID']]['Email'])
    						{
    							$current_indiv['F1Verify'] = 'True';
    						}
    					}
    				}
    		}
    	}

    //if they dont exist at all in mailchimp, create a new person and upload all their data
    foreach($F1DownloadedData as &$current_indiv)
    	{
    			if($current_indiv['ExistsInChimp'] == 'False')
    				{
    						$upload_result = $MailChimp->post("lists/$list_id/members", [
    														'email_address' => $current_indiv['Email'],
    														'status'        => 'subscribed',
    												]);
                //  print_r($upload_result);
    						if($upload_result["status"] != "400")
    							{
    									$subscriber_hash = $MailChimp->subscriberHash($current_indiv['Email']);

    									$result = $MailChimp->patch("lists/$list_id/members/$subscriber_hash", [
    																	'merge_fields' => ['FNAME'=> $current_indiv['firstname'] , 'LNAME'=>$current_indiv['lastname'], 'F1ID' =>$current_indiv['F1ID']],
    															]);
                                  //  print_r($result);
    							}

    							$current_indiv['ExistsInChimp'] = 'True';
    							$current_indiv['F1Verify'] = 'True';
    				}

    		}

    //if they exist in mailchimp but anything but email (or F1ID) changed, patch that information
    foreach($F1DownloadedData as &$current_indiv)
      {
    		if($current_indiv['F1Verify'] == 'False')
    			{
    				if($current_indiv['Email'] == $ChimpDownload[$current_indiv['F1ID']]['Email'])
    					{
    						$subscriber_hash = $MailChimp->subscriberHash($current_indiv['Email']);

    						$result = $MailChimp->patch("lists/$list_id/members/$subscriber_hash", [
    														'merge_fields' => ['FNAME'=> $current_indiv['firstname'] , 'LNAME'=>$current_indiv['lastname'], 'F1ID' =>$current_indiv['F1ID']],
    												]);

    						$current_indiv['ExistsInChimp'] = 'True';
    						$current_indiv['F1Verify'] = 'True';
    					}
    			}
      }

    	//if they exist in mailchimp but have a change in email,
    	//delete the old record and reupload all their Info
    foreach($F1DownloadedData as &$current_indiv)
    	{
    		if($current_indiv['F1Verify'] == 'False')
    			{
    				if($current_indiv['Email'] != $ChimpDownload[$current_indiv['F1ID']]['Email'])
    					{
    						$subscriber_hash = $MailChimp->subscriberHash($ChimpDownload[$current_indiv['F1ID']]['Email']);
    						$result = $MailChimp->delete("lists/$list_id/members/$subscriber_hash");


    						$upload_result = $MailChimp->post("lists/$list_id/members", [
    														'email_address' => $current_indiv['Email'],
    														'status'        => 'subscribed',
    												]);

    						if($upload_result["status"] != "400")
    							{
    									$subscriber_hash = $MailChimp->subscriberHash($current_indiv['Email']);

    									$result = $MailChimp->patch("lists/$list_id/members/$subscriber_hash", [
    																	'merge_fields' => ['FNAME'=> $current_indiv['firstname'] , 'LNAME'=>$current_indiv['lastname'], 'F1ID' =>$current_indiv['F1ID']],
    															]);
    							}
    							$current_indiv['ExistsInChimp'] = 'True';
    							$current_indiv['F1Verify'] = 'True';


    					}
    			}
    	}

    //clean up any subscribers in mailchimp that dont exist in F1 anymore
    foreach($ChimpDownload as &$current_indiv)
    	{
    	  if($current_indiv['ExistsInF1'] == 'False')
    		{
    			$subscriber_hash = $MailChimp->subscriberHash($current_indiv['Email']);
    			$result = $MailChimp->delete("lists/$list_id/members/$subscriber_hash");
    		}
    	}


  }

?>

<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library of interface functions and constants for module tincan
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the tincan specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package local_tincan
 * @copyright  2014 Andrew Downes
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tincan;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

class tincan {
	public static function tincan_quiz_attempt_started($event){
		global $CFG, $DB;
		//not all of these will be used TODO: remove those which aren't used once all is said and done!
		$course  = $DB->get_record('course', array('id' => $event->courseid));
	    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
	    $quiz    = $event->get_record_snapshot('quiz', $attempt->quiz);
	    $cm      = get_coursemodule_from_id('quiz', $event->get_context()->instanceid, $event->courseid);
		
		$statement = array( 
			'actor' => tincan_getactor(), 
			'verb' => array(
				'id' => 'http://adlnet.gov/expapi/verbs/attempted',
				'display' => array(
					'en-US' => 'attempted',
					'en-GB' => 'attempted',
					),
				),
			'object' => array(
				'id' =>  $CFG->wwwroot . '/mod/quiz/view.php?id='. $quiz->id, 
				'definition' => array(
					'name' => array(
						'en-US' => $quiz->name,
						'en-GB' => $quiz->name,
					), 
				),
			), 
		);
		
	
		//send it
		tincan_send_statement($statement, get_config('local_tincan', 'lrsendpoint'), get_config('local_tincan', 'lrslogin'), get_config('local_tincan', 'lrspass'),get_config('local_tincan', 'lrsversion'));	
		
		return true;
	}
	
	public static function tincan_quiz_attempt_submitted($eventdata){
		global $CFG, $DB;
		//not all of these will be used TODO: remove those which aren't used once all is said and done!
		$course  = $DB->get_record('course', array('id' => $event->courseid));
	    $attempt = $event->get_record_snapshot('quiz_attempts', $event->objectid);
	    $quiz    = $event->get_record_snapshot('quiz', $attempt->quiz);
	    $cm      = get_coursemodule_from_id('quiz', $event->get_context()->instanceid, $event->courseid);
		
		$statement = array( 
			'actor' => tincan_getactor(), 
			'verb' => array(
				'id' => 'http://adlnet.gov/expapi/verbs/completed',
				'display' => array(
					'en-US' => 'completed',
					'en-GB' => 'completed',
					),
				),
			'object' => array(
				'id' =>  $CFG->wwwroot . '/mod/quiz/view.php?id='. $quiz->id, 
				'definition' => array(
					'name' => array(
						'en-US' => $quiz->name,
						'en-GB' => $quiz->name,
					), 
				),
			), 
		);
		
	
		//send it
		tincan_send_statement($statement, get_config('local_tincan', 'lrsendpoint'), get_config('local_tincan', 'lrslogin'), get_config('local_tincan', 'lrspass'),get_config('local_tincan', 'lrsversion'));		
		
		return true;
	}
}


//TODO: Put this function in a PHP Tin Can library. 
//TODO: Handle failure nicely. E.g. retry getting. 
//TODO: if this is going in a library, it needs to be able to handle registration too
// Note this has to be incldued in lib rather than locallib as its required by tincan_get_completion_state
function tincan_get_statements($url, $basicLogin, $basicPass, $version, $activityid, $agent, $verb) {

	$streamopt = array(
		'ssl' => array(
			'verify-peer' => false, 
			), 
		'http' => array(
			'method' => 'GET', 
			'ignore_errors' => false, 
			'header' => array(
				'Authorization: Basic ' . base64_encode( $basicLogin . ':' . $basicPass), 
				'Content-Type: application/json', 
				'Accept: application/json, */*; q=0.01',
				'X-Experience-API-Version: '.$version
			)
		), 
	);

	$streamparams = array(
		'activity' => $activityid,
		'agent' => json_encode($agent),
		'verb' => $verb
	);
	
	$context = stream_context_create($streamopt);
	
	$stream = fopen($url . 'statements'.'?'.http_build_query($streamparams,'','&'), 'rb', false, $context);
	
	//Handle possible error codes
	$return_code = @explode(' ', $http_response_header[0]);
    $return_code = (int)$return_code[1];
     
    switch($return_code){
        case 200:
            $ret = stream_get_contents($stream);
			$meta = stream_get_meta_data($stream);
		
			if ($ret) {
				$ret = json_decode($ret, TRUE);
			}
            break;
        default: //error
            $ret = NULL;
			$meta = $return_code;
            break;
    }
	
	return array(
		'contents'=> $ret, 
		'metadata'=> $meta
	);
}

function tincan_getactor()
{
	global $USER, $CFG;
	if ($USER->email){
		return array(
			"name" => fullname($USER),
			"mbox" => "mailto:".$USER->email,
			"objectType" => "Agent"
		);
	}
	else{
		return array(
			"name" => fullname($USER),
			"account" => array(
				"homePage" => $CFG->wwwroot,
				"name" => $USER->id
			),
			"objectType" => "Agent"
		);
	}
}

function tincan_gen_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

        // 16 bits for "time_mid"
        mt_rand( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}


function tincan_json_encode($str)
{
	return str_replace('\\/', '/',json_encode($str));
}

//Note: $numeric_prefix is ignored but kept so that this function has the same number of paramters as http_build_query
function tincan_http_build_query($query_data, $numeric_prefix, $arg_separator)
{
	$rtnArray = array();
	foreach ($query_data as $key => $value) {
		$encodedValue = rawurlencode($value);
		array_push($rtnArray, "{$key}={$encodedValue}");
	}
	return implode("&", $rtnArray);
}

//I've split these two functions up so that tincan_save_state can be potentially re-used outside of Moodle.
function tincan_get_global_parameters_and_save_state($data, $key)
{
	global $tincan;
	return tincan_save_state($data, $tincan->tincanlrsendpoint, $tincan->tincanlrslogin, $tincan->tincanlrspass, $tincan->tincanlrsversion, $tincan->tincanactivityid, tincan_getactor(), $key);
}


//TODO: Put this function in a PHP Tin Can library. 
//TODO: Handle failure nicely. E.g. retry sending. 
//TODO: if this is going in a library, it needs to be able to handle registration too
//TODO: add parameter 'method' for POST/PUT
function tincan_save_state($data, $url, $basicLogin, $basicPass, $version, $activityid, $agent, $key) {


	$streamopt = array(
		'ssl' => array(
			'verify-peer' => false, 
			), 
		'http' => array(
			'method' => 'PUT', 
			'ignore_errors' => false, 
			'header' => array(
				'Authorization: Basic ' . base64_encode( $basicLogin . ':' . $basicPass), 
				'Content-Type: application/json', 
				'Accept: application/json, */*; q=0.01',
				'X-Experience-API-Version: '.$version
			), 
			'content' => tincan_myJson_encode($data), 
		), 
	);
	
	$streamparams = array(
		'activityId' => $activityid,
		'agent' => json_encode($agent),
		'stateId' => $key
	);

	
	$context = stream_context_create($streamopt);
	
	$stream = fopen($url . 'activities/state'.'?'.http_build_query($streamparams,'','&'), 'rb', false, $context);
	
	switch($return_code){
        case 200:
            $ret = stream_get_contents($stream);
			$meta = stream_get_meta_data($stream);
		
			if ($ret) {
				$ret = json_decode($ret, TRUE);
			}
            break;
        	default: //error
            $ret = NULL;
			$meta = $return_code;
            break;
    }
	
	
	return array(
		'contents'=> $ret, 
		'metadata'=> $meta
	);
}

//Query to code reviewer: should getting and setting the state be  a single function with a "method" parameter, or be two separate but very similar functions as I've done here? 

//I've split these two functions up so that tincan_save_state can be potentially re-used outside of Moodle.
function tincan_get_global_parameters_and_get_state($key)
{
	global $tincan;
	return tincan_get_state($tincan->tincanlrsendpoint, $tincan->tincanlrslogin, $tincan->tincanlrspass, $tincan->tincanlrsversion, $tincan->tincanactivityid, tincan_getactor(), $key);
}

//TODO: Put this function in a PHP Tin Can library. 
//TODO: Handle failure nicely. E.g. retry getting. 
//TODO: if this is going in a library, it needs to be able to handle registration too
function tincan_get_state($url, $basicLogin, $basicPass, $version, $activityid, $agent, $key) {

	$streamopt = array(
		'ssl' => array(
			'verify-peer' => false, 
			), 
		'http' => array(
			'method' => 'GET', 
			'ignore_errors' => false, 
			'header' => array(
				'Authorization: Basic ' . base64_encode( $basicLogin . ':' . $basicPass), 
				'Content-Type: application/json', 
				'Accept: application/json, */*; q=0.01',
				'X-Experience-API-Version: '.$version
			)
		), 
	);

	$streamparams = array(
		'activityId' => $activityid,
		'agent' => json_encode($agent),
		'stateId' => $key
	);
	
	$context = stream_context_create($streamopt);
	
	$stream = fopen($url . 'activities/state'.'?'.http_build_query($streamparams,'','&'), 'rb', false, $context);
	
	//Handle possible error codes
	$return_code = @explode(' ', $http_response_header[0]);
    $return_code = (int)$return_code[1];
     
    switch($return_code){
        case 200:
            $ret = stream_get_contents($stream);
			$meta = stream_get_meta_data($stream);
		
			if ($ret) {
				$ret = json_decode($ret, TRUE);
			}
            break;
        default: //error
            $ret = NULL;
			$meta = $return_code;
            break;
    }
	
	return array(
		'contents'=> $ret, 
		'metadata'=> $meta
	);
}


function tincan_send_statement($statement, $endpoint, $basicLogin, $basicPass, $version) {

	$streamopt = array(
		'ssl' => array(
			'verify-peer' => false, 
			), 
		'http' => array(
			'method' => 'POST', 
			'ignore_errors' => false, 
			'header' => array(
				'Authorization: Basic ' . base64_encode( $basicLogin . ':' . $basicPass), 
				'Content-Type: application/json', 
				'Accept: application/json, */*; q=0.01',
				'X-Experience-API-Version: '.$version
			), 
			'content' => tincan_json_encode($statement), 
		), 
	);
	$context = stream_context_create($streamopt);

	$stream = fopen($endpoint . 'statements', 'rb', false, $context);
	
	//Handle possible error codes
	$return_code = @explode(' ', $http_response_header[0]);
    $return_code = (int)$return_code[1];
	
	switch($return_code){
        case 200:
            $ret = stream_get_contents($stream);
			$meta = stream_get_meta_data($stream);
		
			if ($ret) {
				$ret = json_decode($ret, TRUE);
			}
            break;
        	default: //error
            $ret = NULL;
			$meta = $return_code;
            break;
    }
	
	return array(
		'contents'=> $ret, 
		'metadata'=> $meta
	);
}


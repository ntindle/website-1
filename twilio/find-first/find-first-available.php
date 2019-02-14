<?php
require('../../template/config.php');
if ($_GET['code'] !== API_SECRET) {
        http_response_code(401);
        die();
}

require('../phone-numbers-config.php');

$final_status = array('Busy', 'No-answer', 'Canceled', 'Failed', 'Completed');

function dial_attempt($phone_number) {
	$ch = curl_init();

	$post = array(
		'Url' => 'https://' . $_SERVER['SERVER_NAME'] . '/twilio/find-first/call-challenge.php?code=' . API_SECRET,
		'To' => $phone_number,
		'From' => PHONE_NUMBER
	);

	curl_setopt($ch, CURLOPT_URL, 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Calls.json');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);

	$result = curl_exec($ch);
	if (curl_errno($ch)) {
		error_log('Error making twilio call request: ' . curl_error($ch));
	}
	curl_close($ch);
	
	$data = json_decode($result);

	return $data->sid;
}

function call_completed($sid) {

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Calls/' . $sid . '.json');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);

        $result = curl_exec($ch);
        if (curl_errno($ch)) {
                error_log('ERROR (curl) when getting message SID info: ' . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($result);

		if (in_array($data->status, $final_status)) {
			// the call was ended, probably didn't complete the call challenge successfully
			return true;
		}
        return false;
}

function queue_size($queue_name = TWILIO_FIND_FIRST_QUEUE) {
	$ch = curl_init();

	$post = array(
		'FriendlyName' => $queue_name
	);

	curl_setopt($ch, CURLOPT_URL, 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Queues.json');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
	curl_setopt($ch, CURLOPT_USERPWD, TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN);

	$result = curl_exec($ch);
	if (curl_errno($ch)) {
		error_log('Error making twilio queue log request: ' . curl_error($ch));
	}
	curl_close($ch);
	
	$data = json_decode($result);

	return intval($data->current_size);
}

foreach ($phone_numbers as $phone_number) {
	if (queue_size() > 0) {
		$sid = dial_attempt($phone_number);
		do {
			sleep(1);
		} while (!call_completed($sid) && queue_size() > 0);
	} else {
		break;
	}
}
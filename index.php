<?php
/**
 * Bot that helps set up a farming session for the Sri Lankan Ingress Enlightened.
 */
 
 // Define multiple farming
 // 1 = allow multiple farms
 // 0 = disallow multiple farms
define ("MULTIFARM", 1);
 
 // Define debug logging level
 // 0 = no logging
 // 1 = all logs
define ("DEBUGLVL", 0);

define('ACCESS_TOKEN', '320879984:AAEk16-_YOAi4z-Xhjf9Cp9XZopgzzuxMLQ');
global $questions;
$questions = array(
  1 => [
      'question' => 'From where was the longest link out of Sri Lanka made?',
      'answers' => ['Jaffna', 'Mannar'],
      'location' => ['longitude' => 79.867644, 'latitude' => 6.904088]
  ]
);

function build_response($chat_id, $text) {
    $returnvalue = 'https://api.telegram.org/bot' . ACCESS_TOKEN . '/sendMessage?chat_id='
            . $chat_id . '&text=' . $text;
    return $returnvalue;
}
function build_response_keyboard($chat_id, $text, $message_id, $markup) {
    $markup['resize_keyboard'] = true;
    $markup['one_time_keyboard'] = true;
    $markup['selective'] = true;
    $returnvalue = 'https://api.telegram.org/bot' . ACCESS_TOKEN . '/sendMessage?chat_id='
        . $chat_id . '&text=' . $text . '&reply_to_message_id=' . $message_id . '&reply_markup=' . json_encode($markup);
    return $returnvalue;
}
function build_location_response($chat_id, $location) {
    $returnvalue = 'https://api.telegram.org/bot' . ACCESS_TOKEN . '/sendLocation?chat_id='
        . $chat_id .'&longitude=' . $location['longitude'] . '&latitude='.$location['latitude'];
    return $returnvalue;
}
function send_curl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $result = curl_exec($ch);
    if ($result === FALSE) {
        die('Curl failed: ' . curl_error($ch));
    }

    // Close connection
    curl_close($ch);
}
function build_farm_message($id) {
	include_once ('dbAccess.php');
	$db = dbAccess::getInstance();
    $db->setQuery('select * from farms where id=' . $id);
    $currentfarm = $db->loadAssoc();
	$reply = urlencode('Current farm - ' . $currentfarm['location'] . ' ' . $currentfarm['date_and_time'] . '
');
	$reply .= urlencode('Farm creator - ' . $currentfarm['creator'] .'
');
        $db->setQuery('select * from farmers where farm_id=' . $currentfarm['id']);
        $farmers = $db->loadAssocList();
        $i = 1;
        foreach ($farmers as $farmer) {
            $reply .= urlencode($i . '. ' . $farmer['farmer_name'] . '
');
            $i++;
        }
    return $reply;
}

function send_response($input_raw) {
    include 'dbAccess.php';
    global $questions;
    $sequence_commands = array('/getnextquestion' );
    //This array is used to store the questions to be asked when a user sends a message which would require secondary processing for farm selection.
    //[0] - Farm selection question - this is used in later processing to identify which message the bot should reply to
    // [1] - How many segments should there be other than the request message - this is used for validation.
    // [2] - Response to send if validation on message segments fails.
    $selection_questions = array('/getnextquestion' => array('First, you\'re going to have to send your location', 0));

    $db = dbAccess::getInstance();
    //$response = send_curl('https://api.telegram.org/bot112493740:AAHBuoGVyX2_T-qOzl8LgcH-xoFyYUjIsdg/getUpdates');
 /*$input_raw = '{
                  "update_id": 89023643,
                  "message": {
                    "message_id": 9370,
                    "from": {
                      "id": 387220855,
                      "first_name": "Ramindu",
                      "last_name": "Deshapriya",
                      "username": "RamdeshLota"
                    },
                    "chat": {
                      "id":-27924249,
                      "title": "Bot Devs & BAs"
                    },
                    "date": 1440704429,
                    "reply_to_message": {
                      "message_id": 9369,
                      "from": {
                        "id": 112493740,
                        "first_name": "SL ENL Quiz Bot",
                        "username": "SlEnlQuizBot"
                      },
                      "chat": {
                        "id": -27924249,
                        "title": "Bot Devs & BAs"
                      },
                      "date": 1440704423,
                      "text": " "
                    },
                    "text": "/getnextquestion"
                  }
                }';*/
				
    // let's log the raw JSON message first

    if(DEBUGLVL){
        $log = new stdClass();
        $log->message_text = $input_raw;
        $db->insertObject('message_log', $log);
    }
    $messageobj = json_decode($input_raw, true);
    $chat_id = $messageobj['message']['chat']['id'];
	$user_id = $messageobj['message']['from']['id'];
    $message_id = $messageobj['message']['message_id'];
    $username = '@' . $messageobj['message']['from']['username'];
    $reply = '';
	
	$message_txt_parts = explode(' ', $messageobj['message']['text']);
    $complete_message = $messageobj['message']['text'];
    $request_message = $message_txt_parts[0];
    //$request_message = explode('@', $request_message); $request_message = $request_message[0];

	if ($chat_id != $user_id) {
        $reply = urlencode('You cannot use this bot in groups. Please chat with @SlEnlQuizBot.');
        send_curl(build_response($chat_id, $reply));
        return;
	}
	if ($request_message == 'Cancel') {	
        $markup['hide_keyboard'] = true;
        send_curl('https://api.telegram.org/bot' . ACCESS_TOKEN . '/sendMessage?chat_id='
            . $chat_id . '&text=👍&reply_markup=' . json_encode($markup));
            return;
	}

    if (in_array($request_message, $sequence_commands)) {
        // This is an initial message in the chain, generate the farm list and send
        // validate if the message is ready for multifarms
        if(count($message_txt_parts) - 1 < $selection_questions[$request_message][1]){
            $reply = urlencode($username.", " . $selection_questions[$request_message][2]);
            send_curl(build_response($chat_id, $reply));
            return;
        }
        // Logic to check the location should go here
        $username = '@' . $messageobj['message']['from']['username'];
        $keyboard = array('keyboard' => array());
        for($i = 0; $i < count($currentfarms); $i++) {
            $keyboard['keyboard'][$i][0] = $currentfarms[$i]['id'] . '. ' . $currentfarms[$i]['location'] . ' ' . $currentfarms[$i]['date_and_time'];
        }
			$keyboard['keyboard'][count($currentfarms)][0] = "Cancel"; 
        if ($request_message == '/setfarmtime') {
            $reply = urlencode($username.", " . $selection_questions[$request_message][0] . ' |' . $message_txt_parts[1] . ' ' . $message_txt_parts[2]);
        } else if ($request_message == '/setfarmlocation' || $request_message == '/addfarmer' || $request_message == '/removefarmer') {
            $reply = urlencode($username.", " . $selection_questions[$request_message][0] . ' |' . $message_txt_parts[1]);
        } else {
            $reply = urlencode($username.", " . $selection_questions[$request_message][0]);
        }
        send_curl(build_response_keyboard($chat_id, $reply, $message_id, $keyboard));
        return;
    }

    if ($request_message == '/getnextquestion') {
        $time = $location = '';
        $username = '@' . $messageobj['message']['from']['username'];

        send_curl(build_response($chat_id, $reply));
        
        return;
    }
    if (array_key_exists('reply_to_message', $messageobj['message'])) {
        // This is a secondary message on the chain - process it
        $secondary_parts = explode('.', $complete_message);
        $reply_to_message = $messageobj['message']['reply_to_message']['text'];

        if (strpos($reply_to_message, 'send your location') !== false) {
            //location check happens here
            $location = $messageobj['message']['location'];

            // Select a question based on location
            for($i = 0; $i < count($questions[1]['answers']); $i++) {
                $keyboard['keyboard'][$i][0] = $questions[1]['answers'][$i];
            }
            $keyboard['keyboard'][count($questions[1]['answers'])][0] = "Cancel";
            send_curl(build_response($chat_id, $reply));
            return;
        }

        if (strpos($reply_to_message, 'location for') !== false) {
            $location = explode('|', $reply_to_message); $location = $location[1];
            $farm = new stdClass();
            $farm->id = $currentfarm['id'];
            $farm->location = $location;
            $db->updateObject('farms', $farm, 'id');
            $reply .= urlencode('Set farm location to '. $location .'
');

            $reply .= build_farm_message($currentfarm['id']);
            send_curl(build_response($chat_id, $reply));

            return;
        }
    }


    if ($request_message == '/help' || $request_message == '/help@SlEnlQuizBot' || $request_message == '/start@SlEnlQuizBot') {
        $reply = urlencode('This is the SL ENL Quiz Master bot. Commands:
/getnextquestion Gets the next question.
/getnextlocation - Gets the location       		
/help - Display this help text.');

        send_curl(build_response($chat_id, $reply));
        
        return;
    }
}

send_response($_POST);

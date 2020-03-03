<?php
require_once 'vendor/autoload.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function decodeBody($body) {
    $rawData = $body;
    $sanitizedData = strtr($rawData,'-_', '+/');
    $decodedMessage = base64_decode($sanitizedData);
    if(!$decodedMessage){
        $decodedMessage = FALSE;
    }
    return $decodedMessage;
}
 
function getMessage($service, $userId, $messageId) {
    try {
        $message = $service->users_messages->get($userId, $messageId);
        // print 'Message with ID: ' . $message->getId() . ' retrieved.';
        return $message;
    } catch (Exception $e) {
        print 'An error occurred: ' . $e->getMessage();
    }
}


function listMessages($service, $userId) {
    $pageToken = NULL;
    $messages = array();
    $opt_param = array();
    try {
        $opt_param['includeSpamTrash'] = FALSE;
        $opt_param['labelIds'] = 'INBOX';
        $opt_param['maxResults'] = 2;
        $messagesResponse = $service->users_messages->listUsersMessages($userId, $opt_param);
        if ($messagesResponse->getMessages()) {
            $messages = array_merge($messages, $messagesResponse->getMessages());
            $pageToken = $messagesResponse->getNextPageToken();
        }
    } catch (Exception $e) {
        print 'An error occurred: ' . $e->getMessage();
    }  
    return $messages;
}

function main($client) {
    $service = new Google_Service_Gmail($client);

    $user = 'me';
    $messages = listMessages($service, $user);
    foreach ($messages as $message) {
        $message_content = getMessage($service, $user, $message->getId());
        $payload = $message_content->getPayLoad();
        $body = $payload->getBody();
        $headers = $payload->getHeaders();
        foreach($headers as $header) {
            // var_dump($header->name);
            if (strtolower($header->name) == 'from') {
                $from = $header->value;
                preg_match('/<(.*?)>/', $from, $match);
                $from =$match[1];
                echo $from;
                break;
            }
        }
        $FOUND_BODY = decodeBody($body['data']);
        echo $FOUND_BODY;
    }
}

// create Client Request to access Google API
$client = new Google_Client();
$client->setScopes(Google_Service_Gmail::GMAIL_READONLY);
$client->setAuthConfig('config.json');

// authenticate code from Google OAuth Flow
if (isset($_SESSION['token'])) {
    $client->setAccessToken($_SESSION['token']);
    main($client);
} else if(isset($_GET['code'])) {
    $accessToken = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $_SESSION['token'] = $accessToken;
    $client->setAccessToken($accessToken);
    main($client);
} else {
    echo "<a href='".$client->createAuthUrl()."'>Google Login</a>";
}
?>
<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
function log_error($message)
{
    file_put_contents('twilio_errors.log', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

// Include the Twilio PHP library
require_once 'twilio-php-main/src/Twilio/autoload.php';

use Twilio\Rest\Client;

try {
    // Twilio credentials
    $sid    = "SID";  // Replace with your Twilio SID
    $token  = "TOKEN";  // Replace with your Twilio Auth Token
    $twilio = new Client($sid, $token);

    // Initialize an array to store the response
    $response = [];

    // Fetch Twilio Account Information
    try {
        $account = $twilio->api->v2010->accounts($sid)->fetch();
        $response['account'] = [
            'sid' => $account->sid,
            'friendlyName' => $account->friendlyName,
            'status' => $account->status
        ];
    } catch (Exception $e) {
        log_error("Failed to fetch account information: " . $e->getMessage());
    }

    // Fetch relevant Usage Records for SMS (Today)
    try {
        $usageRecords = $twilio->usage->records->today->read([
            'category' => 'sms' // Filtering to SMS-related usage only
        ], 5);  // Limit to 5 records for simplicity

        $response['usageRecords'] = [];
        foreach ($usageRecords as $record) {
            $response['usageRecords'][] = [
                'category' => $record->category,
                'usage' => $record->usage,
                'usageUnit' => $record->usageUnit
            ];
        }
    } catch (Exception $e) {
        log_error("Failed to fetch usage records: " . $e->getMessage());
    }

    // Fetch SMS Usage Records for the past 30 days (Historical Data)
    try {
        $smsUsageRecords = $twilio->usage->records->daily->read([
            'category' => 'sms', // Filter for SMS usage
            'startDate' => (new DateTime('-30 days'))->format('Y-m-d'), // Last 30 days
            'endDate' => (new DateTime())->format('Y-m-d')
        ]);

        $response['smsUsageRecords'] = [];
        foreach ($smsUsageRecords as $record) {
            $response['smsUsageRecords'][] = [
                'date' => $record->startDate->format('Y-m-d'),
                'usage' => $record->usage,
                'usageUnit' => $record->usageUnit
            ];
        }
    } catch (Exception $e) {
        log_error("Failed to fetch SMS usage records: " . $e->getMessage());
    }

    // Fetch 150 Recent Messages
    try {
        $recentMessages = $twilio->messages->read([], 550);  // Fetch up to 550 recent messages
        $response['recentMessages'] = [];
        foreach ($recentMessages as $message) {
            $response['recentMessages'][] = [
                'from' => $message->from,
                'to' => $message->to,
                'body' => $message->body,
                'status' => $message->status
            ];
        }
    } catch (Exception $e) {
        log_error("Failed to fetch recent messages: " . $e->getMessage());
    }

    // Output the response as JSON
    header('Content-Type: application/json');
    echo json_encode($response);
} catch (Exception $e) {
    log_error("General error: " . $e->getMessage());
    echo json_encode(['error' => 'Failed to load data']);
    exit;
}

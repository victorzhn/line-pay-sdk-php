<?php

require __DIR__ . '/_config.php';

// Get saved config
$config = $_SESSION['config'];
// Create LINE Pay client
$linePay = new \victorzhn\linePay\Client([
    'channelId' => $config['channelId'],
    'channelSecret' => $config['channelSecret'],
    'isSandbox' => ($config['isSandbox']) ? true : false, 
    'merchantDeviceProfileId' => $config['merchantDeviceProfileId'],
]);

// Successful page URL
$successUrl = './index.php?route=order';
// Get the transactionId from query parameters
$transactionId = (string) $_GET['transactionId'];
// Get the order from session
$order = $_SESSION['linePayOrder'];

// Check transactionId (Optional)
if ($order['transactionId'] != $transactionId) {
    die("<script>alert('TransactionId doesn\'t match');location.href='./index.php';</script>");
}

// Online Refund API
$refundParams = (isset($_GET['amount']) && $_GET['amount']!="") ? ['refundAmount' => (integer) $_GET['amount']] : null;
$response = $linePay->refund($order['transactionId'], $refundParams);

// Log
saveLog('Refund API', $response);

// Save error info if confirm fails
if (!$response->isSuccessful()) {
    die("<script>alert('Refund Failed\\nErrorCode: {$response['returnCode']}\\nErrorMessage: {$response['returnMessage']}');location.href='{$successUrl}';</script>");
}

// Use Details API to confirm the transaction (Details API verification is  stable then Confirm API)
$response = $linePay->details([
    'transactionId' => $order['transactionId'],
]);
// Log
saveLog('Payment Details API', $response);
// Check the transaction
if (!isset($response["info"][0]['refundList']) || $response["info"][0]['transactionId'] != $transactionId) {
    die("<script>alert('Refund Failed');location.href='{$successUrl}';</script>");
}

// Code for saving the successful order into your application database...
$_SESSION['linePayOrder']['info'] = $response["info"][0];

// Redirect to successful page
header("Location: {$successUrl}");
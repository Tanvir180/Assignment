<?php
require 'vendor/autoload.php'; 

use MongoDB\Client as MongoClient;

// MongoDB configuration
$mongoHost = 'mongodb://localhost:27017';
$mongoDbName = 'assignment';
$mongoCollectionName = 'campaigns';

// MySQL configuration
$mysqlHost = 'localhost';
$mysqlUser = 'root';
$mysqlPass = '';
$mysqlDbName = 'assignment';
$mysqlTable = 'campaigns';

// Function JSON requests
function handleRequest() {
    global $mongoHost, $mongoDbName, $mongoCollectionName;
    global $mysqlHost, $mysqlUser, $mysqlPass, $mysqlDbName, $mysqlTable;

    // Get JSON input from HTTP POST request
    $jsonInput = file_get_contents('php://input');

    // Decode JSON data
    $data = json_decode($jsonInput, true);

    // Validate JSON data
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON', 'json_error' => json_last_error_msg()]);
        exit;
    }

    if (!isset($data[0]) || !isset($data[1])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON structure']);
        exit;
    }

    //MongoDB
    $mongoClient = new MongoClient($mongoHost);
    $mongoDb = $mongoClient->$mongoDbName;
    $collection = $mongoDb->$mongoCollectionName;

    // Insert data
    $insertResult = $collection->insertMany($data);

    if ($insertResult->getInsertedCount() > 0) {
        $response = ['status' => 'success', 'inserted_ids' => $insertResult->getInsertedIds()];
    } else {
        $response = ['status' => 'failure'];
    }

    // Connect to MySQL
    $mysqli = new mysqli($mysqlHost, $mysqlUser, $mysqlPass, $mysqlDbName);

    // Check connection
    if ($mysqli->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'MySQL connection failed']);
        exit;
    }

    $stmt = $mysqli->prepare("INSERT INTO $mysqlTable (code, name, goal, starts, ends, campaign_type_id) VALUES (?, ?, ?, ?, ?, ?)");

    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare MySQL statement']);
        exit;
    }

    foreach ($data as $campaign) {
        $stmt->bind_param(
            'ssissi',
            $campaign['campaign_name'], 
            $campaign['campaign_name'],
            $campaign['campaign_goal'], 
            $campaign['campaign_starts'], 
            $campaign['campaign_ends'], 
            $campaign['campaign_type']
        );
        
        $stmt->execute();
    }

    if ($stmt->affected_rows > 0) {
        $response['mysql_status'] = 'success';
    } else {
        $response['mysql_status'] = 'failure';
    }

    $stmt->close();
    $mysqli->close();

    echo json_encode($response);
}

handleRequest();

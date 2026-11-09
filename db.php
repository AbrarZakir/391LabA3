<?php

$dbHost = getenv('WORKSHOP_DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('WORKSHOP_DB_PORT') ?: '3306';
$dbName = getenv('WORKSHOP_DB_NAME') ?: 'workshop';
$dbUser = getenv('WORKSHOP_DB_USER') ?: 'root';
$dbPassword = getenv('WORKSHOP_DB_PASSWORD');

$pdo = new PDO(
    "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
    $dbUser,
    $dbPassword === false ? '' : $dbPassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

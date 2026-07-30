<?php
/**
 * DockerPanel — Database Initialization Script
 */

define('ROOT_PATH', dirname(__DIR__));
require ROOT_PATH . '/config/config.php';
require ROOT_PATH . '/config/database.php';

try {
    echo "Initializing database...\n";
    Database::initialize();
    
    // Check if users table is empty
    $db = Database::getInstance();
    $userCount = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    
    if ($userCount == 0) {
        echo "Seeding default data...\n";
        Database::seed();
    }
    
    echo "Database initialized successfully.\n";
} catch (Exception $e) {
    echo "ERROR initializing database: " . $e->getMessage() . "\n";
    exit(1);
}

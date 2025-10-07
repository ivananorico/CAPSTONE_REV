<?php
require_once __DIR__ . "/../../../db/Market/market_db.php";

$mapId = 10; // Change to the ID you're trying to delete
echo "Testing delete for map ID: " . $mapId . "\n";

// Check if map exists
$stmt = $pdo->prepare("SELECT * FROM maps WHERE id = ?");
$stmt->execute([$mapId]);
$map = $stmt->fetch();

if ($map) {
    echo "Map exists: " . $map['name'] . "\n";
} else {
    echo "Map not found!\n";
}
<?php
// map_display.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Enable error reporting temporarily for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Include DB
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Only GET allowed");
    }

    // Check if map_id is provided
    if (!isset($_GET['map_id'])) {
        throw new Exception("map_id parameter is required");
    }

    $mapId = (int)$_GET['map_id'];

    // Fetch map data
    $stmt = $pdo->prepare("SELECT id, name, file_path FROM maps WHERE id = ?");
    $stmt->execute([$mapId]);
    $map = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$map) {
        throw new Exception("Map not found");
    }

    // Fetch stalls for this map
    $stmtStalls = $pdo->prepare("
        SELECT id, name, pos_x, pos_y, price, height, length, width 
        FROM stalls 
        WHERE map_id = ?
    ");
    $stmtStalls->execute([$mapId]);
    $stalls = $stmtStalls->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        "status" => "success",
        "map" => [
            "id" => (int)$map['id'],
            "name" => $map['name'],
            "image_path" => $map['file_path']
        ],
        "stalls" => $stalls
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
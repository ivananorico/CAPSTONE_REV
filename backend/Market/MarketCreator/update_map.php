<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    $input = json_decode(file_get_contents('php://input'), true);
    $mapId = $input['map_id'] ?? null;
    $stalls = $input['stalls'] ?? [];

    if (!$mapId) {
        throw new Exception("Map ID is required");
    }

    $pdo->beginTransaction();

    // Update existing stalls and insert new ones
    $updateStmt = $pdo->prepare("
        UPDATE stalls 
        SET name = ?, pos_x = ?, pos_y = ?, price = ?, height = ?, length = ?, width = ?, status = ?
        WHERE id = ? AND map_id = ?
    ");

    $insertStmt = $pdo->prepare("
        INSERT INTO stalls (map_id, name, pos_x, pos_y, price, height, length, width, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($stalls as $stall) {
        if (isset($stall['id']) && !isset($stall['isNew'])) {
            // Update existing stall
            $updateStmt->execute([
                $stall['name'] ?? 'Unnamed Stall',
                $stall['pos_x'] ?? 0,
                $stall['pos_y'] ?? 0,
                $stall['price'] ?? 0,
                $stall['height'] ?? 0,
                $stall['length'] ?? 0,
                $stall['width'] ?? 0,
                $stall['status'] ?? 'available',
                $stall['id'],
                $mapId
            ]);
        } else {
            // Insert new stall
            $insertStmt->execute([
                $mapId,
                $stall['name'] ?? 'Unnamed Stall',
                $stall['pos_x'] ?? 0,
                $stall['pos_y'] ?? 0,
                $stall['price'] ?? 0,
                $stall['height'] ?? 0,
                $stall['length'] ?? 0,
                $stall['width'] ?? 0,
                $stall['status'] ?? 'available'
            ]);
        }
    }

    $pdo->commit();

    echo json_encode([
        "status" => "success",
        "message" => "Map updated successfully"
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
<?php
// save_stalls.php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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
    // Include DB - CORRECTED PATH (3 levels up)
    require_once __DIR__ . "/../../../db/Market/market_db.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST allowed");
    }

    // Check required inputs
    if (!isset($_POST['mapName']) || !isset($_FILES['mapImage']) || !isset($_POST['stalls'])) {
        throw new Exception("mapName, mapImage, and stalls are required");
    }

    $mapName = trim($_POST['mapName']);
    $stalls = json_decode($_POST['stalls'], true);
    if ($stalls === null) throw new Exception("Invalid JSON for stalls");

    // Handle map image upload
    $file = $_FILES['mapImage'];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("Upload error code: " . $file['error']);

    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) throw new Exception("Invalid file type");

    // Ensure uploads directory exists - also corrected to 3 levels up
    $uploadsDir = __DIR__ . "/../../../uploads/market/maps";
    if (!is_dir($uploadsDir)) mkdir($uploadsDir, 0755, true);

    // Generate unique filename
    $filename = 'map_' . time() . '_' . bin2hex(random_bytes(5)) . '.' . $ext;
    $target = $uploadsDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) throw new Exception("Failed to move uploaded file");

    $imagePath = "uploads/market/maps/" . $filename; // relative path for frontend

    // Insert map
    $stmt = $pdo->prepare("INSERT INTO maps (name, file_path) VALUES (?, ?)");
    $stmt->execute([$mapName, $imagePath]);
    $mapId = $pdo->lastInsertId();

    // Insert stalls - CORRECTED COLUMNS (removed status)
    $stmtStall = $pdo->prepare("
        INSERT INTO stalls (map_id, name, pos_x, pos_y, price, height, length, width)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($stalls as $stall) {
        $stmtStall->execute([
            $mapId,
            $stall['name'] ?? 'Unnamed Stall',
            $stall['pos_x'] ?? 0,
            $stall['pos_y'] ?? 0,
            $stall['price'] ?? 0,
            $stall['height'] ?? 0,
            $stall['length'] ?? 0,
            $stall['width'] ?? 0
        ]);
    }

    echo json_encode([
        "status" => "success",
        "map_id" => (int)$mapId,
        "image_path" => $imagePath
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ]);
}
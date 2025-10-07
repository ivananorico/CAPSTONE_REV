<?php
session_start();
require_once '../db/Market/market_db.php';

// Get user data from URL parameters or session
$user_id = $_GET['user_id'] ?? $_SESSION['user_id'] ?? null;
$name = $_GET['name'] ?? $_SESSION['full_name'] ?? 'Guest';
$email = $_GET['email'] ?? $_SESSION['email'] ?? 'Not set';

if (!$user_id) {
    header('Location: ../citizen_portal/login.php');
    exit;
}

// Fetch all available maps
try {
    $mapsStmt = $pdo->query("SELECT id, name FROM maps ORDER BY name");
    $maps = $mapsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $maps = [];
}

// Get selected map from filter
$selectedMapId = $_GET['map_id'] ?? ($maps[0]['id'] ?? null);

// Fetch stalls for selected map
$stalls = [];
$selectedMap = null;
if ($selectedMapId) {
    try {
        // Get map details
        $mapStmt = $pdo->prepare("SELECT * FROM maps WHERE id = ?");
        $mapStmt->execute([$selectedMapId]);
        $selectedMap = $mapStmt->fetch(PDO::FETCH_ASSOC);

        // Get stalls for this map
        $stallsStmt = $pdo->prepare("
            SELECT * FROM stalls 
            WHERE map_id = ? 
            ORDER BY name
        ");
        $stallsStmt->execute([$selectedMapId]);
        $stalls = $stallsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Handle error
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market Stall Portal - Municipal Services</title>
    <link rel="stylesheet" href="market_portal.css">
    <link rel="stylesheet" href="../citizen_portal/navbar.css">
</head>
<body>
    <?php include '../citizen_portal/navbar.php'; ?>
    <div class="portal-container">
        <div class="portal-header">
            <h1>Market Stall Portal</h1>
            <p>Browse available market stalls and apply for rental</p>
        </div>

        <div class="user-info">
            <h3>Welcome, <?php echo htmlspecialchars($name); ?>!</h3>
            <p>User ID: <?php echo htmlspecialchars($user_id); ?> | Email: <?php echo htmlspecialchars($email); ?></p>
        </div>

        <div class="filter-section">
            <h3>Select Market Map</h3>
            <form method="GET" class="map-filter">
                <select name="map_id" class="map-select" onchange="this.form.submit()">
                    <option value="">Select a map...</option>
                    <?php foreach ($maps as $map): ?>
                        <option value="<?php echo $map['id']; ?>" 
                            <?php echo ($map['id'] == $selectedMapId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($map['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
            </form>
        </div>

        <?php if ($selectedMap): ?>
            <div class="status-legend">
                <div class="legend-item">
                    <div class="legend-color legend-available"></div>
                    <span>Available</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-occupied"></div>
                    <span>Occupied</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-reserved"></div>
                    <span>Reserved</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color legend-maintenance"></div>
                    <span>Maintenance</span>
                </div>
            </div>

            <div class="market-map-display" 
                 style="background-image: url('http://localhost/revenue/<?php echo $selectedMap['file_path']; ?>')">
                <?php foreach ($stalls as $stall): ?>
                    <div class="stall-marker <?php echo $stall['status']; ?>" 
                         style="left: <?php echo $stall['pos_x']; ?>px; top: <?php echo $stall['pos_y']; ?>px;"
                         onclick="openStallModal(<?php echo $stall['id']; ?>, '<?php echo addslashes($stall['name']); ?>', <?php echo $stall['price']; ?>, '<?php echo $stall['status']; ?>', <?php echo $stall['length']; ?>, <?php echo $stall['width']; ?>, <?php echo $stall['height']; ?>)">
                        <div class="stall-name"><?php echo htmlspecialchars($stall['name']); ?></div>
                        <div class="stall-price">₱<?php echo number_format($stall['price'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-stalls">
                <p>Please select a market map to view available stalls.</p>
            </div>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="../citizen_portal/dashboard.php" class="back-link">← Back to Dashboard</a>
        </div>
    </div>

    <!-- Modal -->
    <div class="modal-backdrop" id="stallModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalStallName">Stall Name</h3>
                <p id="modalStallStatus" class="detail-value"></p>
            </div>
            
            <div class="stall-details">
                <div class="detail-row">
                    <span class="detail-label">Price:</span>
                    <span class="detail-value" id="modalStallPrice">₱0.00</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Dimensions:</span>
                    <span class="detail-value" id="modalStallDimensions">0m × 0m × 0m</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" id="modalStallStatusText">Available</span>
                </div>
            </div>
            
            <div class="modal-buttons">
                <form id="applicationForm" method="POST" action="application.php" style="display: none;">
                    <input type="hidden" name="stall_id" id="formStallId">
                    <input type="hidden" name="stall_name" id="formStallName">
                    <input type="hidden" name="stall_price" id="formStallPrice">
                    <input type="hidden" name="stall_dimensions" id="formStallDimensions">
                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_id); ?>">
                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                </form>
                
                <button class="btn-apply" id="applyButton" onclick="applyForStall()">Apply for this Stall</button>
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let currentStallId = null;
        let currentStallName = '';
        let currentStallPrice = 0;
        let currentStallDimensions = '';

        function openStallModal(stallId, stallName, price, status, length, width, height) {
            currentStallId = stallId;
            currentStallName = stallName;
            currentStallPrice = price;
            currentStallDimensions = length + 'm × ' + width + 'm × ' + height + 'm';
            
            document.getElementById('modalStallName').textContent = stallName;
            document.getElementById('modalStallPrice').textContent = '₱' + price.toLocaleString('en-US', {minimumFractionDigits: 2});
            document.getElementById('modalStallDimensions').textContent = currentStallDimensions;
            document.getElementById('modalStallStatusText').textContent = status.charAt(0).toUpperCase() + status.slice(1);
            
            // Set form values
            document.getElementById('formStallId').value = stallId;
            document.getElementById('formStallName').value = stallName;
            document.getElementById('formStallPrice').value = price;
            document.getElementById('formStallDimensions').value = currentStallDimensions;
            
            const applyButton = document.getElementById('applyButton');
            if (status === 'available') {
                applyButton.disabled = false;
                applyButton.textContent = 'Apply for this Stall';
            } else {
                applyButton.disabled = true;
                applyButton.textContent = 'Not Available';
            }
            
            document.getElementById('stallModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('stallModal').style.display = 'none';
        }

        function applyForStall() {
            if (confirm('Are you sure you want to apply for "' + currentStallName + '"?')) {
                // Submit the form to application.php
                document.getElementById('applicationForm').submit();
            }
        }

        // Close modal when clicking outside
        document.getElementById('stallModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>
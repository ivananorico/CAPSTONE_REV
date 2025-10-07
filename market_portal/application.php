<?php
session_start();
require_once '../db/Market/market_db.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $stall_id = $_POST['stall_id'] ?? null;
    $stall_name = $_POST['stall_name'] ?? '';
    $stall_price = $_POST['stall_price'] ?? 0;
    $stall_dimensions = $_POST['stall_dimensions'] ?? '';
    $user_id = $_POST['user_id'] ?? null;
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';

    // Validate required fields
    if (!$stall_id || !$user_id) {
        die('Invalid application data.');
    }

    // Check if stall is still available
    try {
        $checkStmt = $pdo->prepare("SELECT status FROM stalls WHERE id = ?");
        $checkStmt->execute([$stall_id]);
        $stall = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$stall || $stall['status'] !== 'available') {
            die('Sorry, this stall is no longer available.');
        }
    } catch (PDOException $e) {
        die('Database error: ' . $e->getMessage());
    }

    // Insert application into database
    try {
        $applicationStmt = $pdo->prepare("
            INSERT INTO stall_applications 
            (stall_id, user_id, applicant_name, applicant_email, stall_name, stall_price, application_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')
        ");
        
        $applicationStmt->execute([
            $stall_id,
            $user_id,
            $name,
            $email,
            $stall_name,
            $stall_price
        ]);

        // Update stall status to reserved
        $updateStmt = $pdo->prepare("UPDATE stalls SET status = 'reserved' WHERE id = ?");
        $updateStmt->execute([$stall_id]);

        $application_id = $pdo->lastInsertId();

    } catch (PDOException $e) {
        die('Database error: ' . $e->getMessage());
    }
} else {
    // If not POST request, redirect back to portal
    header('Location: market_portal.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stall Application - Municipal Services</title>
    <link rel="stylesheet" href="market_portal.css">
    <link rel="stylesheet" href="../citizen_portal/navbar.css">
</head>
<body>
    <?php include '../citizen_portal/navbar.php'; ?>
    
    <div class="portal-container">
        <div class="portal-header">
            <h1>Stall Application Submitted</h1>
            <p>Your market stall application has been received</p>
        </div>

        <div class="application-confirmation">
            <div class="confirmation-icon">✓</div>
            <h2>Application Submitted Successfully!</h2>
            
            <div class="application-details">
                <h3>Application Details</h3>
                <div class="detail-card">
                    <div class="detail-row">
                        <span class="detail-label">Application ID:</span>
                        <span class="detail-value">#<?php echo $application_id; ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Stall Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($stall_name); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Price:</span>
                        <span class="detail-value">₱<?php echo number_format($stall_price, 2); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Dimensions:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($stall_dimensions); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Applicant:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($name); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Application Date:</span>
                        <span class="detail-value"><?php echo date('F j, Y g:i A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value status-pending">Pending Review</span>
                    </div>
                </div>
            </div>

            <div class="next-steps">
                <h3>What Happens Next?</h3>
                <ul>
                    <li>Your application will be reviewed by our market administration team</li>
                    <li>You will receive an email notification once your application is processed</li>
                    <li>If approved, you'll receive further instructions for payment and contract signing</li>
                    <li>Processing typically takes 3-5 business days</li>
                </ul>
            </div>

            <div class="action-buttons">
                <a href="market_portal.php?user_id=<?php echo $user_id; ?>&name=<?php echo urlencode($name); ?>&email=<?php echo urlencode($email); ?>" class="btn-apply">
                    Browse More Stalls
                </a>
                <a href="../citizen_portal/dashboard.php" class="btn-cancel">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
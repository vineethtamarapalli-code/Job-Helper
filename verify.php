<?php
ob_start();

if (file_exists("config.php")) {
    require_once "config.php";
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'config.php missing']);
    exit;
}

header('Content-Type: application/json');
$response = array();

try {
    if (!isset($conn) || !($conn instanceof PDO)) {
        throw new Exception("Database connection failed. Check config.php.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $otp   = $_POST['otp'] ?? '';

        if (empty($email) || empty($otp)) {
            throw new Exception("Email and OTP are required");
        }

        // Fetch OTP using PDO
        $stmt = $conn->prepare("SELECT * FROM users_otp WHERE email = :email ORDER BY id DESC LIMIT 1");
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['otp'] == $otp) {
            $response['status'] = 'success';
            $response['message'] = 'OTP Verified Successfully';
            $_SESSION['otp_verified_email'] = $email;
        } else {
            $response['status'] = 'error';
            $response['message'] = 'Invalid OTP.';
        }
    } else {
        throw new Exception("Invalid request method");
    }
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
?>
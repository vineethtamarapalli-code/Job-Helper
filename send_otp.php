<?php
// Start output buffering
ob_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

if (file_exists("config.php")) {
    require_once "config.php";
} elseif (file_exists("db.php")) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    require_once "db.php";
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database configuration missing.']);
    exit;
}

header('Content-Type: application/json');
$response = array();

try {
    // Check DB Connection (supports both PDO and MySQLi)
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection failed.");
    }
    $isPdo = ($conn instanceof PDO);

    // Check PHPMailer
    $phpMailerPath = __DIR__ . "/PHPMailer/src/PHPMailer.php";
    if (file_exists($phpMailerPath)) {
        require_once __DIR__ . "/PHPMailer/src/PHPMailer.php";
        require_once __DIR__ . "/PHPMailer/src/Exception.php";
        require_once __DIR__ . "/PHPMailer/src/SMTP.php";
    } else {
        throw new Exception("PHPMailer not found.");
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $type = $_POST['type'] ?? 'signup'; // 'signup' or 'forgot'
        $sucCode = $_POST['suc_code'] ?? '';

        if (empty($email)) {
            throw new Exception("Email is required.");
        }

        // --- VALIDATION FOR FORGOT PASSWORD ---
        // Verify that the email belongs to the SUC Code
        if ($type === 'forgot') {
            if (empty($sucCode)) {
                throw new Exception("SUC Code is required for password reset.");
            }

            if ($isPdo) {
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND suc_code = :suc");
                $stmt->execute([':email' => $email, ':suc' => $sucCode]);
                if ($stmt->rowCount() == 0) {
                    throw new Exception("Email does not match the provided SUC Code.");
                }
            } else {
                // Fallback for MySQLi
                $safeEmail = mysqli_real_escape_string($conn, $email);
                $safeSuc = mysqli_real_escape_string($conn, $sucCode);
                $check = mysqli_query($conn, "SELECT id FROM users WHERE email = '$safeEmail' AND suc_code = '$safeSuc'");
                if (mysqli_num_rows($check) == 0) {
                     throw new Exception("Email does not match the provided SUC Code.");
                }
            }
        }

        $otp = rand(100000, 999999);

        // Store OTP
        if ($isPdo) {
            $stmt = $conn->prepare("INSERT INTO users_otp (email, otp) VALUES (:email, :otp)");
            $stmt->execute([':email' => $email, ':otp' => $otp]);
        } else {
            $safeEmail = mysqli_real_escape_string($conn, $email);
            mysqli_query($conn, "INSERT INTO users_otp(email,otp) VALUES('$safeEmail','$otp')");
        }

        // Send Email
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = "vineethsai249@gmail.com"; 
        $mail->Password   = "wqpj vxog ieta wpmz"; 
        $mail->SMTPSecure = 'tls';
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0;

        $mail->setFrom("vineethsai249@gmail.com", "Job Helper Verification");
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = "Your OTP Code";
        $mail->Body    = "<h2>Your Verification Code</h2><p>Your OTP is:</p><h3>$otp</h3>";

        $mail->send();
        
        $response['status'] = 'success';
        $response['message'] = 'OTP sent successfully.';
    } else {
        throw new Exception("Invalid request method.");
    }

} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = $e->getMessage();
} catch (MailException $e) {
    $response['status'] = 'error';
    $response['message'] = "Mailer Error: " . $e->getMessage();
}

ob_end_clean();
echo json_encode($response);
?>
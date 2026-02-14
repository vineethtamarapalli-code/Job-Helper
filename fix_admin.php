<?php
require_once 'config.php';

// Settings
$username = 'vineethadmin';
$password = '986659';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; background: #f9f9f9;'>";
echo "<h2 style='color: #333;'>⚠️ Admin Account Rebuilder</h2>";

try {
    // 1. DROP the table to remove any bad structure
    $conn->exec("DROP TABLE IF EXISTS admins");
    echo "<p>✅ Old admin table deleted (Clean Slate).</p>";

    // 2. CREATE the table fresh with correct settings
    $sql = "CREATE TABLE admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL
    )";
    $conn->exec($sql);
    echo "<p>✅ New admin table created successfully.</p>";

    // 3. INSERT the new admin user
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
    $stmt->execute([$username, $hash]);
    echo "<p>✅ User '<strong>$username</strong>' created.</p>";

    // 4. SELF-TEST (Immediately check if it works)
    $check = $conn->prepare("SELECT password FROM admins WHERE username = ?");
    $check->execute([$username]);
    $stored_user = $check->fetch(PDO::FETCH_ASSOC);

    echo "<hr>";
    
    if ($stored_user && password_verify($password, $stored_user['password'])) {
        echo "<h3 style='color: green;'>🎉 SUCCESS! Account is ready.</h3>";
        echo "<p>You can now login with:</p>";
        echo "<ul><li>User: <strong>$username</strong></li><li>Pass: <strong>$password</strong></li></ul>";
        echo "<br><a href='admin_login.php' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Admin Login</a>";
    } else {
        echo "<h3 style='color: red;'>❌ ERROR: Self-test failed.</h3>";
        echo "<p>The password was saved but could not be verified immediately. This is a server configuration issue.</p>";
    }

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Database Error</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
}

echo "</div>";
?>
-- 1. Add the missing 'status' column to the users table
ALTER TABLE users ADD COLUMN status ENUM('Active', 'Blocked') DEFAULT 'Active';

-- 2. Create the Admin table (if you haven't already)
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

-- 3. Create Settings table for blocking signups (if you haven't already)
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value VARCHAR(255)
);

-- 4. Add default Admin user (User: admin, Pass: password123)
INSERT IGNORE INTO admins (username, password) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- 5. Set default Signup status to Enabled
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('signup_enabled', '1');
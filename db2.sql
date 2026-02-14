CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NULL,          -- User ID who sent it (NULL if sent by Admin)
    receiver_id INT NULL,        -- User ID receiving it (NULL if sent to Admin or Broadcast)
    sender_name VARCHAR(100),    -- Name for display
    message TEXT NOT NULL,
    type ENUM('suggestion', 'personal', 'broadcast') NOT NULL, 
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT DEFAULT 0
);
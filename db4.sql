ALTER TABLE users ADD COLUMN email VARCHAR(191) NOT NULL;
UPDATE users SET email = 'example@gmail.com' WHERE id = 8;

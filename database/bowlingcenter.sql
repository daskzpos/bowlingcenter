-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS bowlingcenter;
USE bowlingcenter;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voornaam VARCHAR(100) NOT NULL,
    achternaam VARCHAR(100) NOT NULL,
    telefoonnummer VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    wachtwoord VARCHAR(255) NOT NULL,
    rol ENUM('gast', 'medewerker', 'directie') DEFAULT 'gast',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optional: Insert a test user (password is 'test123')
INSERT INTO users (voornaam, achternaam, telefoonnummer, email, wachtwoord, rol) 
VALUES ('Test', 'User', '0612345678', 'test@test.nl', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'gast');
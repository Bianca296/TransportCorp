-- Migration: Insert Default Users
-- Created: 2024-01-01 00:00:02

-- Insert Default Admin User
INSERT INTO users (
    username, 
    email, 
    password_hash, 
    first_name, 
    last_name, 
    role, 
    status,
    email_verified
) VALUES (
    'admin',
    'admin@transportcorp.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'System',
    'Administrator',
    'admin',
    'active',
    TRUE
) ON DUPLICATE KEY UPDATE id=id;

-- Insert Sample Customer User
INSERT INTO users (
    username, 
    email, 
    password_hash, 
    first_name, 
    last_name, 
    role, 
    status,
    email_verified
) VALUES (
    'customer1',
    'customer@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'John',
    'Customer',
    'customer',
    'active',
    TRUE
) ON DUPLICATE KEY UPDATE id=id;

-- Insert Sample Employee User
INSERT INTO users (
    username, 
    email, 
    password_hash, 
    first_name, 
    last_name, 
    role, 
    status,
    email_verified
) VALUES (
    'employee1',
    'employee@transportcorp.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Jane',
    'Employee',
    'employee',
    'active',
    TRUE
) ON DUPLICATE KEY UPDATE id=id;

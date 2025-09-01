-- Create contact submissions table
-- Migration: 2024_01_01_000005_create_contact_submissions_table.sql

CREATE TABLE IF NOT EXISTS contact_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NULL,
    subject VARCHAR(200) NOT NULL,
    service_type ENUM('general', 'land', 'air', 'ocean', 'quote', 'support') NOT NULL DEFAULT 'general',
    message TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    status ENUM('new', 'responded', 'closed') NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_service_type (service_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert sample data (optional)
INSERT INTO contact_submissions (name, email, subject, service_type, message, status) VALUES
('John Smith', 'john.smith@example.com', 'Shipping inquiry for electronics', 'air', 'I need to ship electronic equipment from New York to Los Angeles. The package weighs about 25kg. What would be the timeline and cost?', 'new'),
('Sarah Johnson', 'sarah.j@company.com', 'Ocean freight quote request', 'ocean', 'We need to ship container loads from Port of Los Angeles to Shanghai. Looking for regular monthly shipments. Please provide pricing information.', 'responded'),
('Mike Wilson', 'mike.wilson@business.com', 'Land transport for furniture', 'land', 'Need to transport office furniture from Chicago to Detroit. About 500kg total weight. When can this be scheduled?', 'new');

-- Migration: Create Orders Table
-- Created: 2024-01-01 00:00:05
-- Purpose: Store shipping orders with cost information

CREATE TABLE IF NOT EXISTS orders (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT(11) UNSIGNED NOT NULL,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    
    -- Addresses
    pickup_address TEXT NOT NULL,
    delivery_address TEXT NOT NULL,
    
    -- Package details
    package_weight DECIMAL(8,2) NOT NULL,
    package_length DECIMAL(8,2) NULL,
    package_width DECIMAL(8,2) NULL,
    package_height DECIMAL(8,2) NULL,
    package_description VARCHAR(255) NOT NULL,
    
    -- Transport and delivery
    transport_type ENUM('land', 'air', 'ocean') NOT NULL,
    urgent_delivery BOOLEAN DEFAULT FALSE,
    special_instructions TEXT NULL,
    
    -- Cost information
    base_cost DECIMAL(10,2) NOT NULL,
    urgent_surcharge DECIMAL(10,2) DEFAULT 0.00,
    total_cost DECIMAL(10,2) NOT NULL,
    
    -- Order status and tracking
    status ENUM('pending', 'confirmed', 'processing', 'in_transit', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    tracking_number VARCHAR(100) NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    estimated_delivery DATE NULL,
    actual_delivery_date TIMESTAMP NULL,
    
    PRIMARY KEY (id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_transport_type (transport_type),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

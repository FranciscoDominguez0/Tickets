ALTER TABLE ticket_reports 
ADD COLUMN billing_status ENUM('pending', 'confirmed') NOT NULL DEFAULT 'pending' 
AFTER final_price;

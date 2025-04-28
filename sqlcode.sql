-- Create the database
CREATE DATABASE IF NOT EXISTS rv_bank;
USE rv_bank;

-- Create branches table
CREATE TABLE branches (
    branch_id INT PRIMARY KEY AUTO_INCREMENT,
    branch_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT NOT NULL
);

-- Insert branch data
INSERT INTO branches (branch_id, branch_name, email, phone, address) VALUES
(1, 'Whitefield', 'whitefield@rvbank.com', '+91 80 1234 5678', '123 Tech Park, Whitefield'),
(2, 'JP Nagar', 'jpnagar@rvbank.com', '+91 80 2345 6789', '456 Finance Street, JP Nagar'),
(3, 'Yeswantpur', 'yeswantpur@rvbank.com', '+91 80 3456 7890', '789 Business Avenue, Yeswantpur');

-- Create customers table (common customer information)
CREATE TABLE customers (
    customer_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    branch_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
);

-- Create savings_accounts table
CREATE TABLE savings_accounts (
    account_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    branch_id INT NOT NULL,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    open_date DATE NOT NULL,
    initial_deposit DECIMAL(12, 2) NOT NULL,
    current_balance DECIMAL(12, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
);

-- Create current_accounts table
CREATE TABLE current_accounts (
    account_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    branch_id INT NOT NULL,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    current_balance DECIMAL(12, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
);

-- Create loan_accounts table
CREATE TABLE loan_accounts (
    loan_id INT PRIMARY KEY AUTO_INCREMENT,
    customer_id INT NOT NULL,
    branch_id INT NOT NULL,
    loan_number VARCHAR(20) UNIQUE NOT NULL,
    loan_amount DECIMAL(12, 2) NOT NULL,
    term_months INT NOT NULL,
    interest_rate DECIMAL(5, 2) NOT NULL,
    remaining_balance DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
);

-- Create transactions table (parent table for all transaction types)
CREATE TABLE transactions (
    transaction_id INT PRIMARY KEY AUTO_INCREMENT,
    branch_id INT NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(12, 2) NOT NULL,
    FOREIGN KEY (branch_id) REFERENCES branches(branch_id)
);

-- Create transfer_transactions table
CREATE TABLE transfer_transactions (
    transfer_id INT PRIMARY KEY,
    sender_account VARCHAR(20) NOT NULL,
    receiver_account VARCHAR(20) NOT NULL,
    FOREIGN KEY (transfer_id) REFERENCES transactions(transaction_id)
);

-- Create deposit_transactions table
CREATE TABLE deposit_transactions (
    deposit_id INT PRIMARY KEY,
    account_number VARCHAR(20) NOT NULL,
    mode ENUM('cash', 'cheque', 'online') NOT NULL,
    FOREIGN KEY (deposit_id) REFERENCES transactions(transaction_id)
);

-- Create withdraw_transactions table
CREATE TABLE withdraw_transactions (
    withdraw_id INT PRIMARY KEY,
    account_number VARCHAR(20) NOT NULL,
    FOREIGN KEY (withdraw_id) REFERENCES transactions(transaction_id)
);

-- Create account_view_log table (for tracking view account requests)
CREATE TABLE account_view_log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    account_number VARCHAR(20) NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45)
);

-- Function to generate account numbers
DELIMITER //
CREATE FUNCTION generate_account_number(branch_id INT, account_type CHAR(1)) RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE next_id INT;
    DECLARE account_num VARCHAR(20);
    
    -- Get the next available ID for the account type
    IF account_type = 'S' THEN
        SELECT COALESCE(MAX(account_id), 0) + 1 INTO next_id FROM savings_accounts;
    ELSEIF account_type = 'C' THEN
        SELECT COALESCE(MAX(account_id), 0) + 1 INTO next_id FROM current_accounts;
    ELSEIF account_type = 'L' THEN
        SELECT COALESCE(MAX(loan_id), 0) + 1 INTO next_id FROM loan_accounts;
    END IF;
    
    -- Format the account number: BRANCHID-ACCOUNTTYPE-00000ID
    SET account_num = CONCAT(LPAD(branch_id, 2, '0'), '-', account_type, '-', LPAD(next_id, 5, '0'));
    
    RETURN account_num;
END //
DELIMITER ;

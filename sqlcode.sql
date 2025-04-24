-- Branch Table
USE bankdb;
CREATE TABLE IF NOT EXISTS branch (
    BranchID INT AUTO_INCREMENT PRIMARY KEY,
    Location VARCHAR(255)
);

-- Branch Contact Table (for multiple contact numbers per branch)
CREATE TABLE IF NOT EXISTS branch_contact (
    BranchID INT,
    ContactNumber VARCHAR(20),
    PRIMARY KEY (BranchID),
    FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE CASCADE
);
-- Bank Employee Table
CREATE TABLE IF NOT EXISTS bankemployee (
    EmployeeID INT AUTO_INCREMENT PRIMARY KEY,
    Name VARCHAR(100) NOT NULL,
    Position VARCHAR(50),
    Contact VARCHAR(50),
    BranchID INT,
    EmployeeImage BLOB,  -- Added BLOB to store employee images
    FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE SET NULL
);

-- Manager Table
CREATE TABLE IF NOT EXISTS branchmanager (
    EmployeeID INT AUTO_INCREMENT PRIMARY KEY,
	BranchID INT,
	FOREIGN KEY (EmployeeID) REFERENCES bankemployee(EmployeeID) ON DELETE CASCADE,
	FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE SET NULL
);

-- Account Table
CREATE TABLE IF NOT EXISTS account (
    AccountID INT AUTO_INCREMENT PRIMARY KEY,
    Balance DECIMAL(15,2) DEFAULT 0.00,
    OpenDate DATE NOT NULL,
    BranchID INT,
    Status ENUM('Active', 'Inactive', 'Closed') DEFAULT 'Active',
    FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE SET NULL
);

-- Checking Account Table
CREATE TABLE IF NOT EXISTS checkingaccount (
    AccountID INT PRIMARY KEY,
     BranchID INT,
     FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE SET NULL,
    FOREIGN KEY (AccountID) REFERENCES account(AccountID) ON DELETE CASCADE
);

-- Savings Account Table
CREATE TABLE IF NOT EXISTS savingsaccount (
BranchID INT,
FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE SET NULL,
    AccountID INT PRIMARY KEY,
    InterestRate DECIMAL(5,2),
    MinBalance DECIMAL(15,2),
    FOREIGN KEY (AccountID) REFERENCES account(AccountID) ON DELETE CASCADE
);

-- Loan Account Table
CREATE TABLE IF NOT EXISTS loanaccount (
	BranchID INT,
FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE SET NULL,
    AccountID INT PRIMARY KEY,
    LoanAmount DECIMAL(15,2),
    InterestRate DECIMAL(5,2),
    LoanTerm INT,
    FOREIGN KEY (AccountID) REFERENCES account(AccountID) ON DELETE CASCADE
);

-- Transaction Table
CREATE TABLE IF NOT EXISTS transaction (
    TransactionID INT AUTO_INCREMENT PRIMARY KEY,
    AccountID INT,
    Amount DECIMAL(15,2) NOT NULL,
    Type ENUM('Deposit', 'Withdrawal', 'Transfer'),
    Date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Success', 'Pending', 'Failed') DEFAULT 'Success',
    Receipt BLOB,  -- Added BLOB to store transaction receipts as binary data
    FOREIGN KEY (AccountID) REFERENCES account(AccountID) ON DELETE CASCADE
);

-- Deposit Table
CREATE TABLE IF NOT EXISTS deposit (
BranchID INT,
FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE SET NULL,
    TransactionID INT PRIMARY KEY,
    DepositMethod VARCHAR(50),
    FOREIGN KEY (TransactionID) REFERENCES transaction(TransactionID) ON DELETE CASCADE
);

-- Withdrawal Table
CREATE TABLE IF NOT EXISTS withdrawal (
BranchID INT,
FOREIGN KEY (BranchID) REFERENCES branch(BranchID) ON DELETE SET NULL,
    TransactionID INT PRIMARY KEY,
    WithdrawalMethod VARCHAR(50),
    FOREIGN KEY (TransactionID) REFERENCES transaction(TransactionID) ON DELETE CASCADE
);

-- Transfer Table
CREATE TABLE IF NOT EXISTS transfer (
    TransactionID INT PRIMARY KEY,
    ReceiverAccountID INT,
    SenderbranchID INT,
    ReceiverbranchID INT,
FOREIGN KEY (SenderbranchID) REFERENCES branch(BranchID) ON DELETE SET NULL,
FOREIGN KEY (ReceiverbranchID) REFERENCES branch(BranchID) ON DELETE SET NULL,
    FOREIGN KEY (TransactionID) REFERENCES transaction(TransactionID) ON DELETE CASCADE,
    FOREIGN KEY (ReceiverAccountID) REFERENCES account(AccountID) ON DELETE CASCADE
);
SELECT * FROM savingsaccount;


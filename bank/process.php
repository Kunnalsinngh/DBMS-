<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'rv_bank';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set branch IDs
$branch_ids = [
    'Whitefield' => 1,
    'JP Nagar' => 2,
    'Yeswantpur' => 3
];

// Function to check if account exists
function account_exists($conn, $account_number) {
    // Extract account type from account number (format: BRANCHID-ACCOUNTTYPE-00000ID)
    $parts = explode('-', $account_number);
    if (count($parts) < 3) return false;
    
    $account_type = $parts[1];
    
    switch ($account_type) {
        case 'S': 
            $table = 'savings_accounts';
            $column = 'account_number';
            break;
        case 'C': 
            $table = 'current_accounts';
            $column = 'account_number';
            break;
        case 'L': 
            $table = 'loan_accounts';
            $column = 'loan_number';
            break;
        default: 
            return false;
    }
    
    $stmt = $conn->prepare("SELECT 1 FROM $table WHERE $column = ?");
    $stmt->bind_param("s", $account_number);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}

// Function to get account details
function get_account_details($conn, $account_number) {
    $parts = explode('-', $account_number);
    if (count($parts) < 3) return false;
    
    $account_type = $parts[1];
    
    switch ($account_type) {
        case 'S':
            $stmt = $conn->prepare("
                SELECT sa.*, c.name, b.branch_name 
                FROM savings_accounts sa
                JOIN customers c ON sa.customer_id = c.customer_id
                JOIN branches b ON sa.branch_id = b.branch_id
                WHERE sa.account_number = ?
            ");
            break;
        case 'C':
            $stmt = $conn->prepare("
                SELECT ca.*, c.name, b.branch_name 
                FROM current_accounts ca
                JOIN customers c ON ca.customer_id = c.customer_id
                JOIN branches b ON ca.branch_id = b.branch_id
                WHERE ca.account_number = ?
            ");
            break;
        case 'L':
            $stmt = $conn->prepare("
                SELECT la.*, c.name, b.branch_name 
                FROM loan_accounts la
                JOIN customers c ON la.customer_id = c.customer_id
                JOIN branches b ON la.branch_id = b.branch_id
                WHERE la.loan_number = ?
            ");
            break;
        default:
            return false;
    }
    
    $stmt->bind_param("s", $account_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $account = $result->fetch_assoc();
        $account['account_type'] = $account_type;
        return $account;
    }
    
    return false;
}

// Process form based on which form was submitted
if (isset($_POST['accountType'])) {
    // Create Account form submission handling (same as before)
    // ... [keep all the existing create account code] ...
    
} elseif (isset($_POST['transactionType'])) {
    // Make Transaction form submitted
    $transaction_type = $_POST['transactionType'];
    $amount = $_POST['amount'];

    // Validate accounts before processing
    $account_numbers = [];
    $invalid_accounts = [];
    
    if ($transaction_type === 'transfer') {
        $sender_account = $_POST['senderAccount'];
        $receiver_account = $_POST['receiverAccount'];
        
        if (!account_exists($conn, $sender_account)) {
            $invalid_accounts[] = "Sender account ($sender_account) not found";
        }
        if (!account_exists($conn, $receiver_account)) {
            $invalid_accounts[] = "Receiver account ($receiver_account) not found";
        }
        
        $account_numbers = [$sender_account, $receiver_account];
    } else {
        $account_number = $_POST['accountId'];
        if (!account_exists($conn, $account_number)) {
            $invalid_accounts[] = "Account ($account_number) not found";
        }
        $account_numbers = [$account_number];
    }

    // If any invalid accounts, show error
    if (!empty($invalid_accounts)) {
        $error_msg = "Transaction failed:\n" . implode("\n", $invalid_accounts);
        die("<script>alert('$error_msg'); window.location.href = 'index.html';</script>");
    }

    // Determine branch_id from the first account number
    $parts = explode('-', $account_numbers[0]);
    $branch_id = intval($parts[0]);

    // Validate branch_id exists in branches table
    $stmt = $conn->prepare("SELECT branch_id FROM branches WHERE branch_id = ?");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        die("<script>alert('Invalid branch detected!'); window.location.href = 'index.html';</script>");
    }

    // Process the transaction (same as before)
    // ... [keep all the existing transaction processing code] ...
    
} elseif (isset($_POST['accountId']) && !isset($_POST['transactionType'])) {
    // View Account form submitted
    $account_number = $_POST['accountId'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Log the view request
    $stmt = $conn->prepare("INSERT INTO account_view_log (account_number, ip_address) VALUES (?, ?)");
    $stmt->bind_param("ss", $account_number, $ip_address);
    $stmt->execute();
    $stmt->close();
    
    // Get account details
    $account_details = get_account_details($conn, $account_number);
    
    if ($account_details) {
        // Display account details
        $account_type_map = [
            'S' => 'Savings Account',
            'C' => 'Current Account',
            'L' => 'Loan Account'
        ];
        
        $account_type = $account_type_map[$account_details['account_type']];
        $name = $account_details['name'];
        $branch = $account_details['branch_name'];
        
        // Start HTML output
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Account Details - RV Bank</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    margin: 0;
                    padding: 20px;
                    background-color: #f5f5f5;
                }
                .account-container {
                    max-width: 800px;
                    margin: 20px auto;
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #1a73e8;
                    text-align: center;
                    margin-bottom: 30px;
                }
                .account-header {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 20px;
                    padding-bottom: 20px;
                    border-bottom: 1px solid #eee;
                }
                .account-info {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                }
                .info-item {
                    margin-bottom: 15px;
                }
                .info-label {
                    font-weight: bold;
                    color: #555;
                    display: block;
                    margin-bottom: 5px;
                }
                .info-value {
                    font-size: 1.1em;
                }
                .back-btn {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 20px;
                    background-color: #1a73e8;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    transition: background-color 0.3s;
                }
                .back-btn:hover {
                    background-color: #0d47a1;
                }
            </style>
        </head>
        <body>
            <div class="account-container">
                <h1>Account Details</h1>
                
                <div class="account-header">
                    <div>
                        <div class="info-item">
                            <span class="info-label">Account Type</span>
                            <span class="info-value">'.$account_type.'</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Account Holder</span>
                            <span class="info-value">'.$name.'</span>
                        </div>
                    </div>
                    <div>
                        <div class="info-item">
                            <span class="info-label">Branch</span>
                            <span class="info-value">'.$branch.'</span>
                        </div>';
        
        if ($account_details['account_type'] === 'S') {
            echo '<div class="info-item">
                    <span class="info-label">Account Number</span>
                    <span class="info-value">'.$account_details['account_number'].'</span>
                  </div>';
        } elseif ($account_details['account_type'] === 'L') {
            echo '<div class="info-item">
                    <span class="info-label">Loan Number</span>
                    <span class="info-value">'.$account_details['loan_number'].'</span>
                  </div>';
        }
        
        echo '</div>
            </div>
            
            <div class="account-info">';
        
        if ($account_details['account_type'] === 'S') {
            echo '<div class="info-item">
                    <span class="info-label">Open Date</span>
                    <span class="info-value">'.$account_details['open_date'].'</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Current Balance</span>
                    <span class="info-value">₹'.number_format($account_details['current_balance'], 2).'</span>
                  </div>';
        } 
        elseif ($account_details['account_type'] === 'C') {
            echo '<div class="info-item">
                    <span class="info-label">Account Number</span>
                    <span class="info-value">'.$account_details['account_number'].'</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Current Balance</span>
                    <span class="info-value">₹'.number_format($account_details['current_balance'], 2).'</span>
                  </div>';
        } 
        elseif ($account_details['account_type'] === 'L') {
            echo '<div class="info-item">
                    <span class="info-label">Loan Amount</span>
                    <span class="info-value">₹'.number_format($account_details['loan_amount'], 2).'</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Remaining Balance</span>
                    <span class="info-value">₹'.number_format($account_details['remaining_balance'], 2).'</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Term</span>
                    <span class="info-value">'.$account_details['term_months'].' months</span>
                  </div>
                  <div class="info-item">
                    <span class="info-label">Interest Rate</span>
                    <span class="info-value">'.$account_details['interest_rate'].'%</span>
                  </div>';
        }
        
        echo '</div>
            <a href="index.html" class="back-btn">Back to Home</a>
        </div>
        </body>
        </html>';
    } else {
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Account Not Found - RV Bank</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    text-align: center;
                    padding: 50px;
                    background-color: #f5f5f5;
                }
                .error-container {
                    max-width: 500px;
                    margin: 0 auto;
                    background: white;
                    padding: 30px;
                    border-radius: 8px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                h1 {
                    color: #d32f2f;
                }
                .back-btn {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 20px;
                    background-color: #1a73e8;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    transition: background-color 0.3s;
                }
                .back-btn:hover {
                    background-color: #0d47a1;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <h1>Account Not Found</h1>
                <p>The account you requested could not be found. Please check the account number and try again.</p>
                <a href="index.html" class="back-btn">Back to Home</a>
            </div>
        </body>
        </html>';
    }
    exit(); // Stop further execution after displaying the account details
}

// Function to generate account numbers (PHP implementation)
function generate_account_number($conn, $branch_id, $account_type) {
    $prefix = str_pad($branch_id, 2, '0', STR_PAD_LEFT) . '-' . $account_type . '-';
    
    // Determine which table to check based on account type
    $table = '';
    switch ($account_type) {
        case 'S': $table = 'savings_accounts'; break;
        case 'C': $table = 'current_accounts'; break;
        case 'L': $table = 'loan_accounts'; break;
    }
    
    // Get the maximum account ID for this type
    $result = $conn->query("SELECT COALESCE(MAX(account_id), 0) + 1 AS next_id FROM $table");
    $row = $result->fetch_assoc();
    $next_id = $row['next_id'];
    
    return $prefix . str_pad($next_id, 5, '0', STR_PAD_LEFT);
}

$conn->close();
?>

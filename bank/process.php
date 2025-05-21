<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "rv_bank";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Helper function
function clean_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Helper function to check if account exists
function account_exists($conn, $account_number) {
    // Check savings accounts
    $savings_sql = "SELECT 1 FROM savings_accounts WHERE account_number = ?";
    $savings_stmt = $conn->prepare($savings_sql);
    $savings_stmt->bind_param("s", $account_number);
    $savings_stmt->execute();
    if ($savings_stmt->get_result()->num_rows > 0) return true;
    
    // Check current accounts
    $current_sql = "SELECT 1 FROM current_accounts WHERE account_number = ?";
    $current_stmt = $conn->prepare($current_sql);
    $current_stmt->bind_param("s", $account_number);
    $current_stmt->execute();
    if ($current_stmt->get_result()->num_rows > 0) return true;
    
    // Check loan accounts
    $loan_sql = "SELECT 1 FROM loan_accounts WHERE loan_number = ?";
    $loan_stmt = $conn->prepare($loan_sql);
    $loan_stmt->bind_param("s", $account_number);
    $loan_stmt->execute();
    if ($loan_stmt->get_result()->num_rows > 0) return true;
    
    return false;
}

// Helper function to get account balance
function get_account_balance($conn, $account_number) {
    // Check savings accounts
    $savings_sql = "SELECT current_balance FROM savings_accounts WHERE account_number = ?";
    $savings_stmt = $conn->prepare($savings_sql);
    $savings_stmt->bind_param("s", $account_number);
    $savings_stmt->execute();
    $result = $savings_stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['current_balance'];
    }
    
    // Check current accounts
    $current_sql = "SELECT current_balance FROM current_accounts WHERE account_number = ?";
    $current_stmt = $conn->prepare($current_sql);
    $current_stmt->bind_param("s", $account_number);
    $current_stmt->execute();
    $result = $current_stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc()['current_balance'];
    }
    
    return false;
}

// Helper function to update account balances
function update_account_balance($conn, $account_number, $amount, $type) {
    // Check savings accounts first
    $savings_sql = "UPDATE savings_accounts SET current_balance = current_balance " . 
                  ($type == 'deposit' ? '+' : '-') . " ? WHERE account_number = ?";
    $savings_stmt = $conn->prepare($savings_sql);
    $savings_stmt->bind_param("ds", $amount, $account_number);
    $savings_stmt->execute();
    
    if ($savings_stmt->affected_rows > 0) {
        return;
    }
    
    // If not savings, try current accounts
    $current_sql = "UPDATE current_accounts SET current_balance = current_balance " . 
                  ($type == 'deposit' ? '+' : '-') . " ? WHERE account_number = ?";
    $current_stmt = $conn->prepare($current_sql);
    $current_stmt->bind_param("ds", $amount, $account_number);
    $current_stmt->execute();
    
    if ($current_stmt->affected_rows > 0) {
        return;
    }
    
    // If not current, try loan accounts (only for payments)
    if ($type == 'deposit') {
        $loan_sql = "UPDATE loan_accounts SET remaining_balance = remaining_balance - ? WHERE loan_number = ?";
        $loan_stmt = $conn->prepare($loan_sql);
        $loan_stmt->bind_param("ds", $amount, $account_number);
        $loan_stmt->execute();
    }
}

// Helper function to generate account numbers
function generate_account_number($conn, $branch_id, $account_type) {
    $next_id = 0;
    
    if ($account_type == 'S') {
        $sql = "SELECT COALESCE(MAX(account_id), 0) + 1 FROM savings_accounts";
    } elseif ($account_type == 'C') {
        $sql = "SELECT COALESCE(MAX(account_id), 0) + 1 FROM current_accounts";
    } elseif ($account_type == 'L') {
        $sql = "SELECT COALESCE(MAX(loan_id), 0) + 1 FROM loan_accounts";
    }
    
    $result = $conn->query($sql);
    if ($result) {
        $row = $result->fetch_row();
        $next_id = $row[0];
    }
    
    return sprintf("%02d-%s-%05d", $branch_id, $account_type, $next_id);
}

// Helper function to display account details
function display_account_details($account, $account_type) {
    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Account Details</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
        <style>
        :root {
            --primary-blue: #1a73e8;
            --light-blue: #e8f0fe;
            --dark-blue: #0d47a1;
            --white: #ffffff;
            --gray: #f5f5f5;
            --dark-gray: #757575;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--white);
            color: #333;
            line-height: 1.6;
            padding: 20px;
        }
        
        .account-details-container {
            background-color: var(--white);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin: 2rem auto;
            max-width: 800px;
        }
        
        .account-details-container h3 {
            color: var(--primary-blue);
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.8rem;
            border-bottom: 2px solid var(--light-blue);
            padding-bottom: 1rem;
        }
        
        .account-details-container p {
            margin-bottom: 1rem;
            padding: 0.5rem;
            font-size: 1.1rem;
        }
        
        .account-details-container p strong {
            color: var(--dark-blue);
            display: inline-block;
            width: 180px;
        }
        
        .balance-highlight {
            background-color: var(--light-blue);
            padding: 1rem;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.2rem;
            margin: 1.5rem 0;
            text-align: center;
            border-left: 4px solid var(--primary-blue);
        }
        </style>
    </head>
    <body>
        <div class="account-details-container">
            <h3>'.$account_type.' Details</h3>
            <div class="account-details-content">
                <p><strong>Account Number:</strong> '.htmlspecialchars($account['account_number']).'</p>
                <p><strong>Customer Name:</strong> '.htmlspecialchars($account['name']).'</p>
                <p><strong>Branch:</strong> '.htmlspecialchars($account['branch_name']).'</p>
                <div class="balance-highlight">
                    <strong>Current Balance:</strong> ₹'.number_format($account['current_balance'], 2).'
                </div>';

    if ($account_type == 'Savings Account') {
        echo '<p><strong>Open Date:</strong> '.htmlspecialchars($account['open_date']).'</p>
              <p><strong>Initial Deposit:</strong> ₹'.number_format($account['initial_deposit'], 2).'</p>';
    }
    
    echo '    </div>
        </div>
    </body>
    </html>';
}
// Helper function to display loan details
function display_loan_details($loan) {
    echo '<div class="account-details-container">';
    echo '<h3>Loan Account Details</h3>';
    
    echo '<div class="account-details-content">';
    echo '<p><strong>Loan Number:</strong> ' . htmlspecialchars($loan['loan_number']) . '</p>';
    echo '<p><strong>Customer Name:</strong> ' . htmlspecialchars($loan['name']) . '</p>';
    echo '<p><strong>Branch:</strong> ' . htmlspecialchars($loan['branch_name']) . '</p>';
    echo '<p><strong>Loan Amount:</strong> ₹' . number_format($loan['loan_amount'], 2) . '</p>';
    
    echo '<div class="balance-highlight">';
    echo '<strong>Remaining Balance:</strong> ₹' . number_format($loan['remaining_balance'], 2);
    echo '</div>';
    
    echo '<p><strong>Term:</strong> ' . htmlspecialchars($loan['term_months']) . ' months</p>';
    echo '<p><strong>Interest Rate:</strong> ' . htmlspecialchars($loan['interest_rate']) . '%</p>';
    
    echo '</div>'; // Close account-details-content
    echo '</div>'; // Close account-details-container
}

// Handle form type
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["form_type"])) {
        $form_type = clean_input($_POST["form_type"]);

        // -------------------------
        // Account Creation
        // -------------------------
        if ($form_type == "create_account") {
            $name = clean_input($_POST["name"]);
            $branch_name = clean_input($_POST["branch"]);
            $account_type = clean_input($_POST["accountType"]);
            
            // First get branch_id from branch name
            $branch_sql = "SELECT branch_id FROM branches WHERE branch_name = ?";
            $branch_stmt = $conn->prepare($branch_sql);
            $branch_stmt->bind_param("s", $branch_name);
            $branch_stmt->execute();
            $branch_result = $branch_stmt->get_result();
            
            if ($branch_result->num_rows > 0) {
                $branch_row = $branch_result->fetch_assoc();
                $branch_id = $branch_row['branch_id'];
                
                // Insert customer first
                $customer_sql = "INSERT INTO customers (name, branch_id) VALUES (?, ?)";
                $customer_stmt = $conn->prepare($customer_sql);
                $customer_stmt->bind_param("si", $name, $branch_id);
                
                if ($customer_stmt->execute()) {
                    $customer_id = $conn->insert_id;
                    
                    // Now create the appropriate account
                    if ($account_type == "savings") {
                        $open_date = clean_input($_POST["openDate"]);
                        $initial_deposit = clean_input($_POST["initialDeposit"]);
                        
                        // Generate account number
                        $account_number = generate_account_number($conn, $branch_id, 'S');
                        
                        $account_sql = "INSERT INTO savings_accounts (customer_id, branch_id, account_number, open_date, initial_deposit, current_balance) 
                                        VALUES (?, ?, ?, ?, ?, ?)";
                        $account_stmt = $conn->prepare($account_sql);
                        $account_stmt->bind_param("iissdd", $customer_id, $branch_id, $account_number, $open_date, $initial_deposit, $initial_deposit);
                        
                        if ($account_stmt->execute()) {
                            echo '<div class="success-message">Savings account created successfully! Account Number: ' . $account_number . '</div>';
                        } else {
                            echo '<div class="error-message">Error creating savings account: ' . $conn->error . '</div>';
                        }
                    }
                    elseif ($account_type == "current") {
                        // Generate account number
                        $account_number = generate_account_number($conn, $branch_id, 'C');
                        
                        $account_sql = "INSERT INTO current_accounts (customer_id, branch_id, account_number) 
                                      VALUES (?, ?, ?)";
                        $account_stmt = $conn->prepare($account_sql);
                        $account_stmt->bind_param("iis", $customer_id, $branch_id, $account_number);
                        
                        if ($account_stmt->execute()) {
                            echo '<div class="success-message">Current account created successfully! Account Number: ' . $account_number . '</div>';
                        } else {
                            echo '<div class="error-message">Error creating current account: ' . $conn->error . '</div>';
                        }
                    }
                    elseif ($account_type == "loan") {
                        $loan_amount = clean_input($_POST["loanAmount"]);
                        $term = clean_input($_POST["term"]);
                        $interest_rate = clean_input($_POST["interestRate"]);
                        
                        // Generate loan number
                        $loan_number = generate_account_number($conn, $branch_id, 'L');
                        
                        $loan_sql = "INSERT INTO loan_accounts (customer_id, branch_id, loan_number, loan_amount, term_months, interest_rate, remaining_balance) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $loan_stmt = $conn->prepare($loan_sql);
                        $loan_stmt->bind_param("iisdiid", $customer_id, $branch_id, $loan_number, $loan_amount, $term, $interest_rate, $loan_amount);
                        
                        if ($loan_stmt->execute()) {
                            echo '<div class="success-message">Loan account created successfully! Loan Number: ' . $loan_number . '</div>';
                        } else {
                            echo '<div class="error-message">Error creating loan account: ' . $conn->error . '</div>';
                        }
                    }
                } else {
                    echo '<div class="error-message">Error creating customer record: ' . $conn->error . '</div>';
                }
            } else {
                echo '<div class="error-message">Branch not found!</div>';
            }
        }

        // -------------------------
        // Transactions
        // -------------------------
        elseif ($form_type == "make_transaction") {
            $transaction_type = clean_input($_POST["transactionType"]);
            $amount = floatval(clean_input($_POST["amount"]));
            $timestamp = date("Y-m-d H:i:s");
            
            if ($transaction_type == "deposit") {
                $account_number = clean_input($_POST["accountId"]);
                $mode = clean_input($_POST["mode"]);
                
                // First create transaction record
                $transaction_sql = "INSERT INTO transactions (branch_id, amount) VALUES (1, ?)";
                $transaction_stmt = $conn->prepare($transaction_sql);
                $transaction_stmt->bind_param("d", $amount);
                $transaction_stmt->execute();
                $transaction_id = $conn->insert_id;
                
                // Then create deposit transaction
                $deposit_sql = "INSERT INTO deposit_transactions (deposit_id, account_number, mode) VALUES (?, ?, ?)";
                $deposit_stmt = $conn->prepare($deposit_sql);
                $deposit_stmt->bind_param("iss", $transaction_id, $account_number, $mode);
                
                if ($deposit_stmt->execute()) {
                    // Update account balance
                    update_account_balance($conn, $account_number, $amount, 'deposit');
                    echo '<div class="success-message">Deposit successful.</div>';
                } else {
                    echo '<div class="error-message">Error recording deposit: ' . $conn->error . '</div>';
                }
            }
            elseif ($transaction_type == "withdraw") {
                $account_number = clean_input($_POST["accountId"]);
                
                // First check account balance
                $balance = get_account_balance($conn, $account_number);
                if ($balance === false || $balance < $amount) {
                    echo '<div class="error-message">Error: Insufficient funds</div>';
                    exit;
                }
                
                // First create transaction record
                $transaction_sql = "INSERT INTO transactions (branch_id, amount) VALUES (1, ?)";
                $transaction_stmt = $conn->prepare($transaction_sql);
                $transaction_stmt->bind_param("d", $amount);
                $transaction_stmt->execute();
                $transaction_id = $conn->insert_id;
                
                // Then create withdrawal transaction
                $withdraw_sql = "INSERT INTO withdraw_transactions (withdraw_id, account_number) VALUES (?, ?)";
                $withdraw_stmt = $conn->prepare($withdraw_sql);
                $withdraw_stmt->bind_param("is", $transaction_id, $account_number);
                
                if ($withdraw_stmt->execute()) {
                    // Update account balance
                    update_account_balance($conn, $account_number, $amount, 'withdraw');
                    echo '<div class="success-message">Withdrawal successful.</div>';
                } else {
                    echo '<div class="error-message">Error recording withdrawal: ' . $conn->error . '</div>';
                }
            }
            elseif ($transaction_type == "transfer") {
                $sender_account = clean_input($_POST["senderAccount"]);
                $receiver_account = clean_input($_POST["receiverAccount"]);
                $amount = floatval(clean_input($_POST["amount"]));
                
                // Validate both accounts exist
                if (!account_exists($conn, $sender_account)) {
                    echo '<div class="error-message">Error: Sender account does not exist</div>';
                    exit;
                }
                
                if (!account_exists($conn, $receiver_account)) {
                    echo '<div class="error-message">Error: Receiver account does not exist</div>';
                    exit;
                }
                
                // Check sufficient balance
                $sender_balance = get_account_balance($conn, $sender_account);
                if ($sender_balance === false || $sender_balance < $amount) {
                    echo '<div class="error-message">Error: Insufficient funds in sender account</div>';
                    exit;
                }
                
                // Proceed with transfer if all checks pass
                $transaction_sql = "INSERT INTO transactions (branch_id, amount) VALUES (1, ?)";
                $transaction_stmt = $conn->prepare($transaction_sql);
                $transaction_stmt->bind_param("d", $amount);
                $transaction_stmt->execute();
                $transaction_id = $conn->insert_id;
                
                $transfer_sql = "INSERT INTO transfer_transactions (transfer_id, sender_account, receiver_account) VALUES (?, ?, ?)";
                $transfer_stmt = $conn->prepare($transfer_sql);
                $transfer_stmt->bind_param("iss", $transaction_id, $sender_account, $receiver_account);
                
                if ($transfer_stmt->execute()) {
                    update_account_balance($conn, $sender_account, $amount, 'withdraw');
                    update_account_balance($conn, $receiver_account, $amount, 'deposit');
                    echo '<div class="success-message">Transfer successful.</div>';
                } else {
                    echo '<div class="error-message">Error recording transfer: ' . $conn->error . '</div>';
                }
            }
        }

        // -------------------------
        // Account Viewing
        // -------------------------
        elseif ($form_type == "view_account") {
            $account_id = clean_input($_POST["accountId"]);
            
            // Log the view request
            $log_sql = "INSERT INTO account_view_log (account_number, ip_address) VALUES (?, ?)";
            $log_stmt = $conn->prepare($log_sql);
            $log_stmt->bind_param("ss", $account_id, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
            
            // Check savings accounts first
            $savings_sql = "SELECT sa.*, c.name, b.branch_name 
                           FROM savings_accounts sa
                           JOIN customers c ON sa.customer_id = c.customer_id
                           JOIN branches b ON sa.branch_id = b.branch_id
                           WHERE sa.account_number = ?";
            $savings_stmt = $conn->prepare($savings_sql);
            $savings_stmt->bind_param("s", $account_id);
            $savings_stmt->execute();
            $savings_result = $savings_stmt->get_result();
            
            if ($savings_result->num_rows > 0) {
                $account = $savings_result->fetch_assoc();
                display_account_details($account, 'Savings Account');
                exit;
            }
            
            // Check current accounts
            $current_sql = "SELECT ca.*, c.name, b.branch_name 
                           FROM current_accounts ca
                           JOIN customers c ON ca.customer_id = c.customer_id
                           JOIN branches b ON ca.branch_id = b.branch_id
                           WHERE ca.account_number = ?";
            $current_stmt = $conn->prepare($current_sql);
            $current_stmt->bind_param("s", $account_id);
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();
            
            if ($current_result->num_rows > 0) {
                $account = $current_result->fetch_assoc();
                display_account_details($account, 'Current Account');
                exit;
            }
            
            // Check loan accounts
            $loan_sql = "SELECT la.*, c.name, b.branch_name 
                        FROM loan_accounts la
                        JOIN customers c ON la.customer_id = c.customer_id
                        JOIN branches b ON la.branch_id = b.branch_id
                        WHERE la.loan_number = ?";
            $loan_stmt = $conn->prepare($loan_sql);
            $loan_stmt->bind_param("s", $account_id);
            $loan_stmt->execute();
            $loan_result = $loan_stmt->get_result();
            
            if ($loan_result->num_rows > 0) {
                $account = $loan_result->fetch_assoc();
                display_loan_details($account);
                exit;
            }
            
            echo '<div class="error-message">Account not found.</div>';
        }
        else {
            echo '<div class="error-message">Invalid form type: ' . $form_type . '</div>';
        }
    } else {
        echo '<div class="error-message">No form type specified.</div>';
    }
}

$conn->close();
?>

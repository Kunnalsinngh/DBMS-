<?php
// process.php
require 'db_connect.php';

$branchID = intval($_POST['branchSelect']);

// 1) CREATE ACCOUNT
if (isset($_POST['openDate'])) {
    // common account fields
    $openDate = $_POST['openDate'];
    $balance  = floatval($_POST['balance']);
    $status   = $_POST['status'];

    // insert into account
    $stmt = $mysqli->prepare(
      "INSERT INTO account (Balance, OpenDate, BranchID, Status) 
       VALUES (?, ?, ?, ?)"
    );
    $stmt->bind_param("dsis", $balance, $openDate, $branchID, $status);
    $stmt->execute();
    $accountID = $stmt->insert_id;
    $stmt->close();

    // now insert into subtype
    $type = $_POST['accountType'];
    if ($type === 'savings') {
        $ir = floatval($_POST['interestRate']);
        $mb = floatval($_POST['minBalance']);
        $stmt = $mysqli->prepare(
          "INSERT INTO savingsaccount (BranchID, AccountID, InterestRate, MinBalance)
           VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("iidd", $branchID, $accountID, $ir, $mb);
    }
    elseif ($type === 'loan') {
        $loanAmt = floatval($_POST['loanAmount']);
        $loanIr  = floatval($_POST['loanInterestRate']);
        $loanTerm= intval($_POST['loanTerm']);
        $stmt = $mysqli->prepare(
          "INSERT INTO loanaccount (BranchID, AccountID, LoanAmount, InterestRate, LoanTerm)
           VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("iiddi", $branchID, $accountID, $loanAmt, $loanIr, $loanTerm);
    }
    else { // checking/current
        $stmt = $mysqli->prepare(
          "INSERT INTO checkingaccount (AccountID, BranchID) VALUES (?, ?)"
        );
        $stmt->bind_param("ii", $accountID, $branchID);
    }
    $stmt->execute();
    $stmt->close();

    echo "Account #{$accountID} created successfully.";

}
// 2) TRANSACTION
elseif (isset($_POST['amount'])) {
    $accountID = intval($_POST['accountId']);
    $amount    = floatval($_POST['amount']);
    $type      = $_POST['transactionType'];

    // insert into transaction
    $stmt = $mysqli->prepare(
      "INSERT INTO transaction (AccountID, Amount, Type) VALUES (?, ?, ?)"
    );
    $stmt->bind_param("ids", $accountID, $amount, $type);
    $stmt->execute();
    $transID = $stmt->insert_id;
    $stmt->close();

    // subtype
    if ($type === 'Deposit') {
        $method = $_POST['depositMethod'];
        $stmt = $mysqli->prepare(
          "INSERT INTO deposit (BranchID, TransactionID, DepositMethod)
           VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iis", $branchID, $transID, $method);
    }
    elseif ($type === 'Withdrawal') {
        $method = $_POST['withdrawalMethod'];
        $stmt = $mysqli->prepare(
          "INSERT INTO withdrawal (BranchID, TransactionID, WithdrawalMethod)
           VALUES (?, ?, ?)"
        );
        $stmt->bind_param("iis", $branchID, $transID, $method);
    }
    else { // Transfer
        $receiver = intval($_POST['receiverId']);
        $stmt = $mysqli->prepare(
          "INSERT INTO transfer (TransactionID, ReceiverAccountID, SenderbranchID, ReceiverbranchID)
           VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param("iiii", $transID, $receiver, $branchID, $branchID);
    }
    $stmt->execute();
    $stmt->close();

    echo ucfirst($type) . " successful. Transaction ID: {$transID}";
}

$mysqli->close();

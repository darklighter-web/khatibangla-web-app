<?php
/**
 * Credit Flow Test — traces exactly what createOrder would do
 * Visit: https://khatibangla.com/api/credit-test.php
 * DELETE AFTER DEBUGGING
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$db = Database::getInstance();
$steps = [];

// Step 1: Check session
$loggedInCustId = (isCustomerLoggedIn() && getCustomerId() > 0) ? getCustomerId() : 0;
$steps[] = "1. Session: loggedInCustId={$loggedInCustId}";

if (!$loggedInCustId) {
    echo json_encode(['error' => 'NOT LOGGED IN — log in as a customer first', 'steps' => $steps], JSON_PRETTY_PRINT);
    exit;
}

// Step 2: Get customer
$customer = $db->fetch("SELECT * FROM customers WHERE id = ?", [$loggedInCustId]);
$steps[] = "2. Customer found: id={$customer['id']}, phone={$customer['phone']}, store_credit={$customer['store_credit']}";

// Step 3: Simulate form data (what the checkout sends)
$simulatedCreditTk = round(floatval($customer['store_credit']) * floatval(getSetting('store_credit_conversion_rate', '0.60')), 2);
$steps[] = "3. Simulated form: store_credit_used={$simulatedCreditTk} TK (from {$customer['store_credit']} credits × " . getSetting('store_credit_conversion_rate', '0.60') . " rate)";

// Step 4: Run the exact same logic as createOrder
$requestedCreditTk = $simulatedCreditTk;
$creditCustomerId = $loggedInCustId;
$customerId = $loggedInCustId;
$total = 950; // Sample order total

$creditsEnabled = getSetting('store_credits_enabled', '1');
$creditCheckoutEnabled = getSetting('store_credit_checkout', '1');
$steps[] = "4. Settings: enabled='{$creditsEnabled}', checkout='{$creditCheckoutEnabled}'";

$creditsOk = ($creditsEnabled === '1' || $creditsEnabled === 'true' || $creditsEnabled === 'on');
$checkoutOk = ($creditCheckoutEnabled === '1' || $creditCheckoutEnabled === 'true' || $creditCheckoutEnabled === 'on');
$steps[] = "5. Conditions: requestedTk={$requestedCreditTk} > 0? " . ($requestedCreditTk > 0 ? 'YES' : 'NO') . 
           ", custId={$creditCustomerId} > 0? " . ($creditCustomerId > 0 ? 'YES' : 'NO') .
           ", creditsOk={$creditsOk}, checkoutOk={$checkoutOk}";

$allConditionsMet = ($requestedCreditTk > 0 && $creditCustomerId > 0 && $creditsOk && $checkoutOk);
$steps[] = "6. ALL conditions met? " . ($allConditionsMet ? 'YES ✓' : 'NO ✗ — THIS IS WHY CREDITS FAIL');

if ($allConditionsMet) {
    $creditRate = floatval(getSetting('store_credit_conversion_rate', '0.60'));
    if ($creditRate <= 0) $creditRate = 0.60;
    
    $columnBalance = floatval($customer['store_credit']);
    
    $txnRow = $db->fetch("SELECT COALESCE(SUM(amount), 0) as bal FROM store_credit_transactions WHERE customer_id = ?", [$creditCustomerId]);
    $txnBalance = max(0, floatval($txnRow['bal']));
    
    $availableCredits = max($columnBalance, $txnBalance);
    $steps[] = "7. Balances: column={$columnBalance}, txn={$txnBalance}, available={$availableCredits}";
    
    if ($availableCredits >= 1) {
        $availableTk = round($availableCredits * $creditRate, 2);
        $storeCreditUsed = min($requestedCreditTk, $availableTk, $total);
        $storeCreditUsed = max(0, round($storeCreditUsed, 2));
        $creditsDeducted = $creditRate > 0 ? round($storeCreditUsed / $creditRate, 2) : 0;
        $creditsDeducted = min($creditsDeducted, $availableCredits);
        $newTotal = $total - $storeCreditUsed;
        
        $steps[] = "8. WOULD APPLY: creditUsedTk={$storeCreditUsed}, creditsDeducted={$creditsDeducted}, orderTotal={$total} → newTotal={$newTotal}";
        $steps[] = "9. addStoreCredit({$customerId}, -{$creditsDeducted}, 'spend', 'order', orderId, 'description') WOULD be called";
    } else {
        $steps[] = "8. FAILED: Available credits ({$availableCredits}) < 1";
    }
} else {
    $steps[] = "7-9. SKIPPED — conditions not met";
}

// Step 10: Check what FormData would actually contain
$steps[] = "10. FRONTEND CHECK: Open browser console and place an order with credit checked. Look for '[CREDIT] Sending store_credit_used=XXX' in console.";
$steps[] = "11. If you see store_credit_used=0, the checkbox or hidden input has a bug.";
$steps[] = "12. If you see store_credit_used>0 but order still shows 0, the backend is the problem.";

// Write to file for persistent logging
$logFile = dirname(__DIR__) . '/tmp/credit-debug.log';
@mkdir(dirname($logFile), 0755, true);
file_put_contents($logFile, date('Y-m-d H:i:s') . " — " . implode("\n", $steps) . "\n\n", FILE_APPEND);
$steps[] = "Debug log written to: {$logFile}";

echo json_encode([
    'summary' => $allConditionsMet ? 'Credit logic WOULD work — check frontend FormData' : 'Credit conditions FAILED — see steps',
    'steps' => $steps,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

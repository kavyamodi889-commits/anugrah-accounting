<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security check
if (!isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

require_once 'db_config.php';
require_once 'whatsapp_api_config.php';

header('Content-Type: application/json');

// Check if WhatsAppBusinessAPI class exists
if (!class_exists('WhatsAppBusinessAPI')) {
    echo json_encode([
        'success' => false, 
        'error' => 'WhatsApp API configuration not found. Please check whatsapp_api_config.php file exists and is properly configured.'
    ]);
    exit();
}

// Initialize WhatsApp API
try {
    $whatsappAPI = new WhatsAppBusinessAPI();
    
    // Check if API is configured
    if (!$whatsappAPI->isConfigured()) {
        echo json_encode([
            'success' => false,
            'error' => 'WhatsApp Business API is not configured. Please update credentials in whatsapp_api_config.php'
        ]);
        exit();
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error initializing WhatsApp API: ' . $e->getMessage()
    ]);
    exit();
}

// Get action
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'send_bulk_whatsapp_api') {
    // Send to all customers
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit();
    }
    
    // Get all unique customers with phone numbers
    $customerQuery = "
    SELECT name, email, phone
    FROM (
        SELECT DISTINCT name, email, phone FROM users WHERE phone IS NOT NULL AND phone != '' AND TRIM(phone) != ''
        UNION SELECT DISTINCT name, email, phone FROM contact_messages WHERE phone IS NOT NULL AND phone != '' AND TRIM(phone) != ''
        UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM gst_registrations WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM accounting_services WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM msme_registrations WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM fssai_licences WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM cma_data WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM tax_planning WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
    ) as all_customers
    GROUP BY phone
    ORDER BY name";
    
    $result = $conn->query($customerQuery);
    
    if (!$result) {
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $recipients = array();
    $seenPhones = array();
    
    while ($row = $result->fetch_assoc()) {
        $phone = $row['phone'];
        if (in_array($phone, $seenPhones)) continue;
        
        $seenPhones[] = $phone;
        $personalizedMessage = str_replace('{name}', $row['name'], $message);
        
        $recipients[] = array(
            'phone' => $phone,
            'name' => $row['name'],
            'message' => $personalizedMessage
        );
    }
    
    if (empty($recipients)) {
        echo json_encode(['success' => false, 'error' => 'No customers found with phone numbers']);
        exit();
    }
    
    // Send bulk messages
    $bulkResults = $whatsappAPI->sendBulkMessages($recipients);
    
    // Log each message in database
    $adminId = $_SESSION['admin_id'];
    $insertStmt = $conn->prepare("INSERT INTO whatsapp_messages (admin_id, recipient_phone, recipient_name, message, status, message_id, error_message, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    foreach ($bulkResults['details'] as $detail) {
        $status = $detail['success'] ? 'sent' : 'failed';
        $messageId = isset($detail['message_id']) ? $detail['message_id'] : null;
        $errorMsg = isset($detail['error']) ? $detail['error'] : null;
        
        $msgContent = isset($detail['message']) ? $detail['message'] : $message;
        $insertStmt->bind_param("issssss", 
            $adminId, 
            $detail['phone'], 
            $detail['name'], 
            $msgContent,
            $status, 
            $messageId, 
            $errorMsg
        );
        $insertStmt->execute();
    }
    $insertStmt->close();
    
    echo json_encode([
        'success' => true,
        'results' => $bulkResults
    ]);
    
} elseif ($action === 'send_to_selected_api') {
    // Send to selected customers
    $message = isset($_POST['message']) ? $_POST['message'] : '';
    $selectedCustomers = isset($_POST['selected_customers']) ? json_decode($_POST['selected_customers'], true) : array();
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
        exit();
    }
    
    if (empty($selectedCustomers)) {
        echo json_encode(['success' => false, 'error' => 'No customers selected']);
        exit();
    }
    
    $recipients = array();
    foreach ($selectedCustomers as $customer) {
        $personalizedMessage = str_replace('{name}', $customer['name'], $message);
        $recipients[] = array(
            'phone' => $customer['phone'],
            'name' => $customer['name'],
            'message' => $personalizedMessage
        );
    }
    
    // Send bulk messages
    $bulkResults = $whatsappAPI->sendBulkMessages($recipients);
    
    // Log each message in database
    $adminId = $_SESSION['admin_id'];
    $insertStmt = $conn->prepare("INSERT INTO whatsapp_messages (admin_id, recipient_phone, recipient_name, message, status, message_id, error_message, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    foreach ($bulkResults['details'] as $detail) {
        $status = $detail['success'] ? 'sent' : 'failed';
        $messageId = isset($detail['message_id']) ? $detail['message_id'] : null;
        $errorMsg = isset($detail['error']) ? $detail['error'] : null;
        
        $msgText = isset($detail['message']) ? $detail['message'] : $message;
        $insertStmt->bind_param("issssss", 
            $adminId, 
            $detail['phone'], 
            $detail['name'], 
            $msgText,
            $status, 
            $messageId, 
            $errorMsg
        );
        $insertStmt->execute();
    }
    $insertStmt->close();
    
    echo json_encode([
        'success' => true,
        'results' => $bulkResults
    ]);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

$conn->close();
?>
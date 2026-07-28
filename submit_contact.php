<?php
// Set JSON header
header('Content-Type: application/json');

// Include database configuration
require_once 'includes/db.php';

// Initialize response array
$response = array('success' => false, 'message' => '');

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

try {
    // Get and sanitize form data
    $name = isset($_POST['name']) ? sanitizeInput($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
    $service_interest = isset($_POST['service_interest']) ? sanitizeInput($_POST['service_interest']) : '';
    $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($phone) || empty($service_interest) || empty($message)) {
        $response['message'] = 'Please fill in all required fields';
        echo json_encode($response);
        exit;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Please enter a valid email address';
        echo json_encode($response);
        exit;
    }
    
    // Validate phone (10 digits)
    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $response['message'] = 'Please enter a valid 10-digit phone number';
        echo json_encode($response);
        exit;
    }
    
    // Get or create user ID
    $userId = getUserIdByEmail($conn, $email, $name, $phone);
    
    // Set default values
    $status = 'New';
    $priority = 'Normal';
    $response_sent = 0;
    
    // Insert contact message into database
    $stmt = $conn->prepare("INSERT INTO contact_messages (user_id, name, email, phone, service_interest, message, status, priority, response_sent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("isssssssi", $userId, $name, $email, $phone, $service_interest, $message, $status, $priority, $response_sent);
    
    if ($stmt->execute()) {
        $messageId = $stmt->insert_id;
        
        // Log activity
        logActivity($conn, $userId, 'Contact Message Sent', "Sent contact message for $service_interest");
        
        $response['success'] = true;
        $response['message'] = 'Thank you for contacting us! We will get back to you shortly.';
        
        // Optional: Send email notification to admin
        // You can uncomment and configure this section
        /*
        $to = "anugrah0369@gmail.com";
        $subject = "New Contact Message from $name";
        $email_body = "Name: $name\nEmail: $email\nPhone: $phone\nService Interest: $service_interest\n\nMessage:\n$message";
        $headers = "From: noreply@anugrah.com\r\n";
        $headers .= "Reply-To: $email\r\n";
        
        mail($to, $subject, $email_body, $headers);
        */
        
    } else {
        $response['message'] = 'Failed to send message. Please try again.';
        error_log("Contact message insert error: " . $stmt->error);
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    $response['message'] = 'An error occurred. Please try again later.';
    error_log("Contact submission error: " . $e->getMessage());
}

// Close database connection
if ($conn) {
    $conn->close();
}

// Return JSON response
echo json_encode($response);
?>

<?php
/**
 * WhatsApp Business API Configuration
 * Anugrah Accounting Services
 * 
 * This class handles all WhatsApp Business API interactions
 * All messages are sent from the admin's registered WhatsApp Business number
 */

class WhatsAppBusinessAPI {
    private $apiUrl;
    private $phoneNumberId;
    private $accessToken;
    private $adminWhatsAppNumber = '918000687342'; // Admin's WhatsApp Business Number
    
    public function __construct() {
        // IMPORTANT: Replace these with your actual WhatsApp Business API credentials
        // Get these from Meta Business Manager (https://business.facebook.com)
        
        // Option 1: Direct credentials (for testing)
        $this->phoneNumberId = 'YOUR_PHONE_NUMBER_ID'; // Replace with your Phone Number ID
        $this->accessToken = 'YOUR_WHATSAPP_ACCESS_TOKEN'; // Replace with your Access Token
        
        // Option 2: Environment variables (recommended for production)
        // Uncomment these lines and comment out the lines above
        // $this->phoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID');
        // $this->accessToken = getenv('WHATSAPP_ACCESS_TOKEN');
        
        $this->apiUrl = "https://graph.facebook.com/v18.0/{$this->phoneNumberId}/messages";
    }
    
    /**
     * Send WhatsApp message via Business API
     * 
     * @param string $recipientPhone Phone number with country code (e.g., 919876543210)
     * @param string $message Message text
     * @return array Response with status and message ID
     */
    public function sendMessage($recipientPhone, $message) {
        // Clean and format phone number
        $recipientPhone = $this->formatPhoneNumber($recipientPhone);
        
        // Prepare message payload
        $data = array(
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipientPhone,
            'type' => 'text',
            'text' => array(
                'preview_url' => false,
                'body' => $message
            )
        );
        
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            return array(
                'success' => false,
                'error' => 'cURL is not enabled on this server. Please enable cURL extension.',
                'recipient' => $recipientPhone
            );
        }
        
        // Send API request
        $ch = curl_init($this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->accessToken,
            'Content-Type: application/json'
        ));
        
        // Set timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        // Disable SSL verification for local testing (enable for production)
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        // curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Handle cURL errors
        if ($curlError) {
            return array(
                'success' => false,
                'error' => 'Network error: ' . $curlError,
                'recipient' => $recipientPhone
            );
        }
        
        $result = json_decode($response, true);
        
        // Check if message was sent successfully
        if ($httpCode == 200 && isset($result['messages'][0]['id'])) {
            return array(
                'success' => true,
                'message_id' => $result['messages'][0]['id'],
                'recipient' => $recipientPhone
            );
        } else {
            $errorMsg = 'Unknown error';
            if (isset($result['error']['message'])) {
                $errorMsg = $result['error']['message'];
            } elseif (isset($result['error']['error_user_msg'])) {
                $errorMsg = $result['error']['error_user_msg'];
            }
            
            return array(
                'success' => false,
                'error' => $errorMsg,
                'error_code' => isset($result['error']['code']) ? $result['error']['code'] : null,
                'recipient' => $recipientPhone,
                'http_code' => $httpCode
            );
        }
    }
    
    /**
     * Send bulk messages to multiple recipients
     * 
     * @param array $recipients Array of arrays with 'phone', 'name', and 'message' keys
     * @param callable $progressCallback Optional callback function for progress updates
     * @return array Summary of sent/failed messages
     */
    public function sendBulkMessages($recipients, $progressCallback = null) {
        $results = array(
            'total' => count($recipients),
            'sent' => 0,
            'failed' => 0,
            'details' => array()
        );
        
        foreach ($recipients as $index => $recipient) {
            // Rate limiting: Wait 1 second between messages to comply with WhatsApp limits
            if ($index > 0) {
                sleep(1);
            }
            
            $phone = isset($recipient['phone']) ? $recipient['phone'] : '';
            $message = isset($recipient['message']) ? $recipient['message'] : '';
            $name = isset($recipient['name']) ? $recipient['name'] : 'Customer';
            
            // Skip if phone or message is empty
            if (empty($phone) || empty($message)) {
                $results['details'][] = array(
                    'name' => $name,
                    'phone' => $phone,
                    'success' => false,
                    'message_id' => null,
                    'error' => 'Phone number or message is empty'
                );
                $results['failed']++;
                continue;
            }
            
            // Send message
            $result = $this->sendMessage($phone, $message);
            
            // Store result
            $results['details'][] = array(
                'name' => $name,
                'phone' => $phone,
                'success' => $result['success'],
                'message_id' => isset($result['message_id']) ? $result['message_id'] : null,
                'error' => isset($result['error']) ? $result['error'] : null
            );
            
            // Update counters
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
            
            // Call progress callback if provided
            if ($progressCallback && is_callable($progressCallback)) {
                call_user_func($progressCallback, $index + 1, $results['total'], $result);
            }
        }
        
        return $results;
    }
    
    /**
     * Format phone number to international format
     * 
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Add country code if not present (assuming India - 91)
        if (strlen($phone) == 10) {
            $phone = '91' . $phone;
        }
        
        return $phone;
    }
    
    /**
     * Get admin's WhatsApp Business number
     * 
     * @return string Admin's WhatsApp number
     */
    public function getAdminNumber() {
        return $this->adminWhatsAppNumber;
    }
    
    /**
     * Verify API credentials
     * 
     * @return bool True if credentials are valid
     */
    public function verifyCredentials() {
        // Check if credentials are set
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            return false;
        }
        
        // Check if credentials are placeholder values
        if ($this->phoneNumberId === 'YOUR_PHONE_NUMBER_ID' || 
            $this->accessToken === 'YOUR_WHATSAPP_ACCESS_TOKEN') {
            return false;
        }
        
        // Check if cURL is available
        if (!function_exists('curl_init')) {
            return false;
        }
        
        // Try to fetch phone number details to verify credentials
        $ch = curl_init("https://graph.facebook.com/v18.0/{$this->phoneNumberId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->accessToken
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode == 200;
    }
    
    /**
     * Get phone number information
     * 
     * @return array Phone number details or error
     */
    public function getPhoneNumberInfo() {
        if (!function_exists('curl_init')) {
            return array(
                'success' => false,
                'error' => 'cURL is not enabled on this server'
            );
        }
        
        $ch = curl_init("https://graph.facebook.com/v18.0/{$this->phoneNumberId}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->accessToken
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            return json_decode($response, true);
        } else {
            return array(
                'success' => false,
                'error' => 'Failed to fetch phone number information'
            );
        }
    }
    
    /**
     * Check if API is configured
     * 
     * @return bool True if credentials are set (not necessarily valid)
     */
    public function isConfigured() {
        return !empty($this->phoneNumberId) && 
               !empty($this->accessToken) &&
               $this->phoneNumberId !== 'YOUR_PHONE_NUMBER_ID' &&
               $this->accessToken !== 'YOUR_WHATSAPP_ACCESS_TOKEN';
    }
}
?>
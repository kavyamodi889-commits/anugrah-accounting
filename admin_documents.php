<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$adminName = $_SESSION['admin_name'];
$adminRole = $_SESSION['admin_role'];

// ==================== NEW: ADVANCED FUNCTIONS ====================

// Function to get document analytics
function getDocumentAnalytics($conn) {
    $analytics = array();
    
    // Total documents by service type
    $query = "SELECT service_type, COUNT(*) as count FROM documents GROUP BY service_type";
    $result = $conn->query($query);
    $analytics['by_service'] = array();
    while ($row = $result->fetch_assoc()) {
        $analytics['by_service'][$row['service_type']] = $row['count'];
    }
    
    // Documents uploaded this month
    $query = "SELECT COUNT(*) as count FROM documents WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    $analytics['this_month'] = $conn->query($query)->fetch_assoc()['count'];
    
    // Most active users
    $query = "SELECT u.name, u.email, COUNT(d.id) as doc_count FROM documents d 
              JOIN users u ON d.user_id = u.id 
              GROUP BY d.user_id ORDER BY doc_count DESC LIMIT 5";
    $result = $conn->query($query);
    $analytics['top_users'] = array();
    while ($row = $result->fetch_assoc()) {
        $analytics['top_users'][] = $row;
    }
    
    return $analytics;
}

// Function to generate document version history
function createDocumentVersion($conn, $docId, $adminId) {
    $query = "SELECT * FROM documents WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $docId);
    $stmt->execute();
    $doc = $stmt->get_result()->fetch_assoc();
    
    if ($doc) {
        $versionQuery = "INSERT INTO document_versions (document_id, file_name, file_path, file_size, version_number, created_by) 
                        VALUES (?, ?, ?, ?, (SELECT COALESCE(MAX(version_number), 0) + 1 FROM document_versions WHERE document_id = ?), ?)";
        $stmt = $conn->prepare($versionQuery);
        $stmt->bind_param("issiii", $docId, $doc['file_name'], $doc['file_path'], $doc['file_size'], $docId, $adminId);
        return $stmt->execute();
    }
    return false;
}

// Function to check for duplicate documents (by hash)
function checkDuplicateDocument($filePath) {
    if (file_exists($filePath)) {
        return md5_file($filePath);
    }
    return null;
}

// Function to generate shareable link for document
function generateShareableLink($conn, $docId, $expiryHours = 24) {
    $token = bin2hex(random_bytes(32));
    $expiryDate = date('Y-m-d H:i:s', strtotime("+$expiryHours hours"));
    
    $query = "INSERT INTO document_shares (document_id, share_token, expires_at) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $docId, $token, $expiryDate);
    
    if ($stmt->execute()) {
        return $token;
    }
    return null;
}

// Function to bulk download documents
function createBulkDownloadZip($documentIds, $conn) {
    $zip = new ZipArchive();
    $zipFileName = 'uploads/bulk_downloads/documents_' . time() . '.zip';
    
    if (!file_exists('uploads/bulk_downloads')) {
        mkdir('uploads/bulk_downloads', 0777, true);
    }
    
    if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
        $stmt = $conn->prepare("SELECT file_name, file_path FROM documents WHERE id = ?");
        
        foreach ($documentIds as $docId) {
            $stmt->bind_param("i", $docId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($doc = $result->fetch_assoc()) {
                if (file_exists($doc['file_path'])) {
                    $zip->addFile($doc['file_path'], $doc['file_name']);
                }
            }
        }
        $zip->close();
        return $zipFileName;
    }
    return null;
}

// Function to auto-tag documents using AI/ML (simplified version)
function autoTagDocument($fileName, $description) {
    $tags = array();
    $keywords = array(
        'identity' => array('pan', 'aadhaar', 'passport', 'voter', 'driving'),
        'financial' => array('bank', 'statement', 'balance', 'invoice', 'receipt'),
        'registration' => array('gst', 'msme', 'fssai', 'certificate', 'license'),
        'tax' => array('itr', 'income', 'tax', 'return', 'tds')
    );
    
    $searchText = strtolower($fileName . ' ' . $description);
    
    foreach ($keywords as $tag => $words) {
        foreach ($words as $word) {
            if (strpos($searchText, $word) !== false) {
                $tags[] = $tag;
                break;
            }
        }
    }
    
    return array_unique($tags);
}

// Function to send document notification
function sendDocumentNotification($conn, $userId, $docId, $action) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        $title = "Document " . ucfirst($action);
        $message = "Your document has been " . $action . ". Please check your dashboard for details.";
        
        $notifStmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message, type) VALUES (?, ?, ?, 'document')");
        $notifStmt->bind_param("iss", $userId, $title, $message);
        $notifStmt->execute();
        
        // Optional: Send email notification
        // mail($user['email'], $title, $message);
    }
}

// ==================== EXISTING HANDLERS WITH ENHANCEMENTS ====================

// Handle file upload with enhancements
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_document'])) {
    $userId = $_POST['user_id'];
    $serviceType = $_POST['service_type'];
    $serviceId = $_POST['service_id'];
    $documentType = $_POST['document_type'];
    $description = $_POST['description'];
    
    $uploadDir = 'uploads/documents/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    if (isset($_FILES['document']) && $_FILES['document']['error'] == 0) {
        $fileName = $_FILES['document']['name'];
        $fileSize = $_FILES['document']['size'];
        $fileTmpName = $_FILES['document']['tmp_name'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowedExt = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'xls', 'xlsx');
        
        if (in_array($fileExt, $allowedExt)) {
            // Check file size (max 10MB)
            if ($fileSize <= 10485760) {
                // Generate unique filename
                $newFileName = uniqid() . '_' . time() . '.' . $fileExt;
                $filePath = $uploadDir . $newFileName;
                
                // Check for duplicate (by hash)
                $fileHash = md5_file($fileTmpName);
                $dupCheck = $conn->prepare("SELECT id, file_name FROM documents WHERE user_id = ? AND file_size = ?");
                $dupCheck->bind_param("ii", $userId, $fileSize);
                $dupCheck->execute();
                $dupResult = $dupCheck->get_result();
                
                $isDuplicate = false;
                while ($existingDoc = $dupResult->fetch_assoc()) {
                    if (file_exists('uploads/documents/' . basename($existingDoc['file_name']))) {
                        $existingHash = md5_file('uploads/documents/' . basename($existingDoc['file_name']));
                        if ($existingHash === $fileHash) {
                            $isDuplicate = true;
                            $error = "Duplicate document detected! A similar file already exists.";
                            break;
                        }
                    }
                }
                
                if (!$isDuplicate && move_uploaded_file($fileTmpName, $filePath)) {
                    // Auto-tag document
                    $tags = autoTagDocument($fileName, $description);
                    $tagsJson = json_encode($tags);
                    
                    // Save to database
                    $stmt = $conn->prepare("INSERT INTO documents (user_id, service_type, service_id, document_type, file_name, file_path, file_size, file_extension, uploaded_by, description, tags) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isiissiisss", $userId, $serviceType, $serviceId, $documentType, $fileName, $filePath, $fileSize, $fileExt, $_SESSION['admin_id'], $description, $tagsJson);
                    
                    if ($stmt->execute()) {
                        $docId = $stmt->insert_id;
                        
                        // Send notification to user
                        sendDocumentNotification($conn, $userId, $docId, 'uploaded');
                        
                        // Log activity
                        logActivity($conn, $userId, 'DOCUMENT_UPLOAD', "Document uploaded: $fileName");
                        
                        header('Location: admin_documents.php?msg=uploaded');
                        exit();
                    }
                    $stmt->close();
                }
            } else {
                $error = "File size exceeds 10MB limit.";
            }
        } else {
            $error = "Invalid file type. Allowed: PDF, DOC, DOCX, JPG, PNG, XLS, XLSX";
        }
    }
}

// Handle bulk download
if (isset($_POST['bulk_download']) && isset($_POST['doc_ids'])) {
    $docIds = $_POST['doc_ids'];
    if (!empty($docIds)) {
        $zipFile = createBulkDownloadZip($docIds, $conn);
        if ($zipFile) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
            header('Content-Length: ' . filesize($zipFile));
            readfile($zipFile);
            unlink($zipFile); // Delete after download
            exit();
        }
    }
}

// Handle document share
if (isset($_GET['share']) && isset($_GET['doc_id'])) {
    $docId = intval($_GET['doc_id']);
    $token = generateShareableLink($conn, $docId, 48); // 48 hours expiry
    if ($token) {
        $shareUrl = "http://" . $_SERVER['HTTP_HOST'] . "/document_share.php?token=" . $token;
        $_SESSION['share_link'] = $shareUrl;
        header('Location: admin_documents.php?msg=share_created');
        exit();
    }
}

// Handle document delete
if (isset($_GET['delete'])) {
    $docId = $_GET['delete'];
    
    // Get file path
    $stmt = $conn->prepare("SELECT file_path, user_id FROM documents WHERE id = ?");
    $stmt->bind_param("i", $docId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($result) {
        if (file_exists($result['file_path'])) {
            unlink($result['file_path']); // Delete file
        }
        
        // Delete from database
        $conn->query("DELETE FROM documents WHERE id = $docId");
        
        // Send notification
        sendDocumentNotification($conn, $result['user_id'], $docId, 'deleted');
        
        header('Location: admin_documents.php?msg=deleted');
        exit();
    }
}

// Get analytics
$analytics = getDocumentAnalytics($conn);

// Get filter parameters
$filterService = isset($_GET['service']) ? $_GET['service'] : 'all';
$filterType = isset($_GET['type']) ? $_GET['type'] : 'all';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with enhanced search - Include BOTH admin uploaded AND user uploaded documents
$query = "SELECT d.*, 
          COALESCE(u.name, d.user_name) as user_name, 
          COALESCE(u.email, d.user_email) as email, 
          a.full_name as uploaded_by_name,
          CASE 
            WHEN d.uploaded_by IS NOT NULL THEN 'Admin'
            ELSE 'User'
          END as upload_source
          FROM documents d 
          LEFT JOIN users u ON d.user_id = u.id 
          LEFT JOIN admin_users a ON d.uploaded_by = a.id 
          WHERE 1=1";

if ($filterService != 'all') {
    $query .= " AND d.service_type = '" . $conn->real_escape_string($filterService) . "'";
}

if ($filterType != 'all') {
    $query .= " AND d.document_type = '" . $conn->real_escape_string($filterType) . "'";
}

if (!empty($searchQuery)) {
    $searchQuery = $conn->real_escape_string($searchQuery);
    $query .= " AND (d.file_name LIKE '%$searchQuery%' OR u.name LIKE '%$searchQuery%' OR u.email LIKE '%$searchQuery%' OR d.description LIKE '%$searchQuery%')";
}

$query .= " ORDER BY d.created_at DESC";

// Debug: Check if query returns results
$documents = $conn->query($query);
if (!$documents) {
    $error = "Database error: " . $conn->error;
}

// Also get user-uploaded documents from various service tables
function getUserUploadedDocuments($conn) {
    $allDocs = array();
    
    // Get documents from GST Registrations
    $gstQuery = "SELECT 
        g.id as service_id,
        g.user_id,
        g.user_name,
        g.user_email,
        'GST Registration' as service_type,
        g.documents,
        g.created_at,
        g.certificate_path
        FROM gst_registrations g 
        WHERE g.documents IS NOT NULL OR g.certificate_path IS NOT NULL";
    
    $result = $conn->query($gstQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['documents'])) {
                $docs = json_decode($row['documents'], true);
                if (is_array($docs)) {
                    foreach ($docs as $docPath) {
                        if (!empty($docPath) && file_exists($docPath)) {
                            $allDocs[] = array(
                                'service_type' => $row['service_type'],
                                'service_id' => $row['service_id'],
                                'user_id' => $row['user_id'],
                                'user_name' => $row['user_name'],
                                'user_email' => $row['user_email'],
                                'file_path' => $docPath,
                                'file_name' => basename($docPath),
                                'file_size' => filesize($docPath),
                                'created_at' => $row['created_at'],
                                'upload_source' => 'User'
                            );
                        }
                    }
                }
            }
        }
    }
    
    // Get documents from FSSAI
    $fssaiQuery = "SELECT 
        f.id as service_id,
        f.user_id,
        f.user_name,
        f.user_email,
        'FSSAI' as service_type,
        f.documents,
        f.created_at
        FROM fssai_licences f 
        WHERE f.documents IS NOT NULL";
    
    $result = $conn->query($fssaiQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['documents'])) {
                $docs = json_decode($row['documents'], true);
                if (is_array($docs)) {
                    foreach ($docs as $docPath) {
                        if (!empty($docPath) && file_exists($docPath)) {
                            $allDocs[] = array(
                                'service_type' => $row['service_type'],
                                'service_id' => $row['service_id'],
                                'user_id' => $row['user_id'],
                                'user_name' => $row['user_name'],
                                'user_email' => $row['user_email'],
                                'file_path' => $docPath,
                                'file_name' => basename($docPath),
                                'file_size' => filesize($docPath),
                                'created_at' => $row['created_at'],
                                'upload_source' => 'User'
                            );
                        }
                    }
                }
            }
        }
    }
    
    // Get documents from MSME
    $msmeQuery = "SELECT 
        m.id as service_id,
        m.user_id,
        m.user_name,
        m.user_email,
        'MSME' as service_type,
        m.passport_photo,
        m.created_at
        FROM msme_registrations m 
        WHERE m.passport_photo IS NOT NULL";
    
    $result = $conn->query($msmeQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['passport_photo']) && file_exists($row['passport_photo'])) {
                $allDocs[] = array(
                    'service_type' => $row['service_type'],
                    'service_id' => $row['service_id'],
                    'user_id' => $row['user_id'],
                    'user_name' => $row['user_name'],
                    'user_email' => $row['user_email'],
                    'file_path' => $row['passport_photo'],
                    'file_name' => basename($row['passport_photo']),
                    'file_size' => filesize($row['passport_photo']),
                    'created_at' => $row['created_at'],
                    'upload_source' => 'User',
                    'document_type' => 'Passport Photo'
                );
            }
        }
    }
    
    // Get documents from CMA Data (ITR and Loan Statement files)
    $cmaQuery = "SELECT 
        c.id as service_id,
        c.user_id,
        c.user_name,
        c.user_email,
        'CMA Data' as service_type,
        c.itr_year1_file,
        c.itr_year2_file,
        c.itr_year3_file,
        c.loan_statement_file,
        c.created_at
        FROM cma_data c 
        WHERE c.itr_year1_file IS NOT NULL 
           OR c.itr_year2_file IS NOT NULL 
           OR c.itr_year3_file IS NOT NULL 
           OR c.loan_statement_file IS NOT NULL";
    
    $result = $conn->query($cmaQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cmaFiles = array(
                'itr_year1_file' => 'ITR Year 1',
                'itr_year2_file' => 'ITR Year 2',
                'itr_year3_file' => 'ITR Year 3',
                'loan_statement_file' => 'Loan Statement'
            );
            
            foreach ($cmaFiles as $field => $docType) {
                if (!empty($row[$field]) && file_exists($row[$field])) {
                    $allDocs[] = array(
                        'service_type' => $row['service_type'],
                        'service_id' => $row['service_id'],
                        'user_id' => $row['user_id'],
                        'user_name' => $row['user_name'],
                        'user_email' => $row['user_email'],
                        'file_path' => $row[$field],
                        'file_name' => basename($row[$field]),
                        'file_size' => filesize($row[$field]),
                        'created_at' => $row['created_at'],
                        'upload_source' => 'User',
                        'document_type' => $docType
                    );
                }
            }
        }
    }
    
    // Get documents from Income Tax Returns (Bank Statement)
    $itrQuery = "SELECT 
        i.id as service_id,
        i.user_id,
        u.name as user_name,
        u.email as user_email,
        'Income Tax' as service_type,
        i.bank_statement_path,
        i.created_at
        FROM income_tax_returns i
        JOIN users u ON i.user_id = u.id
        WHERE i.bank_statement_path IS NOT NULL";
    
    $result = $conn->query($itrQuery);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['bank_statement_path']) && file_exists($row['bank_statement_path'])) {
                $allDocs[] = array(
                    'service_type' => $row['service_type'],
                    'service_id' => $row['service_id'],
                    'user_id' => $row['user_id'],
                    'user_name' => $row['user_name'],
                    'user_email' => $row['user_email'],
                    'file_path' => $row['bank_statement_path'],
                    'file_name' => basename($row['bank_statement_path']),
                    'file_size' => filesize($row['bank_statement_path']),
                    'created_at' => $row['created_at'],
                    'upload_source' => 'User',
                    'document_type' => 'Bank Statement'
                );
            }
        }
    }
    
    return $allDocs;
}

// Get all user-uploaded documents
$userUploadedDocs = getUserUploadedDocuments($conn);

// Get statistics - Include user-uploaded documents
$totalDocs = $conn->query("SELECT COUNT(*) as count FROM documents")->fetch_assoc()['count'];
$totalDocs += count($userUploadedDocs); // Add user-uploaded documents count

$totalSize = $conn->query("SELECT SUM(file_size) as size FROM documents")->fetch_assoc()['size'];
// Add user-uploaded documents size
foreach ($userUploadedDocs as $doc) {
    $totalSize += $doc['file_size'];
}

$todayDocs = $conn->query("SELECT COUNT(*) as count FROM documents WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['count'];
// Count today's user uploads
$todayUserUploads = 0;
foreach ($userUploadedDocs as $doc) {
    if (date('Y-m-d', strtotime($doc['created_at'])) == date('Y-m-d')) {
        $todayUserUploads++;
    }
}
$todayDocs += $todayUserUploads;

$thisMonthDocs = $analytics['this_month'];
// Count this month's user uploads
$thisMonthUserUploads = 0;
foreach ($userUploadedDocs as $doc) {
    if (date('Y-m', strtotime($doc['created_at'])) == date('Y-m')) {
        $thisMonthUserUploads++;
    }
}
$thisMonthDocs += $thisMonthUserUploads;

// Get all users for dropdown
$users = $conn->query("SELECT id, name, email FROM users ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Document Management - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 260px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px 0; overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h4 { color: white; font-size: 18px; font-weight: 600; margin-bottom: 5px; }
        .sidebar-header p { color: rgba(255,255,255,0.7); font-size: 13px; margin: 0; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu i { width: 20px; margin-right: 12px; font-size: 16px; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-nav h5 { margin: 0; color: #333; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .admin-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .stat-card h3 { font-size: 32px; font-weight: 700; margin-bottom: 5px; color: #667eea; }
        .stat-card p { color: #666; margin: 0; }
        .stat-card.success h3 { color: #28a745; }
        .stat-card.warning h3 { color: #ffc107; }
        .stat-card.info h3 { color: #17a2b8; }
        .table-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .table-card h6 { color: #333; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .filter-bar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .file-icon { font-size: 24px; margin-right: 10px; }
        .file-icon.pdf { color: #dc3545; }
        .file-icon.doc { color: #0d6efd; }
        .file-icon.img { color: #198754; }
        .file-icon.xls { color: #198754; }
        .upload-area { border: 2px dashed #667eea; border-radius: 10px; padding: 40px; text-align: center; background: #f8f9ff; cursor: pointer; transition: all 0.3s; }
        .upload-area:hover { background: #e8ebff; border-color: #764ba2; }
        .upload-area.dragover { background: #d8dbef; border-color: #667eea; }
        .tag-badge { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 11px; margin: 2px; }
        .tag-identity { background: #e3f2fd; color: #1976d2; }
        .tag-financial { background: #e8f5e9; color: #388e3c; }
        .tag-registration { background: #fff3e0; color: #f57c00; }
        .tag-tax { background: #f3e5f5; color: #7b1fa2; }
        .analytics-section { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .top-user-item { padding: 10px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .bulk-actions { background: #f8f9fa; padding: 10px 15px; border-radius: 8px; margin-bottom: 15px; display: none; }
        .bulk-actions.active { display: block; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="text-center">
                <i class="fas fa-calculator" style="font-size: 40px; color: white; margin-bottom: 10px;"></i>
            </div>
            <h4>Anugrah Accounting</h4>
            <p>Admin Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users Management</a></li>
            <li><a href="admin_gst_reg.php"><i class="fas fa-file-invoice"></i> GST Registrations</a></li>
            <li><a href="admin_gst_returns.php"><i class="fas fa-receipt"></i> GST Returns</a></li>
            <li><a href="admin_income_tax.php"><i class="fas fa-money-bill-wave"></i> Income Tax Returns</a></li>
            <li><a href="admin_msme.php"><i class="fas fa-industry"></i> MSME Registrations</a></li>
            <li><a href="admin_fssai.php"><i class="fas fa-utensils"></i> FSSAI Licences</a></li>
            <li><a href="admin_accounting.php"><i class="fas fa-calculator"></i> Accounting Services</a></li>
            <li><a href="admin_cma.php"><i class="fas fa-chart-line"></i> CMA Data</a></li>
            <li><a href="admin_tax_planning.php"><i class="fas fa-piggy-bank"></i> Tax Planning</a></li>
            <li><a href="admin_messages.php"><i class="fas fa-envelope"></i> Contact Messages</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback</a></li>
            <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="admin_documents.php" class="active"><i class="fas fa-folder"></i> Documents</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-folder me-2"></i>Enhanced Document Management</h5>
            <div class="admin-info">
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
            </div>
        </div>
        
        <?php if(isset($_GET['msg'])): ?>
            <?php if($_GET['msg'] == 'uploaded'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>Document uploaded successfully with auto-tagging!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php elseif($_GET['msg'] == 'deleted'): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i>Document deleted successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php elseif($_GET['msg'] == 'share_created'): ?>
            <div class="alert alert-info alert-dismissible fade show">
                <i class="fas fa-share-alt me-2"></i>Shareable link created!
                <input type="text" class="form-control mt-2" value="<?php echo $_SESSION['share_link']; ?>" readonly onclick="this.select()">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['share_link']); ?>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if(isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $totalDocs; ?></h3>
                <p><i class="fas fa-file me-2"></i>Total Documents</p>
            </div>
            <div class="stat-card success">
                <h3><?php echo number_format($totalSize / 1024 / 1024, 2); ?> MB</h3>
                <p><i class="fas fa-database me-2"></i>Total Storage Used</p>
            </div>
            <div class="stat-card warning">
                <h3><?php echo $todayDocs; ?></h3>
                <p><i class="fas fa-calendar-day me-2"></i>Uploaded Today</p>
            </div>
            <div class="stat-card info">
                <h3><?php echo $thisMonthDocs; ?></h3>
                <p><i class="fas fa-calendar-alt me-2"></i>This Month</p>
            </div>
            <div class="stat-card">
                <button class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#uploadModal">
                    <i class="fas fa-upload me-2"></i>Upload Document
                </button>
            </div>
        </div>
        
        <!-- Analytics Section -->
        <div class="analytics-section">
            <h6><i class="fas fa-chart-pie me-2"></i>Document Analytics</h6>
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-muted small">Documents by Service Type</h6>
                    <?php foreach($analytics['by_service'] as $service => $count): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?php echo $service; ?></span>
                        <span class="badge bg-primary"><?php echo $count; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted small">Top 5 Active Users</h6>
                    <?php foreach($analytics['top_users'] as $user): ?>
                    <div class="top-user-item">
                        <div>
                            <strong><?php echo htmlspecialchars($user['name']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                        </div>
                        <span class="badge bg-success"><?php echo $user['doc_count']; ?> docs</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search documents..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                <div class="col-md-3">
                    <select name="service" class="form-select">
                        <option value="all">All Services</option>
                        <option value="GST Registration" <?php echo $filterService=='GST Registration'?'selected':''; ?>>GST Registration</option>
                        <option value="GST Returns" <?php echo $filterService=='GST Returns'?'selected':''; ?>>GST Returns</option>
                        <option value="Income Tax" <?php echo $filterService=='Income Tax'?'selected':''; ?>>Income Tax</option>
                        <option value="MSME" <?php echo $filterService=='MSME'?'selected':''; ?>>MSME</option>
                        <option value="FSSAI" <?php echo $filterService=='FSSAI'?'selected':''; ?>>FSSAI</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="type" class="form-select">
                        <option value="all">All Types</option>
                        <option value="PAN Card" <?php echo $filterType=='PAN Card'?'selected':''; ?>>PAN Card</option>
                        <option value="Aadhaar" <?php echo $filterType=='Aadhaar'?'selected':''; ?>>Aadhaar</option>
                        <option value="Certificate" <?php echo $filterType=='Certificate'?'selected':''; ?>>Certificate</option>
                        <option value="Invoice" <?php echo $filterType=='Invoice'?'selected':''; ?>>Invoice</option>
                        <option value="Bank Statement" <?php echo $filterType=='Bank Statement'?'selected':''; ?>>Bank Statement</option>
                        <option value="Other" <?php echo $filterType=='Other'?'selected':''; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Search
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Bulk Actions Bar -->
        <div class="bulk-actions" id="bulkActionsBar">
            <form method="POST" id="bulkForm">
                <div class="d-flex justify-content-between align-items-center">
                    <span><strong><span id="selectedCount">0</span></strong> documents selected</span>
                    <div>
                        <button type="submit" name="bulk_download" class="btn btn-sm btn-success me-2">
                            <i class="fas fa-download me-1"></i>Download as ZIP
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="clearSelection()">
                            <i class="fas fa-times me-1"></i>Clear Selection
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Documents Table -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6><i class="fas fa-folder-open me-2"></i>All Documents (<?php echo $totalDocs; ?>)</h6>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                        <i class="fas fa-check-square me-1"></i>Select All
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="exportToCSV()">
                        <i class="fas fa-file-csv me-1"></i>Export CSV
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="documentsTable">
                    <thead>
                        <tr>
                            <th width="30px">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll()">
                            </th>
                            <th>File</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Type</th>
                            <th>Tags</th>
                            <th>Size</th>
                            <th>Uploaded By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Merge admin-uploaded and user-uploaded documents
                        $allDocuments = array();
                        
                        // Add documents from database
                        if ($documents && $documents->num_rows > 0) {
                            while($doc = $documents->fetch_assoc()) {
                                $doc['is_from_db'] = true;
                                $allDocuments[] = $doc;
                            }
                        }
                        
                        // Add user-uploaded documents
                        foreach ($userUploadedDocs as $doc) {
                            $doc['is_from_db'] = false;
                            // Apply filters
                            $passFilter = true;
                            
                            if ($filterService != 'all' && $doc['service_type'] != $filterService) {
                                $passFilter = false;
                            }
                            
                            if ($filterType != 'all' && isset($doc['document_type']) && $doc['document_type'] != $filterType) {
                                $passFilter = false;
                            }
                            
                            if (!empty($_GET['search'])) {
                                $search = strtolower($_GET['search']);
                                if (strpos(strtolower($doc['file_name']), $search) === false &&
                                    strpos(strtolower($doc['user_name']), $search) === false &&
                                    strpos(strtolower($doc['user_email']), $search) === false) {
                                    $passFilter = false;
                                }
                            }
                            
                            if ($passFilter) {
                                $allDocuments[] = $doc;
                            }
                        }
                        
                        // Sort by created_at descending
                        usort($allDocuments, function($a, $b) {
                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                        });
                        
                        if (!empty($allDocuments)):
                            foreach($allDocuments as $doc): 
                                $fileExt = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                                $iconClass = 'file';
                                if (in_array($fileExt, array('pdf'))) $iconClass = 'file-pdf pdf';
                                elseif (in_array($fileExt, array('doc', 'docx'))) $iconClass = 'file-word doc';
                                elseif (in_array($fileExt, array('jpg', 'jpeg', 'png'))) $iconClass = 'file-image img';
                                elseif (in_array($fileExt, array('xls', 'xlsx'))) $iconClass = 'file-excel xls';
                                
                                // Decode tags if from database
                                $tags = array();
                                if (isset($doc['tags']) && $doc['is_from_db']) {
                                    $tags = json_decode($doc['tags'], true);
                                    if (!is_array($tags)) $tags = array();
                                }
                                
                                // Auto-detect tags for user uploads
                                if (!$doc['is_from_db']) {
                                    $tags = autoTagDocument($doc['file_name'], isset($doc['document_type']) ? $doc['document_type'] : '');
                                }
                        ?>
                        <tr>
                            <td>
                                <?php if ($doc['is_from_db']): ?>
                                <input type="checkbox" class="doc-checkbox" name="doc_ids[]" value="<?php echo $doc['id']; ?>" onchange="updateBulkActions()">
                                <?php else: ?>
                                <i class="fas fa-user-circle text-muted" title="User uploaded"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <i class="fas fa-<?php echo $iconClass; ?> file-icon"></i>
                                <strong><?php echo htmlspecialchars($doc['file_name']); ?></strong>
                                <?php if (!empty($doc['description'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($doc['description'], 0, 50)); ?><?php echo strlen($doc['description']) > 50 ? '...' : ''; ?></small>
                                <?php endif; ?>
                                <?php if (!$doc['is_from_db']): ?>
                                <br><span class="badge bg-info" style="font-size: 10px;"><i class="fas fa-user me-1"></i>User Uploaded</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($doc['user_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($doc['user_email']); ?></small>
                            </td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($doc['service_type']); ?></span></td>
                            <td><?php echo htmlspecialchars(isset($doc['document_type']) ? $doc['document_type'] : 'Document'); ?></td>
                            <td>
                                <?php if (!empty($tags)): ?>
                                    <?php foreach($tags as $tag): ?>
                                        <span class="tag-badge tag-<?php echo $tag; ?>"><?php echo ucfirst($tag); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <small class="text-muted">No tags</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</td>
                            <td>
                                <?php 
                                if ($doc['is_from_db']) {
                                    echo htmlspecialchars(isset($doc['uploaded_by_name']) ? $doc['uploaded_by_name'] : 'System');
                                } else {
                                    echo '<span class="badge bg-success">Self</span>';
                                }
                                ?>
                            </td>
                            <td><small><?php echo date('M d, Y', strtotime($doc['created_at'])); ?><br><?php echo date('h:i A', strtotime($doc['created_at'])); ?></small></td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-sm btn-primary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $doc['file_path']; ?>" download class="btn btn-sm btn-success" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <?php if ($doc['is_from_db']): ?>
                                    <a href="?share=1&doc_id=<?php echo $doc['id']; ?>" class="btn btn-sm btn-info" title="Share">
                                        <i class="fas fa-share-alt"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-warning" onclick="openEditModal(<?php echo $doc['id']; ?>, '<?php echo htmlspecialchars(isset($doc['document_type']) ? $doc['document_type'] : '', ENT_QUOTES); ?>', '<?php echo htmlspecialchars(isset($doc['description']) ? $doc['description'] : '', ENT_QUOTES); ?>')" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $doc['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this document? This action cannot be undone.')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php else: ?>
                                    <button class="btn btn-sm btn-secondary" disabled title="User uploaded - limited actions">
                                        <i class="fas fa-lock"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php 
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No documents found. Upload your first document to get started!</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-upload me-2"></i>Upload Document</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Client *</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Choose client...</option>
                                <?php 
                                $users->data_seek(0); // Reset pointer
                                while($user = $users->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['name']); ?> - <?php echo htmlspecialchars($user['email']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Type *</label>
                                <select name="service_type" class="form-select" required>
                                    <option value="">Select service...</option>
                                    <option value="GST Registration">GST Registration</option>
                                    <option value="GST Returns">GST Returns</option>
                                    <option value="Income Tax">Income Tax</option>
                                    <option value="MSME">MSME</option>
                                    <option value="FSSAI">FSSAI</option>
                                    <option value="Accounting">Accounting</option>
                                    <option value="CMA">CMA Data</option>
                                    <option value="Tax Planning">Tax Planning</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service ID (Optional)</label>
                                <input type="number" name="service_id" class="form-control" placeholder="Application ID">
                                <small class="text-muted">Link to specific application</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Document Type *</label>
                            <select name="document_type" class="form-select" required>
                                <option value="">Select type...</option>
                                <option value="PAN Card">PAN Card</option>
                                <option value="Aadhaar">Aadhaar Card</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Invoice">Invoice</option>
                                <option value="Bank Statement">Bank Statement</option>
                                <option value="ITR Form">ITR Form</option>
                                <option value="GST Form">GST Form</option>
                                <option value="Registration Document">Registration Document</option>
                                <option value="License">License</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Add notes about this document (optional)..."></textarea>
                            <small class="text-muted">This helps with auto-tagging and search</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Select File *</label>
                            <div class="upload-area" id="uploadArea">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #667eea; margin-bottom: 15px;"></i>
                                <h6>Drag & Drop or Click to Upload</h6>
                                <p class="text-muted mb-0">Supported: PDF, DOC, DOCX, JPG, PNG, XLS, XLSX</p>
                                <p class="text-muted small">Maximum file size: 10MB</p>
                                <input type="file" name="document" id="fileInput" class="d-none" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx" required>
                            </div>
                            <div id="fileInfo" class="mt-2"></div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Smart Features:</strong> Your document will be automatically tagged and duplicate detection will be applied.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="submit" name="upload_document" class="btn btn-primary">
                            <i class="fas fa-upload me-1"></i>Upload Document
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Document Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Document Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="update_document.php">
                    <div class="modal-body">
                        <input type="hidden" name="doc_id" id="edit_doc_id">
                        <div class="mb-3">
                            <label class="form-label">Document Type</label>
                            <select name="document_type" id="edit_document_type" class="form-select">
                                <option value="PAN Card">PAN Card</option>
                                <option value="Aadhaar">Aadhaar Card</option>
                                <option value="Certificate">Certificate</option>
                                <option value="Invoice">Invoice</option>
                                <option value="Bank Statement">Bank Statement</option>
                                <option value="ITR Form">ITR Form</option>
                                <option value="GST Form">GST Form</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            fileInput.files = e.dataTransfer.files;
            displayFileInfo();
        });
        
        fileInput.addEventListener('change', displayFileInfo);
        
        function displayFileInfo() {
            if (fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const size = (file.size / 1024 / 1024).toFixed(2);
                const sizeClass = size > 10 ? 'alert-danger' : 'alert-success';
                const sizeIcon = size > 10 ? 'exclamation-triangle' : 'check-circle';
                fileInfo.innerHTML = `<div class="alert ${sizeClass}"><i class="fas fa-${sizeIcon} me-2"></i><strong>${file.name}</strong> (${size} MB)</div>`;
                
                if (size > 10) {
                    fileInfo.innerHTML += '<p class="text-danger small">File size exceeds 10MB limit!</p>';
                }
            }
        }
        
        // Bulk actions
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.doc-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('bulkActionsBar').classList.toggle('active', count > 0);
            
            // Update hidden inputs in bulk form
            const bulkForm = document.getElementById('bulkForm');
            const existingInputs = bulkForm.querySelectorAll('input[name="doc_ids[]"]');
            existingInputs.forEach(input => input.remove());
            
            checkboxes.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'doc_ids[]';
                input.value = checkbox.value;
                bulkForm.appendChild(input);
            });
        }
        
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.doc-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkActions();
        }
        
        function selectAll() {
            document.getElementById('selectAllCheckbox').checked = true;
            toggleSelectAll();
        }
        
        function clearSelection() {
            document.getElementById('selectAllCheckbox').checked = false;
            toggleSelectAll();
        }
        
        // Edit modal
        function openEditModal(docId, docType, description) {
            document.getElementById('edit_doc_id').value = docId;
            document.getElementById('edit_document_type').value = docType;
            document.getElementById('edit_description').value = description;
            new bootstrap.Modal(document.getElementById('editModal')).show();
        }
        
        // Export to CSV
        function exportToCSV() {
            const table = document.getElementById('documentsTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            rows.forEach(row => {
                const cols = row.querySelectorAll('td, th');
                const rowData = [];
                cols.forEach((col, index) => {
                    if (index > 0 && index < cols.length - 1) { // Skip checkbox and actions columns
                        rowData.push('"' + col.textContent.trim().replace(/"/g, '""') + '"');
                    }
                });
                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            });
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'documents_' + new Date().toISOString().slice(0,10) + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-info')) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>
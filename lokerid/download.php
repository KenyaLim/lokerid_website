<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

// Get file path from database
if (!isset($_GET['file']) || !isset($_GET['type'])) {
    http_response_code(400);
    die('Invalid request');
}

$applicationId = $_GET['file'];
$fileType = $_GET['type']; // cv, portfolio, cover_letter

// Validate file type
$allowedTypes = ['cv', 'portfolio', 'cover_letter'];
if (!in_array($fileType, $allowedTypes)) {
    http_response_code(400);
    die('Invalid file type');
}

try {
    // Get application data with security check
    $sql = "SELECT ja.*, jl.company_id 
            FROM job_applications ja
            JOIN job_listings jl ON ja.job_listing_id = jl.id
            WHERE ja.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch();
    
    if (!$application) {
        http_response_code(404);
        die('Application not found');
    }
    
    // Security check - only company or applicant can download
    $canDownload = false;
    
    // If user is company, check if they own the job listing
    if ($_SESSION['user_role'] === 'company' && 
        isset($_SESSION['company_id']) && 
        $_SESSION['company_id'] == $application['company_id']) {
        $canDownload = true;
    }
    
    // If user is job seeker, check if they are the applicant
    if ($_SESSION['user_role'] === 'job_seeker') {
        // Check different possible user reference columns
        $userColumns = ['job_seeker_id', 'user_id', 'applicant_id', 'seeker_id'];
        foreach ($userColumns as $column) {
            if (isset($application[$column])) {
                if ($column === 'job_seeker_id') {
                    // Get job_seeker_id for current user
                    $stmt = $conn->prepare("SELECT id FROM job_seekers WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $jobSeeker = $stmt->fetch();
                    if ($jobSeeker && $jobSeeker['id'] == $application[$column]) {
                        $canDownload = true;
                        break;
                    }
                } else {
                    if ($_SESSION['user_id'] == $application[$column]) {
                        $canDownload = true;
                        break;
                    }
                }
            }
        }
    }
    
    if (!$canDownload) {
        http_response_code(403);
        die('Access denied');
    }
    
    // Get file path based on type
    $filePath = null;
    $fileName = null;
    
    switch ($fileType) {
        case 'cv':
            $filePath = $application['cv_path'] ?? $application['cv_file'] ?? null;
            $fileName = 'CV_' . $application['full_name'] . '.pdf';
            break;
        case 'portfolio':
            $filePath = $application['portfolio_path'] ?? $application['portfolio_file'] ?? null;
            $fileName = 'Portfolio_' . $application['full_name'] . '.pdf';
            break;
        case 'cover_letter':
            $filePath = $application['cover_letter_path'] ?? $application['cover_letter_file'] ?? null;
            $fileName = 'CoverLetter_' . $application['full_name'] . '.pdf';
            break;
    }
    
    if (!$filePath || !file_exists($filePath)) {
        http_response_code(404);
        die('File not found');
    }
    
    // Clean filename for download
    $fileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
    
    // Set headers for file download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private');
    header('Pragma: private');
    header('Expires: 0');
    
    // Output file content
    readfile($filePath);
    exit();
    
} catch (Exception $e) {
    error_log("Download error: " . $e->getMessage());
    http_response_code(500);
    die('Server error');
}
?>
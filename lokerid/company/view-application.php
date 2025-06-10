<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

// Get application ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$applicationId = $_GET['id'];
$companyId = $_SESSION['company_id'];

// Handle status update if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $validStatuses = ['pending', 'reviewed', 'accepted', 'rejected'];
    
    if (in_array($newStatus, $validStatuses)) {
        $updateStmt = $conn->prepare("UPDATE job_applications SET status = ? WHERE id = ?");
        $result = $updateStmt->execute([$newStatus, $applicationId]);
        
        if ($result) {
            $_SESSION['status_message'] = "Status successfully updated to " . ucfirst($newStatus);
            $_SESSION['status_type'] = "success";
        } else {
            $_SESSION['status_message'] = "Failed to update status. Please try again.";
            $_SESSION['status_type'] = "danger";
        }
        
        // Redirect to prevent form resubmission
        header("Location: view-application.php?id=" . $applicationId);
        exit();
    } else {
        $_SESSION['status_message'] = "Invalid status selected.";
        $_SESSION['status_type'] = "danger";
    }
}

// Get application details - first determine the structure
$stmt = $conn->prepare("
    SELECT 
        ja.*,
        jl.title as job_title,
        jl.company_id
    FROM job_applications ja
    JOIN job_listings jl ON ja.job_listing_id = jl.id
    WHERE ja.id = ? AND jl.company_id = ?
");
$stmt->execute([$applicationId, $companyId]);
$application = $stmt->fetch();

// Check if application exists and belongs to this company
if (!$application) {
    header("Location: dashboard.php");
    exit();
}

// Get user information based on the application data
$userData = [];
$profile = [];

// Determine which user column is being used
$userColumn = null;
$userId = null;

if (isset($application['job_seeker_id'])) {
    $userColumn = 'job_seeker_id';
    $userId = $application['job_seeker_id'];
    
    // Try to get user data from job_seekers table first
    try {
        $userStmt = $conn->prepare("SELECT js.*, u.email, u.full_name FROM job_seekers js JOIN users u ON js.user_id = u.id WHERE js.id = ?");
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // If that fails, try direct user lookup
        $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    }
} elseif (isset($application['user_id'])) {
    $userColumn = 'user_id';
    $userId = $application['user_id'];
    
    // Get user data from users table
    $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$userId]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    // Try to get additional profile data from job_seekers table
    try {
        $profileStmt = $conn->prepare("SELECT * FROM job_seekers WHERE user_id = ?");
        $profileStmt->execute([$userId]);
        $profile = $profileStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        $profile = [];
    }
}

// Merge application data with user data for easier access
if ($userData) {
    $application = array_merge($application, $userData);
}
if ($profile) {
    $application = array_merge($application, $profile);
}

// Check if application exists and belongs to this company
if (!$application) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Details - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Application Details</h2>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>

                <!-- Status Message -->
                <?php if (isset($_SESSION['status_message'])): ?>
                <div class="alert alert-<?php echo $_SESSION['status_type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($_SESSION['status_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php 
                    unset($_SESSION['status_message']);
                    unset($_SESSION['status_type']);
                endif; 
                ?>

                <!-- Application Status Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Application Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Position:</strong> <?php echo htmlspecialchars($application['job_title']); ?></p>
                                <p><strong>Applied Date:</strong> <?php echo date('j F Y, H:i', strtotime($application['applied_at'])); ?></p>
                                <p><strong>Current Status:</strong> 
                                    <span class="badge 
                                        <?php 
                                        $currentStatus = $application['status'] ?? $application['application_status'] ?? 'pending';
                                        switch($currentStatus) {
                                            case 'pending': echo 'bg-warning text-dark'; break;
                                            case 'reviewed': echo 'bg-info text-white'; break;
                                            case 'accepted': echo 'bg-success text-white'; break;
                                            case 'rejected': echo 'bg-danger text-white'; break;
                                            default: echo 'bg-secondary text-white';
                                        }
                                        ?>">
                                        <?php echo ucfirst($currentStatus); ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <form method="POST" class="d-flex align-items-end" id="statusForm">
                                    <div class="me-2 flex-grow-1">
                                        <label for="status" class="form-label">Update Status:</label>
                                        <select name="status" id="status" class="form-select" required>
                                            <?php $currentStatus = $application['status'] ?? $application['application_status'] ?? 'pending'; ?>
                                            <option value="pending" <?php echo $currentStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="reviewed" <?php echo $currentStatus === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                            <option value="accepted" <?php echo $currentStatus === 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                            <option value="rejected" <?php echo $currentStatus === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_status" class="btn btn-primary" id="updateBtn">
                                        <i class="fas fa-sync-alt"></i> Update
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Applicant Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Applicant Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Personal Information</h6>
                                <p><strong>Full Name:</strong> <?php echo htmlspecialchars($application['full_name'] ?? 'Not provided'); ?></p>
                                <p><strong>Email:</strong> 
                                    <?php if (!empty($application['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>">
                                            <?php echo htmlspecialchars($application['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        Not provided
                                    <?php endif; ?>
                                </p>
                                <p><strong>Phone:</strong> 
                                    <?php if (!empty($application['phone_number']) || !empty($application['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($application['phone_number'] ?? $application['phone']); ?>">
                                            <?php echo htmlspecialchars($application['phone_number'] ?? $application['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        Not provided
                                    <?php endif; ?>
                                </p>
                                <p><strong>Date of Birth:</strong> 
                                    <?php echo (!empty($application['birth_date'])) ? date('j F Y', strtotime($application['birth_date'])) : 'Not provided'; ?>
                                </p>
                                <p><strong>Address:</strong> <?php echo htmlspecialchars($application['address'] ?? 'Not provided'); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">Professional Information</h6>
                                <p><strong>Education:</strong> <?php echo htmlspecialchars($application['education'] ?? 'Not provided'); ?></p>
                                
                                <?php if (!empty($application['skills'])): ?>
                                <p><strong>Skills:</strong></p>
                                <div class="mb-3">
                                    <?php 
                                    $skills = explode(',', $application['skills']);
                                    foreach ($skills as $skill): 
                                        $skill = trim($skill);
                                        if (!empty($skill)):
                                    ?>
                                        <span class="badge bg-secondary me-2 mb-2"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($application['linkedin_profile'])): ?>
                                <p><strong>LinkedIn:</strong> 
                                    <a href="<?php echo htmlspecialchars($application['linkedin_profile']); ?>" target="_blank" class="text-decoration-none">
                                        <i class="fab fa-linkedin"></i> View Profile
                                    </a>
                                </p>
                                <?php endif; ?>

                                <?php if (!empty($application['portfolio_url'])): ?>
                                <p><strong>Portfolio:</strong> 
                                    <a href="<?php echo htmlspecialchars($application['portfolio_url']); ?>" target="_blank" class="text-decoration-none">
                                        <i class="fas fa-external-link-alt"></i> View Portfolio
                                    </a>
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Experience -->
                <?php if (!empty($application['experience'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Work Experience</h5>
                    </div>
                    <div class="card-body">
                        <div class="experience-content">
                            <?php echo nl2br(htmlspecialchars($application['experience'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bio -->
                <?php if (!empty($application['bio'])): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">About the Applicant</h5>
                    </div>
                    <div class="card-body">
                        <div class="bio-content">
                            <?php echo nl2br(htmlspecialchars($application['bio'])); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Files Section -->
                 <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Application Files</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <!-- CV -->
                            <?php if (!empty($application['cv_path']) || !empty($application['cv_file'])): ?>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <i class="fas fa-file-pdf fa-2x text-danger me-3"></i>
                                    <div>
                                        <p class="mb-1"><strong>CV/Resume</strong></p>
                                        <a href="../download.php?file=<?php echo $application['id']; ?>&type=cv" 
                                           target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Portfolio -->
                            <?php if (!empty($application['portfolio_path']) || !empty($application['portfolio_file'])): ?>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <i class="fas fa-folder fa-2x text-info me-3"></i>
                                    <div>
                                        <p class="mb-1"><strong>Portfolio</strong></p>
                                        <a href="../download.php?file=<?php echo $application['id']; ?>&type=portfolio" 
                                           target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Cover Letter -->
                            <?php if (!empty($application['cover_letter_path']) || !empty($application['cover_letter_file'])): ?>
                            <div class="col-md-4 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <i class="fas fa-file-alt fa-2x text-success me-3"></i>
                                    <div>
                                        <p class="mb-1"><strong>Cover Letter</strong></p>
                                        <a href="../download.php?file=<?php echo $application['id']; ?>&type=cover_letter" 
                                           target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($application['cv_path']) && empty($application['cv_file']) && 
                                  empty($application['portfolio_path']) && empty($application['portfolio_file']) && 
                                  empty($application['cover_letter_path']) && empty($application['cover_letter_file'])): ?>
                            <p class="text-muted text-center">No files uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-body text-center">
                        <div class="btn-group" role="group">
                            <a href="mailto:<?php echo htmlspecialchars($application['email'] ?? ''); ?>?subject=Regarding your application for <?php echo urlencode($application['job_title']); ?>" 
                               class="btn btn-success <?php echo empty($application['email']) ? 'disabled' : ''; ?>">
                                <i class="fas fa-envelope"></i> Send Email
                            </a>
                            <a href="tel:<?php echo htmlspecialchars($application['phone_number'] ?? $application['phone'] ?? ''); ?>" 
                               class="btn btn-info <?php echo (empty($application['phone_number']) && empty($application['phone'])) ? 'disabled' : ''; ?>">
                                <i class="fas fa-phone"></i> Call Applicant
                            </a>
                            <a href="applications.php" class="btn btn-secondary">
                                <i class="fas fa-list"></i> View All Applications
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add confirmation dialog for status update
        document.getElementById('statusForm').addEventListener('submit', function(e) {
            const selectedStatus = document.getElementById('status').value;
            const currentStatus = '<?php echo $application['status'] ?? $application['application_status'] ?? 'pending'; ?>';
            
            if (selectedStatus !== currentStatus) {
                if (!confirm('Are you sure you want to change the status to ' + selectedStatus.charAt(0).toUpperCase() + selectedStatus.slice(1) + '?')) {
                    e.preventDefault();
                }
            } else {
                alert('Status is already set to ' + selectedStatus.charAt(0).toUpperCase() + selectedStatus.slice(1));
                e.preventDefault();
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>
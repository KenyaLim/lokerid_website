<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'job_seeker') {
    header("Location: login.php");
    exit();
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Debug mode - set to false in production
$debugMode = true;

if ($debugMode) {
    error_log("=== MY APPLICATIONS DEBUG ===");
    error_log("User ID: " . $user_id);
    error_log("User Role: " . $_SESSION['user_role']);
}

// First, check what columns exist in job_applications table
$applications = [];
$job_seeker_id = null;

try {
    // Get table structure for debugging
    if ($debugMode) {
        $stmt = $conn->query("DESCRIBE job_applications");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("=== JOB_APPLICATIONS TABLE STRUCTURE ===");
        foreach ($columns as $column) {
            error_log("Column: " . $column['Field'] . " | Type: " . $column['Type']);
        }
    }

    // Try to get job_seeker_id from job_seekers table if it exists
    try {
        $stmt = $conn->prepare("SELECT id FROM job_seekers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $job_seeker_data = $stmt->fetch();
        if ($job_seeker_data) {
            $job_seeker_id = $job_seeker_data['id'];
            if ($debugMode) error_log("Found job_seeker_id: " . $job_seeker_id);
        } else {
            if ($debugMode) error_log("No job_seeker record found for user_id: " . $user_id);
        }
    } catch (Exception $e) {
        if ($debugMode) error_log("Error getting job_seeker_id: " . $e->getMessage());
    }

    // Strategy 1: Try with job_seeker_id if available
    if ($job_seeker_id) {
        try {
            $stmt = $conn->prepare("
                SELECT 
                    ja.*, 
                    jl.title AS job_title, 
                    jl.location AS job_location, 
                    jl.deadline_date,
                    c.company_name,
                    c.logo_path
                FROM job_applications ja
                JOIN job_listings jl ON ja.job_listing_id = jl.id
                JOIN companies c ON jl.company_id = c.id
                WHERE ja.job_seeker_id = ?
                ORDER BY ja.applied_at DESC
            ");
            $stmt->execute([$job_seeker_id]);
            $applications = $stmt->fetchAll();
            
            if ($debugMode) {
                error_log("Query with job_seeker_id returned " . count($applications) . " applications");
                if (!empty($applications)) {
                    error_log("Sample application data: " . print_r(array_keys($applications[0]), true));
                }
            }
        } catch (Exception $e) {
            if ($debugMode) error_log("Error with job_seeker_id query: " . $e->getMessage());
            $applications = [];
        }
    }

    // Strategy 2: Try with user_id if no results or no job_seeker_id
    if (empty($applications)) {
        try {
            $stmt = $conn->prepare("
                SELECT 
                    ja.*, 
                    jl.title AS job_title, 
                    jl.location AS job_location, 
                    jl.deadline_date,
                    c.company_name,
                    c.logo_path
                FROM job_applications ja
                JOIN job_listings jl ON ja.job_listing_id = jl.id
                JOIN companies c ON jl.company_id = c.id
                WHERE ja.user_id = ?
                ORDER BY ja.applied_at DESC
            ");
            $stmt->execute([$user_id]);
            $applications = $stmt->fetchAll();
            
            if ($debugMode) {
                error_log("Query with user_id returned " . count($applications) . " applications");
                if (!empty($applications)) {
                    error_log("Sample application data: " . print_r(array_keys($applications[0]), true));
                }
            }
        } catch (Exception $e) {
            if ($debugMode) error_log("Error with user_id query: " . $e->getMessage());
            $applications = [];
        }
    }

    // Strategy 3: Try alternative column names if still no results
    if (empty($applications)) {
        $alternativeColumns = ['applicant_id', 'seeker_id'];
        
        foreach ($alternativeColumns as $colName) {
            try {
                $stmt = $conn->prepare("
                    SELECT 
                        ja.*, 
                        jl.title AS job_title, 
                        jl.location AS job_location, 
                        jl.deadline_date,
                        c.company_name,
                        c.logo_path
                    FROM job_applications ja
                    JOIN job_listings jl ON ja.job_listing_id = jl.id
                    JOIN companies c ON jl.company_id = c.id
                    WHERE ja.$colName = ?
                    ORDER BY ja.applied_at DESC
                ");
                $stmt->execute([$user_id]);
                $tempApps = $stmt->fetchAll();
                
                if (!empty($tempApps)) {
                    $applications = $tempApps;
                    if ($debugMode) error_log("Found applications using column: $colName");
                    break;
                }
            } catch (Exception $e) {
                if ($debugMode) error_log("Column $colName doesn't exist or error: " . $e->getMessage());
                continue;
            }
        }
    }

    // If still no applications, let's check what data exists
    if (empty($applications) && $debugMode) {
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM job_applications");
            $totalApps = $stmt->fetchColumn();
            error_log("Total applications in database: " . $totalApps);
            
            if ($totalApps > 0) {
                $stmt = $conn->query("SELECT * FROM job_applications LIMIT 1");
                $sampleApp = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("Sample application columns: " . print_r(array_keys($sampleApp), true));
                error_log("Sample application data: " . print_r($sampleApp, true));
            }
        } catch (Exception $e) {
            error_log("Error checking total applications: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    if ($debugMode) error_log("General error in my-applications.php: " . $e->getMessage());
    $applications = [];
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch(strtolower($status)) {
        case 'pending': return 'bg-warning text-dark';
        case 'reviewed': return 'bg-info text-white';
        case 'accepted': return 'bg-success text-white';
        case 'rejected': return 'bg-danger text-white';
        default: return 'bg-secondary text-white';
    }
}

// Function to get status display text
function getStatusText($status) {
    return ucfirst(strtolower($status));
}

// Debug output (remove in production)
if ($debugMode) {
    error_log("Final applications count: " . count($applications));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Applications - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-file-alt me-2"></i>My Job Applications</h2>
                    <a href="index.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Find More Jobs
                    </a>
                </div>

                <?php if ($debugMode && empty($applications)): ?>
                    <div class="alert alert-info">
                        <h6>Debug Information:</h6>
                        <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
                        <p><strong>Job Seeker ID:</strong> <?php echo $job_seeker_id ?? 'Not found'; ?></p>
                        <p><strong>User Role:</strong> <?php echo $_SESSION['user_role']; ?></p>
                        <p><em>Check browser console or server logs for detailed debug information.</em></p>
                    </div>
                <?php endif; ?>

                <?php if (empty($applications)): ?>
                    <div class="card text-center">
                        <div class="card-body py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="card-title">No Applications Yet</h5>
                            <p class="card-text text-muted">You haven't applied to any jobs yet. Start exploring job opportunities!</p>
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Browse Jobs
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <div class="col-md-12">
                            <p class="text-muted mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                You have <?php echo count($applications); ?> job application(s)
                            </p>
                        </div>
                    </div>

                    <?php foreach ($applications as $app): ?>
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-light">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h5 class="mb-0">
                                            <i class="fas fa-briefcase me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($app['job_title']); ?>
                                        </h5>
                                        <small class="text-muted">
                                            <i class="fas fa-building me-1"></i>
                                            <?php echo htmlspecialchars($app['company_name']); ?>
                                        </small>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php 
                                        // Check multiple possible status column names
                                        $status = $app['status'] ?? $app['application_status'] ?? 'pending';
                                        ?>
                                        <span class="badge <?php echo getStatusBadgeClass($status); ?> fs-6">
                                            <?php echo getStatusText($status); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-2 text-center mb-3">
                                        <?php if (!empty($app['logo_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($app['logo_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($app['company_name']); ?>" 
                                                 class="img-fluid rounded border" 
                                                 style="max-height: 80px; max-width: 80px; object-fit: contain;">
                                        <?php else: ?>
                                            <div class="bg-light rounded border d-flex align-items-center justify-content-center" 
                                                 style="width: 80px; height: 80px;">
                                                <i class="fas fa-building fa-2x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-10">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="text-primary mb-2">
                                                    <i class="fas fa-info-circle me-1"></i>Application Details
                                                </h6>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-map-marker-alt me-1"></i>Location:</strong> 
                                                    <?php echo htmlspecialchars($app['job_location'] ?? 'Not specified'); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-calendar-alt me-1"></i>Deadline:</strong> 
                                                    <?php 
                                                    if (!empty($app['deadline_date'])) {
                                                        echo date('j F Y', strtotime($app['deadline_date']));
                                                    } else {
                                                        echo 'Not specified';
                                                    }
                                                    ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-clock me-1"></i>Applied on:</strong> 
                                                    <?php echo date('j F Y, H:i', strtotime($app['applied_at'])); ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-primary mb-2">
                                                    <i class="fas fa-user me-1"></i>Your Information
                                                </h6>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-envelope me-1"></i>Email:</strong> 
                                                    <?php echo htmlspecialchars($app['email'] ?? 'Not provided'); ?>
                                                </p>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-phone me-1"></i>Phone:</strong> 
                                                    <?php echo htmlspecialchars($app['phone_number'] ?? $app['phone'] ?? 'Not provided'); ?>
                                                </p>
                                                <?php if (!empty($app['location']) || !empty($app['address'])): ?>
                                                <p class="mb-1">
                                                    <strong><i class="fas fa-home me-1"></i>Address:</strong> 
                                                    <?php echo htmlspecialchars($app['location'] ?? $app['address']); ?>
                                                </p>
                                                <?php endif; ?>
                                            </div>
                                        </div>

                                        <!-- Additional Information -->
                                        <?php if (!empty($app['skills']) || !empty($app['experience']) || !empty($app['education']) || !empty($app['bio'])): ?>
                                        <hr>
                                        <div class="row">
                                            <?php if (!empty($app['skills'])): ?>
                                            <div class="col-md-12 mb-2">
                                                <strong><i class="fas fa-tools me-1"></i>Skills:</strong>
                                                <div class="mt-1">
                                                    <?php 
                                                    $skills = explode(',', $app['skills']);
                                                    foreach ($skills as $skill): 
                                                        $skill = trim($skill);
                                                        if (!empty($skill)):
                                                    ?>
                                                        <span class="badge bg-secondary me-1 mb-1"><?php echo htmlspecialchars($skill); ?></span>
                                                    <?php 
                                                        endif;
                                                    endforeach; 
                                                    ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($app['experience'])): ?>
                                            <div class="col-md-12 mb-2">
                                                <strong><i class="fas fa-briefcase me-1"></i>Experience:</strong>
                                                <div class="mt-1 text-muted" style="max-height: 100px; overflow-y: auto;">
                                                    <?php echo nl2br(htmlspecialchars(substr($app['experience'], 0, 200))); ?>
                                                    <?php if (strlen($app['experience']) > 200): ?>
                                                        <span class="text-primary">... (truncated)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($app['education'])): ?>
                                            <div class="col-md-6 mb-2">
                                                <strong><i class="fas fa-graduation-cap me-1"></i>Education:</strong>
                                                <div class="mt-1 text-muted">
                                                    <?php echo nl2br(htmlspecialchars($app['education'])); ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>

                                            <?php if (!empty($app['bio'])): ?>
                                            <div class="col-md-6 mb-2">
                                                <strong><i class="fas fa-user-circle me-1"></i>Bio:</strong>
                                                <div class="mt-1 text-muted">
                                                    <?php echo nl2br(htmlspecialchars(substr($app['bio'], 0, 150))); ?>
                                                    <?php if (strlen($app['bio']) > 150): ?>
                                                        <span class="text-primary">... (truncated)</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>

                                        <!-- Files Section -->
                                        <hr>
                                        <div class="d-flex flex-wrap gap-2">
                                            <strong class="me-2"><i class="fas fa-paperclip me-1"></i>Files:</strong>
                                            
                                            <?php if (!empty($app['cv_path']) || !empty($app['cv_file'])): ?>
                                                <a href="<?php echo htmlspecialchars($app['cv_path'] ?? $app['cv_file']); ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-file-pdf me-1"></i>CV/Resume
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!empty($app['portfolio_path']) || !empty($app['portfolio_file'])): ?>
                                                <a href="<?php echo htmlspecialchars($app['portfolio_path'] ?? $app['portfolio_file']); ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-folder me-1"></i>Portfolio
                                                </a>
                                            <?php endif; ?>

                                            <?php if (!empty($app['cover_letter_path']) || !empty($app['cover_letter_file'])): ?>
                                                <a href="<?php echo htmlspecialchars($app['cover_letter_path'] ?? $app['cover_letter_file']); ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-file-alt me-1"></i>Cover Letter
                                                </a>
                                            <?php endif; ?>

                                            <?php if (empty($app['cv_path']) && empty($app['cv_file']) && 
                                                      empty($app['portfolio_path']) && empty($app['portfolio_file']) && 
                                                      empty($app['cover_letter_path']) && empty($app['cover_letter_file'])): ?>
                                                <span class="text-muted">No files uploaded</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination could be added here if needed -->
                    <div class="d-flex justify-content-center mt-4">
                        <small class="text-muted">
                            Showing all <?php echo count($applications); ?> application(s)
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($debugMode): ?>
    <script>
        console.log('Debug Mode Active');
        console.log('User ID: <?php echo $user_id; ?>');
        console.log('Job Seeker ID: <?php echo $job_seeker_id ?? "null"; ?>');
        console.log('Applications Count: <?php echo count($applications); ?>');
        <?php if (!empty($applications)): ?>
        console.log('Sample Application:', <?php echo json_encode($applications[0]); ?>);
        <?php endif; ?>
    </script>
    <?php endif; ?>
</body>
</html>
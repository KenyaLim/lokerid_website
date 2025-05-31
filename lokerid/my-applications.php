<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a job seeker
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'job_seeker') {
    header("Location: login.php");
    exit();
}

// Get all applications for the current job seeker
$stmt = $conn->prepare("
    SELECT 
        ja.*,
        jl.title as job_title,
        jl.job_type,
        jl.deadline_date,
        c.company_name,
        c.logo_path
    FROM job_applications ja
    JOIN job_listings jl ON ja.job_listing_id = jl.id
    JOIN companies c ON jl.company_id = c.id
    WHERE ja.job_seeker_id = ?
    ORDER BY ja.applied_at DESC
");
$stmt->execute([$_SESSION['job_seeker_id']]);
$applications = $stmt->fetchAll();
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
        <h2 class="mb-4">My Applications</h2>

        <?php if (empty($applications)): ?>
            <div class="alert alert-info">
                You haven't applied to any jobs yet. <a href="index.php">Browse jobs</a> to find opportunities.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($applications as $application): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?php echo htmlspecialchars($application['logo_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($application['company_name']); ?>" 
                                         class="company-logo me-3">
                                    <div>
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($application['job_title']); ?></h5>
                                        <h6 class="text-muted mb-0"><?php echo htmlspecialchars($application['company_name']); ?></h6>
                                    </div>
                                </div>

                                <div class="job-info">
                                    <p class="mb-2">
                                        <i class="fas fa-clock me-2"></i>
                                        <?php echo htmlspecialchars($application['job_type']); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-calendar-check me-2"></i>
                                        Applied on: <?php echo date('j M Y', strtotime($application['applied_at'])); ?>
                                    </p>
                                    <p class="mb-2">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        Deadline: <?php echo date('j M Y', strtotime($application['deadline_date'])); ?>
                                        <?php if (strtotime($application['deadline_date']) < time()): ?>
                                            <span class="badge bg-danger ms-2">Expired</span>
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <div class="mt-3">
                                    <h6>Submitted Documents:</h6>
                                    <div class="d-flex gap-2">
                                        <a href="<?php echo htmlspecialchars($application['cv_path']); ?>" 
                                           class="btn btn-sm btn-primary" target="_blank">
                                            <i class="fas fa-file-pdf me-1"></i>CV
                                        </a>
                                        <?php if ($application['portfolio_path']): ?>
                                            <a href="<?php echo htmlspecialchars($application['portfolio_path']); ?>" 
                                               class="btn btn-sm btn-info" target="_blank">
                                                <i class="fas fa-briefcase me-1"></i>Portfolio
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($application['cover_letter_path']): ?>
                                            <a href="<?php echo htmlspecialchars($application['cover_letter_path']); ?>" 
                                               class="btn btn-sm btn-secondary" target="_blank">
                                                <i class="fas fa-envelope me-1"></i>Cover Letter
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

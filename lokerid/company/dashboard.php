<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

// Get company statistics
$companyId = $_SESSION['company_id'];

// Get total job listings
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM job_listings 
    WHERE company_id = ?
");
$stmt->execute([$companyId]);
$totalJobs = $stmt->fetchColumn();

// Get total applications
$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM job_applications ja
    JOIN job_listings jl ON ja.job_listing_id = jl.id
    WHERE jl.company_id = ?
");
$stmt->execute([$companyId]);
$totalApplications = $stmt->fetchColumn();

// Get active jobs (not past deadline)
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM job_listings 
    WHERE company_id = ? AND deadline_date >= CURRENT_DATE
");
$stmt->execute([$companyId]);
$activeJobs = $stmt->fetchColumn();

// Get latest applications
$stmt = $conn->prepare("
    SELECT 
        ja.*,
        jl.title as job_title
    FROM job_applications ja
    JOIN job_listings jl ON ja.job_listing_id = jl.id
    WHERE jl.company_id = ?
    ORDER BY ja.applied_at DESC
    LIMIT 5
");
$stmt->execute([$companyId]);
$latestApplications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container mt-4">
        <h2 class="mb-4">Dashboard</h2>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Total Job Listings</h6>
                                <h2 class="mt-2 mb-0"><?php echo $totalJobs; ?></h2>
                            </div>
                            <i class="fas fa-briefcase fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Active Jobs</h6>
                                <h2 class="mt-2 mb-0"><?php echo $activeJobs; ?></h2>
                            </div>
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title mb-0">Total Applications</h6>
                                <h2 class="mt-2 mb-0"><?php echo $totalApplications; ?></h2>
                            </div>
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Latest Applications</h5>
                        <a href="applications.php" class="btn btn-primary btn-sm">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($latestApplications)): ?>
                            <p class="text-muted">No applications yet.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Applied Date</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($latestApplications as $application): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($application['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                                <td><?php echo date('j M Y', strtotime($application['applied_at'])); ?></td>
                                                <td>
                                                    <a href="view-application.php?id=<?php echo $application['id']; ?>" class="btn btn-sm btn-primary">
                                                        View Details
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

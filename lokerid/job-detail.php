<?php
session_start();
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    echo "Job ID is required.";
    exit();
}

$job_id = $_GET['id'];

// Ambil detail lowongan pekerjaan
$stmt = $conn->prepare("
    SELECT jl.*, c.company_name, c.logo_path 
    FROM job_listings jl 
    JOIN companies c ON jl.company_id = c.id 
    WHERE jl.id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    echo "Job not found.";
    exit();
}

// Jika user sudah login sebagai pencari kerja, ambil data mereka
$userData = null;
if (isset($_SESSION['user_id']) && ($_SESSION['user_role'] === 'job_seeker' || $_SESSION['role'] === 'job_seeker')) {
    try {
        // Pertama coba ambil dari tabel users (berdasarkan profile.php)
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'job_seeker'");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        
        // Jika tidak ada di users atau tidak lengkap, coba dari job_seekers
        if (!$userData) {
            $stmt = $conn->prepare("
                SELECT u.*, js.full_name, js.birth_date, js.phone_number as phone
                FROM users u 
                JOIN job_seekers js ON u.id = js.user_id 
                WHERE u.id = ?
            ");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch();
        }
        
    } catch (Exception $e) {
        // Log error instead of showing to user
        error_log("Error fetching user data: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($job['title']) ?> - Job Detail</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'components/navbar.php'; ?>

<div class="container mt-4">
    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <div class="d-flex align-items-center mb-3">
                <img src="<?= htmlspecialchars($job['logo_path']) ?>" alt="Company Logo" class="me-3" style="height: 80px; width: 80px; object-fit: cover;">
                <div>
                    <h4 class="mb-1"><?= htmlspecialchars($job['title']) ?></h4>
                    <p class="mb-0"><strong><?= htmlspecialchars($job['company_name']) ?></strong> - <?= htmlspecialchars($job['location']) ?></p>
                    <p class="mb-0"><small class="text-muted">Deadline: <?= htmlspecialchars($job['deadline_date']) ?></small></p>
                    <span class="badge bg-primary"><?= htmlspecialchars($job['job_type']) ?></span>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <h5><i class="fas fa-file-alt me-2"></i>Job Description</h5>
                    <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>

                    <h5><i class="fas fa-list-check me-2"></i>Requirements</h5>
                    <p><?= nl2br(htmlspecialchars($job['requirements'])) ?></p>
                </div>
                <div class="col-md-4">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6><i class="fas fa-money-bill-wave me-2"></i>Salary Range</h6>
                            <p class="mb-2">Rp <?= number_format($job['salary_min']) ?> - Rp <?= number_format($job['salary_max']) ?></p>
                            
                            <h6><i class="fas fa-map-marker-alt me-2"></i>Location</h6>
                            <p class="mb-2"><?= htmlspecialchars($job['location']) ?></p>
                            
                            <h6><i class="fas fa-clock me-2"></i>Job Type</h6>
                            <p class="mb-2"><?= htmlspecialchars($job['job_type']) ?></p>
                            
                            <h6><i class="fas fa-calendar-alt me-2"></i>Deadline</h6>
                            <p class="mb-0"><?= date('d M Y', strtotime($job['deadline_date'])) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <hr class="my-4">

            <?php if ($userData): ?>
    <!-- Check if user has already applied -->
    <?php
    // First get the job_seeker_id from job_seekers table using user_id
    $stmt = $conn->prepare("SELECT id FROM job_seekers WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $jobSeekerRecord = $stmt->fetch();
    
    $hasApplied = false;
    if ($jobSeekerRecord) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE job_listing_id = ? AND job_seeker_id = ?");
        $stmt->execute([$job['id'], $jobSeekerRecord['id']]);
        $hasApplied = $stmt->fetchColumn() > 0;
    }
    ?>
    
    <?php if ($hasApplied): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            <strong>Application Submitted!</strong> You have already applied for this position.
        </div>
    <?php else: ?>
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Jobs
            </a>
            <a href="apply.php?id=<?= $job['id'] ?>" class="btn btn-primary btn-lg">
                <i class="fas fa-paper-plane me-2"></i>Apply for this Job
            </a>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle me-2"></i>
        <strong>Login Required:</strong> 
        Please <a href="login.php" class="alert-link">login as a job seeker</a> to apply for this job.
    </div>
    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
        <a href="index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Back to Jobs
        </a>
    </div>
<?php endif; ?>
        </div>
    </div>
</div>

<?php include 'components/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</script>
</body>
</html>
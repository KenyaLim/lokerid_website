<?php
session_start();
require_once 'config/database.php';

$job_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$job_id) {
    header('Location: index.php');
    exit();
}

$stmt = $conn->prepare("
    SELECT 
        j.*,
        c.company_name,
        c.logo_path,
        c.location as company_location,
        cat.name as category_name
    FROM job_listings j
    JOIN companies c ON j.company_id = c.id
    JOIN job_categories cat ON j.category_id = cat.id
    WHERE j.id = :job_id
");

$stmt->bindParam(':job_id', $job_id);
$stmt->execute();
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <!-- Job Details -->
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <img src="<?php echo htmlspecialchars($job['logo_path']); ?>" alt="Company Logo" class="company-logo me-3" style="width: 80px; height: 80px; object-fit: contain;">
                            <div>
                                <h2 class="card-title mb-1"><?php echo htmlspecialchars($job['title']); ?></h2>
                                <h5 class="company-name text-muted"><?php echo htmlspecialchars($job['company_name']); ?></h5>
                            </div>
                        </div>

                        <div class="job-meta mb-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($job['location']); ?></p>
                                    <p><i class="fas fa-briefcase me-2"></i> <?php echo htmlspecialchars($job['job_type']); ?></p>
                                    <?php if (isset($job['education_level'])): ?>
                                        <p><i class="fas fa-graduation-cap me-2"></i> <?php echo htmlspecialchars($job['education_level']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if (isset($job['experience_level'])): ?>
                                        <p><i class="fas fa-clock me-2"></i> <?php echo htmlspecialchars($job['experience_level']); ?></p>
                                    <?php endif; ?>
                                    <p><i class="fas fa-money-bill-wave me-2"></i> Rp <?php echo number_format($job['salary_min']); ?> - Rp <?php echo number_format($job['salary_max']); ?></p>
                                    <p><i class="fas fa-calendar-alt me-2"></i> Deadline: <?php echo date('d M Y', strtotime($job['deadline_date'])); ?></p>
                                </div>
                            </div>
                        </div>

                        <div class="job-description mb-4">
                            <h4>Job Description</h4>
                            <p><?php echo nl2br(htmlspecialchars($job['description'])); ?></p>
                        </div>

                        <div class="job-requirements mb-4">
                            <h4>Requirements</h4>
                            <?php echo nl2br(htmlspecialchars($job['requirements'])); ?>
                        </div>

                        <?php if (isset($job['responsibilities']) && !empty($job['responsibilities'])): ?>
                        <div class="job-responsibilities mb-4">
                            <h4>Responsibilities</h4>
                            <?php echo nl2br(htmlspecialchars($job['responsibilities'])); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($job['benefits']) && !empty($job['benefits'])): ?>
                        <div class="job-benefits mb-4">
                            <h4>Benefits</h4>
                            <?php echo nl2br(htmlspecialchars($job['benefits'])); ?>
                        </div>
                        <?php endif; ?>

                        <?php if (isset($job['skills_required']) && !empty($job['skills_required'])): ?>
                        <div class="job-skills mb-4">
                            <h4>Required Skills</h4>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach (explode(',', $job['skills_required']) as $skill): ?>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Company Info & Apply Button -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-body">
                        <h4 class="card-title mb-3">Company Information</h4>
                        <p><i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($job['company_name']); ?></p>
                        <p><i class="fas fa-map-marker-alt me-2"></i> <?php echo htmlspecialchars($job['company_location']); ?></p>
                        <div class="company-description mt-3">
                            <p>We are a dynamic company looking for talented individuals to join our team. If you're passionate about your work and looking for a challenging opportunity, we'd love to hear from you!</p>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_role'] == 'job_seeker'): ?>
                    <div class="card">
                        <div class="card-body">
                            <a href="apply.php?job_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-lg w-100">
                                Apply Now
                            </a>
                        </div>
                    </div>
                <?php elseif (!isset($_SESSION['user_id'])): ?>
                    <div class="card">
                        <div class="card-body">
                            <p class="text-center mb-3">Please login to apply for this job</p>
                            <a href="login.php" class="btn btn-primary btn-lg w-100">
                                Login to Apply
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <!-- Theme Toggle Button -->
    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
        <i class="fas fa-moon"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/theme.js"></script>
</body>
</html>

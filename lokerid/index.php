<?php
session_start();
require_once 'config/database.php';

// Get search parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$location = isset($_GET['location']) ? $_GET['location'] : '';
$job_type = isset($_GET['job_type']) ? $_GET['job_type'] : '';
$salary_range = isset($_GET['salary_range']) ? $_GET['salary_range'] : '';

// Base query
$query = "
    SELECT 
        j.*,
        c.company_name,
        c.logo_path,
        cat.name as category_name
    FROM job_listings j
    JOIN companies c ON j.company_id = c.id
    JOIN job_categories cat ON j.category_id = cat.id
    WHERE j.deadline_date >= CURRENT_DATE()
";

// Add search conditions
if (!empty($search)) {
    $query .= " AND (c.company_name LIKE :search OR j.title LIKE :search)";
}
if (!empty($category)) {
    $query .= " AND j.category_id = :category";
}
if (!empty($location)) {
    $query .= " AND j.location LIKE :location";
}
if (!empty($job_type)) {
    $query .= " AND j.job_type = :job_type";
}
if (!empty($salary_range)) {
    list($min, $max) = explode('-', $salary_range);
    $query .= " AND j.salary_min >= :salary_min AND j.salary_max <= :salary_max";
}

$query .= " ORDER BY j.created_at DESC";

$stmt = $conn->prepare($query);

// Bind parameters
if (!empty($search)) {
    $searchTerm = "%$search%";
    $stmt->bindParam(':search', $searchTerm);
}
if (!empty($category)) {
    $stmt->bindParam(':category', $category);
}
if (!empty($location)) {
    $locationTerm = "%$location%";
    $stmt->bindParam(':location', $locationTerm);
}
if (!empty($job_type)) {
    $stmt->bindParam(':job_type', $job_type);
}
if (!empty($salary_range)) {
    $stmt->bindParam(':salary_min', $min);
    $stmt->bindParam(':salary_max', $max);
}

$stmt->execute();
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $conn->query("SELECT * FROM job_categories ORDER BY name");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LokerID - Find Your Dream Job</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>


    <div class="container mt-4">
        <!-- Search Form -->
        <div class="card mb-4">
            <div class="card-body p-3">
                <form action="" method="GET" class="row g-2 justify-content-center">
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control" name="search" placeholder="Search jobs or companies..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-briefcase"></i></span>
                            <select name="category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <input type="text" class="form-control" name="location" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="fas fa-clock"></i></span>
                            <select name="job_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="Full-time" <?php echo $job_type == 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                <option value="Part-time" <?php echo $job_type == 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                <option value="Remote" <?php echo $job_type == 'Remote' ? 'selected' : ''; ?>>Remote</option>
                                <option value="Freelance" <?php echo $job_type == 'Freelance' ? 'selected' : ''; ?>>Freelance</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Job Listings -->
        <div class="row justify-content-center">
            <?php foreach ($jobs as $job): ?>
                <div class="col-md-5 mb-4">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center mb-3">
                                <img src="<?php echo htmlspecialchars($job['logo_path']); ?>" alt="Company Logo" class="company-logo me-3" style="width: 60px; height: 60px; object-fit: contain;">
                                <div>
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                                    <h6 class="company-name text-muted"><?php echo htmlspecialchars($job['company_name']); ?></h6>
                                </div>
                            </div>
                            <div class="job-info">
                                <p class="mb-2">
                                    <i class="fas fa-tag me-2"></i>
                                    <?php echo htmlspecialchars($job['category_name']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-map-marker-alt me-2"></i>
                                    <?php echo htmlspecialchars($job['location']); ?>
                                </p>
                                <p class="mb-2">
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo htmlspecialchars($job['job_type']); ?>
                                </p>
                                <?php if ($job['salary_min'] && $job['salary_max']): ?>
                                    <p class="mb-2">
                                        <i class="fas fa-money-bill-wave me-2"></i>
                                        Rp <?php echo number_format($job['salary_min']); ?> - 
                                        Rp <?php echo number_format($job['salary_max']); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <a href="job-detail.php?id=<?php echo $job['id']; ?>" class="btn btn-outline-primary mt-3">View Details</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>    </div>

    <?php include 'components/footer.php'; ?>
    

    <!-- Login Modal -->
    <div class="modal fade" id="loginModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Login</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="loginForm" action="auth/login.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Login</button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <p>Don't have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#registerModal" data-bs-dismiss="modal">Register here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Register</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="registerForm" action="auth/register.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="job_seeker">Job Seeker</option>
                                <option value="company">Company</option>
                            </select>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Register</button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <p>Already have an account? <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Login here</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/auth.js"></script>
</body>
</html>

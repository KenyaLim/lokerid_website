<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

$companyId = $_SESSION['company_id'];
$jobId = isset($_GET['job_id']) ? $_GET['job_id'] : null;

// Build query based on whether a specific job is selected
$query = "
    SELECT 
        ja.*,
        jl.title as job_title,
        jl.job_type,
        jl.deadline_date
    FROM job_applications ja
    JOIN job_listings jl ON ja.job_listing_id = jl.id
    WHERE jl.company_id = ?
";
$params = [$companyId];

if ($jobId) {
    $query .= " AND jl.id = ?";
    $params[] = $jobId;
}

$query .= " ORDER BY ja.applied_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get company's job listings for filter
$stmt = $conn->prepare("
    SELECT id, title 
    FROM job_listings 
    WHERE company_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$companyId]);
$jobs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Job Applications</h2>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2">
                    <select name="job_id" class="form-select">
                        <option value="">All Jobs</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo $job['id']; ?>" <?php echo $jobId == $job['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <p class="text-muted">No applications found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Position</th>
                                    <th>Applied Date</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Files</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $application): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($application['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($application['job_title']); ?></td>
                                        <td><?php echo date('j M Y', strtotime($application['applied_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($application['email']); ?></td>
                                        <td><?php echo htmlspecialchars($application['phone_number']); ?></td>
                                        <td>
                                            <div class="btn-group">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

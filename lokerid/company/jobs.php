<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

$companyId = $_SESSION['company_id'];

// Handle job deletion
if (isset($_POST['delete_job'])) {
    $jobId = $_POST['job_id'];
    
    // Check if job has any applications
    $stmt = $conn->prepare("
        SELECT COUNT(*) FROM job_applications 
        WHERE job_listing_id = ?
    ");
    $stmt->execute([$jobId]);
    $hasApplications = $stmt->fetchColumn() > 0;

    if (!$hasApplications) {
        // Delete the job if it has no applications
        $stmt = $conn->prepare("
            DELETE FROM job_listings 
            WHERE id = ? AND company_id = ?
        ");
        $stmt->execute([$jobId, $companyId]);
        $successMessage = "Job listing deleted successfully.";
    } else {
        $errorMessage = "Cannot delete job listing because it has applications.";
    }
}

// Get all job listings for the company
$stmt = $conn->prepare("
    SELECT 
        j.*,
        cat.name as category_name,
        (SELECT COUNT(*) FROM job_applications WHERE job_listing_id = j.id) as application_count
    FROM job_listings j
    JOIN job_categories cat ON j.category_id = cat.id
    WHERE j.company_id = ?
    ORDER BY j.created_at DESC
");
$stmt->execute([$companyId]);
$jobs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Jobs - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Job Listings</h2>
            <a href="create-job.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Create New Job
            </a>
        </div>

        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $successMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $errorMessage; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Applications</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($jobs as $job): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($job['title']); ?></td>
                                    <td><?php echo htmlspecialchars($job['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($job['job_type']); ?></td>
                                    <td>
                                        <a href="applications.php?job_id=<?php echo $job['id']; ?>" class="text-decoration-none">
                                            <?php echo $job['application_count']; ?> applications
                                        </a>
                                    </td>
                                    <td><?php echo date('j M Y', strtotime($job['deadline_date'])); ?></td>
                                    <td>
                                        <?php if (strtotime($job['deadline_date']) >= time()): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="edit-job.php?id=<?php echo $job['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $job['id']; ?>, <?php echo $job['application_count']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this job listing?</p>
                    <p id="deleteWarning" class="text-danger"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="job_id" id="deleteJobId">
                        <button type="submit" name="delete_job" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(jobId, applicationCount) {
            document.getElementById('deleteJobId').value = jobId;
            const warningElement = document.getElementById('deleteWarning');
            
            if (applicationCount > 0) {
                warningElement.textContent = 'This job listing has applications and cannot be deleted.';
                document.querySelector('#deleteModal .btn-danger').style.display = 'none';
            } else {
                warningElement.textContent = '';
                document.querySelector('#deleteModal .btn-danger').style.display = 'block';
            }
            
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>

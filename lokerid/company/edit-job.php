<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

// Check if job ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: jobs.php");
    exit();
}

$jobId = $_GET['id'];
$companyId = $_SESSION['company_id'];

// Get job categories
$stmt = $conn->query("SELECT * FROM job_categories ORDER BY name");
$categories = $stmt->fetchAll();

// Get job details
$stmt = $conn->prepare("
    SELECT * FROM job_listings 
    WHERE id = ? AND company_id = ?
");
$stmt->execute([$jobId, $companyId]);
$job = $stmt->fetch();

// Check if job exists and belongs to this company
if (!$job) {
    header("Location: jobs.php");
    exit();
}

// Check if job has applications
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM job_applications 
    WHERE job_listing_id = ?
");
$stmt->execute([$jobId]);
$hasApplications = $stmt->fetchColumn() > 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];

    // Validate required fields
    $requiredFields = ['title', 'category', 'job_type', 'location', 'description', 'requirements', 'deadline_date'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
        }
    }

    // Validate salary range
    if (!empty($_POST['salary_min']) && !empty($_POST['salary_max'])) {
        if (!is_numeric($_POST['salary_min']) || !is_numeric($_POST['salary_max'])) {
            $errors[] = "Salary must be a number.";
        } elseif ($_POST['salary_min'] > $_POST['salary_max']) {
            $errors[] = "Minimum salary cannot be greater than maximum salary.";
        }
    }

    // Validate deadline date
    if (!empty($_POST['deadline_date'])) {
        // Allow current date if job already has applications
        $minDate = $hasApplications ? strtotime('today') : time();
        
        if (strtotime($_POST['deadline_date']) < $minDate) {
            if ($hasApplications) {
                $errors[] = "Deadline date cannot be in the past.";
            } else {
                $errors[] = "Deadline date cannot be in the past.";
            }
        }
    }

    if (empty($errors)) {
        try {
            // Fixed SQL query - removed updated_at column reference
            $stmt = $conn->prepare("
                UPDATE job_listings SET 
                    category_id = ?, 
                    title = ?, 
                    job_type = ?, 
                    salary_min = ?, 
                    salary_max = ?,
                    location = ?, 
                    description = ?, 
                    requirements = ?, 
                    deadline_date = ?
                WHERE id = ? AND company_id = ?
            ");
            
            $stmt->execute([
                $_POST['category'],
                $_POST['title'],
                $_POST['job_type'],
                !empty($_POST['salary_min']) ? $_POST['salary_min'] : null,
                !empty($_POST['salary_max']) ? $_POST['salary_max'] : null,
                $_POST['location'],
                $_POST['description'],
                $_POST['requirements'],
                $_POST['deadline_date'],
                $jobId,
                $companyId
            ]);

            $_SESSION['success_message'] = "Job listing updated successfully.";
            header("Location: jobs.php");
            exit();
        } catch (PDOException $e) {
            // Better error handling - show actual error in development
            $errors[] = "An error occurred while updating the job listing: " . $e->getMessage();
            // For production, use: $errors[] = "An error occurred while updating the job listing.";
        }
    }
}

// Use POST data if available, otherwise use job data
$formData = $_POST ?: $job;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job Listing - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="card-title mb-0">Edit Job Listing</h3>
                            <a href="jobs.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Jobs
                            </a>
                        </div>

                        <?php if ($hasApplications): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                <strong>Note:</strong> This job listing has applications. Be careful when making changes as they may affect existing applicants.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label">Job Title</label>
                                <input type="text" class="form-control" id="title" name="title" required
                                       value="<?php echo htmlspecialchars($formData['title']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo $formData['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="job_type" class="form-label">Job Type</label>
                                <select class="form-select" id="job_type" name="job_type" required>
                                    <option value="">Select Job Type</option>
                                    <?php 
                                    $jobTypes = ['Full-time', 'Part-time', 'Remote', 'Freelance'];
                                    foreach ($jobTypes as $type): 
                                    ?>
                                        <option value="<?php echo $type; ?>" 
                                                <?php echo $formData['job_type'] == $type ? 'selected' : ''; ?>>
                                            <?php echo $type; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="salary_min" class="form-label">Minimum Salary</label>
                                    <input type="number" class="form-control" id="salary_min" name="salary_min"
                                           value="<?php echo htmlspecialchars($formData['salary_min'] ?? ''); ?>"
                                           placeholder="Optional">
                                </div>
                                <div class="col-md-6">
                                    <label for="salary_max" class="form-label">Maximum Salary</label>
                                    <input type="number" class="form-control" id="salary_max" name="salary_max"
                                           value="<?php echo htmlspecialchars($formData['salary_max'] ?? ''); ?>"
                                           placeholder="Optional">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required
                                       value="<?php echo htmlspecialchars($formData['location']); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Job Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php 
                                    echo htmlspecialchars($formData['description']); 
                                ?></textarea>
                                <div class="form-text">Describe the job role, responsibilities, and what you're looking for.</div>
                            </div>

                            <div class="mb-3">
                                <label for="requirements" class="form-label">Requirements</label>
                                <textarea class="form-control" id="requirements" name="requirements" rows="5" required><?php 
                                    echo htmlspecialchars($formData['requirements']); 
                                ?></textarea>
                                <div class="form-text">List the required skills, experience, and qualifications.</div>
                            </div>

                            <div class="mb-3">
                                <label for="deadline_date" class="form-label">Application Deadline</label>
                                <input type="date" class="form-control" id="deadline_date" name="deadline_date" required
                                       value="<?php echo htmlspecialchars($formData['deadline_date']); ?>"
                                       min="<?php echo date('Y-m-d'); ?>">
                                <div class="form-text">
                                    Applications will not be accepted after this date.
                                    <?php if ($hasApplications): ?>
                                        <br><strong>Warning:</strong> Changing this date may affect existing applicants.
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="card border-light bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Job Statistics</h6>
                                            <p class="mb-1"><strong>Created:</strong> <?php echo date('j M Y', strtotime($job['created_at'])); ?></p>
                                            <?php 
                                            // Get application count for this job
                                            $stmt = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE job_listing_id = ?");
                                            $stmt->execute([$jobId]);
                                            $applicationCount = $stmt->fetchColumn();
                                            ?>
                                            <p class="mb-0"><strong>Applications Received:</strong> 
                                                <span class="badge bg-primary"><?php echo $applicationCount; ?></span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card border-light bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">Current Status</h6>
                                            <?php if (strtotime($job['deadline_date']) >= time()): ?>
                                                <span class="badge bg-success mb-2">
                                                    <i class="fas fa-check-circle"></i> Active
                                                </span>
                                                <p class="mb-0 small text-muted">
                                                    Expires on <?php echo date('j M Y', strtotime($job['deadline_date'])); ?>
                                                </p>
                                            <?php else: ?>
                                                <span class="badge bg-danger mb-2">
                                                    <i class="fas fa-times-circle"></i> Expired
                                                </span>
                                                <p class="mb-0 small text-muted">
                                                    Expired on <?php echo date('j M Y', strtotime($job['deadline_date'])); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Job Listing
                                </button>
                                <a href="jobs.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Additional Actions -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Additional Actions</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <a href="applications.php?job_id=<?php echo $job['id']; ?>" class="btn btn-outline-info w-100 mb-2">
                                    <i class="fas fa-users"></i> View Applications
                                </a>
                            </div>
                            <div class="col-md-6">
                                <?php if (!$hasApplications): ?>
                                    <button type="button" class="btn btn-outline-danger w-100 mb-2" 
                                            onclick="confirmDelete(<?php echo $job['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete Job Listing
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary w-100 mb-2" disabled title="Cannot delete job with applications">
                                        <i class="fas fa-trash"></i> Cannot Delete (Has Applications)
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
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
                    <p class="text-danger"><strong>Warning:</strong> This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" action="jobs.php" class="d-inline">
                        <input type="hidden" name="job_id" id="deleteJobId">
                        <button type="submit" name="delete_job" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(jobId) {
            document.getElementById('deleteJobId').value = jobId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        // Auto-resize textareas
        document.addEventListener('DOMContentLoaded', function() {
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(function(textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = this.scrollHeight + 'px';
                });
                
                // Initialize height
                textarea.style.height = 'auto';
                textarea.style.height = textarea.scrollHeight + 'px';
            });
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const salaryMin = document.getElementById('salary_min').value;
            const salaryMax = document.getElementById('salary_max').value;
            
            if (salaryMin && salaryMax && parseInt(salaryMin) > parseInt(salaryMax)) {
                e.preventDefault();
                alert('Minimum salary cannot be greater than maximum salary.');
                return false;
            }
            
            const deadlineDate = document.getElementById('deadline_date').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (deadlineDate < today) {
                e.preventDefault();
                alert('Deadline date cannot be in the past.');
                return false;
            }
        });
    </script>
</body>
</html>
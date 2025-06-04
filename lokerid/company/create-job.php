<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");
    exit();
}

// Get job categories
$stmt = $conn->query("SELECT * FROM job_categories ORDER BY name");
$categories = $stmt->fetchAll();

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
        if (strtotime($_POST['deadline_date']) < time()) {
            $errors[] = "Deadline date cannot be in the past.";
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("
                INSERT INTO job_listings (
                    company_id, category_id, title, job_type, salary_min, salary_max,
                    location, description, requirements, deadline_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $_SESSION['company_id'],
                $_POST['category'],
                $_POST['title'],
                $_POST['job_type'],
                !empty($_POST['salary_min']) ? $_POST['salary_min'] : null,
                !empty($_POST['salary_max']) ? $_POST['salary_max'] : null,
                $_POST['location'],
                $_POST['description'],
                $_POST['requirements'],
                $_POST['deadline_date']
            ]);

            header("Location: jobs.php?created=1");
            exit();
        } catch (PDOException $e) {
            $errors[] = "An error occurred while creating the job listing.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Job Listing - LokerID</title>
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
                        <h3 class="card-title mb-4">Create New Job Listing</h3>

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
                                       value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo isset($_POST['category']) && $_POST['category'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="job_type" class="form-label">Job Type</label>
                                <select class="form-select" id="job_type" name="job_type" required>
                                    <option value="">Select Job Type</option>
                                    <option value="Full-time">Full-time</option>
                                    <option value="Part-time">Part-time</option>
                                    <option value="Remote">Remote</option>
                                    <option value="Freelance">Freelance</option>
                                </select>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="salary_min" class="form-label">Minimum Salary</label>
                                    <input type="number" class="form-control" id="salary_min" name="salary_min"
                                           value="<?php echo isset($_POST['salary_min']) ? htmlspecialchars($_POST['salary_min']) : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="salary_max" class="form-label">Maximum Salary</label>
                                    <input type="number" class="form-control" id="salary_max" name="salary_max"
                                           value="<?php echo isset($_POST['salary_max']) ? htmlspecialchars($_POST['salary_max']) : ''; ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" required
                                       value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Job Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?php 
                                    echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; 
                                ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="requirements" class="form-label">Requirements</label>
                                <textarea class="form-control" id="requirements" name="requirements" rows="5" required><?php 
                                    echo isset($_POST['requirements']) ? htmlspecialchars($_POST['requirements']) : ''; 
                                ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="deadline_date" class="form-label">Application Deadline</label>
                                <input type="date" class="form-control" id="deadline_date" name="deadline_date" required
                                       value="<?php echo isset($_POST['deadline_date']) ? htmlspecialchars($_POST['deadline_date']) : ''; ?>">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Create Job Listing</button>
                                <a href="jobs.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

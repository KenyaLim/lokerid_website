<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a job seeker
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'job_seeker' && $_SESSION['role'] !== 'job_seeker')) {
    header("Location: login.php");
    exit();
}

// Check if job ID is provided and valid
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$jobId = $_GET['id'];

$debugMode = true; // Set to false in production

if ($debugMode) {
    try {
        // Get table structure
        $stmt = $conn->query("DESCRIBE job_applications");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("=== JOB_APPLICATIONS TABLE STRUCTURE ===");
        foreach ($columns as $column) {
            error_log("Column: " . $column['Field'] . " | Type: " . $column['Type'] . " | Null: " . $column['Null'] . " | Key: " . $column['Key']);
        }
        
        // Get sample data to understand the structure
        $stmt = $conn->query("SELECT * FROM job_applications LIMIT 1");
        $sampleRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sampleRow) {
            error_log("=== SAMPLE ROW FROM JOB_APPLICATIONS ===");
            error_log(print_r(array_keys($sampleRow), true));
        }
        
    } catch (Exception $e) {
        error_log("Debug error: " . $e->getMessage());
    }
}

// Simple approach: Get column names and determine the user reference column
$userColumn = null;
$userId = null;

try {
    $stmt = $conn->query("SHOW COLUMNS FROM job_applications");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Check for user reference columns in order of preference
    if (in_array('job_seeker_id', $columns)) {
        $userColumn = 'job_seeker_id';
        // Try to get job_seeker_id
        try {
            $stmt = $conn->prepare("SELECT id FROM job_seekers WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $result = $stmt->fetch();
            if ($result) {
                $userId = $result['id'];
            } else {
                // Create job_seeker record if it doesn't exist
                $stmt = $conn->prepare("INSERT INTO job_seekers (user_id) VALUES (?)");
                $stmt->execute([$_SESSION['user_id']]);
                $userId = $conn->lastInsertId();
            }
        } catch (Exception $e) {
            error_log("Error with job_seeker_id: " . $e->getMessage());
            // Fall back to user_id if available
            if (in_array('user_id', $columns)) {
                $userColumn = 'user_id';
                $userId = $_SESSION['user_id'];
            }
        }
    } elseif (in_array('user_id', $columns)) {
        $userColumn = 'user_id';
        $userId = $_SESSION['user_id'];
    } elseif (in_array('applicant_id', $columns)) {
        $userColumn = 'applicant_id';
        $userId = $_SESSION['user_id'];
    } elseif (in_array('seeker_id', $columns)) {
        $userColumn = 'seeker_id';
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userColumn) {
        $availableColumns = implode(', ', $columns);
        error_log("Available columns: " . $availableColumns);
        die("Cannot find user reference column. Available columns: " . $availableColumns);
    }
    
} catch (Exception $e) {
    error_log("Error getting table structure: " . $e->getMessage());
    die("Database error: " . $e->getMessage());
}

// Get job details
$stmt = $conn->prepare("
    SELECT j.*, c.company_name 
    FROM job_listings j
    JOIN companies c ON j.company_id = c.id
    WHERE j.id = ? AND j.deadline_date >= CURRENT_DATE
");
$stmt->execute([$jobId]);
$job = $stmt->fetch();

if (!$job) {
    header("Location: index.php");
    exit();
}

// Check if already applied
$hasApplied = false;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE job_listing_id = ? AND $userColumn = ?");
    $stmt->execute([$jobId, $userId]);
    $hasApplied = $stmt->fetchColumn() > 0;
} catch (Exception $e) {
    error_log("Error checking application status: " . $e->getMessage());
}

if ($hasApplied) {
    header("Location: job-detail.php?id=" . $jobId . "&message=already_applied");
    exit();
}

// Get user data to pre-fill form
$userData = [];
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // Try to get additional data from job_seekers table if it exists
    try {
        $stmt = $conn->prepare("SELECT * FROM job_seekers WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $jobSeekerData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($jobSeekerData) {
            $userData = array_merge($userData, $jobSeekerData);
        }
    } catch (Exception $e) {
        // job_seekers table might not exist, that's ok
    }
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Validate required fields
    if (empty($_POST['full_name'])) $errors[] = "Full name is required";
    if (empty($_POST['birth_date'])) $errors[] = "Birth date is required";
    if (empty($_POST['email'])) $errors[] = "Email is required";
    if (empty($_POST['phone_number'])) $errors[] = "Phone number is required";
    if (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] != 0) $errors[] = "CV is required";

    // Validate file uploads - UPDATED TO 5MB LIMIT
    $allowedExtensions = ['pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB (changed from 10MB)

    // CV validation
    if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $errors[] = "CV must be in PDF format";
        }
        if ($_FILES['cv_file']['size'] > $maxSize) {
            $errors[] = "CV file size must be less than 5MB";
        }
    }

    // Portfolio validation (optional)
    if (isset($_FILES['portfolio_file']) && $_FILES['portfolio_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['portfolio_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf'])) {
            $errors[] = "Portfolio must be in PDF format";
        }
        if ($_FILES['portfolio_file']['size'] > $maxSize) {
            $errors[] = "Portfolio file size must be less than 5MB";
        }
    }

    // Cover letter validation (optional)
    if (isset($_FILES['cover_letter_file']) && $_FILES['cover_letter_file']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['cover_letter_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf'])) {
            $errors[] = "Cover letter must be in PDF format";
        }
        if ($_FILES['cover_letter_file']['size'] > $maxSize) {
            $errors[] = "Cover letter file size must be less than 5MB";
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Create uploads directory if it doesn't exist
            $uploadDir = "uploads/applications/";
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }

            // Upload CV
            $cvPath = null;
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
                $cvPath = $uploadDir . uniqid() . '_' . basename($_FILES['cv_file']['name']);
                if (!move_uploaded_file($_FILES['cv_file']['tmp_name'], $cvPath)) {
                    throw new Exception("Failed to upload CV file");
                }
            }

            // Upload Portfolio (if provided)
            $portfolioPath = null;
            if (isset($_FILES['portfolio_file']) && $_FILES['portfolio_file']['error'] == 0) {
                $portfolioPath = $uploadDir . uniqid() . '_' . basename($_FILES['portfolio_file']['name']);
                if (!move_uploaded_file($_FILES['portfolio_file']['tmp_name'], $portfolioPath)) {
                    throw new Exception("Failed to upload portfolio file");
                }
            }

            // Upload Cover Letter (if provided)
            $coverLetterPath = null;
            if (isset($_FILES['cover_letter_file']) && $_FILES['cover_letter_file']['error'] == 0) {
                $coverLetterPath = $uploadDir . uniqid() . '_' . basename($_FILES['cover_letter_file']['name']);
                if (!move_uploaded_file($_FILES['cover_letter_file']['tmp_name'], $coverLetterPath)) {
                    throw new Exception("Failed to upload cover letter file");
                }
            }

            // Get the actual columns in job_applications table
            $stmt = $conn->query("SHOW COLUMNS FROM job_applications");
            $actualColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Build dynamic INSERT query based on available columns
            $insertColumns = ['job_listing_id', $userColumn];
            $insertValues = [$jobId, $userId];
            $placeholders = ['?', '?'];
            
            // Add columns that exist in the table
            $columnMappings = [
                'full_name' => $_POST['full_name'],
                'birth_date' => $_POST['birth_date'],
                'email' => $_POST['email'],
                'phone_number' => $_POST['phone_number'],
                'phone' => $_POST['phone_number'], // alternative column name
                'cv_path' => $cvPath,
                'cv_file' => $cvPath, // alternative column name
                'portfolio_path' => $portfolioPath,
                'portfolio_file' => $portfolioPath, // alternative column name
                'cover_letter_path' => $coverLetterPath,
                'cover_letter_file' => $coverLetterPath, // alternative column name
                'skills' => $_POST['skills'] ?? '',
                'experience' => $_POST['experience'] ?? '',
                'education' => $_POST['education'] ?? '',
                'bio' => $_POST['bio'] ?? '',
                'status' => 'pending',
                'applied_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'application_date' => date('Y-m-d H:i:s')
            ];
            
            foreach ($columnMappings as $column => $value) {
                if (in_array($column, $actualColumns)) {
                    $insertColumns[] = $column;
                    $insertValues[] = $value;
                    $placeholders[] = '?';
                }
            }
            
            $sql = "INSERT INTO job_applications (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            error_log("=== SQL QUERY ===");
            error_log("SQL: " . $sql);
            error_log("Values: " . print_r($insertValues, true));
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute($insertValues);

            if (!$result) {
                $errorInfo = $stmt->errorInfo();
                throw new Exception("Database insert failed: " . $errorInfo[2]);
            }

            $conn->commit();
            header("Location: job-detail.php?id=" . $jobId . "&message=applied_success");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            
            // Clean up uploaded files
            if ($cvPath && file_exists($cvPath)) unlink($cvPath);
            if ($portfolioPath && file_exists($portfolioPath)) unlink($portfolioPath);
            if ($coverLetterPath && file_exists($coverLetterPath)) unlink($coverLetterPath);
            
            error_log("=== APPLICATION SUBMISSION ERROR ===");
            error_log("Error: " . $e->getMessage());
            error_log("File: " . $e->getFile());
            error_log("Line: " . $e->getLine());
            error_log("Trace: " . $e->getTraceAsString());
            
            // Show detailed error in debug mode
            if ($debugMode) {
                $errors[] = "Detailed error: " . $e->getMessage() . " (Line: " . $e->getLine() . ")";
            } else {
                $errors[] = "An error occurred while submitting your application. Please try again or contact support if the problem persists.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for <?= htmlspecialchars($job['title']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h3 class="card-title">Apply for <?= htmlspecialchars($job['title']) ?></h3>
                            <h5 class="text-muted"><?= htmlspecialchars($job['company_name']) ?></h5>
                            <p class="text-muted">Please fill out the form below to submit your application</p>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Please fix the following errors:</strong>
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= htmlspecialchars($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="full_name" class="form-label">
                                        <i class="fas fa-user me-2"></i>Full Name *
                                    </label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required
                                           value="<?= htmlspecialchars($userData['full_name'] ?? $userData['name'] ?? '') ?>">
                                    <div class="invalid-feedback">Please provide your full name.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="birth_date" class="form-label">
                                        <i class="fas fa-calendar me-2"></i>Birth Date *
                                    </label>
                                    <input type="date" class="form-control" id="birth_date" name="birth_date" required
                                           value="<?= htmlspecialchars($userData['birth_date'] ?? '') ?>">
                                    <div class="invalid-feedback">Please provide your birth date.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email *
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           value="<?= htmlspecialchars($userData['email'] ?? '') ?>">
                                    <div class="invalid-feedback">Please provide a valid email.</div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="phone_number" class="form-label">
                                        <i class="fas fa-phone me-2"></i>Phone Number *
                                    </label>
                                    <input type="tel" class="form-control" id="phone_number" name="phone_number" required
                                           value="<?= htmlspecialchars($userData['phone_number'] ?? $userData['phone'] ?? '') ?>">
                                    <div class="invalid-feedback">Please provide your phone number.</div>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="cv_file" class="form-label">
                                        <i class="fas fa-file-pdf me-2"></i>CV (PDF) *
                                    </label>
                                    <input type="file" class="form-control" id="cv_file" name="cv_file" accept=".pdf" required>
                                    <div class="invalid-feedback">Please upload your CV in PDF format.</div>
                                    <small class="text-muted">Max size: 5MB</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="portfolio_file" class="form-label">
                                        <i class="fas fa-folder me-2"></i>Portfolio (PDF)
                                    </label>
                                    <input type="file" class="form-control" id="portfolio_file" name="portfolio_file" accept=".pdf">
                                    <small class="text-muted">Optional, Max size: 5MB</small>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label for="cover_letter_file" class="form-label">
                                        <i class="fas fa-file-alt me-2"></i>Cover Letter (PDF)
                                    </label>
                                    <input type="file" class="form-control" id="cover_letter_file" name="cover_letter_file" accept=".pdf">
                                    <small class="text-muted">Optional, Max size: 5MB</small>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="skills" class="form-label">
                                        <i class="fas fa-tools me-2"></i>Skills
                                    </label>
                                    <textarea class="form-control" id="skills" name="skills" rows="3" 
                                              placeholder="List your relevant skills..."><?= htmlspecialchars($userData['skills'] ?? '') ?></textarea>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="experience" class="form-label">
                                        <i class="fas fa-briefcase me-2"></i>Experience
                                    </label>
                                    <textarea class="form-control" id="experience" name="experience" rows="3" 
                                              placeholder="Describe your work experience..."><?= htmlspecialchars($userData['experience'] ?? '') ?></textarea>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="education" class="form-label">
                                        <i class="fas fa-graduation-cap me-2"></i>Education
                                    </label>
                                    <textarea class="form-control" id="education" name="education" rows="3" 
                                              placeholder="Your educational background..."><?= htmlspecialchars($userData['education'] ?? '') ?></textarea>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="bio" class="form-label">
                                        <i class="fas fa-user-edit me-2"></i>Bio
                                    </label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3" 
                                              placeholder="Tell us about yourself..."><?= htmlspecialchars($userData['bio'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <hr class="my-4">
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="job-detail.php?id=<?= $job['id'] ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Job Detail
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (() => {
        'use strict';
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    })();

    // File upload validation - UPDATED TO 5MB LIMIT
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file && file.type !== 'application/pdf') {
                alert('Please select a PDF file only.');
                this.value = '';
            }
            if (file && file.size > 5 * 1024 * 1024) { // 5MB limit (changed from 10MB)
                alert('File size too large. Please select a file smaller than 5MB.');
                this.value = '';
            }
        });
    });
    </script>
</body>
</html>
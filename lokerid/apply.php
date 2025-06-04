<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a job seeker
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'job_seeker') {
    header("Location: login.php");
    exit();
}

// Check if job ID is provided and valid
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$jobId = $_GET['id'];

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
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM job_applications 
    WHERE job_listing_id = ? AND job_seeker_id = ?
");
$stmt->execute([$jobId, $_SESSION['job_seeker_id']]);
if ($stmt->fetchColumn() > 0) {
    header("Location: job-detail.php?id=" . $jobId);
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Validate required fields
    if (empty($_POST['full_name'])) $errors[] = "Full name is required";
    if (empty($_POST['birth_date'])) $errors[] = "Birth date is required";
    if (empty($_POST['phone'])) $errors[] = "Phone number is required";
    if (empty($_FILES['cv'])) $errors[] = "CV is required";

    // Validate file uploads
    $allowedExtensions = ['pdf', 'doc', 'docx'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    // CV validation
    if (isset($_FILES['cv']) && $_FILES['cv']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $errors[] = "CV must be in PDF or DOC format";
        }
        if ($_FILES['cv']['size'] > $maxSize) {
            $errors[] = "CV file size must be less than 5MB";
        }
    }

    // Portfolio validation (optional)
    if (isset($_FILES['portfolio']) && $_FILES['portfolio']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['portfolio']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf'])) {
            $errors[] = "Portfolio must be in PDF format";
        }
        if ($_FILES['portfolio']['size'] > $maxSize) {
            $errors[] = "Portfolio file size must be less than 5MB";
        }
    }

    // Cover letter validation (optional)
    if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] == 0) {
        $ext = strtolower(pathinfo($_FILES['cover_letter']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions)) {
            $errors[] = "Cover letter must be in PDF or DOC format";
        }
        if ($_FILES['cover_letter']['size'] > $maxSize) {
            $errors[] = "Cover letter file size must be less than 5MB";
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Create uploads directory if it doesn't exist
            $uploadDir = "uploads/applications/";
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Upload CV
            $cvPath = $uploadDir . uniqid() . '_' . $_FILES['cv']['name'];
            move_uploaded_file($_FILES['cv']['tmp_name'], $cvPath);

            // Upload Portfolio (if provided)
            $portfolioPath = null;
            if (isset($_FILES['portfolio']) && $_FILES['portfolio']['error'] == 0) {
                $portfolioPath = $uploadDir . uniqid() . '_' . $_FILES['portfolio']['name'];
                move_uploaded_file($_FILES['portfolio']['tmp_name'], $portfolioPath);
            }

            // Upload Cover Letter (if provided)
            $coverLetterPath = null;
            if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] == 0) {
                $coverLetterPath = $uploadDir . uniqid() . '_' . $_FILES['cover_letter']['name'];
                move_uploaded_file($_FILES['cover_letter']['tmp_name'], $coverLetterPath);
            }

            // Insert application into database
            $stmt = $conn->prepare("
                INSERT INTO job_applications 
                (job_listing_id, job_seeker_id, full_name, birth_date, email, phone_number, 
                cv_path, portfolio_path, cover_letter_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $jobId,
                $_SESSION['job_seeker_id'],
                $_POST['full_name'],
                $_POST['birth_date'],
                $_SESSION['user_email'],
                $_POST['phone'],
                $cvPath,
                $portfolioPath,
                $coverLetterPath
            ]);

            $conn->commit();
            header("Location: job-detail.php?id=" . $jobId . "&applied=1");
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "An error occurred while submitting your application. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply - <?php echo htmlspecialchars($job['title']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title">Apply for <?php echo htmlspecialchars($job['title']); ?></h3>
                        <h5 class="text-muted mb-4"><?php echo htmlspecialchars($job['company_name']); ?></h5>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>

                            <div class="mb-3">
                                <label for="birth_date" class="form-label">Birth Date</label>
                                <input type="date" class="form-control" id="birth_date" name="birth_date" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($_SESSION['user_email']); ?>" readonly>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" required>
                            </div>

                            <div class="mb-3">
                                <label for="cv" class="form-label">CV (PDF/DOC/DOCX, max 5MB)</label>
                                <input type="file" class="form-control" id="cv" name="cv" accept=".pdf,.doc,.docx" required>
                            </div>

                            <div class="mb-3">
                                <label for="portfolio" class="form-label">Portfolio (PDF, optional, max 5MB)</label>
                                <input type="file" class="form-control" id="portfolio" name="portfolio" accept=".pdf">
                            </div>

                            <div class="mb-3">
                                <label for="cover_letter" class="form-label">Cover Letter (PDF/DOC/DOCX, optional, max 5MB)</label>
                                <input type="file" class="form-control" id="cover_letter" name="cover_letter" accept=".pdf,.doc,.docx">
                            </div>

                            <button type="submit" class="btn btn-primary">Submit Application</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>    </div>

    <?php include 'components/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

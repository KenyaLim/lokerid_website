<?php
session_start();
require_once 'config/database.php';

if (isset($_POST['register'])) {
    $errors = [];
    $role = $_POST['role'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }

    // Check if email already exists
    $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = "Email already registered";
    }

    // Validate password
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    }

    // Confirm password
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }

    // Additional fields validation
    if ($role == 'job_seeker') {
        if (empty($_POST['full_name'])) {
            $errors[] = "Full name is required";
        }
    } else if ($role == 'company') {
        if (empty($_POST['company_name'])) {
            $errors[] = "Company name is required";
        }
        if (empty($_POST['location'])) {
            $errors[] = "Company location is required";
        }

        // Handle logo upload
        if (!empty($_FILES['logo']['name'])) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB

            if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
                $errors[] = "Logo must be a JPG, PNG, or GIF file";
            }
            if ($_FILES['logo']['size'] > $maxSize) {
                $errors[] = "Logo file size must be less than 5MB";
            }
        }
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Create user account
            $stmt = $conn->prepare("
                INSERT INTO users (email, password, role) 
                VALUES (?, ?, ?)
            ");
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$email, $hashedPassword, $role]);
            $userId = $conn->lastInsertId();

            if ($role == 'job_seeker') {
                // Create job seeker profile
                $stmt = $conn->prepare("
                    INSERT INTO job_seekers (user_id, full_name) 
                    VALUES (?, ?)
                ");
                $stmt->execute([$userId, $_POST['full_name']]);
            } else {
                // Handle company logo upload
                $logoPath = null;
                if (!empty($_FILES['logo']['name'])) {
                    $uploadDir = "uploads/companies/";
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $logoPath = $uploadDir . uniqid() . '_' . $_FILES['logo']['name'];
                    move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath);
                }

                // Create company profile
                $stmt = $conn->prepare("
                    INSERT INTO companies (user_id, company_name, logo_path, location) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $_POST['company_name'],
                    $logoPath,
                    $_POST['location']
                ]);
            }

            $conn->commit();

            // Automatically log in the user
            $_SESSION['user_id'] = $userId;
            $_SESSION['user_role'] = $role;
            $_SESSION['user_email'] = $email;

            if ($role == 'company') {
                $stmt = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
                $stmt->execute([$userId]);
                $company = $stmt->fetch();
                $_SESSION['company_id'] = $company['id'];
                $_SESSION['company_name'] = $company['company_name'];
                header("Location: company/dashboard.php");
            } else {
                $stmt = $conn->prepare("SELECT * FROM job_seekers WHERE user_id = ?");
                $stmt->execute([$userId]);
                $jobSeeker = $stmt->fetch();
                $_SESSION['job_seeker_id'] = $jobSeeker['id'];
                $_SESSION['full_name'] = $jobSeeker['full_name'];
                header("Location: index.php");
            }
            exit();

        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - LokerID</title>
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
                        <h3 class="card-title text-center mb-4">Create an Account</h3>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="registerForm">
                            <div class="mb-3">
                                <label class="form-label">Account Type</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="role" value="job_seeker" id="roleJobSeeker" 
                                               <?php echo (!isset($_POST['role']) || $_POST['role'] == 'job_seeker') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="roleJobSeeker">
                                            Job Seeker
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="role" value="company" id="roleCompany"
                                               <?php echo (isset($_POST['role']) && $_POST['role'] == 'company') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="roleCompany">
                                            Company
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <!-- Job Seeker Fields -->
                            <div id="jobSeekerFields">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name"
                                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                </div>
                            </div>

                            <!-- Company Fields -->
                            <div id="companyFields" style="display: none;">
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">Company Name</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name"
                                           value="<?php echo isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : ''; ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="location" class="form-label">Company Location</label>
                                    <input type="text" class="form-control" id="location" name="location"
                                           value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="logo" class="form-label">Company Logo</label>
                                    <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" name="register" class="btn btn-primary">Register</button>
                            </div>
                        </form>

                        <div class="text-center mt-3">
                            <p>Already have an account? <a href="login.php">Login here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle form fields based on selected role
        document.querySelectorAll('input[name="role"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const jobSeekerFields = document.getElementById('jobSeekerFields');
                const companyFields = document.getElementById('companyFields');
                
                if (this.value === 'job_seeker') {
                    jobSeekerFields.style.display = 'block';
                    companyFields.style.display = 'none';
                } else {
                    jobSeekerFields.style.display = 'none';
                    companyFields.style.display = 'block';
                }
            });
        });

        // Set initial state
        document.querySelector('input[name="role"]:checked').dispatchEvent(new Event('change'));
    </script>
</body>
    <?php include 'components/footer.php'; ?>
</html>

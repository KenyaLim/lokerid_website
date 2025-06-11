<?php
session_start();
require_once 'config/database.php';

if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    // Query untuk mendapatkan user berdasarkan email saja
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Verifikasi password - support untuk password lama (tidak terenkripsi) dan baru (terenkripsi)
    $passwordValid = false;
    
    if ($user) {
        // Cek apakah password sudah terenkripsi (menggunakan password_hash)
        // Password hash biasanya dimulai dengan $2y$ atau $2a$ atau $2x$ (bcrypt)
        if (password_get_info($user['password'])['algo'] !== null) {
            // Password sudah terenkripsi, gunakan password_verify
            $passwordValid = password_verify($password, $user['password']);
        } else {
            // Password belum terenkripsi (data lama), bandingkan langsung
            $passwordValid = ($password === $user['password']);
            
            // Optional: Update password lama ke format terenkripsi
            if ($passwordValid) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$hashedPassword, $user['id']]);
            }
        }
    }
    
    if ($user && $passwordValid) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];

        if ($user['role'] == 'company') {
            $stmt = $conn->prepare("SELECT * FROM companies WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $company = $stmt->fetch();
            $_SESSION['company_id'] = $company['id'];
            $_SESSION['company_name'] = $company['company_name'];
            header("Location: company/dashboard.php");
        } else {
            $stmt = $conn->prepare("SELECT * FROM job_seekers WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $jobSeeker = $stmt->fetch();
            $_SESSION['job_seeker_id'] = $jobSeeker['id'];
            $_SESSION['full_name'] = $jobSeeker['full_name'];
            header("Location: index.php");
        }
        exit();
    } else {
        $error = "Invalid email or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title text-center mb-4">Login</h3>
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" name="login" class="btn btn-primary">Login</button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p>Don't have an account? <a href="register.php">Register here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'components/footer.php'; ?>
    <!-- <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script> -->
</body>
</html>
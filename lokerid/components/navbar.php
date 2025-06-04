<?php
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isCompany() {
    return isLoggedIn() && $_SESSION['user_role'] == 'company';
}

function isJobSeeker() {
    return isLoggedIn() && $_SESSION['user_role'] == 'job_seeker';
}

// Function to get current user data from database
function getCurrentUserData($conn, $user_id, $role) {
    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user_data) {
            // Update session with fresh data
            if ($role == 'company') {
                $_SESSION['company_name'] = $user_data['company_name'] ?? $user_data['full_name'];
            } else {
                $_SESSION['full_name'] = $user_data['full_name'];
            }
            return $user_data;
        }
    } catch (Exception $e) {
        // Handle database error silently
    }
    return null;
}

// Get fresh user data if logged in
if (isLoggedIn() && isset($conn)) {
    getCurrentUserData($conn, $_SESSION['user_id'], $_SESSION['user_role']);
}
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgba(13, 110, 253, 0.7); backdrop-filter: blur(10px);">
    <div class="container">
        <a class="navbar-brand" href="<?php echo isCompany() ? '/lokerid_website/lokerid/company/dashboard.php' : '/lokerid_website/lokerid/index.php'; ?>">
            <img src="/lokerid_website/lokerid/img/logo.png" alt="LokerID Logo" height="60" style="object-fit: contain;" class="d-inline-block align-text-top me-2">
            <span class="fs-5">LokerID</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['user_role'] == 'company'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="company/dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="company/jobs.php">My Jobs</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php if ($_SESSION['user_role'] == 'job_seeker'): ?>
                                <!-- Show profile picture for job seekers -->
                                <?php
                                $profile_pic = '';
                                if (isset($conn)) {
                                    try {
                                        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = :user_id");
                                        $stmt->bindParam(':user_id', $_SESSION['user_id']);
                                        $stmt->execute();
                                        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                        $profile_pic = $user_data['profile_picture'] ?? '';
                                    } catch (Exception $e) {
                                        $profile_pic = '';
                                    }
                                }
                                ?>
                                <img src="<?php echo !empty($profile_pic) ? htmlspecialchars($profile_pic) : 'https://via.placeholder.com/30x30/6c757d/ffffff?text=' . substr($_SESSION['full_name'] ?? 'U', 0, 1); ?>" 
                                     alt="Profile" class="rounded-circle me-2" width="30" height="30" style="object-fit: cover;">
                            <?php endif; ?>
                            
                            <span>
                                <?php 
                                if ($_SESSION['user_role'] == 'company') {
                                    echo htmlspecialchars($_SESSION['company_name'] ?? $_SESSION['full_name'] ?? 'Company');
                                } else {
                                    echo htmlspecialchars($_SESSION['full_name'] ?? 'User');
                                }
                                ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($_SESSION['user_role'] == 'company'): ?>
                                <li><a class="dropdown-item" href="company/profile.php">
                                    <i class="fas fa-building me-2"></i>Company Profile
                                </a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="profile.php">
                                    <i class="fas fa-user me-2"></i>My Profile
                                </a></li>
                                <li><a class="dropdown-item" href="my-applications.php">
                                    <i class="fas fa-file-alt me-2"></i>My Applications
                                </a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/lokerid_website/lokerid/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
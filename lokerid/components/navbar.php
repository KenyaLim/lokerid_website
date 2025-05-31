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
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: rgba(13, 110, 253, 0.7); backdrop-filter: blur(10px);">
    <div class="container">
        <a class="navbar-brand" href="<?php echo isCompany() ? '/codekeni/lokerid/company/dashboard.php' : '/codekeni/lokerid/index.php'; ?>">
            <img src="/codekeni/lokerid/img/logo.png" alt="LokerID Logo" height="60" style="object-fit: contain;" class="d-inline-block align-text-top me-2">
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
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?php echo htmlspecialchars($_SESSION['user_role'] == 'company' ? $_SESSION['company_name'] : $_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php if ($_SESSION['user_role'] == 'company'): ?>
                                <li><a class="dropdown-item" href="company/profile.php">Company Profile</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="profile.php">My Profile</a></li>
                                <li><a class="dropdown-item" href="my-applications.php">My Applications</a></li>                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/codekeni/lokerid/logout.php">Logout</a></li>
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

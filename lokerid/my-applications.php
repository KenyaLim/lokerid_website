<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'job_seeker') {
    header("Location: login.php");
    exit();
}

// Ambil semua aplikasi user
$stmt = $conn->prepare("
    SELECT 
        ja.*, 
        jl.title AS job_title, 
        jl.location AS job_location, 
        jl.deadline_date,
        c.company_name,
        c.logo_path
    FROM job_applications ja
    JOIN job_listings jl ON ja.job_listing_id = jl.id
    JOIN companies c ON jl.company_id = c.id
    WHERE ja.job_seeker_id = ?
    ORDER BY ja.applied_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Applications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'components/navbar.php'; ?>
<div class="container mt-4">
    <h3>My Job Applications</h3>
    <?php if (empty($applications)): ?>
        <p>You haven't applied to any jobs yet.</p>
    <?php else: ?>
        <?php foreach ($applications as $app): ?>
            <div class="card mb-3 shadow-sm">
                <div class="row g-0">
                    <div class="col-md-2 d-flex align-items-center justify-content-center p-2">
                        <img src="<?= htmlspecialchars($app['logo_path']) ?>" alt="Company Logo" class="img-fluid rounded" style="max-height: 80px;">
                    </div>
                    <div class="col-md-10">
                        <div class="card-body">
                            <h5 class="card-title mb-1"><?= htmlspecialchars($app['job_title']) ?></h5>
                            <p class="card-text mb-0">
                                <strong>Company:</strong> <?= htmlspecialchars($app['company_name']) ?><br>
                                <strong>Location:</strong> <?= htmlspecialchars($app['job_location']) ?><br>
                                <strong>Deadline:</strong> <?= htmlspecialchars($app['deadline_date']) ?><br>
                                <strong>Applied on:</strong> <?= htmlspecialchars($app['applied_at']) ?>
                            </p>
                            <hr>
                            <p class="card-text">
                                <strong>Email:</strong> <?= htmlspecialchars($app['email']) ?><br>
                                <strong>Phone:</strong> <?= htmlspecialchars($app['phone_number']) ?><br>
                                <?php if ($app['location']): ?>
                                    <strong>Address:</strong> <?= htmlspecialchars($app['location']) ?><br>
                                <?php endif; ?>
                                <?php if ($app['skills']): ?>
                                    <strong>Skills:</strong> <?= nl2br(htmlspecialchars($app['skills'])) ?><br>
                                <?php endif; ?>
                                <?php if ($app['experience']): ?>
                                    <strong>Experience:</strong> <?= nl2br(htmlspecialchars($app['experience'])) ?><br>
                                <?php endif; ?>
                                <?php if ($app['education']): ?>
                                    <strong>Education:</strong> <?= nl2br(htmlspecialchars($app['education'])) ?><br>
                                <?php endif; ?>
                                <?php if ($app['bio']): ?>
                                    <strong>Bio:</strong> <?= nl2br(htmlspecialchars($app['bio'])) ?><br>
                                <?php endif; ?>
                            </p>
                            <div>
                                <a href="<?= htmlspecialchars($app['cv_path']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">View CV</a>
                                <?php if ($app['portfolio_path']): ?>
                                    <a href="<?= htmlspecialchars($app['portfolio_path']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">View Portfolio</a>
                                <?php endif; ?>
                                <?php if ($app['cover_letter_path']): ?>
                                    <a href="<?= htmlspecialchars($app['cover_letter_path']) ?>" target="_blank" class="btn btn-sm btn-outline-success">View Cover Letter</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php include 'components/footer.php'; ?>
</body>
</html>

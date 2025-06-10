<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a company
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'company') {
    header("Location: ../login.php");  
    exit();
}

$companyId = $_SESSION['company_id'];
$jobId = isset($_GET['job_id']) ? $_GET['job_id'] : null;

// Build query based on whether a specific job is selected
$query = "
    SELECT 
        ja.*,
        jl.title as job_title,
        jl.job_type,
        jl.deadline_date
    FROM job_applications ja
    JOIN job_listings jl ON ja.job_listing_id = jl.id
    WHERE jl.company_id = ?
";
$params = [$companyId];

if ($jobId) {
    $query .= " AND jl.id = ?";
    $params[] = $jobId;
}

$query .= " ORDER BY ja.applied_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Get company's job listings for filter
$stmt = $conn->prepare("
    SELECT id, title 
    FROM job_listings 
    WHERE company_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$companyId]);
$jobs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applications - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Job Applications</h2>
            <div class="d-flex gap-2">
                <form method="GET" class="d-flex gap-2">
                    <select name="job_id" class="form-select">
                        <option value="">All Jobs</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo $job['id']; ?>" <?php echo $jobId == $job['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($applications)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No applications found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Applicant</th>
                                    <th>Position</th>
                                    <th>Applied Date</th>
                                    <th>Status</th>
                                    <th>Contact</th>
                                    <th>Files</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($applications as $application): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-2">
                                                    <?php echo strtoupper(substr($application['full_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($application['full_name']); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($application['job_title']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($application['job_type']); ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo date('j M Y', strtotime($application['applied_at'])); ?>
                                            <br>
                                            <small class="text-muted"><?php echo date('H:i', strtotime($application['applied_at'])); ?></small>
                                        </td>
                                        <td>
                                            <?php 
                                            $status = $application['status'] ?? $application['application_status'] ?? 'pending';
                                            $statusClass = '';
                                            $statusIcon = '';
                                            switch($status) {
                                                case 'pending':
                                                    $statusClass = 'bg-warning text-dark';
                                                    $statusIcon = 'fas fa-clock';
                                                    break;
                                                case 'reviewed':
                                                    $statusClass = 'bg-info text-white';
                                                    $statusIcon = 'fas fa-eye';
                                                    break;
                                                case 'accepted':
                                                    $statusClass = 'bg-success text-white';
                                                    $statusIcon = 'fas fa-check-circle';
                                                    break;
                                                case 'rejected':
                                                    $statusClass = 'bg-danger text-white';
                                                    $statusIcon = 'fas fa-times-circle';
                                                    break;
                                                default:
                                                    $statusClass = 'bg-secondary text-white';
                                                    $statusIcon = 'fas fa-question';
                                            }
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <i class="<?php echo $statusIcon; ?> me-1"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <?php if (!empty($application['email'])): ?>
                                                <a href="mailto:<?php echo htmlspecialchars($application['email']); ?>" 
                                                   class="btn btn-outline-primary btn-sm mb-1" title="Send Email">
                                                    <i class="fas fa-envelope me-1"></i>
                                                    <?php echo htmlspecialchars($application['email']); ?>
                                                </a>
                                                <?php endif; ?>
                                                <?php if (!empty($application['phone_number'])): ?>
                                                <a href="tel:<?php echo htmlspecialchars($application['phone_number']); ?>" 
                                                   class="btn btn-outline-success btn-sm" title="Call">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?php echo htmlspecialchars($application['phone_number']); ?>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group-vertical btn-group-sm">
                                                <?php if (!empty($application['cv_path']) || !empty($application['cv_file'])): ?>
                                                <a href="../download.php?file=<?php echo $application['id']; ?>&type=cv" 
                                                class="btn btn-outline-danger btn-sm mb-1" title="Download CV">
                                                    <i class="fas fa-file-pdf me-1"></i>CV
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($application['portfolio_path']) || !empty($application['portfolio_file'])): ?>
                                                <a href="../download.php?file=<?php echo $application['id']; ?>&type=portfolio" 
                                                class="btn btn-outline-info btn-sm mb-1" title="View Portfolio">
                                                    <i class="fas fa-briefcase me-1"></i>Portfolio
                                                </a>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($application['cover_letter_path']) || !empty($application['cover_letter_file'])): ?>
                                                <a href="../download.php?file=<?php echo $application['id']; ?>&type=cover_letter" 
                                                class="btn btn-outline-secondary btn-sm" title="View Cover Letter">
                                                    <i class="fas fa-envelope me-1"></i>Cover Letter
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="view-application.php?id=<?php echo $application['id']; ?>" 
                                               class="btn btn-primary btn-sm" title="View Application Details">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <style>
        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(0, 123, 255, 0.05);
        }
        
        .btn-group-vertical .btn {
            margin-bottom: 2px;
        }
        
        .btn-group-vertical .btn:last-child {
            margin-bottom: 0;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in and is a job seeker
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'job_seeker') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get user data first
$stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: logout.php');
    exit();
}

// Update session with current user data to fix navbar display
$_SESSION['full_name'] = $user['full_name'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $full_name = $_POST['full_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $location = $_POST['location'] ?? '';
        $skills = $_POST['skills'] ?? '';
        $experience = $_POST['experience'] ?? '';
        $education = $_POST['education'] ?? '';
        $bio = $_POST['bio'] ?? '';
        
        // Handle profile picture upload
        $profile_picture = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            $filesize = $_FILES['profile_picture']['size'];
            
            // Check file size (limit to 5MB)
            if ($filesize > 5 * 1024 * 1024) {
                $error = 'File size too large. Maximum 5MB allowed.';
            } elseif (in_array(strtolower($filetype), $allowed)) {
                $newname = 'profile_' . $user_id . '_' . time() . '.' . strtolower($filetype);
                $upload_path = 'uploads/profiles/' . $newname;
                
                // Create directory if it doesn't exist
                if (!file_exists('uploads/profiles/')) {
                    if (!mkdir('uploads/profiles/', 0755, true)) {
                        $error = 'Failed to create upload directory.';
                    }
                }
                
                // Delete old profile picture if exists
                if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])) {
                    unlink($user['profile_picture']);
                }
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    $profile_picture = $upload_path;
                } else {
                    $error = 'Failed to upload profile picture.';
                }
            } else {
                $error = 'Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.';
            }
        }
        
        // Only proceed with update if no upload errors
        if (empty($error)) {
            // Update user profile
            $query = "UPDATE users SET 
                        full_name = :full_name,
                        email = :email,
                        phone = :phone,
                        location = :location,
                        skills = :skills,
                        experience = :experience,
                        education = :education,
                        bio = :bio";
            
            if ($profile_picture) {
                $query .= ", profile_picture = :profile_picture";
            }
            
            $query .= " WHERE id = :user_id";
            
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':location', $location);
            $stmt->bindParam(':skills', $skills);
            $stmt->bindParam(':experience', $experience);
            $stmt->bindParam(':education', $education);
            $stmt->bindParam(':bio', $bio);
            $stmt->bindParam(':user_id', $user_id);
            
            if ($profile_picture) {
                $stmt->bindParam(':profile_picture', $profile_picture);
            }
            
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $full_name; // Update session
                $message = 'Profile updated successfully!';
                
                // Refresh user data after update
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $error = 'Failed to update profile.';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - LokerID</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .profile-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #dee2e6;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .profile-picture:hover {
            border-color: #007bff;
            transform: scale(1.05);
        }
        .profile-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        .profile-upload input[type=file] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
            z-index: 2;
        }
        .upload-overlay {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,123,255,0.9);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1;
        }
        .upload-overlay:hover {
            background: rgba(0,123,255,1);
            transform: scale(1.1);
        }
        .upload-info {
            font-size: 0.9em;
            color: #6c757d;
            margin-top: 10px;
        }
        .view-photo-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(40, 167, 69, 0.9);
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1;
            border: none;
            text-decoration: none;
        }
        .view-photo-btn:hover {
            background: rgba(40, 167, 69, 1);
            color: white;
            transform: scale(1.1);
        }
        .modal-img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">
                            <i class="fas fa-user me-2"></i>My Profile
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if ($message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <!-- Profile Picture Section -->
                            <div class="text-center mb-4">
                                <div class="profile-upload">
                                    <img src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : 'https://via.placeholder.com/150x150/6c757d/ffffff?text=No+Photo'; ?>" 
                                         alt="Profile Picture" class="profile-picture" id="profilePreview">
                                    
                                    <?php if (!empty($user['profile_picture'])): ?>
                                        <button type="button" class="view-photo-btn" data-bs-toggle="modal" data-bs-target="#photoModal" title="View Photo">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <div class="upload-overlay">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                    <input type="file" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif" id="profileInput">
                                </div>
                                <div class="upload-info">
                                    <p class="text-muted mb-1">Click to change profile picture</p>
                                    <small class="text-muted">Supported formats: JPG, JPEG, PNG, GIF (Max: 5MB)</small>
                                </div>
                            </div>

                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">
                                            <i class="fas fa-user me-2"></i>Full Name *
                                        </label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Email *
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">
                                            <i class="fas fa-phone me-2"></i>Phone Number
                                        </label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="location" class="form-label">
                                            <i class="fas fa-map-marker-alt me-2"></i>Location
                                        </label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?php echo htmlspecialchars($user['location'] ?? ''); ?>" 
                                               placeholder="City, Country">
                                    </div>
                                </div>
                            </div>

                            <!-- Professional Information -->
                            <hr class="my-4">
                            <h5 class="mb-3">
                                <i class="fas fa-briefcase me-2"></i>Professional Information
                            </h5>
                            
                            <div class="mb-3">
                                <label for="skills" class="form-label">
                                    <i class="fas fa-tools me-2"></i>Skills
                                </label>
                                <textarea class="form-control" id="skills" name="skills" rows="3" 
                                          placeholder="List your skills separated by commas (e.g., PHP, JavaScript, MySQL, etc.)"><?php echo htmlspecialchars($user['skills'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="experience" class="form-label">
                                    <i class="fas fa-history me-2"></i>Work Experience
                                </label>
                                <textarea class="form-control" id="experience" name="experience" rows="4" 
                                          placeholder="Describe your work experience, previous positions, etc."><?php echo htmlspecialchars($user['experience'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="education" class="form-label">
                                    <i class="fas fa-graduation-cap me-2"></i>Education
                                </label>
                                <textarea class="form-control" id="education" name="education" rows="3" 
                                          placeholder="Your educational background, degrees, certifications, etc."><?php echo htmlspecialchars($user['education'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="bio" class="form-label">
                                    <i class="fas fa-user-edit me-2"></i>About Me
                                </label>
                                <textarea class="form-control" id="bio" name="bio" rows="4" 
                                          placeholder="Tell us about yourself, your career goals, what makes you unique..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>

                            <hr class="my-4">
                            
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Home
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-line me-2"></i>Profile Statistics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <h4 class="text-primary">
                                        <?php
                                        try {
                                            $stmt = $conn->prepare("SELECT COUNT(*) FROM job_applications WHERE applicant_id = :user_id");
                                            $stmt->bindParam(':user_id', $user_id);
                                            $stmt->execute();
                                            echo $stmt->fetchColumn();
                                        } catch (Exception $e) {
                                            echo '0';
                                        }
                                        ?>
                                    </h4>
                                    <p class="text-muted mb-0">Applications Sent</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <h4 class="text-success">
                                        <?php echo !empty($user['profile_picture']) ? '100' : '80'; ?>%
                                    </h4>
                                    <p class="text-muted mb-0">Profile Complete</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="stat-item">
                                    <h4 class="text-info">
                                        <?php echo isset($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'N/A'; ?>
                                    </h4>
                                    <p class="text-muted mb-0">Member Since</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo View Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1" aria-labelledby="photoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoModalLabel">Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="<?php echo !empty($user['profile_picture']) ? htmlspecialchars($user['profile_picture']) : ''; ?>" 
                         alt="Profile Photo" class="modal-img">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if (!empty($user['profile_picture'])): ?>
                        <a href="<?php echo htmlspecialchars($user['profile_picture']); ?>" 
                           target="_blank" class="btn btn-primary">
                            <i class="fas fa-external-link-alt me-2"></i>Open in New Tab
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'components/footer.php'; ?>

    <!-- Theme Toggle Button -->
    <button class="theme-toggle" id="themeToggle" title="Toggle Dark/Light Mode">
        <i class="fas fa-moon"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/theme.js"></script>
    <script>
        // Profile picture preview and validation
        document.getElementById('profileInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, JPEG, PNG, or GIF)');
                    this.value = '';
                    return;
                }
                
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size too large. Maximum 5MB allowed.');
                    this.value = '';
                    return;
                }
                
                // Preview the image
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                    // Update modal image as well
                    document.querySelector('#photoModal .modal-img').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Make the entire profile picture area clickable for upload
        document.getElementById('profilePreview').addEventListener('click', function(e) {
            // Don't trigger file input if clicking the view button
            if (!e.target.closest('.view-photo-btn')) {
                document.getElementById('profileInput').click();
            }
        });

        // Auto-resize textareas
        document.querySelectorAll('textarea').forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!fullName) {
                alert('Please enter your full name');
                e.preventDefault();
                return;
            }
            
            if (!email) {
                alert('Please enter your email');
                e.preventDefault();
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>
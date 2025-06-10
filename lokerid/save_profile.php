<?php
// save_profile.php
session_start();

// Pastikan user sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Koneksi database
    $host = 'localhost';
    $dbname = 'lokerid_db';
    $username = 'your_username';
    $password = 'your_password';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['role'];
        
        if ($user_role == 'job_seeker') {
            // Simpan profile job seeker
            $full_name = $_POST['full_name'];
            $birth_date = $_POST['birth_date'];
            $phone_number = $_POST['phone_number'];
            
            // Cek apakah profile sudah ada
            $check_stmt = $pdo->prepare("SELECT id FROM job_seekers WHERE user_id = ?");
            $check_stmt->execute([$user_id]);
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing profile
                $stmt = $pdo->prepare("UPDATE job_seekers SET full_name = ?, birth_date = ?, phone_number = ? WHERE user_id = ?");
                $stmt->execute([$full_name, $birth_date, $phone_number, $user_id]);
                $message = "Profile berhasil diperbarui!";
            } else {
                // Insert new profile
                $stmt = $pdo->prepare("INSERT INTO job_seekers (user_id, full_name, birth_date, phone_number) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $full_name, $birth_date, $phone_number]);
                $message = "Profile berhasil disimpan!";
            }
        } 
        elseif ($user_role == 'company') {
            // Simpan profile company
            $company_name = $_POST['company_name'];
            $location = $_POST['location'];
            $logo_path = null;
            
            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
                $upload_dir = 'img/';
                $file_extension = pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION);
                $logo_filename = 'company_' . $user_id . '_' . time() . '.' . $file_extension;
                $logo_path = $upload_dir . $logo_filename;
                
                // Pastikan direktori upload ada
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path)) {
                    // Logo berhasil diupload
                } else {
                    $logo_path = null;
                    $error = "Gagal mengupload logo.";
                }
            }
            
            // Cek apakah profile sudah ada
            $check_stmt = $pdo->prepare("SELECT id FROM companies WHERE user_id = ?");
            $check_stmt->execute([$user_id]);
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing profile
                if ($logo_path) {
                    $stmt = $pdo->prepare("UPDATE companies SET company_name = ?, location = ?, logo_path = ? WHERE user_id = ?");
                    $stmt->execute([$company_name, $location, $logo_path, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE companies SET company_name = ?, location = ? WHERE user_id = ?");
                    $stmt->execute([$company_name, $location, $user_id]);
                }
                $message = "Profile perusahaan berhasil diperbarui!";
            } else {
                // Insert new profile
                $stmt = $pdo->prepare("INSERT INTO companies (user_id, company_name, location, logo_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $company_name, $location, $logo_path]);
                $message = "Profile perusahaan berhasil disimpan!";
            }
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Load existing profile data
try {
    if ($user_role == 'job_seeker') {
        $stmt = $pdo->prepare("SELECT * FROM job_seekers WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($user_role == 'company') {
        $stmt = $pdo->prepare("SELECT * FROM companies WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Error loading profile: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Profile</title>
</head>
<body>
    <?php if (isset($message)): ?>
        <div style="color: green; margin: 10px 0;"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div style="color: red; margin: 10px 0;"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($user_role == 'job_seeker'): ?>
        <!-- Form untuk Job Seeker -->
        <h2>Edit Profile Job Seeker</h2>
        <form method="POST">
            <div>
                <label>Nama Lengkap:</label>
                <input type="text" name="full_name" value="<?php echo isset($profile['full_name']) ? htmlspecialchars($profile['full_name']) : ''; ?>" required>
            </div>
            <div>
                <label>Tanggal Lahir:</label>
                <input type="date" name="birth_date" value="<?php echo isset($profile['birth_date']) ? $profile['birth_date'] : ''; ?>">
            </div>
            <div>
                <label>Nomor Telepon:</label>
                <input type="text" name="phone_number" value="<?php echo isset($profile['phone_number']) ? htmlspecialchars($profile['phone_number']) : ''; ?>">
            </div>
            <button type="submit">Simpan Profile</button>
        </form>
        
    <?php elseif ($user_role == 'company'): ?>
        <!-- Form untuk Company -->
        <h2>Edit Profile Perusahaan</h2>
        <form method="POST" enctype="multipart/form-data">
            <div>
                <label>Nama Perusahaan:</label>
                <input type="text" name="company_name" value="<?php echo isset($profile['company_name']) ? htmlspecialchars($profile['company_name']) : ''; ?>" required>
            </div>
            <div>
                <label>Lokasi:</label>
                <textarea name="location"><?php echo isset($profile['location']) ? htmlspecialchars($profile['location']) : ''; ?></textarea>
            </div>
            <div>
                <label>Logo Perusahaan:</label>
                <input type="file" name="logo" accept="image/*">
                <?php if (isset($profile['logo_path']) && $profile['logo_path']): ?>
                    <p>Logo saat ini: <img src="<?php echo htmlspecialchars($profile['logo_path']); ?>" width="100"></p>
                <?php endif; ?>
            </div>
            <button type="submit">Simpan Profile</button>
        </form>
    <?php endif; ?>
</body>
</html>
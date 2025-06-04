-- Create database
CREATE DATABASE IF NOT EXISTS lokerid_db;
USE lokerid_db;

-- Users table (for both job seekers and companies)
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('job_seeker', 'company') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Job Seekers profile
CREATE TABLE job_seekers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    birth_date DATE,
    phone_number VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Companies profile
CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    logo_path VARCHAR(255),
    location TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Job Categories
CREATE TABLE job_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL
);

-- Job Listings
CREATE TABLE job_listings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    job_type ENUM('Full-time', 'Part-time', 'Remote', 'Freelance') NOT NULL,
    salary_min DECIMAL(12,2),
    salary_max DECIMAL(12,2),
    location TEXT,
    description TEXT NOT NULL,
    requirements TEXT NOT NULL,
    deadline_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (category_id) REFERENCES job_categories(id)
);

-- Job Applications
CREATE TABLE job_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    job_listing_id INT NOT NULL,
    job_seeker_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    birth_date DATE NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    cv_path VARCHAR(255) NOT NULL,
    portfolio_path VARCHAR(255),
    cover_letter_path VARCHAR(255),
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_listing_id) REFERENCES job_listings(id),
    FOREIGN KEY (job_seeker_id) REFERENCES job_seekers(id),
    UNIQUE KEY unique_application (job_listing_id, job_seeker_id)
);

-- untuk profile
ALTER TABLE users ADD COLUMN IF NOT EXISTS phone VARCHAR(20);
ALTER TABLE users ADD COLUMN IF NOT EXISTS location VARCHAR(255);
ALTER TABLE users ADD COLUMN IF NOT EXISTS skills TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS experience TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS education TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT;
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_picture VARCHAR(255);

-- Insert sample job categories
INSERT INTO job_categories (name) VALUES 
('IT'),
('Finance'),
('Education'),
('Marketing'),
('Sales'),
('Design'),
('Healthcare'),
('Engineering');

-- Insert users (2 job seekers and 4 companies)
INSERT INTO users (email, password, role) VALUES
('kenya.s@gmail.com', 'kenya123', 'job_seeker'),
('gojo.satoru@gmail.com', 'gojo123', 'job_seeker'),
('bimbel.siba@example.com', 'bimbel123', 'company'),
('meals.only@example.com', 'meals123', 'company'),
('mi.goceng@example.com', 'migoceng123', 'company'),
('tianwaa@example.com', 'tianwaa123', 'company');

-- Insert job seekers
INSERT INTO job_seekers (user_id, full_name, birth_date, phone_number) VALUES
(1, 'Kenya Lim', '2000-12-06', '081234567890'),
(2, 'Gojo Satoru', '1999-12-06', '081234567891');

-- Insert companies with actual logos
INSERT INTO companies (user_id, company_name, logo_path, location) VALUES
(3, 'Bimbel Siba', 'img/bimbel.png', 'Tangerang'),
(4, 'Meals Only', 'img/meals-only.png', 'Yogyakarta'),
(5, 'Mi Goceng', 'img/mi-goceng.png', 'Yogyakarta'),
(6, 'Tianwaa', 'img/tianwaa.png', 'Jakarta');

-- Insert actual job listings
INSERT INTO job_listings (company_id, category_id, title, job_type, salary_min, salary_max, location, description, requirements, deadline_date) VALUES
(1, 3, 'Mentor Matematika', 'Full-time', 2700000, 3600000, 'Tangerang', 
'Mengajar matematika untuk siswa SD-SMP. Membuat materi pembelajaran yang menarik dan mudah dipahami. Melakukan evaluasi berkala untuk memastikan pemahaman siswa. Berinteraksi dengan orang tua untuk memberikan update perkembangan siswa. Mengembangkan kurikulum yang sesuai dengan kebutuhan siswa.',
'1. S1 Pendidikan Matematika/Ilmu terkait
2. Pengalaman mengajar minimal 2 tahun
3. Memiliki kemampuan komunikasi yang baik
4. Sabar dan teliti dalam mengajar
5. Mampu membuat materi pembelajaran yang menarik
6. Menguasai kurikulum nasional
7. Memiliki kemampuan manajemen kelas yang baik
8. Bersedia mengajar di akhir pekan
9. Memiliki kemampuan problem solving yang baik
10. Berpengalaman dalam mengajar siswa dengan berbagai tingkat kemampuan', '2025-06-30'),

(2, 5, 'Crew Outlet', 'Part-time', 1500000, 2100000, 'Yogyakarta', 
'Melayani pembeli di outlet dengan ramah dan profesional. Memastikan kebersihan dan kerapian outlet. Menjaga stok barang dan melakukan restock. Memproses pembayaran dan memberikan kembalian dengan tepat. Menangani keluhan pelanggan dengan baik. Memastikan display produk selalu menarik dan teratur.',
'1. Usia 18-25 tahun
2. Ramah dan energik
3. Bisa bekerja dalam tim
4. Memiliki kemampuan komunikasi yang baik
5. Jujur dan bertanggung jawab
6. Mampu bekerja di bawah tekanan
7. Memiliki kemampuan multitasking
8. Berpengalaman di bidang retail (diutamakan)
9. Mampu mengoperasikan komputer kasir
10. Bersedia bekerja shift', '2025-06-30'),

(3, 5, 'Crew Outlet', 'Full-time', 1700000, 2100000, 'Yogyakarta', 
'Menyiapkan pesanan makanan dengan cepat dan tepat. Melakukan pelayanan terbaik kepada pelanggan. Memastikan kebersihan area kerja dan peralatan. Menjaga kualitas makanan dan minuman. Mengatur stok bahan baku. Melakukan closing procedure dengan baik. Membantu training crew baru.',
'1. Semangat kerja tinggi
2. Bisa bekerja tim
3. Memiliki kemampuan komunikasi yang baik
4. Jujur dan bertanggung jawab
5. Mampu bekerja di bawah tekanan
6. Memiliki kemampuan multitasking
7. Berpengalaman di bidang F&B (diutamakan)
8. Mampu mengoperasikan peralatan dapur
9. Memiliki pengetahuan tentang food safety
10. Bersedia bekerja shift', '2025-07-15'),

(4, 3, 'Mandarin Teacher', 'Full-time', 3000000, 4000000, 'Jakarta', 
'Mengajar bahasa Mandarin untuk dewasa. Membuat materi pembelajaran yang menarik dan efektif. Mengembangkan kurikulum sesuai kebutuhan siswa. Melakukan evaluasi berkala. Memberikan feedback konstruktif kepada siswa. Berinteraksi dengan manajemen untuk pengembangan program. Mengorganisir kegiatan bahasa Mandarin.',
'1. Native Mandarin atau HSK 5+
2. Pengalaman mengajar minimal 2 tahun
3. Memiliki kemampuan komunikasi yang baik
4. Sabar dan teliti dalam mengajar
5. Mampu membuat materi pembelajaran yang menarik
6. Menguasai metode pengajaran bahasa
7. Memiliki kemampuan manajemen kelas yang baik
8. Berpengalaman mengajar dewasa
9. Memiliki kemampuan problem solving yang baik
10. Bersedia mengajar di akhir pekan', '2025-07-15');

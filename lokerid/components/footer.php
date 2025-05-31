<?php
$currentYear = date('Y');
?>
<footer class="footer mt-auto py-4">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="footer-brand mb-3">
                    <img src="/codekeni/lokerid/img/logo.png" alt="LokerID Logo" height="30" class="mb-3">
                    <h5 class="mb-3">LokerID</h5>
                </div>
                <p class="footer-description">Platform lowongan kerja terpercaya yang menghubungkan talenta terbaik dengan perusahaan impian mereka.</p>
                <div class="social-links mt-3">
                    <a href="#" class="me-2"><i class="bi bi-linkedin"></i></a>
                    <a href="#" class="me-2"><i class="bi bi-instagram"></i></a>
                    <a href="#" class="me-2"><i class="bi bi-twitter"></i></a>
                    <a href="#"><i class="bi bi-facebook"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-6">
                <h6 class="footer-title">Quick Links</h6>
                <ul class="footer-links">
                    <li><a href="/codekeni/lokerid/index.php">Home</a></li>
                    <li><a href="/codekeni/lokerid/register.php">Register</a></li>
                    <li><a href="/codekeni/lokerid/login.php">Login</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="footer-title">For Companies</h6>
                <ul class="footer-links">
                    <li><a href="/codekeni/lokerid/company/dashboard.php">Post a Job</a></li>
                    <li><a href="/codekeni/lokerid/company/jobs.php">Manage Jobs</a></li>
                    <li><a href="/codekeni/lokerid/company/applications.php">Browse Applications</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="footer-title">Contact Info</h6>
                <ul class="footer-contact">
                    <li>
                        <i class="bi bi-geo-alt me-2"></i>
                        Jl. Contoh No. 123, Kota, Indonesia
                    </li>
                    <li>
                        <i class="bi bi-envelope me-2"></i>
                        <a href="mailto:info@lokerid.com">info@lokerid.com</a>
                    </li>
                    <li>
                        <i class="bi bi-telephone me-2"></i>
                        <a href="tel:+6281234567890">+62 812-3456-7890</a>
                    </li>
                </ul>
            </div>
        </div>
        <hr class="footer-divider my-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="copyright mb-0">© <?php echo $currentYear; ?> LokerID. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </div>
</footer>

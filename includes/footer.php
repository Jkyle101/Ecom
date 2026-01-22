</main>
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Student E-Commerce Platform</h3>
                <p>A platform for buying and selling products.</p>
            </div>
            <div class="footer-section">
                <h3>Quick Links</h3>
                <ul>
                    <?php
                    // Determine base path dynamically (same logic as header)
                    $base_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__FILE__)));
                    ?>
                    <li><a href="<?php echo $base_path; ?>/index.php">Home</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['role'] == 'seller'): ?>
                            <li><a href="<?php echo $base_path; ?>/seller/dashboard.php">Dashboard</a></li>
                        <?php elseif($_SESSION['role'] == 'admin'): ?>
                            <li><a href="<?php echo $base_path; ?>/admin/dashboard.php">Admin Dashboard</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo $base_path; ?>/buyer/dashboard.php">Dashboard</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo $base_path; ?>/auth/logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="<?php echo $base_path; ?>/auth/login.php">Login</a></li>
                        <li><a href="<?php echo $base_path; ?>/auth/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> E-Commerce Platform. All Rights Reserved.</p>
        </div>
    </footer>
    <script src="<?php echo $base_path; ?>/assets/js/main.js"></script>
</body>
</html>

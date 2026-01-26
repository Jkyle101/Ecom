<?php
session_start();
include 'includes/header.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'seller') {
        header("Location: seller/dashboard.php");
        exit();
    } else {
        header("Location: buyer/dashboard.php");
        exit();
    }
}
?>

<style>
    /* General reset */
    body, html {
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        scroll-behavior: smooth;
    }

    /* Parallax background sections */
    .parallax {
        height: 100vh; /* full screen height */
        background-attachment: fixed;
        background-position: center;
        background-repeat: no-repeat;
        background-size: cover;
        display: flex;
        justify-content: center;
        align-items: center;
        text-align: center;
        color: white;
        flex-direction: column;
    }

    .overlay {
        background: rgba(0,0,0,0.5); /* dark transparent overlay */
        padding: 40px;
        border-radius: 10px;
    }

    .auth-buttons a {
        margin: 10px;
        padding: 12px 25px;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
    }

    .btn-primary {
        background: #007bff;
        color: white;
    }

    .btn-secondary {
        background: #5ae553ff;
        color: white;
    }
</style>

<!-- Welcome Section -->
<div class="parallax" style="background-image: url('assets/images/GATE.jpg'); background-color: #71c9ce;">
    <div class="overlay" style="width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; flex-direction: column; text-align: center; background: rgba(0,0,0,0.4);">
        <h1 style="font-size: 3rem; margin-bottom: 20px;">Welcome to Student E-Commerce Platform</h1>
        <p style="font-size: 1.2rem; margin-bottom: 30px;">A platform exclusively for school students to buy and sell products</p>
        
        <div class="auth-buttons">
            <a href="auth/login.php" class="btn-primary">Login</a>
            <a href="auth/register.php" class="btn-secondary">Register</a>
        </div>
    </div>
</div>


<!-- Buy Section -->
<div class="parallax" style="background-image: url('assets/images/ADMIN.jpg'); background-color: #a8d8ea;">
    <div class="overlay">
        <i class="fas fa-shopping-bag fa-3x"></i>
        <h2>Buy Products</h2>
        <p>Browse through categories of products offered by sellers</p>
    </div>
</div>

<!-- Sell Section -->
<div class="parallax" style="background-image: url('assets/images/COT.jpg'); background-color: #b5e8c3;">
    <div class="overlay">
        <i class="fas fa-store fa-3x"></i>
        <h2>Sell Products</h2>
        <p>Set up your own shop and start selling your products</p>
    </div>
</div>

<!-- Messaging Section -->
<div class="parallax" style="background-image: url('assets/images/BUILD.jpg'); background-color: #ffb6b9;">
    <div class="overlay">
        <i class="fas fa-comments fa-3x"></i>
        <h2>Direct Messaging</h2>
        <p>Chat directly with sellers to inquire about products</p>
    </div>
</div>

<!-- Back to Home Link -->
<div style="position: fixed; bottom: 20px; right: 20px; z-index: 1000;">
    <a href="#" onclick="window.scrollTo({top: 0, behavior: 'smooth'})" style="text-decoration: none; background: rgba(0,0,0,0.6); color: white; padding: 10px 15px; border-radius: 5px; display: flex; align-items: center;">
        <i class="fas fa-home" style="margin-right: 5px;"></i> Back to Home
    </a>
</div>


<?php include 'includes/footer.php'; ?>


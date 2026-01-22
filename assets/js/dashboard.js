// Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Auto-scroll messages container to bottom
    const messagesContainer = document.querySelector('.messages-container');
    if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Product slider scroll
    const productSlider = document.querySelector('.product-slider');
    if (productSlider) {
        // Add smooth scrolling for product slider
        productSlider.style.scrollBehavior = 'smooth';
    }

    // Notification badge update (if you want to add real-time updates)
    // This is a placeholder for future real-time notification updates
});


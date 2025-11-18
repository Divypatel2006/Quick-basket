    </main>
    
    <footer class="site-footer">
        <div class="footer-main">
            <div class="container">
                <div class="footer-content">
                    <!-- Company Info -->
                    <div class="footer-section">
                        <div class="footer-logo">
                            <h3>🍎 Quick Basket</h3>
                        </div>
                        <p class="footer-description">
                            Your trusted online grocery store for fresh vegetables, fruits, and daily essentials. 
                            We deliver quality products right to your doorstep.
                        </p>
                        <div class="social-links">
                            <a href="#" class="social-link" title="Facebook">📘</a>
                            <a href="#" class="social-link" title="Twitter">🐦</a>
                            <a href="#" class="social-link" title="Instagram">📷</a>
                            <a href="#" class="social-link" title="LinkedIn">💼</a>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="footer-section">
                        <h4 class="footer-title">Quick Links</h4>
                        <ul class="footer-links">
                            <li><a href="index.php">Home</a></li>
                            <li><a href="products.php">All Products</a></li>
                            <li><a href="about.php">About Us</a></li>
                            <li><a href="contact.php">Contact</a></li>
                            <li><a href="faq.php">FAQ</a></li>
                            <?php if(isset($_SESSION['user_id']) && ($_SESSION['role'] ?? 'customer') == 'admin'): ?>
                                <li><a href="admin/index.php">Admin Panel</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <!-- Categories -->
                    <div class="footer-section">
                        <h4 class="footer-title">Categories</h4>
                        <ul class="footer-links">
                            <?php
                            // Display categories from database
                            if (isset($conn)) {
                                $cat_sql = "SELECT * FROM categories LIMIT 5";
                                $cat_result = $conn->query($cat_sql);
                                if ($cat_result && $cat_result->num_rows > 0) {
                                    while($category = $cat_result->fetch_assoc()) {
                                        echo '<li><a href="products.php?category=' . $category['id'] . '">' . htmlspecialchars($category['name']) . '</a></li>';
                                    }
                                } else {
                                    // Fallback categories
                                    $fallback_categories = ['Vegetables', 'Fruits', 'Dairy', 'Bakery', 'Organic'];
                                    foreach ($fallback_categories as $cat) {
                                        echo '<li><a href="products.php">' . $cat . '</a></li>';
                                    }
                                }
                            }
                            ?>
                        </ul>
                    </div>

                    <!-- Contact Info -->
                    <div class="footer-section">
                        <h4 class="footer-title">Contact Info</h4>
                        <div class="contact-info">
                            <div class="contact-item">
                                <span class="contact-icon">📧</span>
                                <span>
                                    <a href="mailto:support@quickbasket.com" class="contact-link">support@quickbasket.com</a>
                                </span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">📞</span>
                                <span>
                                    <a href="tel:+15551234567" class="contact-link">+1 (555) 123-4567</a>
                                </span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">📍</span>
                                <span>123 Grocery Street<br>Fresh City, FC 12345</span>
                            </div>
                            <div class="contact-item">
                                <span class="contact-icon">⏰</span>
                                <span>Mon-Sun: 7:00 AM - 10:00 PM</span>
                            </div>
                        </div>
                    </div>

                    <!-- Newsletter -->
                    <div class="footer-section">
                        <h4 class="footer-title">Newsletter</h4>
                        <p class="newsletter-text">Subscribe to get updates on new products and special offers.</p>
                        <form class="newsletter-form" method="POST" action="subscribe.php" id="newsletterForm">
                            <div class="input-group">
                                <input type="email" name="email" placeholder="Your email address" required 
                                       id="newsletterEmail">
                                <button type="submit" class="newsletter-btn" id="newsletterBtn">
                                    <span class="btn-text">Subscribe</span>
                                    <span class="btn-loading" style="display: none;">⏳</span>
                                </button>
                            </div>
                            <div class="newsletter-message" id="newsletterMessage"></div>
                        </form>
                        <div class="payment-methods">
                            <span class="payment-text">We Accept:</span>
                            <div class="payment-icons">
                                <span class="payment-icon" title="Credit Cards">💳</span>
                                <span class="payment-icon" title="PayPal">🅿️</span>
                                <span class="payment-icon" title="Mobile Payments">📱</span>
                                <span class="payment-icon" title="Bank Transfer">🔗</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="container">
                <div class="footer-bottom-content">
                    <div class="copyright">
                        <p>&copy; <?php echo date('Y'); ?> Quick Basket. All rights reserved.</p>
                    </div>
                    <div class="footer-links-bottom">
                        <a href="privacy.php">Privacy Policy</a>
                        <a href="terms.php">Terms of Service</a>
                        <a href="refund.php">Refund Policy</a>
                        <a href="sitemap.php">Sitemap</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="backToTop" class="back-to-top" title="Go to top" aria-label="Back to top">↑</button>

    <!-- JavaScript -->
    <script>
        // Back to Top Button
        const backToTopButton = document.getElementById('backToTop');

        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.style.display = 'block';
            } else {
                backToTopButton.style.display = 'none';
            }
        });

        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Newsletter Form with AJAX
        document.getElementById('newsletterForm')?.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('newsletterEmail').value;
            const btn = document.getElementById('newsletterBtn');
            const messageDiv = document.getElementById('newsletterMessage');
            
            if (!isValidEmail(email)) {
                showMessage('Please enter a valid email address.', 'error', messageDiv);
                return;
            }

            // Show loading state
            btn.querySelector('.btn-text').style.display = 'none';
            btn.querySelector('.btn-loading').style.display = 'inline';
            btn.disabled = true;

            // Simulate AJAX submission (replace with actual AJAX call)
            setTimeout(() => {
                showMessage('Thank you for subscribing! You will receive our latest updates.', 'success', messageDiv);
                document.getElementById('newsletterForm').reset();
                
                // Reset button state
                btn.querySelector('.btn-text').style.display = 'inline';
                btn.querySelector('.btn-loading').style.display = 'none';
                btn.disabled = false;
            }, 1500);
        });

        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        function showMessage(message, type, container) {
            container.innerHTML = `<div class="message ${type}">${message}</div>`;
            container.style.display = 'block';
            
            setTimeout(() => {
                container.style.display = 'none';
            }, 5000);
        }

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Mobile menu toggle
        function toggleMobileMenu() {
            const nav = document.querySelector('nav ul');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (nav && toggle) {
                nav.classList.toggle('mobile-active');
                toggle.classList.toggle('active');
            }
        }

        // Add to cart notification
        window.showNotification = function(message, type = 'success') {
            // Remove existing notifications
            document.querySelectorAll('.notification').forEach(notif => notif.remove());
            
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" aria-label="Close notification">×</button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        };

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check for session messages
            <?php if (isset($_SESSION['success'])): ?>
                showNotification('<?php echo addslashes($_SESSION['success']); ?>', 'success');
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                showNotification('<?php echo addslashes($_SESSION['error']); ?>', 'error');
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            // Add loading state to forms
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="btn-loading">⏳ Processing...</span>';
                        
                        // Re-enable button after 10 seconds (safety measure)
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 10000);
                    }
                });
            });
        });

        // Print functionality for order details page
        window.printOrder = function() {
            window.print();
        }

        // Quick edit product function
        window.quickEditProduct = function(productId) {
            window.location.href = `admin/edit-product.php?id=${productId}`;
        }
    </script>

    <!-- Enhanced Notification Styles -->
    <style>
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease;
            max-width: 400px;
            font-weight: 500;
        }

        .notification.success {
            background: linear-gradient(135deg, #4CAF50, #45a049);
            border-left: 4px solid #2e7d32;
        }

        .notification.error {
            background: linear-gradient(135deg, #f44336, #d32f2f);
            border-left: 4px solid #b71c1c;
        }

        .notification.info {
            background: linear-gradient(135deg, #2196F3, #1976D2);
            border-left: 4px solid #0d47a1;
        }

        .notification button {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .notification button:hover {
            background: rgba(255,255,255,0.2);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Newsletter message styles */
        .newsletter-message {
            display: none;
            margin-top: 0.5rem;
            padding: 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }

        .newsletter-message .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 0.5rem;
            border-radius: 4px;
        }

        .newsletter-message .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 0.5rem;
            border-radius: 4px;
        }

        /* Contact links */
        .contact-link {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s;
        }

        .contact-link:hover {
            color: #4CAF50;
        }

        /* Loading states */
        .btn-loading {
            display: inline-block;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Print styles for order details */
        @media print {
            .site-footer,
            .back-to-top,
            .notification {
                display: none !important;
            }
        }
    </style>
</body>
</html>
<?php
/**
 * Contact Page
 * Contact form and business information
 */

require_once '../includes/init.php';

$page_title = "Contact Us - Again&Co Vintage Collection";
$page_description = "Get in touch with Again&Co. Contact us for questions, special requests, or to learn more about our vintage collection.";

// Handle contact form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required.";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required.";
    } elseif (strlen($message) < 10) {
        $errors[] = "Message must be at least 10 characters long.";
    }
    
    if (empty($errors)) {
        // In a real application, you would send an email or store in database
        // For now, we'll just show a success message
        
        // Log the message (in production, implement proper email handling)
        error_log("Contact form submission: Name: $name, Email: $email, Subject: $subject");
        
        setFlashMessage('success', 'Thank you for your message! We\'ll get back to you within 24 hours.');
        header('Location: contact.php');
        exit;
    } else {
        setFlashMessage('error', 'Please correct the following errors: ' . implode(' ', $errors));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <meta name="description" content="<?= $page_description ?>">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/favicon.ico">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <main class="main-content">
        <div class="container">
            <?php
            $flash = getFlashMessage();
            if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?>">
                    <?= htmlspecialchars($flash['message']) ?>
                </div>
            <?php endif; ?>
            
            <!-- Contact Header -->
            <section class="contact-header">
                <div class="card">
                    <h1 class="page-title">Contact Us</h1>
                    <p class="lead">We'd love to hear from you! Get in touch with any questions, special requests, or just to say hello.</p>
                </div>
            </section>
            
            <div class="row">
                <!-- Contact Form -->
                <div class="col-md-8">
                    <div class="card">
                        <h2>Send us a Message</h2>
                        <form action="contact.php" method="POST" class="contact-form">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       class="form-control" 
                                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                                       required>
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Subject *</label>
                                <select id="subject" name="subject" class="form-control" required>
                                    <option value="">Please select a subject</option>
                                    <option value="General Inquiry" <?= (isset($_POST['subject']) && $_POST['subject'] === 'General Inquiry') ? 'selected' : '' ?>>General Inquiry</option>
                                    <option value="Product Question" <?= (isset($_POST['subject']) && $_POST['subject'] === 'Product Question') ? 'selected' : '' ?>>Product Question</option>
                                    <option value="Order Support" <?= (isset($_POST['subject']) && $_POST['subject'] === 'Order Support') ? 'selected' : '' ?>>Order Support</option>
                                    <option value="Returns & Exchanges" <?= (isset($_POST['subject']) && $_POST['subject'] === 'Returns & Exchanges') ? 'selected' : '' ?>>Returns & Exchanges</option>
                                    <option value="Sell to Us" <?= (isset($_POST['subject']) && $_POST['subject'] === 'Sell to Us') ? 'selected' : '' ?>>Sell to Us</option>
                                    <option value="Partnership" <?= (isset($_POST['subject']) && $_POST['subject'] === 'Partnership') ? 'selected' : '' ?>>Partnership Opportunity</option>
                                    <option value="Other" <?= (isset($_POST['subject']) && $_POST['subject'] === 'Other') ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Message *</label>
                                <textarea id="message" 
                                          name="message" 
                                          class="form-control" 
                                          rows="6" 
                                          placeholder="Tell us how we can help you..."
                                          required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg">Send Message</button>
                        </form>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="col-md-4">
                    <div class="card">
                        <h3>Get in Touch</h3>
                        
                        <div class="contact-info">
                            <div class="contact-item">
                                <h4>📧 Email</h4>
                                <p>
                                    <a href="mailto:hello@againco.com">hello@againco.com</a><br>
                                    <small>We respond within 24 hours</small>
                                </p>
                            </div>
                            
                            <div class="contact-item">
                                <h4>📞 Phone</h4>
                                <p>
                                    <a href="tel:+1234567890">(123) 456-7890</a><br>
                                    <small>Mon-Fri: 9 AM - 6 PM EST</small>
                                </p>
                            </div>
                            
                            <div class="contact-item">
                                <h4>📍 Visit Our Showroom</h4>
                                <p>
                                    123 Vintage Lane<br>
                                    Retro District<br>
                                    Fashion City, FC 12345<br>
                                    <small>By appointment only</small>
                                </p>
                            </div>
                            
                            <div class="contact-item">
                                <h4>💬 Social Media</h4>
                                <p>
                                    <a href="#" target="_blank">Instagram @againco</a><br>
                                    <a href="#" target="_blank">Facebook</a><br>
                                    <a href="#" target="_blank">Pinterest</a><br>
                                    <small>Follow for daily vintage finds</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Business Hours -->
                    <div class="card">
                        <h3>Business Hours</h3>
                        <div class="business-hours">
                            <div class="hours-item">
                                <span class="day">Monday - Friday</span>
                                <span class="time">9:00 AM - 6:00 PM</span>
                            </div>
                            <div class="hours-item">
                                <span class="day">Saturday</span>
                                <span class="time">10:00 AM - 4:00 PM</span>
                            </div>
                            <div class="hours-item">
                                <span class="day">Sunday</span>
                                <span class="time">Closed</span>
                            </div>
                        </div>
                        <p class="text-muted">
                            <small>* Showroom visits by appointment only</small>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- FAQ Section -->
            <section class="contact-faq">
                <div class="card">
                    <h2>Frequently Asked Questions</h2>
                    <div class="faq-grid">
                        <div class="faq-item">
                            <h4>How can I sell items to Again&Co?</h4>
                            <p>We're always looking for quality vintage pieces! Email us photos and descriptions of your items to <a href="mailto:selling@againco.com">selling@againco.com</a> and we'll get back to you within 48 hours.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>Do you authenticate all items?</h4>
                            <p>Yes! Every item goes through our authentication process before being listed. We guarantee the authenticity of all vintage pieces in our collection.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>What's your return policy?</h4>
                            <p>We offer a 30-day return policy for unworn items in original condition. Items must be returned with tags attached and original packaging.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>Do you offer international shipping?</h4>
                            <p>Currently we ship within the continental United States. International shipping is coming soon! Join our newsletter to be notified when it's available.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>Can I schedule a showroom visit?</h4>
                            <p>Absolutely! Our showroom is open by appointment only. Call us at <a href="tel:+1234567890">(123) 456-7890</a> or email to schedule your visit.</p>
                        </div>
                        
                        <div class="faq-item">
                            <h4>How do I track my order?</h4>
                            <p>Once your order ships, you'll receive a tracking number via email. You can also check your order status in your account dashboard.</p>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Call to Action -->
            <section class="contact-cta">
                <div class="card text-center">
                    <h2>Ready to Start Shopping?</h2>
                    <p>Browse our carefully curated vintage collection and find your next treasure.</p>
                    <a href="../pages/products/catalog.php" class="btn btn-primary btn-lg">Shop Collection</a>
                </div>
            </section>
        </div>
    </main>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
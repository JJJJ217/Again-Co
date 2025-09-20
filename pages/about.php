<?php
/**
 * About Page
 * Information about Again&Co vintage store
 */

require_once '../includes/init.php';

$page_title = "About Us - Again&Co Vintage Collection";
$page_description = "Learn about Again&Co's story, our passion for vintage items, and our commitment to sustainable fashion and unique finds.";
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
            
            <!-- About Header -->
            <section class="about-header">
                <div class="card">
                    <h1 class="page-title">About Again&Co</h1>
                    <p class="lead">Bringing vintage treasures back to life, one unique piece at a time.</p>
                </div>
            </section>
            
            <!-- Our Story -->
            <section class="about-story">
                <div class="card">
                    <div class="row">
                        <div class="col-md-6">
                            <h2>Our Story</h2>
                            <p>
                                Founded in 2020, Again&Co was born from a passion for vintage fashion and sustainable living. 
                                We believe that every pre-owned item has a story to tell and deserves a second chance to shine.
                            </p>
                            <p>
                                What started as a small vintage boutique has grown into a curated online marketplace where 
                                fashion enthusiasts, collectors, and environmentally conscious shoppers can discover authentic 
                                vintage pieces from decades past.
                            </p>
                            <p>
                                Our name "Again&Co" reflects our core philosophy: giving beautiful items the opportunity to 
                                be loved and cherished again, while building a community of like-minded individuals who 
                                appreciate quality, craftsmanship, and timeless style.
                            </p>
                        </div>
                        <div class="col-md-6">
                            <div class="about-image-placeholder">
                                <p class="text-center text-muted">
                                    <i class="icon-vintage"></i><br>
                                    Vintage Collection Image
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Our Mission -->
            <section class="about-mission">
                <div class="card">
                    <h2>Our Mission</h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mission-item">
                                <h3>🌱 Sustainability</h3>
                                <p>
                                    Promoting sustainable fashion by extending the lifecycle of quality garments 
                                    and reducing textile waste in our environment.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mission-item">
                                <h3>✨ Quality</h3>
                                <p>
                                    Carefully curating authentic vintage pieces that showcase exceptional 
                                    craftsmanship and timeless design from bygone eras.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mission-item">
                                <h3>🤝 Community</h3>
                                <p>
                                    Building a community of vintage lovers who share our passion for unique finds, 
                                    historical fashion, and sustainable living.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- What We Offer -->
            <section class="about-offering">
                <div class="card">
                    <h2>What We Offer</h2>
                    <div class="offering-grid">
                        <div class="offering-item">
                            <h4>👗 Vintage Clothing</h4>
                            <p>Authentic vintage dresses, coats, blouses, and accessories from the 1950s to 1990s.</p>
                        </div>
                        <div class="offering-item">
                            <h4>💎 Jewelry & Accessories</h4>
                            <p>Vintage jewelry, handbags, scarves, and accessories to complete your retro look.</p>
                        </div>
                        <div class="offering-item">
                            <h4>🎵 Music & Vinyl</h4>
                            <p>Rare vinyl records, vintage music memorabilia, and collectible albums.</p>
                        </div>
                        <div class="offering-item">
                            <h4>🏠 Home Decor</h4>
                            <p>Vintage home decor items, ceramics, and collectibles to add character to your space.</p>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Our Promise -->
            <section class="about-promise">
                <div class="card">
                    <h2>Our Promise to You</h2>
                    <ul class="promise-list">
                        <li><strong>Authenticity:</strong> Every item is carefully examined and authenticated before listing.</li>
                        <li><strong>Quality:</strong> We only sell items that meet our high standards for condition and craftsmanship.</li>
                        <li><strong>Fair Pricing:</strong> Competitive prices that reflect the true value of vintage pieces.</li>
                        <li><strong>Customer Service:</strong> Dedicated support to help you find exactly what you're looking for.</li>
                        <li><strong>Secure Shopping:</strong> Safe and secure checkout process with multiple payment options.</li>
                        <li><strong>Satisfaction Guarantee:</strong> 30-day return policy on all purchases (conditions apply).</li>
                    </ul>
                </div>
            </section>
            
            <!-- Call to Action -->
            <section class="about-cta">
                <div class="card text-center">
                    <h2>Join Our Vintage Community</h2>
                    <p>
                        Ready to discover your next vintage treasure? Browse our carefully curated collection 
                        and become part of the Again&Co family.
                    </p>
                    <div class="cta-buttons">
                        <a href="../pages/products/catalog.php" class="btn btn-primary btn-lg">Shop Collection</a>
                        <a href="../pages/contact.php" class="btn btn-secondary btn-lg">Get in Touch</a>
                    </div>
                </div>
            </section>
        </div>
    </main>
    
    <?php include '../includes/footer.php'; ?>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
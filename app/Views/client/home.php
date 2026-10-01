<?php
$categories = [
    ['name' => 'Vegetables', 'items' => '120+ items', 'image' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=360&q=80'],
    ['name' => 'Fruits', 'items' => '80+ items', 'image' => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?auto=format&fit=crop&w=360&q=80'],
    ['name' => 'Herbs & Greens', 'items' => '60+ items', 'image' => 'https://images.unsplash.com/photo-1515586000433-45406d8e6662?auto=format&fit=crop&w=360&q=80'],
    ['name' => 'Dairy & Eggs', 'items' => '40+ items', 'image' => 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?auto=format&fit=crop&w=360&q=80'],
    ['name' => 'Nuts & Seeds', 'items' => '50+ items', 'image' => 'https://images.unsplash.com/photo-1599901860904-17e6ed7083a0?auto=format&fit=crop&w=360&q=80'],
];

$products = [
    ['name' => 'Organic Tomatoes', 'price' => 'Rs 2.49 / kg', 'image' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=360&q=80'],
    ['name' => 'Fresh Carrots', 'price' => 'Rs 1.49 / kg', 'image' => 'https://images.unsplash.com/photo-1447175008436-054170c2e979?auto=format&fit=crop&w=360&q=80'],
    ['name' => 'Green Spinach', 'price' => 'Rs 1.29 / bunch', 'image' => 'https://images.unsplash.com/photo-1576045057995-568f588f82fb?auto=format&fit=crop&w=360&q=80'],
    ['name' => 'Red Apples', 'price' => 'Rs 2.99 / kg', 'image' => 'https://images.unsplash.com/photo-1567306226416-28f0efdc88ce?auto=format&fit=crop&w=360&q=80'],
    ['name' => 'Farm Eggs (6pcs)', 'price' => 'Rs 1.99 / pack', 'image' => 'https://images.unsplash.com/photo-1518569656558-1f25e69d93d7?auto=format&fit=crop&w=360&q=80'],
];

$benefits = [
    ['icon' => 'basket', 'title' => 'Farm Fresh', 'copy' => 'Handpicked with care'],
    ['icon' => 'sprout', 'title' => 'Chemical Free', 'copy' => 'Safe for you & family'],
    ['icon' => 'leaf', 'title' => 'Sustainably Grown', 'copy' => 'Good for nature'],
    ['icon' => 'shield', 'title' => 'Premium Quality', 'copy' => 'Best quality assured'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sabjiwalah fresh grocery and vegetable delivery">
    <title>Sabjiwalah - Fresh from Nature</title>
    <link rel="preconnect" href="https://images.unsplash.com">
    <link rel="stylesheet" href="/assets/css/home.css">
</head>
<body>
    <div class="page-shell">
        <div class="top-strip" aria-label="Store benefits">
            <span>FREE DELIVERY on orders over Rs 499</span>
            <span>Eat Fresh, Live Healthy</span>
            <span>Support 24/7</span>
        </div>

        <header class="site-header">
            <a class="brand" href="/" aria-label="Sabjiwalah home">
                <span class="brand-mark">SB</span>
                <span>
                    <strong>Sabjiwalah</strong>
                    <small>Fresh from Nature</small>
                </span>
            </a>

            <nav class="main-nav" aria-label="Primary navigation">
                <a class="is-active" href="/">Home</a>
                <a href="/products">Shop</a>
                <a href="#categories">Categories</a>
                <a href="#deals">Deals</a>
                <a href="#about">About Us</a>
                <a href="#contact">Contact</a>
            </nav>

            <div class="header-actions" aria-label="Quick actions">
                <button type="button" aria-label="Search">⌕</button>
                <a href="/login" aria-label="Account">○</a>
                <button class="cart-button" type="button" aria-label="Cart">
                    <span>Cart</span>
                    <strong>2</strong>
                </button>
            </div>
        </header>

        <main>
            <section class="hero">
                <div class="hero-copy">
                    <h1>Fresh Food.<br>Healthy Life.<br><span>Happy You.</span></h1>
                    <p>100% organic fruits, vegetables & more delivered fresh to your door.</p>
                    <a class="primary-button" href="/products">Shop Now <span>→</span></a>

                    <div class="hero-badges" aria-label="Service highlights">
                        <span><strong>100% Organic</strong><small>Pure & Natural</small></span>
                        <span><strong>Fast Delivery</strong><small>On Time, Every Time</small></span>
                        <span><strong>Secure Payment</strong><small>Safe & Protected</small></span>
                    </div>
                </div>

                <div class="hero-visual" aria-label="Fresh vegetable basket">
                    <img src="https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=900&q=90" alt="Basket of fresh vegetables">
                    <div class="fresh-seal">
                        <span>EAT FRESH</span>
                        <strong>STAY HEALTHY</strong>
                    </div>
                    <i class="leaf leaf-one"></i>
                    <i class="leaf leaf-two"></i>
                    <i class="leaf leaf-three"></i>
                </div>
            </section>

            <section class="farm-card" id="about">
                <div class="farmer-image">
                    <img src="https://images.unsplash.com/photo-1605000797499-95a51c5269ae?auto=format&fit=crop&w=520&q=80" alt="Farmer holding fresh produce">
                </div>
                <div class="farm-copy">
                    <small>WELCOME TO SABJIWALAH</small>
                    <h2>From Our Farm<br>To Your Table</h2>
                    <p>We bring you the freshest, handpicked produce from trusted farms. Quality you can trust, every single time.</p>
                    <a href="#categories">Learn More <span>→</span></a>
                </div>
                <div class="farm-stats">
                    <span><strong>25+</strong><small>Local Farms</small></span>
                    <span><strong>500+</strong><small>Fresh Products</small></span>
                    <span><strong>10K+</strong><small>Happy Customers</small></span>
                </div>
            </section>

            <section class="section-block" id="categories">
                <div class="section-heading">
                    <h2>Shop by Category</h2>
                    <a href="/products">View all →</a>
                </div>

                <div class="category-grid">
                    <?php foreach ($categories as $category) : ?>
                        <article class="category-card">
                            <img src="<?= esc($category['image']) ?>" alt="<?= esc($category['name']) ?>">
                            <h3><?= esc($category['name']) ?></h3>
                            <p><?= esc($category['items']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="deal-banner" id="deals">
                <div>
                    <small>LIMITED TIME OFFER</small>
                    <h2>UP TO 30% OFF</h2>
                    <p>On Fresh Vegetables</p>
                    <a class="deal-button" href="/products">Grab the Deal <span>→</span></a>
                </div>
                <img src="https://images.unsplash.com/photo-1557844352-761f2565b576?auto=format&fit=crop&w=900&q=80" alt="Fresh vegetables offer">
                <strong class="deal-stamp">Best Quality<br>Best Price</strong>
            </section>

            <section class="section-block">
                <div class="section-heading">
                    <h2>Why Choose Us?</h2>
                </div>
                <div class="benefit-grid">
                    <?php foreach ($benefits as $benefit) : ?>
                        <article class="benefit-card">
                            <span><?= esc($benefit['icon']) ?></span>
                            <div>
                                <h3><?= esc($benefit['title']) ?></h3>
                                <p><?= esc($benefit['copy']) ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="section-block">
                <div class="section-heading">
                    <h2>Top Picks For You</h2>
                    <a href="/products">View all →</a>
                </div>

                <div class="product-grid">
                    <?php foreach ($products as $product) : ?>
                        <article class="product-card">
                            <button type="button" aria-label="Save <?= esc($product['name']) ?>">♡</button>
                            <img src="<?= esc($product['image']) ?>" alt="<?= esc($product['name']) ?>">
                            <h3><?= esc($product['name']) ?></h3>
                            <p><?= esc($product['price']) ?></p>
                            <a href="/products">Add to Cart</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="newsletter" id="contact">
                <div class="mail-icon">✉</div>
                <div>
                    <h2>Stay Healthy, Stay Updated!</h2>
                    <p>Subscribe to get best offers, health tips & fresh updates in your inbox.</p>
                </div>
                <form action="#" method="post">
                    <label class="sr-only" for="newsletter-email">Email address</label>
                    <input id="newsletter-email" type="email" placeholder="Enter your email">
                    <button type="submit">Subscribe</button>
                </form>
                <img src="https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=340&q=80" alt="Basket of vegetables">
            </section>
        </main>

        <footer class="site-footer">
            <div>
                <a class="brand footer-brand" href="/">
                    <span class="brand-mark">SB</span>
                    <span>
                        <strong>Sabjiwalah</strong>
                        <small>Fresh from Nature</small>
                    </span>
                </a>
                <p>Your trusted source for fresh, organic and healthy food. We care for you and the planet.</p>
            </div>
            <div>
                <h3>Quick Links</h3>
                <a href="/products">Shop</a>
                <a href="#categories">Categories</a>
                <a href="#deals">Deals</a>
                <a href="#about">About Us</a>
            </div>
            <div>
                <h3>Customer Service</h3>
                <a href="/account">My Account</a>
                <a href="#">Track Order</a>
                <a href="#">Shipping & Delivery</a>
                <a href="#">Returns & Refunds</a>
            </div>
            <div>
                <h3>Information</h3>
                <a href="#">Privacy Policy</a>
                <a href="#">Terms & Conditions</a>
                <a href="#">Refund Policy</a>
                <a href="#">Careers</a>
            </div>
            <div>
                <h3>Payment Methods</h3>
                <div class="payments">
                    <span>VISA</span>
                    <span>PayPal</span>
                    <span>UPI</span>
                </div>
            </div>
            <p class="copyright">© 2026 Sabjiwalah. All Rights Reserved.</p>
        </footer>
    </div>
</body>
</html>

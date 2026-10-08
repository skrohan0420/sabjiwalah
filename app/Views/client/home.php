<?php
$categoryTabs = [
    ['name' => 'All', 'icon' => 'bag', 'active' => true],
    ['name' => 'Vegetables', 'icon' => 'leaf'],
    ['name' => 'Fruits', 'icon' => 'apple'],
    ['name' => 'Dairy', 'icon' => 'milk'],
    ['name' => 'Herbs', 'icon' => 'sprout'],
    ['name' => 'Offers', 'icon' => 'gift'],
];

$promoTiles = [
    ['title' => 'Fresh Vegetables', 'offer' => 'Up to 35% OFF', 'image' => 'https://images.unsplash.com/photo-1566385101042-1a0aa0c1268c?auto=format&fit=crop&w=360&q=80'],
    ['title' => 'Daily Fruits', 'offer' => 'Sweet picks', 'image' => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?auto=format&fit=crop&w=360&q=80'],
    ['title' => 'Milk & Eggs', 'offer' => 'Morning ready', 'image' => 'https://images.unsplash.com/photo-1628088062854-d1870b4553da?auto=format&fit=crop&w=360&q=80'],
    ['title' => 'Kitchen Staples', 'offer' => 'Smart savings', 'image' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?auto=format&fit=crop&w=360&q=80'],
];

$fallbackImages = [
    'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1447175008436-054170c2e979?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1576045057995-568f588f82fb?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1567306226416-28f0efdc88ce?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1518569656558-1f25e69d93d7?auto=format&fit=crop&w=420&q=80',
];

$productSections = [
    ['title' => 'Fresh picks for you', 'products' => $products, 'show_all' => true],
    ['title' => 'Chai-time companions', 'products' => array_reverse($products), 'show_all' => false],
];

$isLoggedIn = (bool) session('is_logged_in');
$accountUrl = base_url('account');
$accountLabel = $isLoggedIn ? 'Account' : 'My Account';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sabjiwalah fresh grocery and vegetable delivery">
    <title>Sabjiwalah - Fresh Grocery Delivery</title>
    <link rel="preconnect" href="https://images.unsplash.com">
    <link rel="preload" href="<?= esc(base_url('assets/images/sabjiwalah-cart-logo-header.png?v=20261003'), 'attr') ?>" as="image" type="image/png">
    <link rel="preload" href="<?= esc(base_url('assets/images/sabjiwalah-wordmark-header.png?v=20261003'), 'attr') ?>" as="image" type="image/png">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/home.css?v=20261005-orders-nav'), 'attr') ?>">
    <script src="<?= esc(base_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
</head>
<body>
    <div class="app-shell">
        <header class="home-header">
            <div class="status-row" aria-label="Service status">
                <span class="brand-mark" aria-label="Sabjiwalah">
                    <img class="brand-cart-logo" src="<?= esc(base_url('assets/images/sabjiwalah-cart-logo-header.png?v=20261003'), 'attr') ?>" alt="" width="184" height="132" decoding="async" fetchpriority="high">
                    <img class="brand-wordmark" src="<?= esc(base_url('assets/images/sabjiwalah-wordmark-header.png?v=20261003'), 'attr') ?>" alt="" width="620" height="160" decoding="async" fetchpriority="high">
                </span>
                <div class="header-icons">
                    <button class="home-share-button" type="button" aria-label="Share Sabjiwalah" data-share-page></button>
                </div>
            </div>

            <div class="delivery-copy">
                <button type="button" data-location-open aria-haspopup="dialog" aria-controls="location-sheet">
                    <strong class="delivery-pin" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false">
                            <path d="M12 21s6.4-5.5 6.4-11.1A6.4 6.4 0 0 0 5.6 9.9C5.6 15.5 12 21 12 21Z"></path>
                            <circle cx="12" cy="9.9" r="2.3"></circle>
                        </svg>
                    </strong>
                    <span class="sr-only">Delivery location</span>
                    <span class="delivery-location-lines">
                        <span data-current-location-label>Surajpur, Greater Noida</span>
                        <small data-current-location-detail>Uttar Pradesh, India</small>
                    </span>
                </button>
            </div>
        </header>

        <div class="sticky-shop-controls">
            <form class="search-box" action="<?= esc(base_url('search'), 'attr') ?>" method="get" role="search" data-search-redirect="<?= esc(base_url('search'), 'attr') ?>">
                <label class="sr-only" for="home-search">Search products</label>
                <span class="search-icon" aria-hidden="true"></span>
                <input
                    id="home-search"
                    name="q"
                    type="search"
                    readonly
                    aria-readonly="true"
                    placeholder="Search for atta, dal, coke and more"
                    data-search-placeholder
                    data-search-placeholders="Search fresh vegetables|Search fruits for today|Search atta, dal and rice|Search milk and breakfast|Search tomato, onion, potato|Search paneer and curd|Search tea-time snacks|Search cooking oil and ghee|Search pooja essentials|Search weekly grocery deals|Search leafy greens|Search cold drinks"
                >
                <span class="search-placeholder-anim" data-search-placeholder-anim aria-hidden="true">Search for atta, dal, coke and more</span>
                <button type="submit" aria-label="Search by voice or text"></button>
            </form>

            <nav class="category-tabs" aria-label="Shop categories">
                <?php foreach ($categoryTabs as $category) : ?>
                    <a class="<?= ! empty($category['active']) ? 'is-active' : '' ?>" href="<?= esc(base_url('products'), 'attr') ?>">
                        <span class="tab-icon tab-<?= esc($category['icon'], 'attr') ?>" aria-hidden="true">
                            <?php if ($category['icon'] === 'bag') : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M6.8 9.2h10.4l1.1 9.8H5.7l1.1-9.8Z"></path>
                                    <path d="M9 9.2V7.6a3 3 0 0 1 6 0v1.6"></path>
                                    <path d="M8.6 12.5h6.8"></path>
                                    <path d="M8.1 15.5h7.8"></path>
                                </svg>
                            <?php elseif ($category['icon'] === 'leaf') : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M6.2 17.8c2.5-1.2 4.5-3 6-5.4"></path>
                                    <path d="M9.4 15.4c-2.3-.8-3.9-2.5-4.8-5.1 3.8-.4 6.8.6 8.9 3"></path>
                                    <path d="M12.2 12.4c.8-4.2 3.6-6.8 8.2-7.8-.3 5.3-3.1 8.8-8.3 10.3"></path>
                                </svg>
                            <?php elseif ($category['icon'] === 'apple') : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M12 8.7c1.4-1.3 4.2-1.1 5.5.7 1.9 2.8.2 8.8-2.7 9.8-1 .3-1.8-.2-2.8-.2s-1.8.5-2.8.2c-2.9-1-4.6-7-2.7-9.8 1.3-1.8 4.1-2 5.5-.7Z"></path>
                                    <path d="M12 8.4c0-2.2 1.2-3.7 3.5-4.6"></path>
                                    <path d="M10.6 6.5c-.9-.8-2-1.1-3.2-.8"></path>
                                </svg>
                            <?php elseif ($category['icon'] === 'milk') : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M9.3 3.7h5.4v3.5l1.6 1.9v10.1c0 .7-.5 1.2-1.2 1.2H8.9c-.7 0-1.2-.5-1.2-1.2V9.1l1.6-1.9V3.7Z"></path>
                                    <path d="M9.3 7.2h5.4"></path>
                                    <path d="M8.4 12.2h7.2"></path>
                                    <path d="M8.4 16.2h7.2"></path>
                                </svg>
                            <?php elseif ($category['icon'] === 'sprout') : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M12 20V9.7"></path>
                                    <path d="M12 10.6c-3.1-.3-5.2-2-6.2-5 3.2-.2 5.5 1.2 6.8 4"></path>
                                    <path d="M12 13.7c2.4-.2 4.3-1.5 5.8-3.9 1.3 3.6-.4 6.2-5 7.8"></path>
                                    <path d="M8.4 20h7.2"></path>
                                </svg>
                            <?php else : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M5.3 10h13.4v9.8H5.3V10Z"></path>
                                    <path d="M4.3 7h15.4v3H4.3V7Z"></path>
                                    <path d="M12 7v12.8"></path>
                                    <path d="M12 7c-2-3.4-5.6-2.3-5 .4"></path>
                                    <path d="M12 7c2-3.4 5.6-2.3 5 .4"></path>
                                </svg>
                            <?php endif; ?>
                        </span>
                        <?php if (! empty($category['badge'])) : ?>
                            <em><?= esc($category['badge']) ?></em>
                        <?php endif; ?>
                        <strong><?= esc($category['name']) ?></strong>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <main>
            <section class="deal-hero" aria-labelledby="deal-heading">
                <p>Weekend fresh drop</p>
                <h2 id="deal-heading">Steal Rs 9 Deal</h2>
                <span>Get any one item at special price</span>
            </section>

            <?php foreach ($productSections as $sectionIndex => $section) : ?>
                <section class="commerce-section" aria-labelledby="product-section-<?= esc((string) $sectionIndex, 'attr') ?>">
                    <h2 id="product-section-<?= esc((string) $sectionIndex, 'attr') ?>"><?= esc($section['title']) ?></h2>

                    <?php if ($section['products'] === []) : ?>
                        <article class="empty-card product-empty-card">
                            <h3>Fresh stock coming soon</h3>
                            <p>Products added by the store will appear here.</p>
                        </article>
                    <?php else : ?>
                        <div class="commerce-grid">
                            <?php foreach ($section['products'] as $index => $product) : ?>
                                <?= view('client/products/_catalog_card', compact('product', 'index', 'sectionIndex', 'fallbackImages')) ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if (! empty($section['show_all'])) : ?>
                            <a class="see-all-products" href="<?= esc(base_url('products'), 'attr') ?>">
                                <span>
                                    <?php foreach (array_slice($section['products'], 0, 3) as $thumbIndex => $thumbProduct) : ?>
                                        <?php $thumbImage = $thumbProduct['image'] ?: $fallbackImages[$thumbIndex % count($fallbackImages)]; ?>
                                        <img src="<?= esc(app_asset_url($thumbImage)) ?>" alt="" data-fallback-image="<?= esc(base_url('assets/images/product-placeholder.svg'), 'attr') ?>">
                                    <?php endforeach; ?>
                                </span>
                                See all products
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            
        </main>

        <?= view('client/products/_floating_cart') ?>

        <div class="location-sheet" id="location-sheet" data-location-sheet hidden>
            <button class="location-close" type="button" data-location-close aria-label="Close location selector"></button>
            <section class="location-panel" role="dialog" aria-modal="true" aria-labelledby="location-title">
                <h2 id="location-title">Select delivery location</h2>
                <form class="location-search" data-location-search-form>
                    <span aria-hidden="true"></span>
                    <label class="sr-only" for="location-search-input">Search delivery location</label>
                    <input
                        id="location-search-input"
                        name="location"
                        type="search"
                        autocomplete="street-address"
                        placeholder="Search for area, street name..."
                        data-location-search
                    >
                </form>
                <div class="location-results" data-location-results hidden></div>
                <button class="location-current" type="button" data-use-current-location>
                    <span aria-hidden="true"></span>
                    <strong>Use current location</strong>
                    <em data-location-current-address>Surajpur, Greater Noida, Uttar Pradesh, India</em>
                    <i aria-hidden="true"></i>
                </button>
            </section>
        </div>

        <nav class="bottom-nav" aria-label="Bottom navigation">
            <a class="is-active" href="<?= esc(base_url(''), 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3.8 10.9 12 4.2l8.2 6.7"></path>
                        <path d="M5.7 9.9v8.5c0 1 .7 1.7 1.7 1.7h9.2c1 0 1.7-.7 1.7-1.7V9.9"></path>
                        <path d="M9.7 20.1v-5.8h4.6v5.8"></path>
                        <path d="M10.1 4.9h3.8"></path>
                    </svg>
                </span>
                Home
            </a>
            <a href="<?= esc(base_url('orders'), 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M6.2 4.7h11.6v15l-2-1.2-1.9 1.2-1.9-1.2-1.9 1.2-1.9-1.2-2 1.2v-15Z"></path>
                        <path d="M8.9 8.4h6.2"></path>
                        <path d="M8.9 12h6.2"></path>
                        <path d="M8.9 15.6h3.7"></path>
                    </svg>
                </span>
                My Orders
            </a>
            <a href="<?= esc(base_url('products'), 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <rect x="4.8" y="4.8" width="5.2" height="5.2" rx="1.3"></rect>
                        <rect x="14" y="4.8" width="5.2" height="5.2" rx="1.3"></rect>
                        <rect x="4.8" y="14" width="5.2" height="5.2" rx="1.3"></rect>
                        <rect x="14" y="14" width="5.2" height="5.2" rx="1.3"></rect>
                    </svg>
                </span>
                Categories
            </a>
            <a href="<?= esc($accountUrl, 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 12.4a4.2 4.2 0 1 0 0-8.4 4.2 4.2 0 0 0 0 8.4Z"></path>
                        <path d="M4.9 20.2c.7-4.2 3.1-6.3 7.1-6.3s6.4 2.1 7.1 6.3"></path>
                        <path d="M8.8 18.5h6.4"></path>
                    </svg>
                </span>
                Account
            </a>
        </nav>
    </div>
    <script src="<?= esc(base_url('assets/js/cart.js?v=20261005-search-redirect'), 'attr') ?>"></script>
</body>
</html>

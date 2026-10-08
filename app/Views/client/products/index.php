<?php
$categoryItems = [
    ['name' => 'All', 'image' => 'https://images.unsplash.com/photo-1566385101042-1a0aa0c1268c?auto=format&fit=crop&w=160&q=80', 'active' => true],
    ['name' => 'Fresh Vegetables', 'image' => 'https://images.unsplash.com/photo-1597362925123-77861d3fbac7?auto=format&fit=crop&w=160&q=80'],
    ['name' => 'Fresh Fruits', 'image' => 'https://images.unsplash.com/photo-1619566636858-adf3ef46400b?auto=format&fit=crop&w=160&q=80'],
    ['name' => 'Exotics', 'image' => 'https://images.unsplash.com/photo-1563565375-f3fdfdbefa83?auto=format&fit=crop&w=160&q=80'],
    ['name' => 'Coriander & Others', 'image' => 'https://images.unsplash.com/photo-1600326145552-327f74b9c189?auto=format&fit=crop&w=160&q=80'],
    ['name' => 'Flowers & Leaves', 'image' => 'https://images.unsplash.com/photo-1582794543139-8ac9cb0f7b11?auto=format&fit=crop&w=160&q=80'],
    ['name' => 'Seasonal', 'image' => 'https://images.unsplash.com/photo-1506806732259-39c2d0268443?auto=format&fit=crop&w=160&q=80'],
    ['name' => 'Freshly Cut & Sprouts', 'image' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?auto=format&fit=crop&w=160&q=80'],
];

$fallbackImages = [
    'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1447175008436-054170c2e979?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1576045057995-568f588f82fb?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1567306226416-28f0efdc88ce?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1518569656558-1f25e69d93d7?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1506806732259-39c2d0268443?auto=format&fit=crop&w=420&q=80',
];

$accountUrl = base_url('account');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Shop fresh vegetables, fruits and daily groceries from Sabjiwalah">
    <title>Products - Sabjiwalah</title>
    <link rel="preconnect" href="https://images.unsplash.com">
    <link rel="preload" href="<?= esc(base_url('assets/images/sabjiwalah-cart-logo-header.png?v=20261003'), 'attr') ?>" as="image" type="image/png">
    <link rel="preload" href="<?= esc(base_url('assets/images/sabjiwalah-wordmark-header.png?v=20261003'), 'attr') ?>" as="image" type="image/png">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/home.css?v=20261005-orders-nav'), 'attr') ?>">
    <script src="<?= esc(base_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
</head>
<body class="category-body">
    <div class="app-shell category-app-shell">
        <header class="category-shop-header">
            <div class="category-shop-topbar">
                <a class="category-icon-button category-back-link" href="<?= esc(base_url(''), 'attr') ?>" aria-label="Go back" data-history-back></a>
                <div class="category-shop-title">
                    <h1>Top deals</h1>
                    <button type="button" aria-label="Delivery location">
                        <strong>Delivering to</strong>
                        <span>Surajpur, Greater Noida</span>
                    </button>
                </div>
                <a class="category-icon-button category-search-link" href="<?= esc(base_url('search'), 'attr') ?>" aria-label="Search products"></a>
                <button class="category-icon-button category-share-button" type="button" aria-label="Share" data-share-page></button>
            </div>

            <nav class="category-filter-bar" aria-label="Sort and filter products">
                <button class="category-filter-chip is-featured" type="button" aria-label="Deals">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="8" cy="8" r="2.2"></circle>
                        <circle cx="16" cy="16" r="2.2"></circle>
                        <path d="M17.5 6.5 6.5 17.5"></path>
                    </svg>
                </button>
                <button class="category-filter-chip has-icon" type="button">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4.5 7h15"></path>
                        <path d="M7.5 12h9"></path>
                        <path d="M10 17h4"></path>
                        <circle cx="9" cy="7" r="1.6"></circle>
                        <circle cx="15" cy="12" r="1.6"></circle>
                    </svg>
                    Filters
                </button>
                <button class="category-filter-chip has-icon" type="button">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 5v13"></path>
                        <path d="m4 15 3 3 3-3"></path>
                        <path d="M13 7h7"></path>
                        <path d="M13 12h5"></path>
                        <path d="M13 17h3"></path>
                    </svg>
                    Sort
                </button>
                <button class="category-filter-chip" type="button">Bedsheet Type</button>
                <button class="category-filter-chip" type="button">Fabric</button>
            </nav>
        </header>

        <main class="category-page">
            <div class="category-layout">
                <aside class="category-rail" aria-label="Product categories">
                    <?php foreach ($categoryItems as $category) : ?>
                        <a class="<?= ! empty($category['active']) ? 'is-active' : '' ?>" href="<?= esc(base_url('products'), 'attr') ?>">
                            <span>
                                <img src="<?= esc(app_asset_url($category['image'])) ?>" alt="" data-fallback-image="<?= esc(base_url('assets/images/product-placeholder.svg'), 'attr') ?>">
                            </span>
                            <strong><?= esc($category['name']) ?></strong>
                        </a>
                    <?php endforeach; ?>
                </aside>

                <section class="category-products" aria-label="Products">
                    <?php if ($products === []) : ?>
                        <article class="empty-card product-empty-card">
                            <h2>Fresh stock coming soon</h2>
                            <p>Products added by the store will appear here.</p>
                        </article>
                    <?php else : ?>
                        <div class="commerce-grid category-products-grid">
                            <?php foreach ($products as $index => $product) : ?>
                                <?php
                                $effectivePrice = (float) ($product['sale_price'] ?? $product['price']);
                                $originalPrice = (float) $product['price'];
                                $image = $product['image'] ?: $fallbackImages[$index % count($fallbackImages)];
                                $carouselImages = [$image];
                                for ($offset = 1; count($carouselImages) < 3 && $offset <= count($fallbackImages); $offset++) {
                                    $candidateImage = $fallbackImages[($index + $offset) % count($fallbackImages)];
                                    if (! in_array($candidateImage, $carouselImages, true)) {
                                        $carouselImages[] = $candidateImage;
                                    }
                                }
                                $discount = $originalPrice > $effectivePrice && $originalPrice > 0
                                    ? (int) round((($originalPrice - $effectivePrice) / $originalPrice) * 100)
                                    : 0;
                                $recipeCount = 2 + (($index * 7) % 29);
                                ?>
                                <article class="catalog-card category-product-card">
                                    <div class="product-media">
                                        <button
                                            class="save-button"
                                            type="button"
                                            data-save-product="<?= esc($product['uid'], 'attr') ?>"
                                            aria-label="Save <?= esc($product['name'], 'attr') ?>"
                                            aria-pressed="false"
                                        ></button>
                                        <a class="product-photo" href="<?= esc(base_url('products/'), 'attr') ?><?= esc($product['uid'], 'url') ?>" data-product-carousel>
                                            <span class="product-carousel-viewport">
                                                <span class="product-carousel-track" data-carousel-track>
                                                    <?php foreach ($carouselImages as $slideIndex => $carouselImage) : ?>
                                                        <img
                                                            src="<?= esc(app_asset_url($carouselImage)) ?>"
                                                            alt="<?= $slideIndex === 0 ? esc($product['name']) : '' ?>"
                                                            data-fallback-image="<?= esc(base_url('assets/images/product-placeholder.svg'), 'attr') ?>"
                                                            draggable="false"
                                                            <?= $slideIndex > 0 ? 'loading="lazy"' : '' ?>
                                                        >
                                                    <?php endforeach; ?>
                                                </span>
                                            </span>
                                        </a>
                                        <div class="media-footer">
                                            <span class="media-dots" role="tablist" aria-label="<?= esc($product['name'], 'attr') ?> images">
                                                <?php foreach ($carouselImages as $slideIndex => $carouselImage) : ?>
                                                    <button
                                                        class="<?= $slideIndex === 0 ? 'is-active' : '' ?>"
                                                        type="button"
                                                        data-carousel-dot
                                                        data-carousel-index="<?= esc((string) $slideIndex, 'attr') ?>"
                                                        aria-label="Show image <?= esc((string) ($slideIndex + 1), 'attr') ?>"
                                                        aria-selected="<?= $slideIndex === 0 ? 'true' : 'false' ?>"
                                                        role="tab"
                                                    ></button>
                                                <?php endforeach; ?>
                                            </span>
                                            <span class="veg-mark" aria-label="Vegetarian product"></span>
                                        </div>
                                        <div class="pack-action-row">
                                            <span class="pack-size"><?= esc($product['unit']) ?></span>
                                            <div
                                                class="cart-card-control"
                                                data-cart-control
                                                data-product-uid="<?= esc($product['uid'], 'attr') ?>"
                                                data-product-name="<?= esc($product['name'], 'attr') ?>"
                                                data-product-unit="<?= esc($product['unit'], 'attr') ?>"
                                                data-product-price="<?= esc((string) $effectivePrice, 'attr') ?>"
                                                data-product-image="<?= esc(app_asset_url($image), 'attr') ?>"
                                                data-current-quantity="0"
                                            >
                                                <button
                                                    class="cart-add-button"
                                                    type="button"
                                                    data-add-to-cart
                                                    data-product-uid="<?= esc($product['uid'], 'attr') ?>"
                                                    data-quantity="1"
                                                >
                                                    ADD
                                                </button>
                                                <div class="cart-stepper" aria-label="Cart quantity">
                                                    <button type="button" data-cart-decrement aria-label="Decrease quantity">&minus;</button>
                                                    <span data-cart-quantity-value>1</span>
                                                    <button type="button" data-cart-increment aria-label="Increase quantity">+</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="catalog-copy">
                                        <div class="catalog-price">
                                            <strong>Rs <?= esc(number_format($effectivePrice, 0)) ?></strong>
                                            <?php if ($originalPrice > $effectivePrice) : ?>
                                                <s>Rs <?= esc(number_format($originalPrice, 0)) ?></s>
                                            <?php endif; ?>
                                        </div>
                                        <h3>
                                            <a href="<?= esc(base_url('products/'), 'attr') ?><?= esc($product['uid'], 'url') ?>">
                                                <?= esc($product['name']) ?>
                                            </a>
                                        </h3>
                                        <div class="category-card-meta">
                                            <span><?= esc((string) (10 + ($index % 4) * 2)) ?> mins</span>
                                            <em><?= esc((string) $recipeCount) ?> recipes</em>
                                        </div>
                                        <?php if ($discount > 0) : ?>
                                            <p class="catalog-offer"><?= esc($discount . '% OFF on MRP') ?></p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>

        <a class="floating-cart-bar" href="<?= esc(base_url('checkout'), 'attr') ?>" data-floating-cart hidden>
            <span class="floating-cart-thumbs" data-floating-cart-thumbs aria-hidden="true"></span>
            <span>
                <strong>Checkout</strong>
                <em data-floating-cart-count>0 items</em>
            </span>
            <i aria-hidden="true"></i>
        </a>

        <nav class="bottom-nav" aria-label="Bottom navigation">
            <a href="<?= esc(base_url(''), 'attr') ?>">
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
            <a class="is-active" href="<?= esc(base_url('products'), 'attr') ?>">
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
    <script src="<?= esc(base_url('assets/js/navigation.js?v=20261004-history-back'), 'attr') ?>"></script>
    <script src="<?= esc(base_url('assets/js/cart.js?v=20261005-search-redirect'), 'attr') ?>"></script>
</body>
</html>

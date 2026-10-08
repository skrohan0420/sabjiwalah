<?php
$query = (string) ($query ?? '');
$displayQuery = $query !== '' ? $query : 'Search products';

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
    <?= view('shared/favicon') ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Search fresh vegetables, fruits and daily groceries from Sabjiwalah">
    <title><?= esc($displayQuery) ?> - Sabjiwalah Search</title>
    <link rel="preconnect" href="https://images.unsplash.com">
    <link rel="stylesheet" href="<?= esc(app_static_url('assets/css/home.css'), 'attr') ?>">
    <script src="<?= esc(app_static_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
    <?= view('shared/theme') ?>
</head>
<body class="search-body">
    <div class="app-shell search-app-shell">
        <header class="search-results-header">
            <form class="search-results-bar" action="<?= esc(base_url('search'), 'attr') ?>" method="get" role="search">
                <a class="search-back-button" href="<?= esc(base_url(''), 'attr') ?>" aria-label="Go back" data-history-back></a>
                <label class="sr-only" for="search-results-input">Search products</label>
                <input
                    id="search-results-input"
                    name="q"
                    type="search"
                    value="<?= esc($query, 'attr') ?>"
                    placeholder="Search products"
                    autofocus
                >
                <button class="search-voice-button" type="submit" aria-label="Search"></button>
            </form>

            <nav class="search-filter-strip" aria-label="Sort and filter products">
                <button class="search-filter-chip has-icon" type="button">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M4.5 7h15"></path>
                        <path d="M7.5 12h9"></path>
                        <path d="M10 17h4"></path>
                        <circle cx="9" cy="7" r="1.6"></circle>
                        <circle cx="15" cy="12" r="1.6"></circle>
                    </svg>
                    Filters
                </button>
                <button class="search-filter-chip has-icon" type="button">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M7 5v13"></path>
                        <path d="m4 15 3 3 3-3"></path>
                        <path d="M13 7h7"></path>
                        <path d="M13 12h5"></path>
                        <path d="M13 17h3"></path>
                    </svg>
                    Sort
                </button>
                <button class="search-filter-chip is-selected" type="button">
                    <span class="filter-veg-icon" aria-hidden="true"></span>
                    Veg
                </button>
                <button class="search-filter-chip" type="button">
                    <span class="filter-nonveg-icon" aria-hidden="true"></span>
                    Non-veg
                </button>
                <button class="search-filter-chip" type="button">Brand</button>
            </nav>
        </header>

        <main class="search-results-page">
            <?php if ($products === []) : ?>
                <article class="empty-card product-empty-card search-empty-card">
                    <h2>No products found</h2>
                    <p>Try searching for vegetables, fruits, or daily groceries.</p>
                </article>
            <?php else : ?>
                <section class="search-products" aria-label="<?= esc($query !== '' ? 'Search results for ' . $query : 'All search products', 'attr') ?>">
                    <div class="commerce-grid">
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
                            $ratingCount = number_format(900 + (($index + 1) * 617));
                            ?>
                            <article class="catalog-card">
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
                                                        data-fallback-image="<?= esc(app_static_url('assets/images/product-placeholder.svg'), 'attr') ?>"
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
                                    <p class="catalog-offer">
                                        <?= $discount > 0 ? esc($discount . '% OFF on MRP') : 'Price Drop' ?>
                                    </p>
                                    <h3>
                                        <a href="<?= esc(base_url('products/'), 'attr') ?><?= esc($product['uid'], 'url') ?>">
                                            <?= esc($product['name']) ?>
                                        </a>
                                    </h3>
                                    <div class="rating-row" aria-label="Product rating">
                                        <span class="rating-stars" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span>
                                        <span><?= esc($ratingCount) ?></span>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
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
    <script src="<?= esc(app_static_url('assets/js/navigation.js'), 'attr') ?>"></script>
    <script src="<?= esc(app_static_url('assets/js/cart.js'), 'attr') ?>"></script>
</body>
</html>

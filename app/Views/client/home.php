<?php
$categoryTabs = [
    ['name' => 'All', 'icon' => 'bag', 'active' => true],
    ['name' => 'Vegetables', 'icon' => 'leaf', 'badge' => 'Fresh'],
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
$accountUrl = $isLoggedIn ? '/account' : '/login';
$accountLabel = $isLoggedIn ? 'Account' : 'Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sabjiwalah fresh grocery and vegetable delivery">
    <title>Sabjiwalah - Fresh Grocery Delivery</title>
    <link rel="preconnect" href="https://images.unsplash.com">
    <link rel="preload" href="/assets/images/sabjiwalah-cart-logo-header.png?v=20261003" as="image" type="image/png">
    <link rel="preload" href="/assets/images/sabjiwalah-wordmark-header.png?v=20261003" as="image" type="image/png">
    <link rel="stylesheet" href="/assets/css/home.css?v=20261003-brand-cache">
</head>
<body>
    <div class="app-shell">
        <header class="home-header">
            <div class="status-row" aria-label="Service status">
                <span class="brand-mark" aria-label="Sabjiwalah">
                    <img class="brand-cart-logo" src="/assets/images/sabjiwalah-cart-logo-header.png?v=20261003" alt="" width="184" height="132" decoding="async" fetchpriority="high">
                    <img class="brand-wordmark" src="/assets/images/sabjiwalah-wordmark-header.png?v=20261003" alt="" width="620" height="160" decoding="async" fetchpriority="high">
                </span>
                <div class="header-icons">
                    <a class="wallet-pill" href="/cart" aria-label="Cart total">
                        <span>Rs</span>
                        <strong data-cart-count-badge>0</strong>
                    </a>
                    <a class="profile-button" href="<?= esc($accountUrl, 'attr') ?>" aria-label="<?= esc($accountLabel, 'attr') ?>">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 12.5a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" />
                            <path d="M4.75 20a7.25 7.25 0 0 1 14.5 0" />
                        </svg>
                    </a>
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
            <form class="search-box" action="/products" method="get" role="search">
                <label class="sr-only" for="home-search">Search products</label>
                <span class="search-icon" aria-hidden="true"></span>
                <input id="home-search" name="q" type="search" placeholder="Search for atta, dal, coke and more">
                <button type="submit" aria-label="Search by voice or text"></button>
            </form>

            <nav class="category-tabs" aria-label="Shop categories">
                <?php foreach ($categoryTabs as $category) : ?>
                    <a class="<?= ! empty($category['active']) ? 'is-active' : '' ?>" href="/products">
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

            <div class="cart-message home-cart-message" data-cart-message role="status"></div>

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
                                <?php
                                $effectivePrice = (float) ($product['sale_price'] ?? $product['price']);
                                $originalPrice = (float) $product['price'];
                                $image = $product['image'] ?: $fallbackImages[($index + $sectionIndex) % count($fallbackImages)];
                                $carouselImages = [$image];
                                for ($offset = 1; count($carouselImages) < 4 && $offset <= count($fallbackImages); $offset++) {
                                    $candidateImage = $fallbackImages[($index + $sectionIndex + $offset) % count($fallbackImages)];
                                    if (! in_array($candidateImage, $carouselImages, true)) {
                                        $carouselImages[] = $candidateImage;
                                    }
                                }
                                $discount = $originalPrice > $effectivePrice && $originalPrice > 0
                                    ? (int) round((($originalPrice - $effectivePrice) / $originalPrice) * 100)
                                    : 0;
                                $ratingCount = number_format(6400 + (($index + 1) * 1207) + ($sectionIndex * 237));
                                ?>
                                <article class="catalog-card">
                                    <div class="product-media">
                                        <?php if ($index === 2 && $sectionIndex === 0) : ?>
                                            <span class="product-ribbon" aria-label="Fasting Special">
                                                <span aria-hidden="true">Fasting Special</span>
                                            </span>
                                        <?php endif; ?>
                                        <button
                                            class="save-button"
                                            type="button"
                                            data-save-product="<?= esc($product['uid'], 'attr') ?>"
                                            aria-label="Save <?= esc($product['name'], 'attr') ?>"
                                            aria-pressed="false"
                                        ></button>
                                        <a class="product-photo" href="/products/<?= esc($product['uid'], 'url') ?>" data-product-carousel>
                                            <span class="product-carousel-viewport">
                                                <span class="product-carousel-track" data-carousel-track>
                                                    <?php foreach ($carouselImages as $slideIndex => $carouselImage) : ?>
                                                        <img
                                                            src="<?= esc($carouselImage) ?>"
                                                            alt="<?= $slideIndex === 0 ? esc($product['name']) : '' ?>"
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
                                                data-product-image="<?= esc($image, 'attr') ?>"
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
                                            <a href="/products/<?= esc($product['uid'], 'url') ?>">
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

                        <?php if (! empty($section['show_all'])) : ?>
                            <a class="see-all-products" href="/products">
                                <span>
                                    <?php foreach (array_slice($section['products'], 0, 3) as $thumbIndex => $thumbProduct) : ?>
                                        <?php $thumbImage = $thumbProduct['image'] ?: $fallbackImages[$thumbIndex % count($fallbackImages)]; ?>
                                        <img src="<?= esc($thumbImage) ?>" alt="">
                                    <?php endforeach; ?>
                                </span>
                                See all products
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <section class="price-drops" aria-labelledby="drops-heading">
                <h2 id="drops-heading">Mega Price Drops</h2>
                <div class="promo-grid">
                    <?php foreach ($promoTiles as $tile) : ?>
                        <a class="promo-card" href="/products">
                            <span><?= esc($tile['offer']) ?></span>
                            <h3><?= esc($tile['title']) ?></h3>
                            <img src="<?= esc($tile['image']) ?>" alt="">
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="quick-links" aria-label="Quick shop shortcuts">
                <a href="/products"><span>%</span> Deals</a>
                <a href="/products"><span></span> Fresh</a>
                <a href="/products"><span></span> Greens</a>
                <a href="/products"><span></span> Staples</a>
            </section>
        </main>

        <a class="floating-cart-bar" href="/cart" data-floating-cart hidden>
            <span class="floating-cart-thumbs" data-floating-cart-thumbs aria-hidden="true"></span>
            <span>
                <strong>View cart</strong>
                <em data-floating-cart-count>0 items</em>
            </span>
            <i aria-hidden="true"></i>
        </a>

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
            <a class="is-active" href="/">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3.5 11.2 12 4l8.5 7.2"></path>
                        <path d="M5.8 10.4v8.1c0 .8.6 1.4 1.4 1.4h9.6c.8 0 1.4-.6 1.4-1.4v-8.1"></path>
                        <path d="M9.6 19.9v-5.4h4.8v5.4"></path>
                    </svg>
                </span>
                Home
            </a>
            <a href="/products">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M4.3 9.5h15.4l-1.2-4.1H5.5L4.3 9.5Z"></path>
                        <path d="M5.4 9.5v9.2c0 .8.6 1.4 1.4 1.4h10.4c.8 0 1.4-.6 1.4-1.4V9.5"></path>
                        <path d="M9.2 20.1v-6h5.6v6"></path>
                        <path d="M3.7 9.5c.3 1.5 1.4 2.4 2.7 2.4s2.4-.9 2.7-2.4c.3 1.5 1.4 2.4 2.9 2.4s2.6-.9 2.9-2.4c.3 1.5 1.4 2.4 2.7 2.4s2.4-.9 2.7-2.4"></path>
                    </svg>
                </span>
                Shop
            </a>
            <a href="/products">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M8.4 3.8 12 7.4 8.4 11 4.8 7.4 8.4 3.8Z"></path>
                        <path d="M15.6 3.8 19.2 7.4 15.6 11 12 7.4 15.6 3.8Z"></path>
                        <path d="M8.4 13 12 16.6l-3.6 3.6-3.6-3.6L8.4 13Z"></path>
                        <path d="M15.6 13l3.6 3.6-3.6 3.6-3.6-3.6 3.6-3.6Z"></path>
                    </svg>
                </span>
                Categories
            </a>
            <a href="<?= esc($accountUrl, 'attr') ?>">
                <span class="nav-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 12.1a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Z"></path>
                        <path d="M4.8 20.1c.8-4.1 3.3-6.2 7.2-6.2s6.4 2.1 7.2 6.2"></path>
                    </svg>
                </span>
                <?= esc($accountLabel) ?>
            </a>
        </nav>
    </div>
    <script src="/assets/js/cart.js?v=20261003-optimistic-cart"></script>
</body>
</html>

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
    <link rel="stylesheet" href="/assets/css/home.css">
</head>
<body>
    <div class="app-shell">
        <header class="home-header">
            <div class="status-row" aria-label="Service status">
                <span>Sabjiwalah in</span>
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
                <h1>13 minutes</h1>
                <a href="/account">
                    <strong>HOME</strong>
                    <span>Surajpur, Greater Noida</span>
                </a>
            </div>

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
                                    <path d="M7.2 8.6h9.6l1 11H6.2l1-11Z"></path>
                                    <path d="M9 8.6V7a3 3 0 0 1 6 0v1.6"></path>
                                    <path d="M8.8 12.2h6.4"></path>
                                </svg>
                            <?php elseif ($category['icon'] === 'leaf' || $category['icon'] === 'sprout') : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M5.1 18.9C5.4 10 11.4 5.7 19.6 4.4c-.5 8.2-5 13.7-13.1 14.3"></path>
                                    <path d="M6.1 18.3c3.1-3.6 6.1-6.2 10.1-8"></path>
                                </svg>
                            <?php elseif ($category['icon'] === 'apple') : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M12 8.5c1.5-1.4 4.6-1.2 5.9.8 1.9 2.9.1 9.2-2.9 10.3-1 .4-2-.2-3-.2s-2 .6-3 .2c-3-1.1-4.8-7.4-2.9-10.3 1.3-2 4.4-2.2 5.9-.8Z"></path>
                                    <path d="M12 8.4c.1-2.2 1.3-3.7 3.2-4.5"></path>
                                    <path d="M11.8 8.2C10.8 6.7 9.6 6 8.2 6"></path>
                                </svg>
                            <?php elseif ($category['icon'] === 'milk') : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M9 3.8h6v3.5l1.4 1.8v10.1c0 .7-.6 1.2-1.2 1.2H8.8c-.6 0-1.2-.5-1.2-1.2V9.1L9 7.3V3.8Z"></path>
                                    <path d="M9 7.3h6"></path>
                                    <path d="M8.4 12.5h7.2"></path>
                                </svg>
                            <?php else : ?>
                                <svg viewBox="0 0 24 24" focusable="false">
                                    <path d="M5.2 10.2h13.6v10H5.2v-10Z"></path>
                                    <path d="M4.4 7.2h15.2v3H4.4v-3Z"></path>
                                    <path d="M12 7.2v13"></path>
                                    <path d="M12 7.1c-2.2-3.7-6.1-2.1-4.8.7"></path>
                                    <path d="M12 7.1c2.2-3.7 6.1-2.1 4.8.7"></path>
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
        </header>

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
                                $discount = $originalPrice > $effectivePrice && $originalPrice > 0
                                    ? (int) round((($originalPrice - $effectivePrice) / $originalPrice) * 100)
                                    : 0;
                                $ratingCount = number_format(6400 + (($index + 1) * 1207) + ($sectionIndex * 237));
                                $stockLeft = isset($product['stock_quantity']) ? (int) $product['stock_quantity'] : null;
                                ?>
                                <article class="catalog-card">
                                    <div class="product-media">
                                        <?php if ($index === 2 && $sectionIndex === 0) : ?>
                                            <span class="product-ribbon" aria-label="Fasting Special">
                                                <span aria-hidden="true">Fasting</span>
                                            </span>
                                        <?php endif; ?>
                                        <button
                                            class="save-button"
                                            type="button"
                                            data-save-product="<?= esc($product['uid'], 'attr') ?>"
                                            aria-label="Save <?= esc($product['name'], 'attr') ?>"
                                            aria-pressed="false"
                                        ></button>
                                        <a class="product-photo" href="/products/<?= esc($product['uid'], 'url') ?>">
                                            <img src="<?= esc($image) ?>" alt="<?= esc($product['name']) ?>">
                                        </a>
                                        <div class="media-footer">
                                            <span class="media-dots" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                                            <span class="veg-mark" aria-label="Vegetarian product"></span>
                                        </div>
                                        <div class="pack-action-row">
                                            <span class="pack-size"><?= esc($product['unit']) ?></span>
                                            <div
                                                class="cart-card-control"
                                                data-cart-control
                                                data-product-uid="<?= esc($product['uid'], 'attr') ?>"
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
                                        <p class="delivery-row">
                                            <span aria-hidden="true"></span>
                                            11 mins
                                            <?php if ($stockLeft !== null && $stockLeft <= 5) : ?>
                                                <em><?= esc((string) $stockLeft) ?> left</em>
                                            <?php endif; ?>
                                        </p>
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
    <script src="/assets/js/cart.js"></script>
</body>
</html>

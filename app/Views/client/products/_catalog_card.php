<?php
$index = $index ?? 0;
$sectionIndex = $sectionIndex ?? 0;
$fallbackImages = $fallbackImages ?? [
    'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1447175008436-054170c2e979?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1576045057995-568f588f82fb?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1567306226416-28f0efdc88ce?auto=format&fit=crop&w=420&q=80',
    'https://images.unsplash.com/photo-1518569656558-1f25e69d93d7?auto=format&fit=crop&w=420&q=80',
];
?>
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
            <?= view('client/products/_detail_control', ['item' => array_replace($product, ['image' => $image]), 'primary' => false]) ?>
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

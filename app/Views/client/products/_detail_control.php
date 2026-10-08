<div class="cart-card-control <?= $primary ? 'primary-control' : '' ?>" data-cart-control
     data-current-quantity="0" data-product-uid="<?= esc($item['uid'], 'attr') ?>"
     data-product-name="<?= esc($item['name'], 'attr') ?>"
     data-product-unit="<?= esc($item['unit'], 'attr') ?>"
     data-product-image="<?= esc(app_asset_url($item['image'] ?: 'assets/images/product-placeholder.svg'), 'attr') ?>"
     data-product-price="<?= esc((string) ($item['sale_price'] ?? $item['price']), 'attr') ?>">
    <button type="button" class="cart-add-button" data-add-to-cart data-product-uid="<?= esc($item['uid'], 'attr') ?>" data-quantity="1" <?= (int) $item['stock_quantity'] < 1 ? 'disabled' : '' ?>><?= (int) $item['stock_quantity'] < 1 ? 'Sold out' : 'ADD' ?></button>
    <div class="cart-stepper" aria-label="Cart quantity for <?= esc($item['name'], 'attr') ?>">
        <button type="button" data-cart-decrement aria-label="Remove one <?= esc($item['name'], 'attr') ?>">−</button>
        <span data-cart-quantity-value>1</span>
        <button type="button" data-cart-increment aria-label="Add one <?= esc($item['name'], 'attr') ?>">+</button>
    </div>
</div>

<?php
$order=$detail['order'];
$when=static fn(?string $stamp): string => $stamp ? (new DateTimeImmutable($stamp))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('j M Y, g:i a').' IST' : 'Time not recorded';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= view('shared/favicon') ?>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($order['order_number']) ?> - Sabjiwalah</title>
    <link rel="stylesheet" href="<?= esc(app_static_url('assets/css/home.css'), 'attr') ?>">
    <link rel="stylesheet" href="<?= esc(app_static_url('assets/css/delivery-pin.css'), 'attr') ?>">
    <link rel="stylesheet" href="<?= esc(app_static_url('assets/css/customer-order.css'), 'attr') ?>">
    <script src="<?= esc(app_static_url('assets/js/app-url.js'), 'attr') ?>" data-base-url="<?= esc(base_url(), 'attr') ?>"></script>
    <?= view('shared/theme') ?>
</head>
<body class="orders-body customer-order-body">
<div class="app-shell orders-app-shell">
    <header class="orders-header customer-progress-header"><a class="orders-back-link" href="<?= esc(base_url('orders'), 'attr') ?>" aria-label="Back to my orders"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></a><div><h1>Order progress</h1><p>Order #<?= esc($order['order_number']) ?></p></div><span class="customer-header-mark" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Zm0 0 8 4 8-4M12 11v10M8 5l8 4"/></svg></span></header>
    <main class="orders-page customer-order" data-customer-order="<?= esc($order['uid'], 'attr') ?>">
        <section class="customer-order-card" id="customer-order-private" aria-labelledby="customer-status">
            <h2 id="customer-status"><?= esc($order['status_label']) ?></h2>
            <p id="customer-tracking-message" role="status" aria-live="polite">Live order updates</p>
            <ol class="customer-progress" id="customer-progress" aria-label="Delivery progress">
                <?php foreach($detail['progress'] as $step): ?>
                <li class="is-<?= esc($step['state'], 'attr') ?>" <?= $step['state']==='current'?'aria-current="step"':'' ?>><strong><?= esc($step['label']) ?></strong><span><?= esc($step['recorded_at']?$when($step['recorded_at']):($step['state']==='upcoming'?'Upcoming':'Time not recorded')) ?></span></li>
                <?php endforeach ?>
            </ol>
            <p id="customer-partner" <?= $order['order_status']!=='out_for_delivery'?'hidden':'' ?>><?= esc($detail['delivery_partner']?'Delivery partner: '.$detail['delivery_partner']['name']:'Delivery partner information is currently unavailable.') ?></p>
            <p id="customer-eta" <?= $order['order_status']!=='out_for_delivery'?'hidden':'' ?>>An estimated arrival time is not available yet.</p>
            <section class="order-delivery-pin" data-delivery-pin="<?= esc($order['uid'], 'attr') ?>" <?= $order['order_status']!=='out_for_delivery'?'hidden':'' ?> aria-label="Your delivery PIN">
                <p>Generate your delivery PIN when your order arrives. Share it only after receiving your groceries. Each PIN expires after 30 minutes.</p>
                <button type="button" data-pin-generate>Generate delivery PIN</button><output data-pin-value aria-label="Your delivery PIN" hidden></output>
                <p data-pin-message role="status" aria-live="polite">Shown once. Generating a new PIN replaces the previous one.</p>
            </section>
            <h3>Order details</h3>
            <p class="customer-placed">Placed <?= esc($when($order['placed_at'])) ?></p>
            <ul class="customer-items"><?php foreach($detail['items'] as $item): ?><li><div><strong><?= esc($item['product_name']) ?></strong><span><?= esc($item['quantity'].' × '.$item['unit']) ?></span></div><b>₹<?= esc(number_format((float)$item['total_price'],2)) ?></b></li><?php endforeach ?></ul>
            <div id="customer-payment" class="customer-payment-summary"><div><span>Total paid / payable</span><strong data-order-total>₹<?= esc(number_format((float)$order['total_amount'],2)) ?></strong></div><div><span data-order-payment-method><?= esc($order['payment_method'] === 'cod' ? 'Pay on delivery' : strtoupper($order['payment_method'])) ?></span><small data-order-payment-status="<?= esc($order['payment_status'], 'attr') ?>"><?= esc(ucwords(str_replace('_',' ',$order['payment_status']))) ?></small></div></div>
            <h3>Delivery address</h3><p class="customer-address"><?= esc(implode(', ',array_filter([$order['address_line'],$order['city'],$order['postal_code']]))) ?></p>
        </section>
        <a class="customer-orders-link" href="<?= esc(base_url('orders'), 'attr') ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 6-6 6 6 6M4 12h16"/></svg>Back to my orders</a>
    </main>
</div>
<script src="<?= esc(app_static_url('assets/js/delivery-pin.js'), 'attr') ?>"></script>
<script src="<?= esc(app_static_url('assets/js/customer-order.js'), 'attr') ?>"></script>
</body>
</html>

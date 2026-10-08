<?php
$panels = [
    'help' => ['title' => 'How can we help?', 'text' => 'Track your delivery and view order details in Your orders. To change your doorstep, open Address book and confirm your pin.'],
    'about' => ['title' => 'About Sabjiwalah', 'text' => 'Fresh groceries, vegetables and everyday essentials, brought together for your doorstep. Browse your favourites, choose your delivery location and manage your orders in one place.'],
    'privacy' => ['title' => 'Account privacy', 'text' => 'Your phone number is used to sign in. Your name and delivery address are used for orders. This browser remembers your delivery pin and welcome details. You can edit your profile and change your location from your account.'],
    'notifications' => ['title' => 'Notification preferences', 'text' => 'Check the latest status of your orders in Your orders. Push notifications are currently unavailable.'],
];
foreach ($panels as $id => $panel) : ?>
    <dialog class="account-dialog" id="<?= esc($id) ?>-dialog" aria-labelledby="<?= esc($id) ?>-dialog-title">
        <div class="account-dialog-heading"><h2 id="<?= esc($id) ?>-dialog-title"><?= esc($panel['title']) ?></h2><button type="button" data-account-dialog-close aria-label="Close <?= esc($panel['title'], 'attr') ?>">×</button></div>
        <p><?= esc($panel['text']) ?></p>
        <?php if (in_array($id, ['help', 'notifications'], true)) : ?><a class="guest-continue" href="<?= esc(base_url('orders'), 'attr') ?>">View your orders</a><?php endif; ?>
        <?php if ($id === 'privacy') : ?><button class="account-dialog-secondary" type="button" data-account-clear-browser>Clear saved browser details</button><p class="account-menu-message" data-account-privacy-message role="status"></p><?php endif; ?>
    </dialog>
<?php endforeach; ?>

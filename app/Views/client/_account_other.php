<?php
$rows = [
    ['label' => 'Share the app', 'path' => 'M12 16V4m-5 5 5-5 5 5M5 14v5h14v-5', 'share' => true],
    ['label' => 'About us', 'path' => 'M12 11v6m0-10v1M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', 'dialog' => 'about-dialog'],
    ['label' => 'Account privacy', 'path' => 'M6 10h12v10H6V10Zm3 0V7a3 3 0 0 1 6 0v3m-3 4v2', 'dialog' => 'privacy-dialog'],
    ['label' => 'Notification preferences', 'path' => 'M5 17h14l-2-3V9a5 5 0 0 0-10 0v5l-2 3Zm5 3h4', 'dialog' => 'notifications-dialog'],
];
if (!empty($loggedIn)) $rows[] = ['label' => 'Log out', 'path' => 'M10 4H5v16h5m-2-8h12m-4-4 4 4-4 4', 'url' => base_url('logout')];
?>
<section class="account-list" aria-labelledby="account-other-title">
    <h2 id="account-other-title">Other information</h2>
    <?php foreach ($rows as $row) : ?>
        <?php if (isset($row['url'])) : ?><a class="account-row" href="<?= esc($row['url'], 'attr') ?>">
        <?php else : ?><button class="account-row" type="button" <?= !empty($row['share']) ? 'data-share-app' : 'data-account-dialog="' . esc($row['dialog'], 'attr') . '"' ?>><?php endif; ?>
            <span class="account-row-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="<?= esc($row['path'], 'attr') ?>"/></svg></span>
            <span><?= esc($row['label']) ?></span>
            <svg class="account-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
        <?= isset($row['url']) ? '</a>' : '</button>' ?>
    <?php endforeach; ?>
</section>
<p class="account-menu-message" data-account-menu-message role="status"></p>

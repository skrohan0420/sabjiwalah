<?php
// Reusable feedback block: state is loading, empty, or error; all text is escaped.
$state = in_array($state ?? '', ['loading', 'empty', 'error'], true) ? $state : 'empty';
$defaults = ['loading' => 'Loading…', 'empty' => 'Nothing to show yet.', 'error' => 'Unable to load this section. Please try again.'];
?>
<?php if ($state === 'loading'): ?>
<div class="admin-loading" role="status"><span class="admin-spinner" aria-hidden="true"></span><span><?= esc($message ?? $defaults[$state]) ?></span></div>
<?php elseif ($state === 'error'): ?>
<div class="admin-alert admin-alert-error" role="alert"><?= esc($message ?? $defaults[$state]) ?></div>
<?php else: ?>
<div class="admin-empty" role="status"><?= esc($message ?? $defaults[$state]) ?></div>
<?php endif ?>

<div class="welcome-overlay" data-onboarding hidden data-required="<?= ! empty($onboardingRequired) ? 'true' : 'false' ?>" data-save-account="<?= ! empty($onboardingRequired) ? 'true' : 'false' ?>" data-account-email="<?= esc((string) session('user_email'), 'attr') ?>" data-account-name="<?= esc((string) session('user_name'), 'attr') ?>">
    <section class="welcome-panel" role="dialog" aria-modal="true" aria-labelledby="welcome-title" tabindex="-1">
        <div class="welcome-topline">
            <span class="welcome-brand"><img src="<?= esc(app_static_url('assets/images/sabjiwalah-cart-logo-header.png'), 'attr') ?>" alt="" width="42" height="30"> Sabjiwalah</span>
            <?php if (empty($onboardingRequired)) : ?>
                <button class="welcome-skip" type="button" data-onboarding-skip>Later</button>
            <?php else : ?>
                <span class="welcome-required-badge">Finish setup</span>
            <?php endif; ?>
        </div>
        <div class="welcome-progress" aria-label="Setup progress"><span data-welcome-progress>01 / 02</span><i class="is-active"></i><i data-welcome-second></i></div>
        <div class="welcome-illustration" aria-hidden="true"><svg viewBox="0 0 160 96"><ellipse cx="80" cy="84" rx="50" ry="5" fill="#d9e6c9"/><path d="M43 39h76l-10 40H54Z" fill="#f8d274"/><path d="M60 42 74 13m29 29L89 13" stroke="#506d3a" stroke-width="5" stroke-linecap="round"/><path d="M63 51v18m17-18v18m17-18v18" stroke="#cba849" stroke-width="4" stroke-linecap="round"/><circle cx="49" cy="31" r="14" fill="#e9835b"/><path d="m47 15 8 8-12 1Z" fill="#417342"/><ellipse cx="88" cy="31" rx="14" ry="16" fill="#88ad59"/><path d="M88 16q7-11 14-8q-1 11-14 8" fill="#417342"/><path d="M110 38q-11-21 5-25q14 3 12 18Z" fill="#417342"/></svg></div>
        <h2 id="welcome-title" data-welcome-title>Fresh starts here.</h2>
        <p class="welcome-subtitle" data-welcome-subtitle>A little hello before we fill your basket.</p>
        <form data-welcome-name-form>
            <label class="welcome-label" for="welcome-name">What should we call you?</label>
            <input class="welcome-name" id="welcome-name" name="name" autocomplete="given-name" type="text" maxlength="120" required placeholder="Your name" data-welcome-name>
            <button class="welcome-primary" type="submit">Continue <span aria-hidden="true">→</span></button>
        </form>
        <div data-welcome-location-step hidden>
            <button class="welcome-location-card" type="button" data-welcome-location>
                <span class="welcome-location-icon" aria-hidden="true">⌖</span>
                <span><strong data-welcome-location-label>Pin your delivery address</strong><small data-welcome-location-detail>Search your area or use your current location</small></span>
                <span aria-hidden="true">›</span>
            </button>
            <p class="welcome-location-hint">Place the pin at your doorstep for a more precise delivery address.</p>
            <button class="welcome-primary" type="button" data-welcome-finish>Choose location <span aria-hidden="true">→</span></button>
            <button class="welcome-back" type="button" data-welcome-back>← Change name</button>
        </div>
        <p class="welcome-error" data-welcome-error role="status"></p>
        <p class="welcome-footnote">Good food. A fresh start. Every day.</p>
    </section>
</div>

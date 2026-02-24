<nav class="navbar d-flex justify-content-between">
    <div class="nav-links">
        <a href="dashboard"><?= __('dashboard') ?></a>
        <a href="enter-data"><?= __('enter_data') ?></a>
        <a href="history"><?= __('history') ?></a>
        <a href="template-generator"><?= __('template_generator') ?></a>
        <a href="configurations"><?= __('configurations') ?></a>
    </div>
    <div class="lang-switcher me-3">
        <a href="api/lang/en" class="text-decoration-none <?= \app\helpers\Translation::getLang() === 'en' ? 'fw-bold border-bottom border-2 border-primary' : '' ?>">EN</a>
        <span class="text-muted mx-1">|</span>
        <a href="api/lang/es" class="text-decoration-none <?= \app\helpers\Translation::getLang() === 'es' ? 'fw-bold border-bottom border-2 border-primary' : '' ?>">ES</a>
    </div>
</nav>
<?php
$headerRestaurantName = isset($restaurantName) && is_string($restaurantName) && $restaurantName !== ''
    ? $restaurantName
    : 'Maison Melinot';

$headerActivePage = isset($activePage) && is_string($activePage)
    ? $activePage
    : '';

$headerMenuId = isset($mobileMenuId) && is_string($mobileMenuId) && $mobileMenuId !== ''
    ? $mobileMenuId
    : 'mobile-menu-main';

$headerAbsolute = isset($headerAbsolute) ? (bool) $headerAbsolute : true;
$showHeaderStatus = isset($showHeaderStatus)
    ? (bool) $showHeaderStatus
    : (isset($statusLabel) || isset($statusDetail));

$headerStatusLabel = isset($statusLabel) && is_string($statusLabel) && $statusLabel !== ''
    ? $statusLabel
    : 'Horaires';

$headerStatusDetail = isset($statusDetail) && is_string($statusDetail) && $statusDetail !== ''
    ? $statusDetail
    : 'Indisponibles';

$headerStatusClasses = isset($statusClasses) && is_string($statusClasses) && $statusClasses !== ''
    ? $statusClasses
    : 'border-white/30 bg-white/10 text-white';

$headerRootClass = $headerAbsolute
    ? 'fixed inset-x-0 top-0 z-30 bg-transparent'
    : 'fixed inset-x-0 top-0 z-30 bg-black';

$headerSpacerClass = $headerAbsolute ? 'hidden' : 'block h-24 md:h-28';
?>
<?php if (!$headerAbsolute): ?>
    <div data-site-header-spacer class="<?php echo $headerSpacerClass; ?>"></div>
<?php endif; ?>
<style>
    button:hover,
    input[type="button"]:hover,
    input[type="submit"]:hover,
    input[type="reset"]:hover {
        cursor: pointer;
    }
</style>
<header
    data-site-header
    class="<?php echo $headerRootClass; ?> transition-all duration-300 ease-out <?php echo $headerAbsolute ? '' : 'shadow-lg shadow-black/10'; ?>">
    <nav class="mx-auto max-w-7xl px-6 py-6 lg:px-10">
        <div class="flex items-center justify-between gap-4">
            <a href="/" class="text-white tracking-[0.3em] uppercase text-xs sm:text-sm"><?php echo htmlspecialchars($headerRestaurantName, ENT_QUOTES, 'UTF-8'); ?></a>

            <div class="hidden items-center gap-4 md:flex">
                <?php if ($showHeaderStatus): ?>
                    <div class="rounded border px-3 py-2 text-[11px] leading-tight backdrop-blur-sm <?php echo htmlspecialchars($headerStatusClasses, ENT_QUOTES, 'UTF-8'); ?>">
                        <p class="uppercase tracking-[0.16em]"><?php echo htmlspecialchars($headerStatusLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="normal-case tracking-normal"><?php echo htmlspecialchars($headerStatusDetail, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php endif; ?>

                <div class="flex gap-3 text-xs uppercase tracking-[0.15em] md:text-sm">
                    <a href="/" class="rounded border px-4 py-2 transition <?php echo $headerActivePage === 'home' ? 'border-white bg-white text-black' : 'border-white/70 text-white hover:bg-white hover:text-black'; ?>">Accueil</a>
                    <a href="/carte" class="rounded border px-4 py-2 transition <?php echo $headerActivePage === 'nourriture' ? 'border-white bg-white text-black' : 'border-white/70 text-white hover:bg-white hover:text-black'; ?>">Notre Carte</a>
                    <a href="/vins" class="rounded border px-4 py-2 transition <?php echo $headerActivePage === 'vins' ? 'border-white bg-white text-black' : 'border-white/70 text-white hover:bg-white hover:text-black'; ?>">Nos Vins</a>
                    <a href="/boissons" class="rounded border px-4 py-2 transition <?php echo $headerActivePage === 'boissons' ? 'border-white bg-white text-black' : 'border-white/70 text-white hover:bg-white hover:text-black'; ?>">Nos Boissons</a>
                    <a href="/contact" class="rounded border px-4 py-2 transition <?php echo $headerActivePage === 'contact' ? 'border-white bg-white text-black' : 'border-white/70 text-white hover:bg-white hover:text-black'; ?>">Contact</a>
                </div>
            </div>

            <button
                type="button"
                class="inline-flex items-center rounded border border-white/70 p-2 text-white md:hidden"
                data-collapse-toggle="<?php echo htmlspecialchars($headerMenuId, ENT_QUOTES, 'UTF-8'); ?>"
                aria-controls="<?php echo htmlspecialchars($headerMenuId, ENT_QUOTES, 'UTF-8'); ?>"
                aria-expanded="false">
                <span class="sr-only">Ouvrir le menu principal</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 17 14" xmlns="http://www.w3.org/2000/svg">
                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M1 1h15M1 7h15M1 13h15"/>
                </svg>
            </button>
        </div>

        <div id="<?php echo htmlspecialchars($headerMenuId, ENT_QUOTES, 'UTF-8'); ?>" class="fixed inset-0 z-[60] hidden h-screen w-screen overflow-y-auto bg-black p-6 md:hidden">
            <div class="mx-auto flex min-h-full w-full max-w-7xl flex-col">
                <div class="flex items-center justify-between">
                    <span class="text-white tracking-[0.3em] uppercase text-xs"><?php echo htmlspecialchars($headerRestaurantName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <button
                        type="button"
                        class="inline-flex items-center rounded border border-white/70 p-2 text-white"
                        data-collapse-toggle="<?php echo htmlspecialchars($headerMenuId, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-controls="<?php echo htmlspecialchars($headerMenuId, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-expanded="true">
                        <span class="sr-only">Fermer le menu</span>
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 14 14" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"/>
                        </svg>
                    </button>
                </div>

                <?php if ($showHeaderStatus): ?>
                    <div class="mt-6 rounded border px-3 py-2 text-[11px] leading-tight <?php echo htmlspecialchars($headerStatusClasses, ENT_QUOTES, 'UTF-8'); ?>">
                        <p class="uppercase tracking-[0.16em]"><?php echo htmlspecialchars($headerStatusLabel, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="normal-case tracking-normal"><?php echo htmlspecialchars($headerStatusDetail, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                <?php endif; ?>

                <div class="mt-10 flex flex-1 flex-col justify-center gap-4 text-sm uppercase tracking-[0.2em]">
                    <a href="/" class="rounded border px-4 py-3 text-center <?php echo $headerActivePage === 'home' ? 'border-white bg-white text-black' : 'border-white/80 text-white'; ?>">Accueil</a>
                    <a href="/carte" class="rounded border px-4 py-3 text-center <?php echo $headerActivePage === 'nourriture' ? 'border-white bg-white text-black' : 'border-white/80 text-white'; ?>">Notre Carte</a>
                    <a href="/vins" class="rounded border px-4 py-3 text-center <?php echo $headerActivePage === 'vins' ? 'border-white bg-white text-black' : 'border-white/80 text-white'; ?>">Nos Vins</a>
                    <a href="/boissons" class="rounded border px-4 py-3 text-center <?php echo $headerActivePage === 'boissons' ? 'border-white bg-white text-black' : 'border-white/80 text-white'; ?>">Nos boissons</a>
                    <a href="/contact" class="rounded border px-4 py-3 text-center <?php echo $headerActivePage === 'contact' ? 'border-white bg-white text-black' : 'border-white/80 text-white'; ?>">Contact</a>
                </div>
            </div>
        </div>
    </nav>
</header>

<script>
(function () {
    const header = document.querySelector('[data-site-header]');
    const spacer = document.querySelector('[data-site-header-spacer]');
    if (!header) return;

    const isHomeStyleHeader = <?php echo $headerAbsolute ? 'true' : 'false'; ?>;
    const mobileMenu = document.getElementById(<?php echo json_encode($headerMenuId); ?>);
    let lastScrollY = window.scrollY || 0;
    let ticking = false;
    let headerHeight = 0;

    const syncSpacer = () => {
        if (!spacer) return;
        headerHeight = header.offsetHeight || headerHeight;
        spacer.style.height = headerHeight + 'px';
    };

    const isMenuOpen = () => mobileMenu && !mobileMenu.classList.contains('hidden');

    const updateHeader = () => {
        const currentScrollY = window.scrollY || 0;
        const scrollingDown = currentScrollY > lastScrollY;
        const nearTop = currentScrollY < 40;

        header.classList.toggle('-translate-y-full', !nearTop && scrollingDown && !isMenuOpen());
        header.classList.toggle('translate-y-0', nearTop || !scrollingDown || isMenuOpen());

        header.classList.toggle('bg-transparent', isHomeStyleHeader && nearTop);
        header.classList.toggle('bg-black/90', isHomeStyleHeader && !nearTop);
        header.classList.toggle('backdrop-blur-md', isHomeStyleHeader && !nearTop);

        header.classList.toggle('shadow-lg', !nearTop);

        lastScrollY = currentScrollY <= 0 ? 0 : currentScrollY;
        ticking = false;
    };

    syncSpacer();
    updateHeader();

    window.addEventListener('resize', syncSpacer, { passive: true });
    window.addEventListener('scroll', function () {
        if (!ticking) {
            window.requestAnimationFrame(updateHeader);
            ticking = true;
        }
    }, { passive: true });
})();
</script>


<?php
$footerRestaurantName = isset($restaurantName) && is_string($restaurantName) && $restaurantName !== ''
    ? $restaurantName
    : 'Maison Melinot';

$footerInstagram = isset($instagram) && is_string($instagram)
    ? trim($instagram)
    : '';
?>
<footer class="border-t border-zinc-200 px-6 py-8 text-center text-xs uppercase tracking-[0.16em] text-zinc-500 lg:px-10">
    <div class="flex flex-col items-center justify-center gap-3 md:flex-row md:gap-6">
        <span><?php echo date('Y'); ?> - <?php echo htmlspecialchars($footerRestaurantName, ENT_QUOTES, 'UTF-8'); ?></span>
        <a href="../mentions-legales.php" class="hover:text-black transition">Mentions legales</a>
        <?php if ($footerInstagram !== ''): ?>
            <a href="<?php echo htmlspecialchars($footerInstagram, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="hover:text-black transition">Instagram</a>
        <?php endif; ?>
    </div>
</footer>


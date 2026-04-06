<?php
$restaurantName = 'Maison Melinot';
$pdfRelativePath = 'files/carte-boissons.pdf';
$pdfPublicPath = '/' . $pdfRelativePath;
$pdfAbsolutePath = __DIR__ . '/' . $pdfRelativePath;
$pdfExists = is_file($pdfAbsolutePath);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos boissons - <?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.5.2/flowbite.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Times New Roman', serif;
            background: #fffae8;
            color: #1a1a1a;
        }
        .section-title {
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }
        .pdf-viewer {
            width: 100%;
            height: 72vh;
            min-height: 460px;
        }
        @media (max-width: 768px) {
            .pdf-viewer {
                height: calc(100vw * 1.35);
                min-height: 320px;
                max-height: 85vh;
            }
        }
    </style>
</head>
<body>
    <?php
    $activePage = 'boissons';
    $mobileMenuId = 'mobile-menu-boissons';
    $headerAbsolute = true;
    $showHeaderStatus = false;
    include __DIR__ . '/components/header.php';
    ?>

    <section class="relative h-[46vh] min-h-[320px] overflow-hidden">
        <div class="absolute inset-0 bg-[url('img/ext-bg.jpg')] bg-cover bg-center"></div>
        <div class="absolute inset-0 bg-black/60"></div>
        <div class="relative z-10 flex h-full items-center justify-center px-6 text-center">
            <div>
                <p class="mb-3 text-sm uppercase tracking-[0.35em] text-zinc-200">Restaurant bistronomique</p>
                <h1 class="text-4xl font-normal uppercase tracking-[0.22em] text-white md:text-6xl">Carte des boissons</h1>
            </div>
        </div>
    </section>

    <main class="mx-auto max-w-6xl px-6 py-16 lg:px-10">
        <section class="rounded-lg border border-zinc-200 bg-[#EDE8D0] p-6 shadow-sm md:p-8">
            <div class="mb-6 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <h2 class="section-title text-xl md:text-2xl">Consulter la carte</h2>
                <a href="<?php echo htmlspecialchars($pdfPublicPath, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded border border-black px-5 py-2 text-xs uppercase tracking-[0.14em] transition hover:bg-black hover:text-white">
                    Telecharger le PDF
                </a>
            </div>

            <?php if ($pdfExists): ?>
                <iframe
                    title="Carte des boissons PDF"
                    src="<?php echo htmlspecialchars($pdfPublicPath, ENT_QUOTES, 'UTF-8'); ?>#toolbar=1&navpanes=0"
                    class="pdf-viewer rounded border border-zinc-200"
                    loading="lazy">
                </iframe>
                <p class="mt-4 text-sm text-zinc-600">
                    Si le lecteur ne s'affiche pas, utilisez le bouton "Telecharger le PDF".
                </p>
            <?php else: ?>
                <div class="rounded border border-amber-200 bg-amber-50 p-5 text-amber-900">
                    <p class="font-semibold">Le fichier PDF est introuvable.</p>
                    <p class="mt-2 text-sm">
                        Toute nos excuses, la carte est temporairement indisponible.
                    </p>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include __DIR__ . '/components/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.5.2/flowbite.min.js"></script>
</body>
</html>


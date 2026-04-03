<?php
$restaurantName = 'Maison Melinot';
require_once __DIR__ . '/config.php';
$dbConfig = getDatabaseConfig();

$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
    $stmt = $pdo->query("SELECT nom, email, telephone, adresse_physique FROM restaurant_settings ORDER BY id ASC LIMIT 1");
    $settings = $stmt->fetch();

    if (!empty($settings['nom'])) {
        $restaurantName = $settings['nom'];
    }
} catch (PDOException $e) {
    $settings = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentions legales - <?php echo htmlspecialchars($restaurantName); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.5.2/flowbite.min.css" rel="stylesheet" />
</head>
<body class="bg-white text-zinc-900" style="font-family: 'Times New Roman', serif;">
    <?php
    $activePage = 'mentions';
    $mobileMenuId = 'mobile-menu-mentions';
    $headerAbsolute = false;
    $showHeaderStatus = false;
    include __DIR__ . '/components/header.php';
    ?>

    <main class="mx-auto max-w-4xl px-6 py-16 lg:px-10">
        <a href="/" class="mb-8 inline-flex rounded border border-black px-4 py-2 text-xs uppercase tracking-[0.14em] hover:bg-black hover:text-white transition">Retour accueil</a>
        <h1 class="mb-10 text-4xl uppercase tracking-[0.2em]">Mentions legales</h1>

        <section class="space-y-6 text-zinc-700 leading-7">
            <div>
                <h2 class="mb-2 text-lg uppercase tracking-[0.1em] text-zinc-900">Editeur du site</h2>
                <p><?php echo htmlspecialchars($restaurantName); ?></p>
                <?php if (!empty($settings['adresse_physique'])): ?>
                    <p><?php echo htmlspecialchars($settings['adresse_physique']); ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['telephone'])): ?>
                    <p>Telephone : <?php echo htmlspecialchars($settings['telephone']); ?></p>
                <?php endif; ?>
                <?php if (!empty($settings['email'])): ?>
                    <p>Email : <?php echo htmlspecialchars($settings['email']); ?></p>
                <?php endif; ?>
            </div>

            <div>
                <h2 class="mb-2 text-lg uppercase tracking-[0.1em] text-zinc-900">Hebergement</h2>
                <p>Site heberge sur votre infrastructure WAMP locale.</p>
            </div>

            <div>
                <h2 class="mb-2 text-lg uppercase tracking-[0.1em] text-zinc-900">Propriete intellectuelle</h2>
                <p>Le contenu de ce site (textes, images, logos) est reserve. Toute reproduction sans autorisation prealable est interdite.</p>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/components/footer.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.5.2/flowbite.min.js"></script>
</body>
</html>


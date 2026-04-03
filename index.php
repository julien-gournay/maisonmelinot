<?php
$tagline = 'Restaurant bistronomique';
$heroImage = 'img/ext-bg.jpg';

require_once __DIR__ . '/config.php';
$dbConfig = getDatabaseConfig();

$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

$planning = [];
$exceptionsByDate = [];
$upcomingExceptions = [];
$daysOrder = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);

    $sql_settings = "SELECT nom, adresse_physique, Maps_url, telephone, email, a_propos, a_propos2, instagram FROM restaurant_settings ORDER BY id ASC LIMIT 1";
    $stmt_s = $pdo->query($sql_settings);
    $settings = $stmt_s->fetch();

    if ($settings) {
        if (!empty($settings['nom'])) {
            $restaurantName = $settings['nom'];
        }
        if (!empty($settings['adresse_physique'])) {
            $address = $settings['adresse_physique'];
        }
        if (!empty($settings['Maps_url'])) {
            $mapsEmbedUrl = $settings['Maps_url'];
        }
        if (!empty($settings['telephone'])) {
            $phone = $settings['telephone'];
        }
        if (!empty($settings['email'])) {
            $email = $settings['email'];
        }
        if (!empty($settings['a_propos'])) {
            $aboutText = $settings['a_propos'];
        }
        if (!empty($settings['a_propos2'])) {
            $aboutText2 = $settings['a_propos2'];
        }
        if (!empty($settings['instagram'])) {
            $instagram = $settings['instagram'];
        }
    }

    // Requete pour recuperer les horaires et conserver un ordre stable par service
    $sql_horaires = "SELECT jour, h_debut, h_fin FROM horaires ORDER BY id ASC, h_debut ASC";
    $stmt_h = $pdo->query($sql_horaires);
    $horaires_raw = $stmt_h->fetchAll();

    $sql_exceptions = "SELECT date, h_debut, h_fin, ferme FROM exceptions WHERE date >= CURDATE() ORDER BY date ASC, h_debut ASC";
    $stmt_e = $pdo->query($sql_exceptions);
    $exceptions_raw = $stmt_e->fetchAll();

    // Regroupement par jour pour gerer les doubles services (midi/soir)
    foreach ($horaires_raw as $h) {
        $day = trim($h['jour']);
        $planning[$day][] = [
            'debut' => substr($h['h_debut'], 0, 5),
            'fin'   => substr($h['h_fin'], 0, 5),
        ];
    }

    foreach ($exceptions_raw as $exception) {
        $dateKey = $exception['date'];
        $exceptionsByDate[$dateKey][] = [
            'debut' => $exception['h_debut'] ? substr($exception['h_debut'], 0, 5) : null,
            'fin'   => $exception['h_fin'] ? substr($exception['h_fin'], 0, 5) : null,
            'ferme' => (int) $exception['ferme'] === 1,
        ];
    }
} catch (PDOException $e) {
    // Si la BDD est indisponible, on garde un affichage fonctionnel
    $planning = [];
    $exceptionsByDate = [];
}

$mapsQuery = urlencode($restaurantName . ' ' . $address);
$mapsIframeSrc = !empty($mapsEmbedUrl)
    ? $mapsEmbedUrl
    : 'https://www.google.com/maps?q=' . $mapsQuery . '&output=embed';

$openingHours = [];
foreach ($daysOrder as $day) {
    if (empty($planning[$day])) {
        $openingHours[$day] = 'Fermeture';
        continue;
    }

    $slots = array_map(
        static function ($slot) {
            return $slot['debut'] . ' - ' . $slot['fin'];
        },
        $planning[$day]
    );

    $openingHours[$day] = implode(' | ', $slots);
}

$daysMapByIndex = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];
$todayDate = date('Y-m-d');
$todayDay = $daysMapByIndex[(int) date('N')];

if (!empty($exceptionsByDate[$todayDate])) {
    $todayExceptions = $exceptionsByDate[$todayDate];
    $isClosedToday = false;
    $todaySlots = [];

    foreach ($todayExceptions as $exception) {
        if ($exception['ferme']) {
            $isClosedToday = true;
            break;
        }

        if (!empty($exception['debut']) && !empty($exception['fin'])) {
            $todaySlots[] = $exception['debut'] . ' - ' . $exception['fin'];
        }
    }

    if ($isClosedToday) {
        $openingHours[$todayDay] = 'Fermeture exceptionnelle';
    } elseif (!empty($todaySlots)) {
        $openingHours[$todayDay] = implode(' | ', $todaySlots) . ' (exception)';
    }
}

foreach ($exceptionsByDate as $dateKey => $entries) {
    $dateObject = DateTime::createFromFormat('Y-m-d', $dateKey);
    if (!$dateObject) {
        continue;
    }

    $entryDay = $daysMapByIndex[(int) $dateObject->format('N')];
    $label = $entryDay . ' ' . $dateObject->format('d/m/Y');
    $isClosed = false;
    $slots = [];

    foreach ($entries as $entry) {
        if ($entry['ferme']) {
            $isClosed = true;
            break;
        }

        if (!empty($entry['debut']) && !empty($entry['fin'])) {
            $slots[] = $entry['debut'] . ' - ' . $entry['fin'];
        }
    }

    if ($isClosed) {
        $upcomingExceptions[] = ['date' => $label, 'status' => 'Fermeture exceptionnelle'];
        continue;
    }

    if (!empty($slots)) {
        $upcomingExceptions[] = ['date' => $label, 'status' => implode(' | ', $slots)];
    }
}

$timezone = new DateTimeZone('Europe/Paris');
$now = new DateTime('now', $timezone);

$getSlotsForDate = static function (DateTime $date) use ($planning, $exceptionsByDate, $daysMapByIndex) {
    $dateKey = $date->format('Y-m-d');

    if (!empty($exceptionsByDate[$dateKey])) {
        $exceptionSlots = [];

        foreach ($exceptionsByDate[$dateKey] as $entry) {
            if (!empty($entry['ferme'])) {
                return [];
            }

            if (!empty($entry['debut']) && !empty($entry['fin'])) {
                $exceptionSlots[] = ['debut' => $entry['debut'], 'fin' => $entry['fin']];
            }
        }

        if (!empty($exceptionSlots)) {
            return $exceptionSlots;
        }
    }

    $dayName = $daysMapByIndex[(int) $date->format('N')];
    return $planning[$dayName] ?? [];
};

$currentSlots = $getSlotsForDate($now);
$isOpenNow = false;
$currentCloseTime = '';
$nextOpeningLabel = '';
$nowTime = $now->format('H:i');

foreach ($currentSlots as $slot) {
    if ($nowTime >= $slot['debut'] && $nowTime < $slot['fin']) {
        $isOpenNow = true;
        $currentCloseTime = $slot['fin'];
        break;
    }

    if ($nowTime < $slot['debut']) {
        $nextOpeningLabel = "Aujourd'hui a " . $slot['debut'];
        break;
    }
}

if (!$isOpenNow && $nextOpeningLabel === '') {
    for ($i = 1; $i <= 14; $i++) {
        $futureDate = (clone $now)->modify('+' . $i . ' day');
        $futureSlots = $getSlotsForDate($futureDate);

        if (empty($futureSlots)) {
            continue;
        }

        $when = $i === 1
            ? 'Demain'
            : ($daysMapByIndex[(int) $futureDate->format('N')] . ' ' . $futureDate->format('d/m'));

        $nextOpeningLabel = $when . ' a ' . $futureSlots[0]['debut'];
        break;
    }
}

$statusLabel = $isOpenNow ? 'Ouvert' : 'Ferme';
$statusDetail = $isOpenNow
    ? ('Jusqu\'a ' . $currentCloseTime)
    : ($nextOpeningLabel !== '' ? ('Prochaine ouverture : ' . $nextOpeningLabel) : 'Aucune ouverture planifiee');
$statusClasses = $isOpenNow
    ? 'border-emerald-300/50 bg-emerald-500/20 text-emerald-100'
    : 'border-rose-300/50 bg-rose-500/20 text-rose-100';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Favicon standard -->
    <link rel="icon" type="image/jpg" href="img/favicon.jpg">
    <!-- Solution de repli pour anciens navigateurs
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">-->
    <!-- Apple Touch Icon
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/icons/apple-touch-icon.png">-->
    <title>Accueil - <?php echo htmlspecialchars($restaurantName); ?></title>
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
    </style>
</head>
<body>
    <?php
    $activePage = 'home';
    $mobileMenuId = 'mobile-menu-index';
    $headerAbsolute = true;
    $showHeaderStatus = true;
    include __DIR__ . '/components/header.php';
    ?>

    <section class="relative h-[88vh] min-h-[620px] overflow-hidden">
        <img src="<?php echo htmlspecialchars($heroImage); ?>" alt="Photo du restaurant <?php echo htmlspecialchars($restaurantName); ?>" class="h-full w-full object-cover" />
        <div class="absolute inset-0 bg-black/45"></div>
        <div class="absolute inset-0 flex items-center justify-center px-6 text-center">
            <div>
                <p class="mb-3 text-sm uppercase tracking-[0.35em] text-zinc-200"><?php echo htmlspecialchars($tagline); ?></p>
                <h1 class="mb-8 text-5xl font-normal uppercase tracking-[0.22em] text-white md:text-7xl"><?php echo htmlspecialchars($restaurantName); ?></h1>
                <a href="carte.php" class="inline-flex items-center rounded border border-white px-8 py-3 text-sm uppercase tracking-[0.2em] text-white transition hover:bg-white hover:text-black">Decouvrir la carte</a>
            </div>
        </div>
    </section>

    <main class="mx-auto max-w-6xl px-6 py-16 lg:px-10">
        <section id="a-propos" class="mb-16">
            <h2 class="section-title mb-6 text-center text-2xl">A propos</h2>
            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-lg border border-zinc-200 bg-[#EDE8D0] p-8 shadow-sm">
                    <p class="text-lg leading-8 text-zinc-700">
                        <?php echo nl2br(htmlspecialchars($aboutText)); ?>
                    </p>
                </div>
                <?php if (!empty($aboutText2)): ?>
                    <div class="rounded-lg border border-zinc-200 bg-[#EDE8D0] p-8 shadow-sm">
                        <p class="text-lg leading-8 text-zinc-700">
                            <?php echo nl2br(htmlspecialchars($aboutText2)); ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section id="familial" class="mb-16">
            <h2 class="section-title mb-8 text-center text-2xl">Un lieu familial</h2>
            <div class="grid gap-6 md:grid-cols-2">
                <article class="rounded-lg border border-zinc-200 bg-[#EDE8D0] p-7 shadow-sm">
                    <h3 class="mb-4 text-xl uppercase tracking-[0.08em]">Terrain de petanque</h3>
                    <p class="leading-7 text-zinc-700">
                        Prolongez votre repas autour d'une partie de petanque dans un espace exterieur dedie. Ideal pour partager un moment convivial entre amis ou en famille apres le service.
                    </p>
                </article>
                <article class="rounded-lg border border-zinc-200 bg-[#EDE8D0] p-7 shadow-sm">
                    <h3 class="mb-4 text-xl uppercase tracking-[0.08em]">Aire de jeu pour enfants</h3>
                    <p class="leading-7 text-zinc-700">
                        Les plus jeunes profitent d'une aire de jeu securisee pendant que les parents degustent leur repas en toute serenite. Une experience pensee pour toute la famille.
                    </p>
                </article>
            </div>
        </section>

        <section id="venir" class="mb-8">
            <h2 class="section-title mb-8 text-center text-2xl">Informations pratiques</h2>
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="overflow-hidden rounded-lg border border-zinc-200 shadow-sm">
                    <iframe
                        title="Carte Google Maps de <?php echo htmlspecialchars($restaurantName); ?>"
                        src="<?php echo htmlspecialchars($mapsIframeSrc); ?>"
                        class="h-[340px] w-full"
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                    <div class="border-t border-zinc-200 bg-[#EDE8D0] p-6">
                        <h3 class="mb-3 text-lg uppercase tracking-[0.08em]">Adresse</h3>
                        <p class="mb-3 text-zinc-700"><?php echo htmlspecialchars($address); ?></p>
                        <?php if (!empty($phone)): ?>
                            <p class="mb-1 text-zinc-700"><strong>Telephone :</strong> <?php echo htmlspecialchars($phone); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($email)): ?>
                            <p class="mb-4 text-zinc-700"><strong>Email :</strong> <?php echo htmlspecialchars($email); ?></p>
                        <?php endif; ?>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $mapsQuery; ?>" target="_blank" rel="noopener noreferrer" class="inline-flex rounded border border-black px-5 py-2 text-sm uppercase tracking-[0.12em] transition hover:bg-black hover:text-white">Itineraire Google Maps</a>
                    </div>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-[#EDE8D0] p-7 shadow-sm">
                    <h3 class="mb-4 text-xl uppercase tracking-[0.08em]">Horaires</h3>
                    <div class="divide-y divide-zinc-200 border-y border-zinc-200">
                        <?php foreach ($openingHours as $day => $hours): ?>
                            <div class="flex items-center justify-between py-2 text-sm md:text-base">
                                <span class="font-semibold"><?php echo htmlspecialchars($day); ?></span>
                                <span class="text-zinc-700"><?php echo htmlspecialchars($hours); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($upcomingExceptions)): ?>
                        <div class="mt-5 rounded border border-amber-200 bg-amber-50 p-4">
                            <h4 class="mb-2 text-xs uppercase tracking-[0.16em] text-amber-800">Prochaines exceptions</h4>
                            <div class="space-y-1 text-sm text-amber-900">
                                <?php foreach ($upcomingExceptions as $exception): ?>
                                    <p>
                                        <span class="font-semibold"><?php echo htmlspecialchars($exception['date']); ?> :</span>
                                        <?php echo htmlspecialchars($exception['status']); ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if (!empty($instagram)): ?>
            <section id="instagram" class="mb-8 rounded-lg border border-zinc-200 bg-[#EDE8D0] from-pink-50 via-purple-50 to-blue-50 p-8 text-center shadow-sm">
                <h2 class="section-title mb-6 text-2xl">Suivez-nous sur Instagram</h2>
                <p class="mb-6 text-lg text-zinc-700">Découvrez nos plats et l'ambiance de Maison Mélinot en images</p>
                <a href="<?php echo htmlspecialchars($instagram); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded border border-pink-500 bg-gradient-to-r from-pink-500 to-purple-500 px-8 py-3 text-sm uppercase tracking-[0.12em] font-semibold text-white transition hover:shadow-lg hover:scale-105">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1 1 12.324 0 6.162 6.162 0 0 1-12.324 0zM12 16a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm4.965-10.322a1.44 1.44 0 1 1 2.881.001 1.44 1.44 0 0 1-2.881-.001z"/>
                    </svg>
                    @Maison Mélinot
                </a>
            </section>
        <?php endif; ?>
    </main>

    <?php include __DIR__ . '/components/footer.php'; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.5.2/flowbite.min.js"></script>
</body>
</html>


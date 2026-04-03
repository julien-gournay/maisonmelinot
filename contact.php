<?php
$restaurantName = 'Maison Melinot';
$restaurantEmail = '';
$restaurantPhone = '';

require_once __DIR__ . '/config.php';
$dbConfig = getDatabaseConfig();

$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
    $stmt = $pdo->query("SELECT nom, email, telephone FROM restaurant_settings ORDER BY id ASC LIMIT 1");
    $settings = $stmt->fetch();

    if (!empty($settings['nom'])) {
        $restaurantName = $settings['nom'];
    }
    if (!empty($settings['email'])) {
        $restaurantEmail = $settings['email'];
    }
    if (!empty($settings['telephone'])) {
        $restaurantPhone = $settings['telephone'];
    }
} catch (PDOException $e) {
    // La page reste fonctionnelle meme si la BDD ne repond pas.
}

$formData = [
    'nom' => '',
    'email' => '',
    'objet' => '',
    'message' => '',
];
$errors = [];
$successMessage = '';

$containsHeaderInjection = static function (string $value): bool {
    return preg_match('/[\r\n]/', $value) === 1;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['nom'] = trim((string) ($_POST['nom'] ?? ''));
    $formData['email'] = trim((string) ($_POST['email'] ?? ''));
    $formData['objet'] = trim((string) ($_POST['objet'] ?? ''));
    $formData['message'] = trim((string) ($_POST['message'] ?? ''));

    if ($formData['nom'] === '') {
        $errors[] = 'Le nom est obligatoire.';
    }
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Veuillez saisir un email valide.';
    }
    if ($formData['objet'] === '') {
        $errors[] = 'L\'objet est obligatoire.';
    }
    if (mb_strlen($formData['message']) < 10) {
        $errors[] = 'Le message doit contenir au moins 10 caracteres.';
    }

    if ($containsHeaderInjection($formData['nom']) || $containsHeaderInjection($formData['email']) || $containsHeaderInjection($formData['objet'])) {
        $errors[] = 'Le formulaire contient des caracteres invalides.';
    }

    if ($restaurantEmail === '' || !filter_var($restaurantEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'adresse email du restaurant est indisponible. Merci de reessayer plus tard.';
    }

    if (empty($errors)) {
        $mailSubject = '[Contact Site] ' . $formData['objet'];
        $mailBody = "Nouveau message via le formulaire de contact\n\n"
            . "Nom : {$formData['nom']}\n"
            . "Email : {$formData['email']}\n"
            . "Objet : {$formData['objet']}\n\n"
            . "Message :\n{$formData['message']}\n";

        $domain = isset($_SERVER['HTTP_HOST']) ? preg_replace('/^www\./i', '', $_SERVER['HTTP_HOST']) : 'localhost';
        $fromAddress = 'no-reply@' . $domain;

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $restaurantName . ' <' . $fromAddress . '>',
            'Reply-To: ' . $formData['email'],
            'X-Mailer: PHP/' . phpversion(),
        ];

        $mailSent = mail($restaurantEmail, $mailSubject, $mailBody, implode("\r\n", $headers));

        if ($mailSent) {
            $successMessage = 'Merci, votre message a bien ete envoye. Nous vous repondrons rapidement.';
            $formData = ['nom' => '', 'email' => '', 'objet' => '', 'message' => ''];
        } else {
            $errors[] = 'Une erreur est survenue pendant l\'envoi. Merci de reessayer dans quelques instants.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact - <?php echo htmlspecialchars($restaurantName, ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.5.2/flowbite.min.css" rel="stylesheet" />
</head>
<body class="bg-white text-zinc-900" style="font-family: 'Times New Roman', serif; background: #fffae8;">
    <?php
    $activePage = 'contact';
    $mobileMenuId = 'mobile-menu-contact';
    $headerAbsolute = false;
    $showHeaderStatus = false;
    include __DIR__ . '/components/header.php';
    ?>

    <main class="mx-auto max-w-6xl px-6 py-16 lg:px-10">
        <h1 class="mb-4 text-4xl uppercase tracking-[0.2em]">Contact</h1>
        <p class="mb-10 max-w-3xl text-zinc-700">
            Une question, une reservation de groupe ou une demande particuliere ? Ecrivez-nous via le formulaire ci-dessous.
        </p>

        <div class="grid gap-8 lg:grid-cols-[2fr_1fr]">
            <section class="rounded-lg border border-zinc-200 bg-[#EDE8D0] p-8 shadow-sm">
                <?php if (!empty($successMessage)): ?>
                    <div class="mb-6 rounded border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
                        <?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="mb-6 rounded border border-rose-200 bg-rose-50 px-4 py-3 text-rose-800">
                        <ul class="list-disc pl-5">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post" action="" class="space-y-5">
                    <div>
                        <label for="nom" class="mb-2 block text-xs uppercase tracking-[0.14em] text-zinc-700">Nom</label>
                        <input id="nom" name="nom" type="text" required value="<?php echo htmlspecialchars($formData['nom'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded border border-zinc-300 px-4 py-3 focus:border-black focus:outline-none" />
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-xs uppercase tracking-[0.14em] text-zinc-700">Email</label>
                        <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded border border-zinc-300 px-4 py-3 focus:border-black focus:outline-none" />
                    </div>

                    <div>
                        <label for="objet" class="mb-2 block text-xs uppercase tracking-[0.14em] text-zinc-700">Objet</label>
                        <input id="objet" name="objet" type="text" required value="<?php echo htmlspecialchars($formData['objet'], ENT_QUOTES, 'UTF-8'); ?>" class="w-full rounded border border-zinc-300 px-4 py-3 focus:border-black focus:outline-none" />
                    </div>

                    <div>
                        <label for="message" class="mb-2 block text-xs uppercase tracking-[0.14em] text-zinc-700">Message</label>
                        <textarea id="message" name="message" rows="6" required class="w-full rounded border border-zinc-300 px-4 py-3 focus:border-black focus:outline-none"><?php echo htmlspecialchars($formData['message'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <button type="submit" class="inline-flex rounded border border-black px-6 py-3 text-xs uppercase tracking-[0.14em] transition hover:bg-black hover:text-white">
                        Envoyer le message
                    </button>
                </form>
            </section>

            <aside class="rounded-lg border border-zinc-200 bg-[#EDE8D0] p-8 shadow-sm">
                <h2 class="mb-4 text-xl uppercase tracking-[0.1em]">Informations</h2>
                <?php if ($restaurantPhone !== ''): ?>
                    <p class="mb-3 text-zinc-700"><strong>Telephone :</strong><br><?php echo htmlspecialchars($restaurantPhone, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <?php if ($restaurantEmail !== ''): ?>
                    <p class="mb-6 text-zinc-700"><strong>Email :</strong><br><?php echo htmlspecialchars($restaurantEmail, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php endif; ?>
                <p class="text-sm text-zinc-600">Nous faisons au mieux pour vous repondre dans la journee.</p>
            </aside>
        </div>
    </main>

    <?php include __DIR__ . '/components/footer.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/flowbite/2.5.2/flowbite.min.js"></script>
</body>
</html>



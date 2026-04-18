<?php
session_start();

require_once __DIR__ . '/../config.php';
$dbConfig = getDatabaseConfig();

$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

// ===== SÉCURITÉ : Configuration des sessions =====
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_httponly', 1);
// ini_set('session.cookie_secure', 1); // Décommenter en HTTPS
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', 3600); // 1 heure

// Headers de sécurité HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); // HTTPS only

// ===== SÉCURITÉ : Initialiser les logs =====
$logFile = __DIR__ . '/logs/activity.log';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

function logActivity($action, $details = '') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $message = "[$timestamp] IP: $ip | Action: $action | Details: $details\n";
    @file_put_contents($logFile, $message, FILE_APPEND);
}

// ===== SÉCURITÉ : Initialiser les tokens CSRF =====
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// ===== SÉCURITÉ : Rate limiting pour le login =====
function checkLoginAttempts() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attemptsFile = __DIR__ . '/logs/login_attempts.txt';
    $maxAttempts = 5;
    $lockoutTime = 300; // 5 minutes

    if (!is_dir(__DIR__ . '/logs')) {
        mkdir(__DIR__ . '/logs', 0755, true);
    }

    $attempts = [];
    if (file_exists($attemptsFile)) {
        $lines = @file($attemptsFile, FILE_IGNORE_NEW_LINES);
        foreach ($lines as $line) {
            if (empty($line)) continue;
            $data = json_decode($line, true);
            if ($data && $data['ip'] === $ip && time() - $data['time'] < $lockoutTime) {
                $attempts[] = $data;
            }
        }
    }

    if (count($attempts) >= $maxAttempts) {
        return false; // Compte verrouillé
    }

    return true;
}

function recordLoginAttempt() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attemptsFile = __DIR__ . '/logs/login_attempts.txt';
    $data = json_encode(['ip' => $ip, 'time' => time()]);
    @file_put_contents($attemptsFile, $data . "\n", FILE_APPEND);
}

function clearLoginAttempts() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attemptsFile = __DIR__ . '/logs/login_attempts.txt';
    if (file_exists($attemptsFile)) {
        $lines = @file($attemptsFile, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        $lockoutTime = 300;

        foreach ($lines as $line) {
            if (empty($line)) continue;
            $data = json_decode($line, true);
            if ($data && $data['ip'] !== $ip && time() - $data['time'] < $lockoutTime) {
                $newLines[] = $line;
            }
        }

        @file_put_contents($attemptsFile, implode("\n", $newLines) . "\n");
    }
}

// ===== SÉCURITÉ : Mot de passe backoffice =====
$backofficePassword = getenv('BACKOFFICE_PASSWORD') ?: 'maison2024';
$isAuthenticated = isset($_SESSION['backoffice_auth']) && $_SESSION['backoffice_auth'] === true;

// ===== SÉCURITÉ : Traitement de la connexion avec vérification CSRF =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    // Vérifier le CSRF token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        logActivity('LOGIN_FAILED', 'Token CSRF invalide');
        $loginError = 'Erreur de sécurité : jeton CSRF invalide.';
    } elseif (!checkLoginAttempts()) {
        logActivity('LOGIN_BLOCKED', 'Trop de tentatives');
        $loginError = 'Compte temporairement verrouillé. Réessayez dans 5 minutes.';
    } else {
        $password = trim($_POST['password'] ?? '');

        // Vérifier le mot de passe
        if (hash_equals(hash('sha256', $password), hash('sha256', $backofficePassword))) {
            clearLoginAttempts();
            $_SESSION['backoffice_auth'] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Régénérer le token
            logActivity('LOGIN_SUCCESS', 'Connexion réussie');
            header('Location: ?page=dashboard');
            exit;
        } else {
            recordLoginAttempt();
            logActivity('LOGIN_FAILED', 'Mot de passe incorrect');
            $loginError = 'Mot de passe incorrect.';
        }
    }
}

// ===== SÉCURITÉ : Déconnexion =====
if (isset($_GET['logout'])) {
    logActivity('LOGOUT', 'Déconnexion');
    $_SESSION = [];
    session_destroy();
    header('Location: ./');
    exit;
}

// ===== SÉCURITÉ : Vérifier l'authentification avant chaque action =====
$page = isset($_GET['page']) ? sanitizeString($_GET['page']) : 'login';
$pdo = null;
$message = '';
$error = '';

if (!$isAuthenticated && $page !== 'login') {
    $page = 'login';
}

// Fonctions utiles
function sanitizeString($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

// ...existing code...

function executeAction($pdo, $action) {
    global $message, $error;

    if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $nom = trim($_POST['nom'] ?? '');
            $adresse = trim($_POST['adresse_physique'] ?? '');
            $phone = trim($_POST['telephone'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $apropos = trim($_POST['a_propos'] ?? '');
            $apropos2 = trim($_POST['a_propos2'] ?? '');
            $instagram = trim($_POST['instagram'] ?? '');
            $maps_url = trim($_POST['Maps_url'] ?? '');

            $sql = "UPDATE restaurant_settings SET nom = :nom, adresse_physique = :addr, telephone = :phone, 
                    email = :email, a_propos = :apropos, a_propos2 = :apropos2, instagram = :ig, Maps_url = :maps 
                    WHERE id = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nom' => $nom,
                ':addr' => $adresse,
                ':phone' => $phone,
                ':email' => $email,
                ':apropos' => $apropos,
                ':apropos2' => $apropos2,
                ':ig' => $instagram,
                ':maps' => $maps_url,
            ]);
            $message = 'Paramètres restaurant mis à jour avec succès.';
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }

    if ($action === 'upload_pdf' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $type = $_POST['pdf_type'] ?? '';
            if (!in_array($type, ['carte-food', 'carte-vins', 'carte-boissons'])) {
                throw new Exception('Type de PDF invalide.');
            }

            if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('Erreur lors du téléchargement du fichier.');
            }

            $file = $_FILES['pdf_file'];
            if ($file['type'] !== 'application/pdf') {
                throw new Exception('Le fichier doit être un PDF.');
            }

            $fileName = $type . '.pdf';
            $filePath = __DIR__ . '/../files/' . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Impossible de sauvegarder le fichier.');
            }

            $message = "PDF '$type' mis à jour avec succès.";
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }

    if ($action === 'save_horaires' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $horaires = $_POST['horaires'] ?? [];

            foreach ($horaires as $id => $data) {
                $id = (int) $id;
                $jour = trim($data['jour'] ?? '');
                $h_debut = trim($data['h_debut'] ?? '');
                $h_fin = trim($data['h_fin'] ?? '');

                if (!$jour || !$h_debut || !$h_fin) continue;

                $sql = "UPDATE horaires SET jour = :jour, h_debut = :debut, h_fin = :fin WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':jour' => $jour,
                    ':debut' => $h_debut,
                    ':fin' => $h_fin,
                    ':id' => $id,
                ]);
            }

            $message = 'Horaires mis à jour avec succès.';
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }

    if ($action === 'add_horaire' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $jour = trim($_POST['horaire_jour'] ?? '');
            $h_debut = trim($_POST['horaire_h_debut'] ?? '');
            $h_fin = trim($_POST['horaire_h_fin'] ?? '');

            if (!$jour || !$h_debut || !$h_fin) {
                throw new Exception('Tous les champs sont obligatoires.');
            }

            $sql = "INSERT INTO horaires (jour, h_debut, h_fin) VALUES (:jour, :debut, :fin)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':jour' => $jour,
                ':debut' => $h_debut,
                ':fin' => $h_fin,
            ]);

            $message = 'Horaire ajouté avec succès.';
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }

    if ($action === 'delete_horaire' && isset($_GET['id'])) {
        try {
            $id = (int) $_GET['id'];
            $sql = "DELETE FROM horaires WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $message = 'Horaire supprimé.';
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }

    if ($action === 'add_exception' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $date = trim($_POST['exception_date'] ?? '');
            $ferme = isset($_POST['exception_ferme']) ? 1 : 0;
            $h_debut = trim($_POST['exception_h_debut'] ?? '');
            $h_fin = trim($_POST['exception_h_fin'] ?? '');

            if (!$date) throw new Exception('La date est obligatoire.');

            $sql = "INSERT INTO exceptions (date, h_debut, h_fin, ferme) VALUES (:date, :debut, :fin, :ferme)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':date' => $date,
                ':debut' => $ferme ? NULL : $h_debut,
                ':fin' => $ferme ? NULL : $h_fin,
                ':ferme' => $ferme,
            ]);

            $message = 'Exception ajoutée avec succès.';
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }

    if ($action === 'delete_exception' && isset($_GET['id'])) {
        try {
            $id = (int) $_GET['id'];
            $sql = "DELETE FROM exceptions WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);
            $message = 'Exception supprimée.';
        } catch (Exception $e) {
            $error = 'Erreur : ' . $e->getMessage();
        }
    }
}

// Connexion BDD
try {
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $options);
} catch (PDOException $e) {
    $error = 'Erreur de connexion à la BDD : ' . $e->getMessage();
}

// Exécuter les actions
if ($isAuthenticated && $pdo) {
    $action = $_GET['action'] ?? ($_POST['action'] ?? '');
    if ($action && ($_SERVER['REQUEST_METHOD'] !== 'POST')) {
        executeAction($pdo, $action);
    } elseif ($action && $_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        executeAction($pdo, $action);
    } elseif ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {
        logActivity('ACTION_FAILED', "Action $action : CSRF token invalide");
        $error = 'Erreur de sécurité : jeton CSRF invalide.';
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backoffice Maison Melinot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f3f4f6;
        }
        .sidebar { background: #1f2937; }
        .content { background: white; }

        /* Mobile menu styles */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -256px;
                top: 0;
                width: 256px;
                height: 100vh;
                z-index: 1000;
                transition: left 0.3s ease;
                overflow-y: auto;
            }
            .sidebar.active {
                left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
            }
            .sidebar-overlay.active {
                display: block;
            }
            .mobile-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: #1f2937;
                color: white;
                padding: 1rem;
                margin-bottom: 1rem;
            }
            .menu-toggle {
                cursor: pointer;
                font-size: 1.5rem;
                background: none;
                border: none;
                color: white;
            }
        }

        @media (min-width: 769px) {
            .mobile-header,
            .sidebar-overlay,
            .menu-toggle {
                display: none !important;
            }
        }

        /* Table responsive */
        @media (max-width: 768px) {
            table {
                font-size: 0.875rem;
            }
            table th, table td {
                padding: 0.5rem !important;
            }
            .actions-column {
                white-space: nowrap;
            }
        }

        /* Cursor pointer on buttons */
        button, a[class*="bg-"], .cursor-pointer {
            cursor: pointer;
        }

        /* Padding adjustments for mobile */
        @media (max-width: 768px) {
            .p-8 {
                padding: 1rem;
            }
            .p-6 {
                padding: 1rem;
            }
            h1 {
                font-size: 1.5rem !important;
            }
        }
    </style>
</head>
<body>
    <script>
        function toggleMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('active');
            overlay.classList.toggle('active');
        }

        function closeMobileMenu() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
        }

        // Close menu when clicking overlay
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('sidebar-overlay');
            if (overlay) {
                overlay.addEventListener('click', closeMobileMenu);
            }
        });
    </script>
    <?php if (!$isAuthenticated): ?>
        <!-- Page de connexion -->
        <div class="min-h-screen flex items-center justify-center bg-gray-900">
            <div class="w-full max-w-md bg-white rounded-lg shadow-xl p-8">
                <h1 class="text-3xl font-bold text-gray-900 mb-6 text-center">Backoffice</h1>
                <p class="text-center text-gray-600 mb-6">Maison Melinot</p>

                <?php if (isset($loginError)): ?>
                    <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 rounded">
                        <?php echo htmlspecialchars($loginError); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="">
                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Mot de passe
                        </label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <input type="hidden" name="action" value="login" />
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />
                    <button
                        type="submit"
                        class="w-full bg-blue-600 text-white font-semibold py-2 rounded-lg hover:bg-blue-700 transition"
                    >
                        Se connecter
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <!-- Interface backoffice -->
        <div id="sidebar-overlay" class="sidebar-overlay" onclick="closeMobileMenu()"></div>

        <!-- Mobile Header -->
        <div class="mobile-header">
            <h2 class="text-xl font-bold">Backoffice</h2>
            <button class="menu-toggle" onclick="toggleMobileMenu()">☰</button>
        </div>

        <div class="flex h-screen md:h-auto flex-col md:flex-row">
            <!-- Sidebar -->
            <div id="sidebar" class="sidebar w-full md:w-64 text-white p-6 md:block">
                <h2 class="text-2xl font-bold mb-8 hidden md:block">Backoffice</h2>
                <nav class="space-y-3">
                    <a href="?page=dashboard" class="block px-4 py-2 rounded hover:bg-gray-700 transition <?php echo $page === 'dashboard' ? 'bg-blue-600' : ''; ?>" onclick="closeMobileMenu()">
                        Tableau de bord
                    </a>
                    <a href="?page=settings" class="block px-4 py-2 rounded hover:bg-gray-700 transition <?php echo $page === 'settings' ? 'bg-blue-600' : ''; ?>" onclick="closeMobileMenu()">
                        Paramètres restaurant
                    </a>
                    <a href="?page=horaires" class="block px-4 py-2 rounded hover:bg-gray-700 transition <?php echo $page === 'horaires' ? 'bg-blue-600' : ''; ?>" onclick="closeMobileMenu()">
                        Horaires
                    </a>
                    <a href="?page=exceptions" class="block px-4 py-2 rounded hover:bg-gray-700 transition <?php echo $page === 'exceptions' ? 'bg-blue-600' : ''; ?>" onclick="closeMobileMenu()">
                        Exceptions
                    </a>
                    <a href="?page=files" class="block px-4 py-2 rounded hover:bg-gray-700 transition <?php echo $page === 'files' ? 'bg-blue-600' : ''; ?>" onclick="closeMobileMenu()">
                        PDF
                    </a>
                    <hr class="border-gray-600 my-4" />
                    <a href="?logout=1" class="block px-4 py-2 rounded hover:bg-red-600 transition" onclick="closeMobileMenu()">
                        Déconnexion
                    </a>
                </nav>
            </div>

            <!-- Contenu -->
            <div class="flex-1 overflow-auto">
                <div class="p-4 md:p-8">
                    <?php if ($message): ?>
                        <div class="mb-4 p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Tableau de bord
                    if ($page === 'dashboard'):
                    ?>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Tableau de bord</h1>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Paramètres</h3>
                                <p class="text-gray-600 mb-4 text-sm">Modifier les informations du restaurant</p>
                                <a href="?page=settings" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 cursor-pointer">
                                    Accéder
                                </a>
                            </div>
                            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Horaires</h3>
                                <p class="text-gray-600 mb-4 text-sm">Gérer les horaires d'ouverture</p>
                                <a href="?page=horaires" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 cursor-pointer">
                                    Accéder
                                </a>
                            </div>
                            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Exceptions</h3>
                                <p class="text-gray-600 mb-4 text-sm">Ajouter des fermetures exceptionnelles</p>
                                <a href="?page=exceptions" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 cursor-pointer">
                                    Accéder
                                </a>
                            </div>
                            <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">PDF</h3>
                                <p class="text-gray-600 mb-4 text-sm">Télécharger les cartes PDF</p>
                                <a href="?page=files" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 cursor-pointer">
                                    Accéder
                                </a>
                            </div>
                        </div>
                    <?php
                    // Page des paramètres
                    elseif ($page === 'settings' && $pdo):
                        $settings = $pdo->query("SELECT * FROM restaurant_settings ORDER BY id ASC LIMIT 1")->fetch();
                    ?>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Paramètres restaurant</h1>
                        <div class="w-full md:max-w-2xl bg-white p-4 md:p-6 rounded-lg shadow">
                            <form method="post" action="">
                                <input type="hidden" name="action" value="save_settings" />

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                                    <input type="text" name="nom" value="<?php echo htmlspecialchars($settings['nom'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>

                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Adresse physique</label>
                                    <input type="text" name="adresse_physique" value="<?php echo htmlspecialchars($settings['adresse_physique'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Téléphone</label>
                                    <input type="text" name="telephone" value="<?php echo htmlspecialchars($settings['telephone'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Instagram</label>
                                    <input type="url" name="instagram" value="<?php echo htmlspecialchars($settings['instagram'] ?? ''); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" />
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">URL Google Maps</label>
                                    <textarea name="Maps_url" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"><?php echo htmlspecialchars($settings['Maps_url'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">À propos (bloc 1)</label>
                                    <textarea name="a_propos" rows="4 md:rows-5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"><?php echo htmlspecialchars($settings['a_propos'] ?? ''); ?></textarea>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">À propos (bloc 2)</label>
                                    <textarea name="a_propos2" rows="4 md:rows-5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"><?php echo htmlspecialchars($settings['a_propos2'] ?? ''); ?></textarea>
                                </div>

                                <button type="submit" class="w-full md:w-auto bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition cursor-pointer">
                                    Enregistrer les modifications
                                </button>
                            </form>
                        </div>
                    <?php
                    // Page des horaires
                    elseif ($page === 'horaires' && $pdo):
                        $horaires = $pdo->query("SELECT * FROM horaires ORDER BY id ASC")->fetchAll();
                    ?>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Horaires</h1>

                        <div class="w-full md:max-w-2xl bg-white p-4 md:p-6 rounded-lg shadow mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ajouter un horaire</h3>
                            <form method="post" action="">
                                <input type="hidden" name="action" value="add_horaire" />
                                
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Jour</label>
                                    <select name="horaire_jour" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                                        <option value="">-- Sélectionner un jour --</option>
                                        <option value="Lundi">Lundi</option>
                                        <option value="Mardi">Mardi</option>
                                        <option value="Mercredi">Mercredi</option>
                                        <option value="Jeudi">Jeudi</option>
                                        <option value="Vendredi">Vendredi</option>
                                        <option value="Samedi">Samedi</option>
                                        <option value="Dimanche">Dimanche</option>
                                    </select>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Heure début</label>
                                    <input type="time" name="horaire_h_debut" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                                </div>
                                
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Heure fin</label>
                                    <input type="time" name="horaire_h_fin" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                                </div>
                                
                                <button type="submit" class="w-full md:w-auto bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition cursor-pointer">
                                    Ajouter horaire
                                </button>
                            </form>
                        </div>
                        
                        <div class="w-full bg-white p-4 md:p-6 rounded-lg shadow overflow-x-auto">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Horaires existants</h3>
                            <form method="post" action="">
                                <input type="hidden" name="action" value="save_horaires" />
                                
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />

                                <div class="overflow-x-auto">
                                    <table class="w-full border-collapse border border-gray-300 text-sm">
                                        <thead class="bg-gray-100">
                                            <tr>
                                                <th class="border border-gray-300 px-2 md:px-4 py-2">Jour</th>
                                                <th class="border border-gray-300 px-2 md:px-4 py-2">Début</th>
                                                <th class="border border-gray-300 px-2 md:px-4 py-2">Fin</th>
                                                <th class="border border-gray-300 px-2 md:px-4 py-2">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($horaires as $h): ?>
                                                <tr>
                                                    <td class="border border-gray-300 px-2 md:px-4 py-2">
                                                        <select name="horaires[<?php echo $h['id']; ?>][jour]" class="w-full px-2 py-1 border border-gray-200 rounded text-sm">
                                                            <option value="">-- Jour --</option>
                                                            <option value="Lundi" <?php echo $h['jour'] === 'Lundi' ? 'selected' : ''; ?>>Lundi</option>
                                                            <option value="Mardi" <?php echo $h['jour'] === 'Mardi' ? 'selected' : ''; ?>>Mardi</option>
                                                            <option value="Mercredi" <?php echo $h['jour'] === 'Mercredi' ? 'selected' : ''; ?>>Mercredi</option>
                                                            <option value="Jeudi" <?php echo $h['jour'] === 'Jeudi' ? 'selected' : ''; ?>>Jeudi</option>
                                                            <option value="Vendredi" <?php echo $h['jour'] === 'Vendredi' ? 'selected' : ''; ?>>Vendredi</option>
                                                            <option value="Samedi" <?php echo $h['jour'] === 'Samedi' ? 'selected' : ''; ?>>Samedi</option>
                                                            <option value="Dimanche" <?php echo $h['jour'] === 'Dimanche' ? 'selected' : ''; ?>>Dimanche</option>
                                                        </select>
                                                    </td>
                                                    <td class="border border-gray-300 px-2 md:px-4 py-2">
                                                        <input type="time" name="horaires[<?php echo $h['id']; ?>][h_debut]" value="<?php echo htmlspecialchars($h['h_debut']); ?>" class="w-full px-2 py-1 border border-gray-200 rounded text-sm" />
                                                    </td>
                                                    <td class="border border-gray-300 px-2 md:px-4 py-2">
                                                        <input type="time" name="horaires[<?php echo $h['id']; ?>][h_fin]" value="<?php echo htmlspecialchars($h['h_fin']); ?>" class="w-full px-2 py-1 border border-gray-200 rounded text-sm" />
                                                    </td>
                                                    <td class="border border-gray-300 px-2 md:px-4 py-2 text-center">
                                                        <a href="?page=horaires&action=delete_horaire&id=<?php echo $h['id']; ?>" onclick="return confirm('Confirmer la suppression ?')" class="text-red-600 hover:text-red-800 cursor-pointer text-sm">
                                                            Supprimer
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <button type="submit" class="mt-4 w-full md:w-auto bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition cursor-pointer">
                                    Enregistrer les modifications
                                </button>
                            </form>
                        </div>
                    <?php
                    // Page des exceptions
                    elseif ($page === 'exceptions' && $pdo):
                        $exceptions = $pdo->query("SELECT * FROM exceptions ORDER BY date DESC")->fetchAll();
                    ?>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Exceptions d'ouverture</h1>

                        <div class="w-full md:max-w-2xl bg-white p-4 md:p-6 rounded-lg shadow mb-8">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Ajouter une exception</h3>
                            <form method="post" action="">
                                <input type="hidden" name="action" value="add_exception" />

                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                                    <input type="date" name="exception_date" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                                </div>

                                <div class="mb-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="exception_ferme" class="mr-2 cursor-pointer" />
                                        <span class="text-sm font-medium text-gray-700">Restaurant fermé</span>
                                    </label>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Horaire début (optionnel)</label>
                                    <input type="time" name="exception_h_debut" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Horaire fin (optionnel)</label>
                                    <input type="time" name="exception_h_fin" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                                </div>

                                <button type="submit" class="w-full md:w-auto bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition cursor-pointer">
                                    Ajouter exception
                                </button>
                            </form>
                        </div>

                        <div class="w-full bg-white p-4 md:p-6 rounded-lg shadow overflow-x-auto">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Exceptions existantes</h3>
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse border border-gray-300 text-sm">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="border border-gray-300 px-2 md:px-4 py-2">Date</th>
                                            <th class="border border-gray-300 px-2 md:px-4 py-2">Horaires / Statut</th>
                                            <th class="border border-gray-300 px-2 md:px-4 py-2">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($exceptions as $exc): ?>
                                            <tr>
                                                <td class="border border-gray-300 px-2 md:px-4 py-2"><?php echo htmlspecialchars($exc['date']); ?></td>
                                                <td class="border border-gray-300 px-2 md:px-4 py-2 text-sm">
                                                    <?php
                                                    if ($exc['ferme']) {
                                                        echo 'Fermé';
                                                    } else {
                                                        echo htmlspecialchars($exc['h_debut'] . ' - ' . $exc['h_fin']);
                                                    }
                                                    ?>
                                                </td>
                                                <td class="border border-gray-300 px-2 md:px-4 py-2">
                                                    <a href="?page=exceptions&action=delete_exception&id=<?php echo $exc['id']; ?>" onclick="return confirm('Confirmer la suppression ?')" class="text-red-600 hover:text-red-800 cursor-pointer text-sm">
                                                        Supprimer
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php
                    // Page des fichiers
                    elseif ($page === 'files'):
                    ?>
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">Gestion des PDF</h1>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6">
                            <?php
                            $pdfs = [
                                ['type' => 'carte-food', 'label' => 'Carte des plats', 'file' => 'carte-food.pdf'],
                                ['type' => 'carte-vins', 'label' => 'Carte des vins', 'file' => 'carte-vins.pdf'],
                                ['type' => 'carte-boissons', 'label' => 'Carte des boissons', 'file' => 'carte-boissons.pdf'],
                            ];

                            foreach ($pdfs as $pdf):
                                $filePath = __DIR__ . '/../files/' . $pdf['file'];
                                $exists = file_exists($filePath);
                                $size = $exists ? filesize($filePath) : 0;
                            ?>
                                <div class="bg-white p-4 md:p-6 rounded-lg shadow">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4"><?php echo htmlspecialchars($pdf['label']); ?></h3>
                                    <p class="text-sm text-gray-600 mb-4">
                                        <?php echo $exists ? 'Fichier présent (' . number_format($size / 1024, 2) . ' KB)' : 'Aucun fichier'; ?>
                                    </p>

                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="action" value="upload_pdf" />
                                        <input type="hidden" name="pdf_type" value="<?php echo htmlspecialchars($pdf['type']); ?>" />

                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" />

                                        <div class="mb-4">
                                            <input type="file" name="pdf_file" accept="application/pdf" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm" />
                                        </div>

                                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition cursor-pointer">
                                            Télécharger
                                        </button>
                                    </form>

                                    <?php if ($exists): ?>
                                        <a href="/files/<?php echo htmlspecialchars($pdf['file']); ?>" target="_blank" class="block text-center mt-3 text-sm text-blue-600 hover:text-blue-800 cursor-pointer">
                                            Voir le PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</body>
</html>




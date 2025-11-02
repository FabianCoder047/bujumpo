<?php
require_once '../includes/auth_check.php';
checkRole(['admin']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Filtres
$filter_user = $_GET['user'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Construction de la requête
$where_conditions = [];
$params = [];

if ($filter_user) {
    $where_conditions[] = "u.nom LIKE ? OR u.prenom LIKE ?";
    $params[] = "%$filter_user%";
    $params[] = "%$filter_user%";
}

// Helper to transform IDs to human-readable labels in log texts
function transformLogText(string $text, PDO $db): string {
    static $camionCache = [];
    static $typeMarchCache = [];

    // Replace camion_id=123 with camion={immatriculation}
    $text = preg_replace_callback('/\bcamion_id\s*=\s*(\d+)\b/', function($m) use ($db, &$camionCache) {
        $id = (int)$m[1];
        if (!isset($camionCache[$id])) {
            $stmt = $db->prepare("SELECT immatriculation FROM camions WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $camionCache[$id] = $row && !empty($row['immatriculation']) ? $row['immatriculation'] : 'Inconnu';
        }
        return 'camion=' . $camionCache[$id];
    }, $text);

    // Replace JSON "type_marchandise_id": 3 with "type_marchandise":"Nom"
    $text = preg_replace_callback('/"type_marchandise_id"\s*:\s*(\d+)/', function($m) use ($db, &$typeMarchCache) {
        $id = (int)$m[1];
        if (!isset($typeMarchCache[$id])) {
            $stmt = $db->prepare("SELECT nom FROM types_marchandises WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $typeMarchCache[$id] = $row && !empty($row['nom']) ? $row['nom'] : 'Inconnu';
        }
        return '"type_marchandise":"' . $typeMarchCache[$id] . '"';
    }, $text);

    // Replace JSON "camion_id": 7 with "camion":"IMM"
    $text = preg_replace_callback('/"camion_id"\s*:\s*(\d+)/', function($m) use ($db, &$camionCache) {
        $id = (int)$m[1];
        if (!isset($camionCache[$id])) {
            $stmt = $db->prepare("SELECT immatriculation FROM camions WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $camionCache[$id] = $row && !empty($row['immatriculation']) ? $row['immatriculation'] : 'Inconnu';
        }
        return '"camion":"' . $camionCache[$id] . '"';
    }, $text);

    // Replace plain type_marchandise_id=3 with type_marchandise=Nom
    $text = preg_replace_callback('/\btype_marchandise_id\s*=\s*(\d+)\b/', function($m) use ($db, &$typeMarchCache) {
        $id = (int)$m[1];
        if (!isset($typeMarchCache[$id])) {
            $stmt = $db->prepare("SELECT nom FROM types_marchandises WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            $typeMarchCache[$id] = $row && !empty($row['nom']) ? $row['nom'] : 'Inconnu';
        }
        return 'type_marchandise=' . $typeMarchCache[$id];
    }, $text);

    return $text;
}

if ($filter_action) {
    $where_conditions[] = "l.action LIKE ?";
    $params[] = "%$filter_action%";
}

if ($filter_date) {
    $where_conditions[] = "DATE(l.created_at) = ?";
    $params[] = $filter_date;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Compter le total
$count_sql = "SELECT COUNT(*) FROM logs l JOIN users u ON l.user_id = u.id $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_logs = $stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// Récupération des logs
$logs_sql = "
    SELECT l.*, u.nom, u.prenom, u.role 
    FROM logs l 
    JOIN users u ON l.user_id = u.id 
    $where_clause
    ORDER BY l.created_at DESC 
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($logs_sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Helper to redact element IDs from log texts
function redactIds(string $text): string {
    $patterns = [
        // (id 123) or (ID=123)
        '/\(\s*[iI][dD]\s*[:=]?\s*\d+\s*\)/',
        // id=123, ID:123, id-123, id#123
        '/\b[iI][dD]\s*[:=#-]?\s*\d+\b/',
        // Generic *_id=123 patterns (e.g., camion_id=7)
        '/\b([A-Za-z0-9]+_id)\s*=\s*\d+\b/',
        // JSON "*_id": 123 (e.g., "type_marchandise_id":3)
        '/"([A-Za-z0-9]+_id)"\s*:\s*\d+/',
        // Hash references like #123
        '/#\d+\b/',
    ];
    $replacements = [
        '',                    // remove (id ...)
        '[ID]',                // replace plain id numbers
        '$1=[ID]',             // mask key=value ids
        '"$1":"[ID]"',     // mask JSON key: id value
        '',                    // remove hash numbers
    ];
    return preg_replace($patterns, $replacements, $text);
}

// Statistiques
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as total FROM logs WHERE DATE(created_at) = CURDATE()");
$stats['aujourdhui'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM logs WHERE DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)");
$stats['hier'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM logs WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stats['semaine'] = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs du Système - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-blue-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-blue-800">
            <i class="fas fa-anchor text-2xl mr-2"></i>
            <span class="text-xl font-bold">Port de BUJUMBURA</span>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <p class="text-blue-300 text-sm font-medium">Administration</p>
            </div>
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="types.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
                <i class="fas fa-tags mr-3"></i>
                Gestion des Types
            </a>
            
            <a href="users.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
                <i class="fas fa-users mr-3"></i>
                Gestion des Utilisateurs
            </a>
            
            <a href="logs.php" class="flex items-center px-4 py-3 text-white bg-blue-800">
                <i class="fas fa-list mr-3"></i>
                Logs
            </a>
            <a href="rapports.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
                <i class="fas fa-file-alt mr-3"></i>
                Rapports
            </a>
        </nav>
    </div>

    <!-- Contenu principal -->
    <div class="ml-0 lg:ml-64">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-6 py-4">
                <button class="lg:hidden text-gray-600 hover:text-gray-900" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['prenom'] . ' ' . $user['nom']) ?></p>
                        <p class="text-sm text-gray-500">Administrateur</p>
                    </div>
                    <a href="../auth/logout.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenu -->
        <main class="p-6">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Logs du Système</h1>
                <p class="text-gray-600 mt-2">Historique des actions du système</p>
            </div>

            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-calendar-day text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['aujourdhui'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-calendar-minus text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Hier</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['hier'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-calendar-week text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Cette Semaine</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['semaine'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtres -->
            <div class="bg-white rounded-lg shadow p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filtres</h3>
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Utilisateur</label>
                        <input type="text" name="user" value="<?= htmlspecialchars($filter_user) ?>" placeholder="Nom ou prénom" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                        <input type="text" name="action" value="<?= htmlspecialchars($filter_action) ?>" placeholder="Type d'action" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                        <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            <i class="fas fa-search mr-2"></i>Filtrer
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tableau des logs -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="text-lg font-semibold text-gray-900">Historique des Actions</h3>
                    <span class="text-sm text-gray-500"><?= $total_logs ?> log(s) au total</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Utilisateur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Action</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Détails</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Heure</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                <i class="fas fa-user text-blue-600 text-xs"></i>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($log['prenom'] . ' ' . $log['nom']) ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?= ucfirst($log['role']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars(redactIds(transformLogText((string)$log['action'], $db))) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= htmlspecialchars(redactIds(transformLogText((string)$log['details'], $db))) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="px-6 py-3 border-t border-gray-200 flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        Page <?= $page ?> sur <?= $total_pages ?>
                    </div>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&user=<?= urlencode($filter_user) ?>&action=<?= urlencode($filter_action) ?>&date=<?= urlencode($filter_date) ?>" class="px-3 py-1 text-sm bg-gray-200 rounded hover:bg-gray-300">
                            Précédent
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>&user=<?= urlencode($filter_user) ?>&action=<?= urlencode($filter_action) ?>&date=<?= urlencode($filter_date) ?>" class="px-3 py-1 text-sm <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300' ?> rounded">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&user=<?= urlencode($filter_user) ?>&action=<?= urlencode($filter_action) ?>&date=<?= urlencode($filter_date) ?>" class="px-3 py-1 text-sm bg-gray-200 rounded hover:bg-gray-300">
                            Suivant
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }
    </script>
</body>
</html>

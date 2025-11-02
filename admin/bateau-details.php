<?php
require_once '../includes/auth_check.php';
checkRole(['admin']);
require_once '../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: bateaux.php');
    exit();
}

$bateau_id = (int)$_GET['id'];
$db = getDB();

// Récupérer les détails du bateau
$stmt = $db->prepare("
    SELECT b.*, tb.nom as type_bateau, tb.id as type_bateau_id,
           po.nom as port_origine, po.pays as pays_origine,
           pd.nom as port_destination, pd.pays as pays_destination
    FROM bateaux b
    LEFT JOIN types_bateaux tb ON b.type_bateau_id = tb.id
    LEFT JOIN ports po ON b.port_origine_id = po.id
    LEFT JOIN ports pd ON b.port_destination_id = pd.id
    WHERE b.id = ?
");
$stmt->execute([$bateau_id]);
$bateau = $stmt->fetch();

if (!$bateau) {
    header('Location: bateaux.php');
    exit();
}

// Vérifier si c'est un bateau passager
$is_passager = stripos($bateau['type_bateau'], 'passager') !== false;

// Récupérer les détails spécifiques selon le type de bateau
if ($is_passager) {
    // Détails des passagers
    $stmt = $db->prepare("
        SELECT numero_passager, 
               COALESCE(SUM(poids_marchandises), 0) as poids_total
        FROM passagers_bateaux 
        WHERE bateau_id = ?
        GROUP BY numero_passager
        ORDER BY numero_passager
    ");
    $stmt->execute([$bateau_id]);
    $passagers = $stmt->fetchAll();
    
    $total_passagers = count($passagers);
    $total_poids_passagers = array_sum(array_column($passagers, 'poids_total'));
} else {
    // Détails des marchandises
    $stmt = $db->prepare("
        SELECT tm.nom as type_marchandise, 
               COUNT(*) as quantite,
               COALESCE(SUM(mb.poids), 0) as poids_total
        FROM marchandises_bateaux mb
        JOIN types_marchandises tm ON mb.type_marchandise_id = tm.id
        WHERE mb.bateau_id = ?
        GROUP BY tm.nom, tm.id
        ORDER BY tm.nom
    ");
    $stmt->execute([$bateau_id]);
    $marchandises = $stmt->fetchAll();
    
    $total_marchandises = array_sum(array_column($marchandises, 'quantite'));
    $total_poids_marchandises = array_sum(array_column($marchandises, 'poids_total'));
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Bateau - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
            
            <a href="dashboard.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-blue-800">
                <i class="fas fa-tachometer-alt w-5 mr-3"></i>
                Tableau de bord
            </a>
            <a href="bateaux.php" class="flex items-center px-6 py-3 bg-blue-800 text-white">
                <i class="fas fa-ship w-5 mr-3"></i>
                Bateaux
            </a>
            <a href="camions.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-blue-800">
                <i class="fas fa-truck w-5 mr-3"></i>
                Camions
            </a>
            <a href="ports.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-blue-800">
                <i class="fas fa-map-marker-alt w-5 mr-3"></i>
                Ports
            </a>
            <a href="types.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-blue-800">
                <i class="fas fa-tags w-5 mr-3"></i>
                Types
            </a>
            <a href="logs.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-blue-800">
                <i class="fas fa-history w-5 mr-3"></i>
                Historique
            </a>
            <a href="users.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-blue-800">
                <i class="fas fa-users w-5 mr-3"></i>
                Utilisateurs
            </a>
        </nav>
    </div>

    <div class="ml-0 lg:ml-64">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-6 py-4">
                <button class="lg:hidden text-gray-600 hover:text-gray-900" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div class="flex items-center">
                    <i class="fas fa-ship text-2xl text-blue-600 mr-2"></i>
                    <h1 class="text-xl font-semibold text-gray-900">Détails du Bateau</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($_SESSION['user']['nom'] ?? 'Utilisateur') ?></p>
                        <p class="text-sm text-gray-500"><?= ucfirst($_SESSION['user']['role'] ?? 'Utilisateur') ?></p>
                    </div>
                    <a href="../auth/logout.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenu -->
        <main class="p-6">
            <div class="mb-6">
                <a href="bateaux.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                    <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
                </a>
                
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-2xl font-bold text-gray-900">
                            <?= htmlspecialchars($bateau['nom']) ?>
                            <span class="text-sm font-normal text-gray-500">(<?= htmlspecialchars($bateau['immatriculation']) ?>)</span>
                        </h2>
                        <p class="text-gray-600 mt-1">
                            <?= htmlspecialchars($bateau['type_bateau']) ?> 
                            <?php if ($is_passager): ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 ml-2">
                                    <i class="fas fa-users mr-1"></i> Passagers
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 ml-2">
                                    <i class="fas fa-boxes mr-1"></i> Marchandises
                                </span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Informations générales</h3>
                            <dl class="space-y-3">
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Capitaine</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2"><?= htmlspecialchars($bateau['capitaine']) ?></dd>
                                </div>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Provenance</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">
                                        <?= htmlspecialchars($bateau['port_origine'] ?? 'N/A') ?>
                                        <?php if (!empty($bateau['pays_origine'])): ?>
                                            <span class="text-gray-500">(<?= htmlspecialchars($bateau['pays_origine']) ?>)</span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Destination</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">
                                        <?= htmlspecialchars($bateau['port_destination'] ?? 'N/A') ?>
                                        <?php if (!empty($bateau['pays_destination'])): ?>
                                            <span class="text-gray-500">(<?= htmlspecialchars($bateau['pays_destination']) ?>)</span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Date d'entrée</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">
                                        <?= $bateau['date_entree'] ? date('d/m/Y H:i', strtotime($bateau['date_entree'])) : 'N/A' ?>
                                    </dd>
                                </div>
                                <?php if ($bateau['date_sortie']): ?>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Date de sortie</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">
                                        <?= date('d/m/Y H:i', strtotime($bateau['date_sortie'])) ?>
                                    </dd>
                                </div>
                                <?php endif; ?>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Statut</dt>
                                    <dd class="mt-1 text-sm sm:col-span-2">
                                        <?php if ($bateau['date_sortie']): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Sorti
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Au port
                                            </span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <?php if ($is_passager): ?>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Nombre de passagers</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:col-span-2">
                                        <?= $total_passagers ?> passager<?= $total_passagers > 1 ? 's' : '' ?>
                                    </dd>
                                </div>
                                <?php endif; ?>
                            </dl>
                        </div>

                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">
                                <?= $is_passager ? 'Détails des passagers' : 'Détails des marchandises' ?>
                            </h3>
                            
                            <?php if ($is_passager): ?>
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-users text-blue-500 text-2xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-blue-800">
                                                <?= $total_passagers ?> passager<?= $total_passagers > 1 ? 's' : '' ?>
                                            </h4>
                                            <p class="text-sm text-blue-700 mt-1">
                                                Poids total des bagages : <span class="font-medium"><?= number_format($total_poids_passagers, 2, ',', ' ') ?> kg</span>
                                            </p>
                                        </div>
                                    </div>

                                    <?php if ($passagers): ?>
                                        <div class="mt-4">
                                            <h5 class="text-sm font-medium text-gray-900 mb-2">Répartition par passager :</h5>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-blue-50">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">N° Passager</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids bagages (kg)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <?php foreach ($passagers as $passager): ?>
                                                        <tr>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                                <?= htmlspecialchars($passager['numero_passager']) ?>
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                                <?= number_format($passager['poids_total'], 2, ',', ' ') ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-boxes text-green-500 text-2xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h4 class="text-sm font-medium text-green-800">
                                                <?= $total_marchandises ?> type<?= $total_marchandises > 1 ? 's' : '' ?> de marchandise<?= $total_marchandises > 1 ? 's' : '' ?>
                                            </h4>
                                            <p class="text-sm text-green-700 mt-1">
                                                Poids total : <span class="font-medium"><?= number_format($total_poids_marchandises, 2, ',', ' ') ?> kg</span>
                                            </p>
                                        </div>
                                    </div>

                                    <?php if ($marchandises): ?>
                                        <div class="mt-4">
                                            <h5 class="text-sm font-medium text-gray-900 mb-2">Détail par type :</h5>
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-green-50">
                                                        <tr>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité</th>
                                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids (kg)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <?php foreach ($marchandises as $marchandise): ?>
                                                        <tr>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                                <?= htmlspecialchars($marchandise['type_marchandise']) ?>
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                                <?= number_format($marchandise['quantite'], 0, ',', ' ') ?>
                                                            </td>
                                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                                                <?= number_format($marchandise['poids_total'], 2, ',', ' ') ?>
                                                            </td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('lg:translate-x-0');
        }

        // Fermer le menu latéral sur les écrans mobiles lors de la sélection d'un élément
        document.querySelectorAll('#sidebar a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleSidebar();
                }
            });
        });
    </script>
</body>
</html>

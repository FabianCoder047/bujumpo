<?php
require_once '../includes/auth_check.php';
checkRole(['admin']);
require_once '../config/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: camions.php');
    exit();
}

$camion_id = (int)$_GET['id'];
$db = getDB();

// Récupérer les détails du camion
$stmt = $db->prepare("
    SELECT c.*, tc.nom as type_camion
    FROM camions c
    LEFT JOIN types_camions tc ON c.type_camion_id = tc.id
    WHERE c.id = ?
");
$stmt->execute([$camion_id]);
$camion = $stmt->fetch();

if (!$camion) {
    header('Location: camions.php');
    exit();
}

// Récupérer les détails des marchandises
$stmt = $db->prepare("
    SELECT tm.nom as type_marchandise, 
           SUM(mc.quantite) as quantite,
           COALESCE(SUM(mc.poids), 0) as poids_total
    FROM marchandises_camions mc
    JOIN types_marchandises tm ON mc.type_marchandise_id = tm.id
    WHERE mc.camion_id = ?
    GROUP BY tm.id, tm.nom
    ORDER BY tm.nom
");
$stmt->execute([$camion_id]);
$marchandises = $stmt->fetchAll();
$total_marchandises = array_sum(array_column($marchandises, 'quantite'));
$total_poids = array_sum(array_column($marchandises, 'poids_total'));

// Récupérer l'historique des pesages
$stmt = $db->prepare("
    SELECT * FROM pesages 
    WHERE camion_id = ?
    ORDER BY date_pesage DESC
");
$stmt->execute([$camion_id]);
$pesages = $stmt->fetchAll();

$db = null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails du Camion - Port de BUJUMBURA</title>
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
            
            <a href="dashboard.php" class="flex items-center px-6 py-3 text-blue-100 hover:bg-blue-800">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Tableau de bord
            </a>
            
            <a href="bateaux.php" class="flex items-center px-6 py-3 text-blue-100 hover:bg-blue-800">
                <i class="fas fa-ship mr-3"></i>
                Bateaux
            </a>
            
            <a href="camions.php" class="flex items-center px-6 py-3 bg-blue-800 text-white">
                <i class="fas fa-truck mr-3"></i>
                Camions
            </a>
            
            <a href="logs.php" class="flex items-center px-6 py-3 text-blue-100 hover:bg-blue-800">
                <i class="fas fa-clipboard-list mr-3"></i>
                Journal d'activité
            </a>
            
            <div class="mt-8 px-4 mb-4">
                <p class="text-blue-300 text-sm font-medium">Compte</p>
            </div>
            
            <a href="profile.php" class="flex items-center px-6 py-3 text-blue-100 hover:bg-blue-800">
                <i class="fas fa-user mr-3"></i>
                Mon profil
            </a>
            
            <a href="../auth/logout.php" class="flex items-center px-6 py-3 text-blue-100 hover:bg-blue-800">
                <i class="fas fa-sign-out-alt mr-3"></i>
                Déconnexion
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
                    <i class="fas fa-truck text-2xl text-blue-600 mr-2"></i>
                    <h1 class="text-xl font-semibold text-gray-900">Détails du Camion</h1>
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
                <a href="camions.php" class="inline-flex items-center text-blue-600 hover:text-blue-800 mb-4">
                    <i class="fas fa-arrow-left mr-2"></i> Retour à la liste
                </a>
                
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-2xl font-bold text-gray-900">
                            <?= htmlspecialchars($camion['marque']) ?>
                            <span class="text-sm font-normal text-gray-500">(<?= htmlspecialchars($camion['immatriculation']) ?>)</span>
                        </h2>
                        <div class="flex items-center mt-2">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?= $camion['statut'] === 'entree' ? 'bg-yellow-100 text-yellow-800' : 
                                   ($camion['statut'] === 'en_pesage' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') ?>">
                                <?= $camion['statut'] === 'entree' ? 'En attente' : 
                                   ($camion['statut'] === 'en_pesage' ? 'En pesage' : 'Sorti') ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="px-6 py-4 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Informations générales</h3>
                            <dl class="space-y-3">
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        <?= htmlspecialchars($camion['type_camion']) ?>
                                    </dd>
                                </div>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Marque</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        <?= htmlspecialchars($camion['marque']) ?>
                                    </dd>
                                </div>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Immatriculation</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        <?= htmlspecialchars($camion['immatriculation']) ?>
                                    </dd>
                                </div>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Chauffeur</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        <?= htmlspecialchars($camion['chauffeur']) ?>
                                    </dd>
                                </div>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Agence</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        <?= htmlspecialchars($camion['agence']) ?>
                                    </dd>
                                </div>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Date d'entrée</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        <?= date('d/m/Y H:i', strtotime($camion['date_entree'])) ?>
                                    </dd>
                                </div>
                                <?php if ($camion['date_sortie']): ?>
                                <div class="sm:grid sm:grid-cols-3 sm:gap-4">
                                    <dt class="text-sm font-medium text-gray-500">Date de sortie</dt>
                                    <dd class="mt-1 text-sm text-gray-900 sm:mt-0 sm:col-span-2">
                                        <?= date('d/m/Y H:i', strtotime($camion['date_sortie'])) ?>
                                    </dd>
                                </div>
                                <?php endif; ?>
                            </dl>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Détails des marchandises</h3>
                            
                            <?php if (empty($marchandises)): ?>
                                <div class="bg-yellow-50 p-4 rounded-lg">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl"></i>
                                        </div>
                                        <div class="ml-3">
                                            <h3 class="text-sm font-medium text-yellow-800">Aucune marchandise enregistrée</h3>
                                            <div class="mt-2 text-sm text-yellow-700">
                                                <p>Ce camion ne contient aucune marchandise pour le moment.</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <div class="flex items-start">
                                        <div class="flex-shrink-0">
                                            <i class="fas fa-boxes text-green-500 text-2xl"></i>
                                        </div>
                                        <div class="ml-3 flex-1">
                                            <h4 class="text-sm font-medium text-green-800">
                                                <?= $total_marchandises ?> type(s) de marchandise(s)
                                            </h4>
                                            <p class="text-sm text-green-700 mt-1">
                                                Poids total : <span class="font-medium"><?= number_format($total_poids, 2, ',', ' ') ?> kg</span>
                                            </p>
                                            
                                            <div class="mt-4">
                                                <h5 class="text-sm font-medium text-green-800 mb-2">Détail par type :</h5>
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
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Historique des pesages</h3>
                                
                                <?php if (empty($pesages)): ?>
                                    <div class="bg-yellow-50 p-4 rounded-lg">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl"></i>
                                            </div>
                                            <div class="ml-3">
                                                <h3 class="text-sm font-medium text-yellow-800">Aucun pesage enregistré</h3>
                                                <div class="mt-2 text-sm text-yellow-700">
                                                    <p>Aucun pesage n'a été effectué pour ce camion.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTAV (kg)</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTAC (kg)</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PTRA (kg)</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Charge essieu (kg)</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Surcharge</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($pesages as $pesage): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= date('d/m/Y H:i', strtotime($pesage['date_pesage'])) ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= number_format($pesage['ptav'], 0, ',', ' ') ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= number_format($pesage['ptac'], 0, ',', ' ') ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= number_format($pesage['ptra'], 0, ',', ' ') ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                        <?= number_format($pesage['charge_essieu'], 2, ',', ' ') ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($pesage['surcharge']): ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                                Oui
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                                Non
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
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

<?php
require_once '../includes/auth_check.php';
checkRole(['EnregistreurBateaux']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_bateau':
                $type_bateau_id = $_POST['type_bateau_id'] ?? '';
                $nom = trim($_POST['nom'] ?? '');
                $immatriculation = trim($_POST['immatriculation'] ?? '');
                $capitaine = trim($_POST['capitaine'] ?? '');
                $port_origine_id = $_POST['port_origine_id'] ?? '';
                $port_destination_id = $_POST['port_destination_id'] ?? '';
                $statut = $_POST['statut'] ?? 'entree';
                
                if ($type_bateau_id && $nom && $capitaine) {
                    $immVal = ($immatriculation !== '') ? $immatriculation : null;
                    try {
                        $stmt = $db->prepare("INSERT INTO bateaux (type_bateau_id, nom, immatriculation, capitaine, port_origine_id, port_destination_id, statut) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$type_bateau_id, $nom, $immVal, $capitaine, $port_origine_id, $port_destination_id, $statut]);
                    } catch (PDOException $e) {
                        if ($e->getCode() === '23000' && strpos($e->getMessage(), '1062') !== false) {
                            $stmt = $db->prepare("INSERT INTO bateaux (type_bateau_id, nom, immatriculation, capitaine, port_origine_id, port_destination_id, statut) VALUES (?, ?, NULL, ?, ?, ?, ?)");
                            $stmt->execute([$type_bateau_id, $nom, $capitaine, $port_origine_id, $port_destination_id, $statut]);
                        } else {
                            throw $e;
                        }
                    }
                    $bateauId = $db->lastInsertId();
                    
                    // Si des marchandises sont spécifiées, les ajouter
                    if (isset($_POST['marchandises'])) {
                        foreach ($_POST['marchandises'] as $marchandise) {
                            if (!empty($marchandise['type_id']) && !empty($marchandise['poids'])) {
                                $stmt = $db->prepare("INSERT INTO marchandises_bateaux (bateau_id, type_marchandise_id, mouvement, poids, quantite) VALUES (?, ?, ?, ?, ?)");
                                $stmt->execute([$bateauId, $marchandise['type_id'], $statut, $marchandise['poids'], $marchandise['quantite'] ?? 1]);
                            }
                        }
                    }
                    
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Enregistrement Bateau', "Bateau enregistré: $nom ($statut)"]);
                    
                    $success = "Bateau enregistré avec succès";
                }
                break;
                
            case 'update_statut':
                $bateau_id = $_POST['bateau_id'] ?? '';
                $nouveau_statut = $_POST['nouveau_statut'] ?? '';
                
                if ($bateau_id && $nouveau_statut) {
                    $stmt = $db->prepare("UPDATE bateaux SET statut = ?, date_sortie = NOW() WHERE id = ?");
                    $stmt->execute([$nouveau_statut, $bateau_id]);
                    
                    // Récupérer nom et immatriculation pour le log
                    $binfo = $db->prepare("SELECT nom, immatriculation FROM bateaux WHERE id = ?");
                    $binfo->execute([$bateau_id]);
                    $brow = $binfo->fetch();
                    $bnom = $brow && isset($brow['nom']) ? $brow['nom'] : (string)$bateau_id;
                    $bimmat = $brow && isset($brow['immatriculation']) && $brow['immatriculation'] !== null ? $brow['immatriculation'] : '';
                    $ref = $bimmat ? ("$bnom - $bimmat") : $bnom;
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Changement Statut Bateau', "Bateau: $ref - Nouveau statut: $nouveau_statut"]);
                    
                    $success = "Statut du bateau mis à jour";
                }
                break;
        }
        header('Location: dashboard.php');
        exit();
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupération des données
$types_bateaux = $db->query("SELECT * FROM types_bateaux ORDER BY nom")->fetchAll();
$types_marchandises = $db->query("SELECT * FROM types_marchandises ORDER BY nom")->fetchAll();
$ports = $db->query("SELECT * FROM ports ORDER BY nom")->fetchAll();

// Bateaux au port (dernier mouvement = entrée) par (nom, immatriculation) normalisés - compatible MySQL 5.7
$bateaux_port = $db->query("
    SELECT 
        b.*, 
        tb.nom AS type_bateau, 
        po.nom AS port_origine, 
        pd.nom AS port_destination,
        (SELECT COUNT(*) FROM marchandises_bateaux WHERE bateau_id = b.id) AS nb_marchandises,
        (SELECT COUNT(*) FROM passagers_bateaux WHERE bateau_id = b.id) AS nb_passagers,
        (tb.nom LIKE '%passager%' OR tb.nom LIKE '%Passager%') AS est_passager
    FROM (
        SELECT t2.last_id
        FROM (
            SELECT 
                UPPER(TRIM(nom)) AS norm_nom,
                UPPER(TRIM(COALESCE(immatriculation, ''))) AS norm_immat,
                MAX(GREATEST(IFNULL(date_entree,'0000-01-01'), IFNULL(date_sortie,'0000-01-01'))) AS last_ts
            FROM bateaux
            GROUP BY UPPER(TRIM(nom)), UPPER(TRIM(COALESCE(immatriculation, '')))
        ) t
        JOIN (
            SELECT 
                UPPER(TRIM(b.nom)) AS norm_nom,
                UPPER(TRIM(COALESCE(b.immatriculation, ''))) AS norm_immat,
                GREATEST(IFNULL(b.date_entree,'0000-01-01'), IFNULL(b.date_sortie,'0000-01-01')) AS ts,
                MAX(b.id) AS last_id
            FROM bateaux b
            GROUP BY UPPER(TRIM(b.nom)), UPPER(TRIM(COALESCE(b.immatriculation, ''))), 
                     GREATEST(IFNULL(b.date_entree,'0000-01-01'), IFNULL(b.date_sortie,'0000-01-01'))
        ) t2
        ON t.norm_nom = t2.norm_nom AND t.norm_immat = t2.norm_immat AND t.last_ts = t2.ts
    ) li
    JOIN bateaux b ON b.id = li.last_id
    LEFT JOIN types_bateaux tb ON b.type_bateau_id = tb.id
    LEFT JOIN ports po ON b.port_origine_id = po.id
    LEFT JOIN ports pd ON b.port_destination_id = pd.id
    WHERE b.date_sortie IS NULL
    ORDER BY GREATEST(IFNULL(b.date_entree,'0000-01-01'), IFNULL(b.date_sortie,'0000-01-01')) DESC, b.id DESC
")->fetchAll();

// Bateaux sortis récemment
$bateaux_sortis = $db->query("
    SELECT 
        b.*, 
        tb.nom as type_bateau, 
        po.nom as port_origine, 
        pd.nom as port_destination
    FROM bateaux b
    LEFT JOIN types_bateaux tb ON b.type_bateau_id = tb.id
    LEFT JOIN ports po ON b.port_origine_id = po.id
    LEFT JOIN ports pd ON b.port_destination_id = pd.id
    WHERE b.date_sortie IS NOT NULL
    ORDER BY b.date_sortie DESC
    LIMIT 50
")->fetchAll();

// Statistiques
$stats = [];
// Compter les bateaux au port selon le même calcul (dernier mouvement = entrée) - MySQL 5.7 compatible
$stmt = $db->query("
    SELECT COUNT(*) AS total
    FROM (
        SELECT t2.last_id
        FROM (
            SELECT 
                UPPER(TRIM(nom)) AS norm_nom,
                UPPER(TRIM(COALESCE(immatriculation, ''))) AS norm_immat,
                MAX(GREATEST(IFNULL(date_entree,'0000-01-01'), IFNULL(date_sortie,'0000-01-01'))) AS last_ts
            FROM bateaux
            GROUP BY UPPER(TRIM(nom)), UPPER(TRIM(COALESCE(immatriculation, '')))
        ) t
        JOIN (
            SELECT 
                UPPER(TRIM(b.nom)) AS norm_nom,
                UPPER(TRIM(COALESCE(b.immatriculation, ''))) AS norm_immat,
                GREATEST(IFNULL(b.date_entree,'0000-01-01'), IFNULL(b.date_sortie,'0000-01-01')) AS ts,
                MAX(b.id) AS last_id
            FROM bateaux b
            GROUP BY UPPER(TRIM(b.nom)), UPPER(TRIM(COALESCE(b.immatriculation, ''))), 
                     GREATEST(IFNULL(b.date_entree,'0000-01-01'), IFNULL(b.date_sortie,'0000-01-01'))
        ) t2
        ON t.norm_nom = t2.norm_nom AND t.norm_immat = t2.norm_immat AND t.last_ts = t2.ts
    ) li
    JOIN bateaux b ON b.id = li.last_id
    WHERE b.date_sortie IS NULL
");
$stats['bateaux_port'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bateaux WHERE DATE(date_entree) = CURDATE()");
$stats['entrees_aujourdhui'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bateaux WHERE DATE(date_sortie) = CURDATE()");
$stats['sorties_aujourdhui'] = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Vigile Maritime - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-purple-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-purple-800">
            <i class="fas fa-anchor text-2xl mr-2"></i>
            <span class="text-xl font-bold">Port de BUJUMBURA</span>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <p class="text-purple-300 text-sm font-medium">Vigile Maritime</p>
            </div>
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-white bg-purple-800">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="enregistrer.php" class="flex items-center px-4 py-3 text-purple-200 hover:bg-purple-800 hover:text-white transition duration-200">
                <i class="fas fa-plus mr-3"></i>
                Enregistrer Bateau
            </a>
            
            <a href="historique.php" class="flex items-center px-4 py-3 text-purple-200 hover:bg-purple-800 hover:text-white transition duration-200">
                <i class="fas fa-history mr-3"></i>
                Historique
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
                        <p class="text-sm text-gray-500">Vigile Maritime</p>
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
                <h1 class="text-3xl font-bold text-gray-900">Dashboard Vigile Maritime</h1>
                <p class="text-gray-600 mt-2">Gestion des bateaux et marchandises maritimes</p>
            </div>

            <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>

            <!-- Statistiques -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-ship text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Bateaux au Port</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['bateaux_port'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-arrow-down text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Entrées Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['entrees_aujourdhui'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-arrow-up text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Sorties Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['sorties_aujourdhui'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Actions Rapides</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="enregistrer.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <i class="fas fa-ship text-purple-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Enregistrer un Bateau</p>
                            <p class="text-sm text-gray-600">Entrée ou sortie maritime</p>
                        </div>
                    </a>
                    
                    <a href="historique.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <i class="fas fa-list text-blue-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Voir l'Historique</p>
                            <p class="text-sm text-gray-600">Tous les bateaux enregistrés</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Bateaux au port -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Bateaux au Port</h2>
                
                <?php if (empty($bateaux_port)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-ship text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">Aucun bateau au port pour le moment</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Bateau</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Capitaine</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Port Origine</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Port Destination</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Entrée</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($bateaux_port as $bateau): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($bateau['nom']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($bateau['immatriculation'] ?? '') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($bateau['type_bateau']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($bateau['capitaine']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($bateau['port_origine'] ?? 'BUJUMBURA') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($bateau['port_destination'] ?? 'BUJUMBURA') ?>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d/m/Y H:i', strtotime($bateau['date_entree'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="voirDetails(<?= $bateau['id'] ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                    <a href="enregistrer.php?bateau_id=<?= $bateau['id'] ?>" class="text-purple-600 hover:text-purple-900">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>

            <!-- Bateaux sortis récemment -->
            <div class="bg-white rounded-lg shadow p-6 mt-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Bateaux sortis récemment</h2>
                <?php if (empty($bateaux_sortis)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-ship text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">Aucun bateau sorti récemment</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Bateau</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Capitaine</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Port Origine</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Port Destination</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Sortie</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($bateaux_sortis as $bateau): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($bateau['nom']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($bateau['immatriculation'] ?? '') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($bateau['type_bateau']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($bateau['capitaine']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($bateau['port_origine'] ?? 'BUJUMBURA') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($bateau['port_destination'] ?? 'BUJUMBURA') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $bateau['date_sortie'] ? date('d/m/Y H:i', strtotime($bateau['date_sortie'])) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="voirDetails(<?= $bateau['id'] ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                    <a href="enregistrer.php?bateau_id=<?= $bateau['id'] ?>" class="text-purple-600 hover:text-purple-900">
                                        <i class="fas fa-edit"></i> Modifier
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal Détails -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 overflow-y-auto" onclick="closeDetailsModal()">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <h3 class="text-lg font-semibold text-gray-900">Détails du Bateau</h3>
                </div>
                
                <div id="detailsContent" class="p-6 overflow-y-auto flex-grow">
                    <!-- Contenu dynamique -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Fermer la modale en appuyant sur la touche Échap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDetailsModal();
    }
});

function closeDetailsModal() {
    const modal = document.getElementById('detailsModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function voirDetails(bateauId) {
            console.log('Fonction voirDetails appelée avec ID:', bateauId);
            console.log('URL de l\'API:', `api/bateau-details.php?id=${bateauId}`);
            
            fetch(`api/bateau-details.php?id=${bateauId}`)
                .then(response => {
                    console.log('Réponse reçue, statut:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Données reçues:', data);
                    if (data.success) {
                        const bateau = data.bateau;
                        const marchandises = data.marchandises || [];
                        const passagers = data.passagers || [];
                        
                        console.log('Type de bateau:', bateau.type_bateau);
                        console.log('Est un bateau passager?', bateau.type_bateau && bateau.type_bateau.toLowerCase().includes('passager'));
                        console.log('Nombre de passagers:', passagers.length);
                        console.log('Détails des passagers:', passagers);
                        
                        document.getElementById('detailsContent').innerHTML = `
                            <div class="mb-6">
                                <h4 class="text-md font-semibold text-gray-900 mb-2">${bateau.nom} - ${bateau.immatriculation || ''}</h4>
                                <p class="text-sm text-gray-600">Capitaine: ${bateau.capitaine || '-'}</p>
                                <p class="text-sm text-gray-600">Agence: ${bateau.agence || '-'}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Type de Bateau</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${bateau.type_bateau || '-'}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date ${bateau.date_sortie ? "de Sortie" : "d'Entrée"}</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${new Date(bateau.date_sortie || bateau.date_entree).toLocaleString('fr-FR')}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Port Origine</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${bateau.port_origine || (bateau.statut==='sortie'?'BUJUMBURA':'N/A')}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Port Destination</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${bateau.port_destination || (bateau.statut==='entree'?'BUJUMBURA':'N/A')}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Hauteur (m)</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${(bateau.hauteur ?? '') !== '' ? bateau.hauteur : '-'}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Longueur (m)</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${(bateau.longueur ?? '') !== '' ? bateau.longueur : '-'}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Largeur (m)</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${(bateau.largeur ?? '') !== '' ? bateau.largeur : '-'}</div>
                                </div>
                            </div>
                            
                            ${
                                passagers.length > 0 ? `
                                <div class="mb-6">
                                    <h4 class="text-md font-semibold text-gray-900 mb-4">Passagers</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Passager</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poids Marchandises (kg)</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                ${(function() {
                                                    let totalPoids = 0;
                                                    const rows = passagers.map((p, index) => {
                                                        const poids = parseFloat(p.poids_total) || 0;
                                                        totalPoids += poids;
                                                        return `
                                                            <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                    Passager #${p.numero_passager}
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    ${poids.toFixed(2)} kg
                                                                </td>
                                                            </tr>`;
                                                    });
                                                    
                                                    // Ajouter la ligne de total
                                                    rows.push(`
                                                        <tr class="bg-gray-50 font-semibold">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                                TOTAL
                                                            </td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                ${totalPoids.toFixed(2)} kg
                                                            </td>
                                                        </tr>
                                                    `);
                                                    
                                                    return rows.join('');
                                                })()}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                ` : '<div class="mb-6"><p class="text-gray-500">Aucun passager enregistré</p></div>'
                            }
                            ${
                                marchandises.length > 0 ? `
                                <div class="mb-6">
                                    <h4 class="text-md font-semibold text-gray-900 mb-4">Marchandises</h4>
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poids (kg)</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                ${(function() {
                                                    let totalPoids = 0;
                                                    let totalQuantite = 0;
                                                    
                                                    const rows = marchandises.map((m, index) => {
                                                        const quantite = parseInt(m.quantite) || 0;
                                                        const poids = parseFloat(m.poids) || 0;
                                                        totalPoids += poids;
                                                        totalQuantite += quantite;
                                                        
                                                        return `
                                                            <tr class="${index % 2 === 0 ? 'bg-white' : 'bg-gray-50'}">
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${m.type_marchandise || 'Non spécifié'}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${quantite}</td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${poids.toFixed(2)} kg</td>
                                                            </tr>`;
                                                    });
                                                    
                                                    // Ajouter la ligne de total
                                                    rows.push(`
                                                        <tr class="bg-gray-50 font-semibold">
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">TOTAL</td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${totalQuantite}</td>
                                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${totalPoids.toFixed(2)} kg</td>
                                                        </tr>
                                                    `);
                                                    
                                                    return rows.join('');
                                                })()}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                ` : '<div class="mb-6"><p class="text-gray-500">Aucune marchandise enregistrée</p></div>'
                            }
                            
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeDetailsModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded hover:bg-gray-300">
                                    Fermer
                                </button>
                                
                            </div>
                        `;
                        
                        const modal = document.getElementById('detailsModal');
                        console.log('Modal élément:', modal);
                        if (modal) {
                            console.log('Affichage de la modale');
                            modal.classList.remove('hidden');
                            // Empêcher la fermeture lors du clic à l'intérieur de la modale
                            const modalContent = modal.querySelector('.bg-white');
                            if (modalContent) {
                                modalContent.addEventListener('click', (e) => {
                                    e.stopPropagation();
                                });
                            } else {
                                console.error('Contenu de la modale non trouvé');
                            }
                        } else {
                            console.error('Élément modal non trouvé');
                        }
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erreur',
                        text: "Erreur lors du chargement des détails du bateau"
                    });
                });
        }

        // Suppression de l'action "Marquer Sortie"; la sortie est gérée via un enregistrement de type sortie

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
    </script>
</body>
</html>

<?php /* Section supplémentaire: Bateaux sortis récemment */ ?>

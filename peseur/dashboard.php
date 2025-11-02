<?php
require_once '../includes/auth_check.php';
checkRole(['peseur']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Vérifier la présence de la colonne 'mouvement' dans pesages
$hasMouvementCol = false;
try {
    $col = $db->query("SHOW COLUMNS FROM pesages LIKE 'mouvement'")->fetch();
    $hasMouvementCol = $col ? true : false;
} catch (Exception $e) { /* ignore */ }

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] == '1');
    
    try {
        switch ($action) {
            case 'peser_camion':
                $camion_id = $_POST['camion_id'] ?? '';
                $ptav = (float)($_POST['ptav'] ?? 0);
                $ptac = (float)($_POST['ptac'] ?? 0);
                $ptra = (float)($_POST['ptra'] ?? 0);
                $charge_essieu = (float)($_POST['charge_essieu'] ?? 0);
                $destinataire = trim($_POST['destinataire'] ?? '');
                
                if ($camion_id && $ptav && $ptac && $ptra) {
                    if (!($ptac > $ptav)) {
                        throw new Exception("Contrôle refusé: PTAC doit être strictement supérieur au PTAV.");
                    }
                    if (!($ptra > $ptav && $ptra > $ptac)) {
                        throw new Exception("Contrôle refusé: PTRA doit être strictement supérieur au PTAV et au PTAC.");
                    }
                    // Mettre à jour le destinataire si fourni
                    if ($destinataire) {
                        $stmt = $db->prepare("UPDATE camions SET destinataire = ? WHERE id = ?");
                        $stmt->execute([$destinataire, $camion_id]);
                    }
                    
                    // Total poids marchandises
                    $total_poids_marchandises = 0;
                    if (isset($_POST['marchandises'])) {
                        foreach ($_POST['marchandises'] as $marchandiseId => $data) {
                            $quantite = isset($data['quantite']) ? (int)$data['quantite'] : 0;
                            // Normaliser les décimales pour le poids (accepte virgule)
                            $poidsRaw = isset($data['poids']) ? (string)$data['poids'] : '';
                            $poidsStr = str_replace(',', '.', $poidsRaw);
                            $poids = ($poidsStr !== '' && is_numeric($poidsStr)) ? (float)$poidsStr : 0;
                            if ($quantite > 0 || $poids > 0) {
                                $stmt = $db->prepare("UPDATE marchandises_camions SET quantite = ?, poids = ?, est_decharge = 0 WHERE id = ?");
                                $stmt->execute([$quantite ?: null, $poids ?: null, $marchandiseId]);
                                if ($poids > 0) {
                                    $total_poids_marchandises += $poids;
                                }
                            }
                        }
                    } else {
                        // Si aucune marchandise envoyée, recalculer depuis la base existante
                        $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(poids,0)),0) AS total FROM marchandises_camions WHERE camion_id = ?");
                        $stmt->execute([$camion_id]);
                        $row = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($row && isset($row['total'])) {
                            $total_poids_marchandises = (float)$row['total'];
                        }
                    }

                    // Vérifier s'il y a déjà un pesage pour ce camion
                    $stmt = $db->prepare("SELECT id FROM pesages WHERE camion_id = ? ORDER BY date_pesage DESC LIMIT 1");
                    $stmt->execute([$camion_id]);
                    $pesage = $stmt->fetch();
                    
                    if ($pesage) {
                        // Mettre à jour le pesage existant
                        $stmt = $db->prepare("UPDATE pesages SET ptav = ?, ptac = ?, ptra = ?, charge_essieu = ?, total_poids_marchandises = ?, surcharge = ? WHERE id = ?");
                        $chargeAutorisee = max($ptac - $ptav, 0);
                        $surcharge = ($ptac > 0 && $total_poids_marchandises > $chargeAutorisee) ? 1 : 0;
                        $stmt->execute([$ptav, $ptac, $ptra, $charge_essieu, $total_poids_marchandises, $surcharge, $pesage['id']]);
                    } else {
                        // Créer un nouveau pesage
                        if ($hasMouvementCol) {
                            $stmt = $db->prepare("INSERT INTO pesages (camion_id, ptav, ptac, ptra, charge_essieu, total_poids_marchandises, surcharge, mouvement) VALUES (?, ?, ?, ?, ?, ?, ?, 'entree')");
                        } else {
                            $stmt = $db->prepare("INSERT INTO pesages (camion_id, ptav, ptac, ptra, charge_essieu, total_poids_marchandises, surcharge) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        }
                        $chargeAutorisee = max($ptac - $ptav, 0);
                        $surcharge = ($ptac > 0 && $total_poids_marchandises > $chargeAutorisee) ? 1 : 0;
                        $stmt->execute([$camion_id, $ptav, $ptac, $ptra, $charge_essieu, $total_poids_marchandises, $surcharge]);
                    }
                    
                    // Décharger automatiquement toutes les marchandises après pesage
                    $stmt = $db->prepare("UPDATE marchandises_camions SET est_decharge = 1, date_dechargement = NOW() WHERE camion_id = ?");
                    $stmt->execute([$camion_id]);

                    // Mettre à jour le statut du camion pour permettre l'autorisation de sortie
                    $stmt = $db->prepare("UPDATE camions SET statut = 'en_attente_sortie' WHERE id = ?");
                    $stmt->execute([$camion_id]);
                    
                    // Récupérer l'immatriculation pour le log
                    $imm = null;
                    $s = $db->prepare("SELECT immatriculation FROM camions WHERE id = ?");
                    $s->execute([$camion_id]);
                    $r = $s->fetch();
                    if ($r) { $imm = $r['immatriculation']; }
                    $camionRef = $imm ?: (string)$camion_id;
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Pesage + Déchargement', "Pesage et déchargement auto du camion: $camionRef - PTAV: $ptav, PTAC: $ptac, Surcharge: " . ($surcharge ? 'Oui' : 'Non')]);
                    
                    $success = "Pesage enregistré et marchandises déchargées. Le camion est en attente de sortie.";
                }
                break;
                
            case 'decharger_camion':
                $camion_id = $_POST['camion_id'] ?? '';
                
                if ($camion_id) {
                    // Marquer toutes les marchandises comme déchargées
                    $stmt = $db->prepare("UPDATE marchandises_camions SET est_decharge = 1, date_dechargement = NOW() WHERE camion_id = ?");
                    $stmt->execute([$camion_id]);
                    
                    // Mettre à jour le statut du camion
                    $stmt = $db->prepare("UPDATE camions SET statut = 'en_attente_sortie' WHERE id = ?");
                    $stmt->execute([$camion_id]);
                    
                    // Récupérer l'immatriculation pour le log
                    $imm = null;
                    $s = $db->prepare("SELECT immatriculation FROM camions WHERE id = ?");
                    $s->execute([$camion_id]);
                    $r = $s->fetch();
                    if ($r) { $imm = $r['immatriculation']; }
                    $camionRef = $imm ?: (string)$camion_id;
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Déchargement Camion', "Marchandises déchargées pour le camion: $camionRef"]);
                    
                    $success = "Marchandises marquées comme déchargées avec succès";
                }
                break;
                
            case 'autoriser_sortie':
                $camion_id = $_POST['camion_id'] ?? '';
                $destinataire = trim($_POST['destinataire'] ?? '');
                $observations = trim($_POST['observations'] ?? '');
                $retour_vide = isset($_POST['retour_vide']) && $_POST['retour_vide'] == '1';
                
                if ($camion_id) {
                    // Mettre à jour les informations du camion
                    $stmt = $db->prepare("UPDATE camions SET statut = 'sortie', date_sortie = NOW(), destinataire = ?, observations_sortie = ?, retour_vide = ? WHERE id = ?");
                    $stmt->execute([$destinataire, $observations, $retour_vide ? 1 : 0, $camion_id]);
                    // S'assurer que la colonne 'mouvement' existe (ajout à la volée si nécessaire)
                    if (!$hasMouvementCol) {
                        try {
                            $db->exec("ALTER TABLE pesages ADD COLUMN mouvement ENUM('entree','sortie') NOT NULL DEFAULT 'entree' AFTER surcharge");
                            $hasMouvementCol = true;
                        } catch (Exception $e) { /* ignore if already exists or insufficient privileges */ }
                    }

                    // Marquer les marchandises sorties si déchargées
                    $stmt = $db->prepare("UPDATE marchandises_camions SET est_sorti = 1 WHERE camion_id = ? AND est_decharge = 1");
                    $stmt->execute([$camion_id]);

                    // Récupérer la dernière mesure si disponible
                    $last = $db->prepare("SELECT ptav, ptac, ptra, charge_essieu, total_poids_marchandises, surcharge FROM pesages WHERE camion_id = ? ORDER BY date_pesage DESC LIMIT 1");
                    $last->execute([$camion_id]);
                    $vals = $last->fetch(PDO::FETCH_ASSOC) ?: [
                        'ptav' => 0,
                        'ptac' => 0,
                        'ptra' => 0,
                        'charge_essieu' => 0,
                        'total_poids_marchandises' => 0,
                        'surcharge' => 0,
                    ];

                    // Enregistrer systématiquement un pesage de sortie avec logs détaillés
                    try {
                        if ($hasMouvementCol) {
                            $ins = $db->prepare("INSERT INTO pesages (camion_id, ptav, ptac, ptra, charge_essieu, total_poids_marchandises, surcharge, mouvement, date_pesage) VALUES (?, ?, ?, ?, ?, ?, ?, 'sortie', NOW())");
                            $ok = $ins->execute([
                                $camion_id,
                                (float)$vals['ptav'],
                                (float)$vals['ptac'],
                                (float)$vals['ptra'],
                                (float)$vals['charge_essieu'],
                                (float)$vals['total_poids_marchandises'],
                                (int)$vals['surcharge'],
                            ]);
                        } else {
                            // Si la colonne n'existe vraiment pas, on insère sans mouvement pour ne pas bloquer
                            $ins = $db->prepare("INSERT INTO pesages (camion_id, ptav, ptac, ptra, charge_essieu, total_poids_marchandises, surcharge, date_pesage) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                            $ok = $ins->execute([
                                $camion_id,
                                (float)$vals['ptav'],
                                (float)$vals['ptac'],
                                (float)$vals['ptra'],
                                (float)$vals['charge_essieu'],
                                (float)$vals['total_poids_marchandises'],
                                (int)$vals['surcharge'],
                            ]);
                        }
                        $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                        $logStmt->execute([
                            $user['id'],
                            'Pesage Sortie',
                            'Insertion pesage sortie pour camion ID ' . $camion_id . ' - status=' . ($ok ? 'OK' : 'FAIL')
                        ]);
                    } catch (PDOException $e) {
                        $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                        $logStmt->execute([
                            $user['id'],
                            'Pesage Sortie ERREUR',
                            'Erreur PDO: ' . $e->getMessage()
                        ]);
                    }
                    
                    // Récupérer l'immatriculation pour le log
                    $imm = null;
                    $s = $db->prepare("SELECT immatriculation FROM camions WHERE id = ?");
                    $s->execute([$camion_id]);
                    $r = $s->fetch();
                    if ($r) { $imm = $r['immatriculation']; }
                    $camionRef = $imm ?: (string)$camion_id;
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([
                        $user['id'], 
                        'Autorisation Sortie', 
                        "Autorisation de sortie pour camion: $camionRef - " . 
                        "Destinataire: $destinataire, " . 
                        ($retour_vide ? 'Retour à vide' : 'Avec chargement')
                    ]);
                    
                    $success = "Sortie autorisée avec succès";
                }
                break;
        }
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => isset($success) ? $success : 'OK']);
            exit();
        }
        header('Location: dashboard.php');
        exit();
    } catch (Exception $e) {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit();
        }
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupération des données
// Camions en attente de pesage
$camions_attente = $db->query("
    SELECT 
        c.*, 
        tc.nom as type_camion, 
        p.ptav, 
        p.ptac, 
        p.ptra, 
        p.charge_essieu, 
        p.date_pesage,
        (SELECT COUNT(*) FROM marchandises_camions mc WHERE mc.camion_id = c.id AND mc.est_decharge = 1) as nb_marchandises_dechargees,
        (SELECT COUNT(*) FROM marchandises_camions mc WHERE mc.camion_id = c.id) as total_marchandises
    FROM camions c 
    LEFT JOIN types_camions tc ON c.type_camion_id = tc.id
    LEFT JOIN (
        SELECT camion_id, ptav, ptac, ptra, charge_essieu, date_pesage
        FROM pesages
        WHERE (camion_id, date_pesage) IN (
            SELECT camion_id, MAX(date_pesage)
            FROM pesages
            GROUP BY camion_id
        )
    ) p ON c.id = p.camion_id
    WHERE c.statut IN ('entree', 'en_pesage', 'en_attente_sortie')
    ORDER BY 
        CASE 
            WHEN c.statut = 'en_attente_sortie' THEN 0
            WHEN c.statut = 'en_pesage' THEN 1
            ELSE 2
        END,
        c.date_entree ASC
")->fetchAll();

// Statistiques
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE statut = 'entree'");
$stats['en_attente'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE statut = 'en_pesage'");
$stats['en_pesage'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM pesages WHERE DATE(date_pesage) = CURDATE()");
$stats['peses_aujourdhui'] = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Peseur - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="js/dashboard.js?v=5"></script>
    <script>
        window.peserCamion = window.peserCamion || function(id){ if (typeof peserCamion === 'function') { return peserCamion(id); } };
    </script>
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
                <p class="text-blue-300 text-sm font-medium">Peseur</p>
            </div>
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-white bg-blue-800">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="historique.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
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
                        <p class="text-sm text-gray-500">Peseur</p>
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
                <h1 class="text-3xl font-bold text-gray-900">Dashboard Peseur</h1>
                <p class="text-gray-600 mt-2">Gestion des pesages et autorisations de sortie</p>
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

            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                            <i class="fas fa-truck-loading text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">En attente de pesage</p>
                            <p class="text-2xl font-bold"><?= $stats['en_attente'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600 mr-4">
                            <i class="fas fa-weight-hanging text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">En cours de pesage</p>
                            <p class="text-2xl font-bold"><?= $stats['en_pesage'] ?></p>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div>
                            <p class="text-gray-500 text-sm">Pesages aujourd'hui</p>
                            <p class="text-2xl font-bold"><?= $stats['peses_aujourdhui'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Liste des camions -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Camions Chargés - Pesage</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Camion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chauffeur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date d'entrée</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($camions_attente)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Aucun camion en attente de pesage</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($camions_attente as $camion): 
                                    $statut_class = '';
                                    $statut_text = '';
                                    $actions = [];

                                    if ($camion['statut'] === 'en_attente_sortie') {
                                        $statut_class = 'bg-yellow-100 text-yellow-800';
                                        $statut_text = 'En attente de sortie';
                                        $actions = [
                                            'voir' => ['icon' => 'eye', 'class' => 'text-blue-600 hover:text-blue-900', 'onclick' => "voirDetails('{$camion['id']}')"],
                                            'autoriser' => ['icon' => 'check-circle', 'class' => 'text-green-600 hover:text-green-900', 'onclick' => "autoriserSortie('{$camion['id']}')"]
                                        ];
                                    } elseif ($camion['statut'] === 'en_pesage') {
                                        $statut_class = 'bg-blue-100 text-blue-800';
                                        $statut_text = 'En cours de pesage';
                                        $actions = [
                                            'voir' => ['icon' => 'eye', 'class' => 'text-blue-600 hover:text-blue-900', 'onclick' => "voirDetails('{$camion['id']}')"],
                                            'peser' => ['icon' => 'weight', 'class' => 'text-indigo-600 hover:text-indigo-900', 'onclick' => "peserCamion('{$camion['id']}')"]
                                        ];
                                    } else {
                                        $statut_class = 'bg-gray-100 text-gray-800';
                                        $statut_text = 'En attente';
                                        $actions = [
                                            'voir' => ['icon' => 'eye', 'class' => 'text-blue-600 hover:text-blue-900', 'onclick' => "voirDetails('{$camion['id']}')"],
                                            'peser' => ['icon' => 'weight', 'class' => 'text-indigo-600 hover:text-indigo-900', 'onclick' => "peserCamion('{$camion['id']}')"]
                                        ];
                                    }
                                ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                <i class="fas fa-truck text-blue-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900"><?= isset($camion['immatriculation']) ? htmlspecialchars((string)$camion['immatriculation']) : 'N/A' ?></div>
                                                <div class="text-sm text-gray-500"><?= isset($camion['type_camion']) ? htmlspecialchars((string)$camion['type_camion']) : 'N/A' ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?= isset($camion['chauffeur']) ? htmlspecialchars((string)$camion['chauffeur']) : 'N/A' ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $statut_class ?>">
                                            <?= $statut_text ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($camion['date_entree'])) ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex space-x-3">
                                            <?php foreach ($actions as $action): ?>
                                                <button onclick="<?= $action['onclick'] ?>" class="<?= $action['class'] ?> hover:bg-gray-100 p-1 rounded">
                                                    <i class="fas fa-<?= $action['icon'] ?>"></i>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Pesage -->
    <div id="pesageModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <h3 class="text-lg font-semibold text-gray-900">Pesage du Camion</h3>
                </div>
                
                <div id="pesageContent" class="p-6 overflow-y-auto flex-grow">
                    <!-- Contenu dynamique -->
                </div>

                
            </div>
        </div>
    </div>
    
    <style>
        /* Styles pour le défilement personnalisé */
        #pesageContent::-webkit-scrollbar {
            width: 8px;
        }
        #pesageContent::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        #pesageContent::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }
        #pesageContent::-webkit-scrollbar-thumb:hover {
            background: #a0aec0;
        }
    </style>
</body>
</html>

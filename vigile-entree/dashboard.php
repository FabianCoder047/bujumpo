<?php
require_once '../includes/auth_check.php';
checkRole(['EnregistreurEntreeRoute']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_camion':
                $type_camion_id = $_POST['type_camion_id'] ?? '';
                $marque = trim($_POST['marque'] ?? '');
                $immatriculation = trim($_POST['immatriculation'] ?? '');
                // Normaliser l'immatriculation (trim + majuscules)
                if ($immatriculation !== '') { $immatriculation = strtoupper($immatriculation); }
                $chauffeur = trim($_POST['chauffeur'] ?? '');
                $agence = trim($_POST['agence'] ?? '');
                $provenance_port_id = $_POST['provenance_port_id'] ?? '';
                $destinataire = trim($_POST['destinataire'] ?? '');
                $t1 = trim($_POST['t1'] ?? '');
                $est_charge = isset($_POST['est_charge']) ? 1 : 0;
                
                if ($type_camion_id && $marque && $immatriculation && $chauffeur && $agence) {
                    // Vérifier si un camion avec la même immatriculation est déjà à l'intérieur (date_sortie NULL)
                    $chk = $db->prepare("SELECT id, date_sortie FROM camions WHERE immatriculation = ? ORDER BY date_entree DESC LIMIT 1");
                    $chk->execute([$immatriculation]);
                    $ex = $chk->fetch(PDO::FETCH_ASSOC);
                    if ($ex && empty($ex['date_sortie'])) {
                        $error = "Ce camion (" . htmlspecialchars($immatriculation) . ") est déjà enregistré et n'est pas encore sorti.";
                        break;
                    }
                    // Vérifier d'abord si les colonnes existent
                    try {
                        $stmt = $db->query("SHOW COLUMNS FROM camions LIKE 'provenance_port_id'");
                        $has_provenance = $stmt->fetch() !== false;
                        
                        if ($has_provenance) {
                            $stmt = $db->prepare("INSERT INTO camions (type_camion_id, marque, immatriculation, chauffeur, agence, provenance_port_id, destinataire, t1, est_charge) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$type_camion_id, $marque, $immatriculation, $chauffeur, $agence, $provenance_port_id ?: null, $destinataire ?: null, $t1 ?: null, $est_charge]);
                        } else {
                            $stmt = $db->prepare("INSERT INTO camions (type_camion_id, marque, immatriculation, chauffeur, agence, est_charge) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$type_camion_id, $marque, $immatriculation, $chauffeur, $agence, $est_charge]);
                        }
                    } catch (Exception $e) {
                        // Si erreur, utiliser la requête simple
                        $stmt = $db->prepare("INSERT INTO camions (type_camion_id, marque, immatriculation, chauffeur, agence, est_charge) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$type_camion_id, $marque, $immatriculation, $chauffeur, $agence, $est_charge]);
                    }
                    $camionId = $db->lastInsertId();
                    
                    // Si le camion est chargé, ajouter les marchandises (vérification doublons)
                    if ($est_charge && isset($_POST['marchandises'])) {
                        $marchandises = $_POST['marchandises'];
                        $types_utilises = [];
                        foreach ($marchandises as $marchandise) {
                            if (!empty($marchandise['type_id'])) {
                                $type_id = (int)$marchandise['type_id'];
                                if (!in_array($type_id, $types_utilises)) {
                                    $types_utilises[] = $type_id;
                                    $stmt = $db->prepare("INSERT INTO marchandises_camions (camion_id, type_marchandise_id, mouvement) VALUES (?, ?, 'entree')");
                                    $stmt->execute([$camionId, $type_id]);
                                }
                            }
                        }
                    }
                    
                    // Récupérer le nom du port pour le log si fourni
                    $portNom = null;
                    if (!empty($provenance_port_id)) {
                        $pstmt = $db->prepare("SELECT nom FROM ports WHERE id = ?");
                        $pstmt->execute([$provenance_port_id]);
                        $prow = $pstmt->fetch();
                        $portNom = $prow ? $prow['nom'] : null;
                    }
                    $provenanceLog = $portNom ? $portNom : '-';
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Enregistrement Camion', "Camion enregistré: $immatriculation - Provenance: $provenanceLog, Destinataire: $destinataire, T1: $t1"]);
                    
                    $success = "Camion enregistré avec succès";
                }
                break;

            case 'update_camion':
                $camion_id = $_POST['camion_id'] ?? '';
                $type_camion_id = $_POST['type_camion_id'] ?? '';
                $marque = trim($_POST['marque'] ?? '');
                $immatriculation = trim($_POST['immatriculation'] ?? '');
                $chauffeur = trim($_POST['chauffeur'] ?? '');
                $agence = trim($_POST['agence'] ?? '');
                $provenance_port_id = $_POST['provenance_port_id'] ?? '';
                $destinataire = trim($_POST['destinataire'] ?? '');
                $t1 = trim($_POST['t1'] ?? '');
                $est_charge = isset($_POST['est_charge']) ? 1 : 0;

                if ($camion_id && $type_camion_id && $marque && $immatriculation && $chauffeur && $agence) {
                    // Vérifier si les colonnes existent pour la mise à jour
                    try {
                        $stmt = $db->query("SHOW COLUMNS FROM camions LIKE 'provenance_port_id'");
                        $has_provenance = $stmt->fetch() !== false;
                        
                        if ($has_provenance) {
                            $stmt = $db->prepare("UPDATE camions SET type_camion_id = ?, marque = ?, immatriculation = ?, chauffeur = ?, agence = ?, provenance_port_id = ?, destinataire = ?, t1 = ?, est_charge = ? WHERE id = ?");
                            $stmt->execute([$type_camion_id, $marque, $immatriculation, $chauffeur, $agence, $provenance_port_id ?: null, $destinataire ?: null, $t1 ?: null, $est_charge, $camion_id]);
                        } else {
                            $stmt = $db->prepare("UPDATE camions SET type_camion_id = ?, marque = ?, immatriculation = ?, chauffeur = ?, agence = ?, est_charge = ? WHERE id = ?");
                            $stmt->execute([$type_camion_id, $marque, $immatriculation, $chauffeur, $agence, $est_charge, $camion_id]);
                        }
                    } catch (Exception $e) {
                        // Si erreur, utiliser la requête simple
                        $stmt = $db->prepare("UPDATE camions SET type_camion_id = ?, marque = ?, immatriculation = ?, chauffeur = ?, agence = ?, est_charge = ? WHERE id = ?");
                        $stmt->execute([$type_camion_id, $marque, $immatriculation, $chauffeur, $agence, $est_charge, $camion_id]);
                    }

                    // Mettre à jour les marchandises (simple stratégie: effacer puis recréer si est_charge)
                    $del = $db->prepare("DELETE FROM marchandises_camions WHERE camion_id = ?");
                    $del->execute([$camion_id]);
                    if ($est_charge && isset($_POST['marchandises'])) {
                        $types_utilises = [];
                        foreach ($_POST['marchandises'] as $m) {
                            if (!empty($m['type_id'])) {
                                $type_id = (int)$m['type_id'];
                                if (!in_array($type_id, $types_utilises)) {
                                    $types_utilises[] = $type_id;
                                    $ins = $db->prepare("INSERT INTO marchandises_camions (camion_id, type_marchandise_id, mouvement) VALUES (?, ?, 'entree')");
                                    $ins->execute([$camion_id, $type_id]);
                                }
                            }
                        }
                    }

                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Modification Camion', "Mise à jour du camion: $immatriculation"]);

                    $success = "Camion mis à jour avec succès";
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
$types_camions = $db->query("SELECT * FROM types_camions ORDER BY nom")->fetchAll();
$types_marchandises = $db->query("SELECT * FROM types_marchandises ORDER BY nom")->fetchAll();

// Camions récents
$camions_recents = $db->query("
    SELECT c.*, tc.nom as type_camion,
           p.nom as nom_port,
           COUNT(mc.id) as nb_marchandises
    FROM camions c 
    LEFT JOIN types_camions tc ON c.type_camion_id = tc.id
    LEFT JOIN marchandises_camions mc ON c.id = mc.camion_id
    LEFT JOIN ports p ON c.provenance_port_id = p.id
    WHERE c.date_entree >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY c.id
    ORDER BY c.date_entree DESC
    LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Vigile Entrée - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-green-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-green-800">
            <i class="fas fa-anchor text-2xl mr-2"></i>
            <span class="text-xl font-bold">Port de BUJUMBURA</span>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <p class="text-green-300 text-sm font-medium">Enregistreur Entrée Route</p>
            </div>
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-white bg-green-800">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="enregistrer.php" class="flex items-center px-4 py-3 text-green-200 hover:bg-green-800 hover:text-white transition duration-200">
                <i class="fas fa-plus mr-3"></i>
                Enregistrer Camion
            </a>
            
            <a href="historique.php" class="flex items-center px-4 py-3 text-green-200 hover:bg-green-800 hover:text-white transition duration-200">
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
                        <p class="text-sm text-gray-500">Enregistreur Entrée Route</p>
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
                <h1 class="text-3xl font-bold text-gray-900">Dashboard Enregistreur Entrée Route</h1>
                <p class="text-gray-600 mt-2">Enregistrement des camions entrant au port</p>
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
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-truck text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Entrées Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php 
                                $stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE DATE(date_entree) = CURDATE()");
                                echo $stmt->fetch()['total'];
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-weight-hanging text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Camions Chargés</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php 
                                $stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE est_charge = 1 AND DATE(date_entree) = CURDATE()");
                                echo $stmt->fetch()['total'];
                                ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">En Attente de Pesage</p>
                            <p class="text-2xl font-semibold text-gray-900">
                                <?php 
                                $stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE est_charge = 1 AND statut = 'entree'");
                                echo $stmt->fetch()['total'];
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions rapides -->
            <div class="bg-white rounded-lg shadow p-6 mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Actions Rapides</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a href="enregistrer.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <i class="fas fa-truck text-green-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Enregistrer un Camion</p>
                            <p class="text-sm text-gray-600">Nouvelle entrée au port</p>
                        </div>
                    </a>
                    
                    <a href="historique.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-gray-50 transition duration-200">
                        <i class="fas fa-list text-blue-600 text-xl mr-3"></i>
                        <div>
                            <p class="font-medium text-gray-900">Voir l'Historique</p>
                            <p class="text-sm text-gray-600">Tous les camions enregistrés</p>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Camions récents -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Entrées Récentes (24h)</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-[1200px] divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Camion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provenance</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destinataire</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">T1</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chauffeur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chargé</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Heure</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($camions_recents as $camion): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($camion['marque']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($camion['immatriculation']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($camion['type_camion']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= !empty($camion['nom_port']) ? htmlspecialchars($camion['nom_port']) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= isset($camion['destinataire']) ? htmlspecialchars($camion['destinataire']) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= isset($camion['t1']) ? htmlspecialchars($camion['t1']) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($camion['chauffeur']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        <?= $camion['est_charge'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                        <?= $camion['est_charge'] ? 'Oui (' . $camion['nb_marchandises'] . ')' : 'Non' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('H:i', strtotime($camion['date_entree'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                        <?= $camion['statut'] === 'entree' ? 'bg-yellow-100 text-yellow-800' : 
                                            ($camion['statut'] === 'en_pesage' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') ?>">
                                        <?= ucfirst($camion['statut']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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

<?php
require_once '../includes/auth_check.php';
checkRole(['EnregistreurBateaux']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Chercher l'ID du port de BUJUMBURA pour préaffectation masquée
$bujumburaId = null;
try {
    $stmtPort = $db->prepare("SELECT id FROM ports WHERE UPPER(nom) = UPPER(?) LIMIT 1");
    $stmtPort->execute(['BUJUMBURA']);
    $rowPort = $stmtPort->fetch();
    if ($rowPort) {
        $bujumburaId = (int)$rowPort['id'];
    }
} catch (Exception $e) {
    // silencieux: si introuvable, l'UI ne préremplira pas mais le submit traitera la logique
}

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
                $agence = trim($_POST['agence'] ?? '');
                $hauteur = $_POST['hauteur'] !== '' ? $_POST['hauteur'] : null;
                $longueur = $_POST['longueur'] !== '' ? $_POST['longueur'] : null;
                $largeur = $_POST['largeur'] !== '' ? $_POST['largeur'] : null;
                $port_origine_id = $_POST['port_origine_id'] ?? '';
                $port_destination_id = $_POST['port_destination_id'] ?? '';
                $statut = $_POST['statut'] ?? 'entree';
                
                // Immatriculation facultative -> convertir vide en NULL
                $immatriculationValue = $immatriculation !== '' ? $immatriculation : null;

                // Règle provenance/destination selon le mouvement avec BUJUMBURA masqué
                if ($statut === 'entree') {
                    // Entrée: destination = BUJUMBURA
                    if (empty($bujumburaId)) {
                        // chercher dynamiquement au cas où non chargé en haut
                        $tmp = $db->prepare("SELECT id FROM ports WHERE UPPER(nom)=UPPER(?) LIMIT 1");
                        $tmp->execute(['BUJUMBURA']);
                        $row = $tmp->fetch();
                        $bujumburaId = $row ? (int)$row['id'] : null;
                    }
                    $port_destination_id = $bujumburaId;
                    // on ne tient compte que de la provenance saisie
                } else {
                    // Sortie: provenance = BUJUMBURA
                    if (empty($bujumburaId)) {
                        $tmp = $db->prepare("SELECT id FROM ports WHERE UPPER(nom)=UPPER(?) LIMIT 1");
                        $tmp->execute(['BUJUMBURA']);
                        $row = $tmp->fetch();
                        $bujumburaId = $row ? (int)$row['id'] : null;
                    }
                    $port_origine_id = $bujumburaId;
                    // on ne tient compte que de la destination saisie
                }

                if ($type_bateau_id && $nom && $capitaine) {
                    if ($longueur !== null && $largeur !== null) {
                        $__L = (float)$longueur; $__l = (float)$largeur; if (!($__L > $__l)) { throw new Exception("La longueur doit être strictement supérieure à la largeur."); }
                    }
                    try {
                        $stmt = $db->prepare("INSERT INTO bateaux (type_bateau_id, nom, immatriculation, capitaine, agence, hauteur, longueur, largeur, port_origine_id, port_destination_id, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$type_bateau_id, $nom, $immatriculationValue, $capitaine, $agence !== '' ? $agence : null, $hauteur, $longueur, $largeur, $port_origine_id !== '' ? $port_origine_id : null, $port_destination_id !== '' ? $port_destination_id : null, $statut]);
                    } catch (PDOException $e) {
                        if ($e->getCode() === '23000' && strpos($e->getMessage(), '1062') !== false) {
                            try {
                                $idx = $db->query("SHOW INDEX FROM bateaux WHERE Column_name = 'immatriculation' AND Non_unique = 0")->fetch(PDO::FETCH_ASSOC);
                                if ($idx && !empty($idx['Key_name'])) {
                                    $db->exec("ALTER TABLE bateaux DROP INDEX `" . $idx['Key_name'] . "`");
                                }
                                // Retry with the same immatriculation (duplicates now allowed)
                                $stmt = $db->prepare("INSERT INTO bateaux (type_bateau_id, nom, immatriculation, capitaine, agence, hauteur, longueur, largeur, port_origine_id, port_destination_id, statut) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $stmt->execute([$type_bateau_id, $nom, $immatriculationValue, $capitaine, $agence !== '' ? $agence : null, $hauteur, $longueur, $largeur, $port_origine_id !== '' ? $port_origine_id : null, $port_destination_id !== '' ? $port_destination_id : null, $statut]);
                            } catch (Exception $e2) {
                                throw $e; // if unable to drop index, bubble original error
                            }
                        } else {
                            throw $e;
                        }
                    }
                    $bateauId = $db->lastInsertId();

                    // Renseigner la date correspondante selon le mouvement
                    if ($statut === 'sortie') {
                        // Pour une création en sortie, ne pas renseigner de date d'entrée
                        $stmt = $db->prepare("UPDATE bateaux SET date_sortie = NOW(), date_entree = NULL WHERE id = ?");
                        $stmt->execute([$bateauId]);
                    } else {
                        $stmt = $db->prepare("UPDATE bateaux SET date_entree = NOW() WHERE id = ?");
                        $stmt->execute([$bateauId]);
                    }
                    
                    // Si des marchandises sont spécifiées, les ajouter (sans doublons)
                    if (isset($_POST['marchandises'])) {
                        $types_deja_ajoutes = [];
                        foreach ($_POST['marchandises'] as $marchandise) {
                            if (!empty($marchandise['type_id']) && !empty($marchandise['poids'])) {
                                $typeId = (int)$marchandise['type_id'];
                                if (!in_array($typeId, $types_deja_ajoutes)) {
                                    $stmt = $db->prepare("INSERT INTO marchandises_bateaux (bateau_id, type_marchandise_id, mouvement, poids, quantite) VALUES (?, ?, ?, ?, ?)");
                                    $stmt->execute([$bateauId, $typeId, $statut, $marchandise['poids'], $marchandise['quantite'] ?? 1]);
                                    $types_deja_ajoutes[] = $typeId;
                                }
                            }
                        }
                    }

                    // Si passagers sont spécifiés (type passager)
                    if (isset($_POST['passagers']) && is_array($_POST['passagers'])) {
                        $num = 1;
                        foreach ($_POST['passagers'] as $passager) {
                            if ($passager['poids'] !== '' && $passager['poids'] !== null) {
                                $stmt = $db->prepare("INSERT INTO passagers_bateaux (bateau_id, numero_passager, poids_marchandises) VALUES (?, ?, ?)");
                                $stmt->execute([$bateauId, $num, $passager['poids']]);
                            }
                            $num++;
                        }
                    }
                    
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Enregistrement Bateau', "Bateau enregistré: $nom ($statut)"]);
                    
                    $success = "Bateau enregistré avec succès";
                }
                break;
                
            case 'update_bateau':
                $bateau_id = (int)($_POST['bateau_id'] ?? 0);
                $type_bateau_id = $_POST['type_bateau_id'] ?? '';
                $nom = trim($_POST['nom'] ?? '');
                $immatriculation = trim($_POST['immatriculation'] ?? '');
                $capitaine = trim($_POST['capitaine'] ?? '');
                $agence = trim($_POST['agence'] ?? '');
                $hauteur = $_POST['hauteur'] !== '' ? $_POST['hauteur'] : null;
                $longueur = $_POST['longueur'] !== '' ? $_POST['longueur'] : null;
                $largeur = $_POST['largeur'] !== '' ? $_POST['largeur'] : null;
                $port_origine_id = $_POST['port_origine_id'] ?? '';
                $port_destination_id = $_POST['port_destination_id'] ?? '';
                $nouveau_statut = $_POST['statut'] ?? 'entree';
                
                if ($bateau_id && $type_bateau_id && $nom && $capitaine) {
                    if ($longueur !== null && $largeur !== null) {
                        $__L = (float)$longueur; $__l = (float)$largeur; if (!($__L > $__l)) { throw new Exception("La longueur doit être strictement supérieure à la largeur."); }
                    }
                    // Règle provenance/destination selon le mouvement basé sur le nouveau statut
                    if ($nouveau_statut === 'entree') {
                        if (empty($bujumburaId)) {
                            $tmp = $db->prepare("SELECT id FROM ports WHERE UPPER(nom)=UPPER(?) LIMIT 1");
                            $tmp->execute(['BUJUMBURA']);
                            $row = $tmp->fetch();
                            $bujumburaId = $row ? (int)$row['id'] : null;
                        }
                        $port_destination_id = $bujumburaId;
                    } else if ($nouveau_statut === 'sortie') {
                        if (empty($bujumburaId)) {
                            $tmp = $db->prepare("SELECT id FROM ports WHERE UPPER(nom)=UPPER(?) LIMIT 1");
                            $tmp->execute(['BUJUMBURA']);
                            $row = $tmp->fetch();
                            $bujumburaId = $row ? (int)$row['id'] : null;
                        }
                        $port_origine_id = $bujumburaId;
                    }
                    
                    // Mettre à jour le bateau
                    $immatriculationValue = $immatriculation !== '' ? $immatriculation : null;
                    try {
                        $stmt = $db->prepare("UPDATE bateaux SET type_bateau_id = ?, nom = ?, immatriculation = ?, capitaine = ?, agence = ?, hauteur = ?, longueur = ?, largeur = ?, port_origine_id = ?, port_destination_id = ?, statut = ? WHERE id = ?");
                        $stmt->execute([$type_bateau_id, $nom, $immatriculationValue, $capitaine, $agence !== '' ? $agence : null, $hauteur, $longueur, $largeur, $port_origine_id !== '' ? $port_origine_id : null, $port_destination_id !== '' ? $port_destination_id : null, $nouveau_statut, $bateau_id]);
                    } catch (PDOException $e) {
                        if ($e->getCode() === '23000' && strpos($e->getMessage(), '1062') !== false) {
                            try {
                                $idx = $db->query("SHOW INDEX FROM bateaux WHERE Column_name = 'immatriculation' AND Non_unique = 0")->fetch(PDO::FETCH_ASSOC);
                                if ($idx && !empty($idx['Key_name'])) {
                                    $db->exec("ALTER TABLE bateaux DROP INDEX `" . $idx['Key_name'] . "`");
                                }
                                // Retry update with same immatriculation
                                $stmt = $db->prepare("UPDATE bateaux SET type_bateau_id = ?, nom = ?, immatriculation = ?, capitaine = ?, agence = ?, hauteur = ?, longueur = ?, largeur = ?, port_origine_id = ?, port_destination_id = ?, statut = ? WHERE id = ?");
                                $stmt->execute([$type_bateau_id, $nom, $immatriculationValue, $capitaine, $agence !== '' ? $agence : null, $hauteur, $longueur, $largeur, $port_origine_id !== '' ? $port_origine_id : null, $port_destination_id !== '' ? $port_destination_id : null, $nouveau_statut, $bateau_id]);
                            } catch (Exception $e2) {
                                throw $e;
                            }
                        } else {
                            throw $e;
                        }
                    }

                    // Mettre à jour les dates selon le nouveau statut
                    if ($nouveau_statut === 'sortie') {
                        $stmt = $db->prepare("UPDATE bateaux SET date_sortie = NOW() WHERE id = ?");
                        $stmt->execute([$bateau_id]);
                    } else {
                        $stmt = $db->prepare("UPDATE bateaux SET date_entree = NOW(), date_sortie = NULL WHERE id = ?");
                        $stmt->execute([$bateau_id]);
                    }
                    
                    // Supprimer les anciennes marchandises et passagers
                    $stmt = $db->prepare("DELETE FROM marchandises_bateaux WHERE bateau_id = ?");
                    $stmt->execute([$bateau_id]);
                    $stmt = $db->prepare("DELETE FROM passagers_bateaux WHERE bateau_id = ?");
                    $stmt->execute([$bateau_id]);
                    
                    // Ajouter les nouvelles marchandises (sans doublons)
                    if (isset($_POST['marchandises'])) {
                        $types_deja_ajoutes = [];
                        foreach ($_POST['marchandises'] as $marchandise) {
                            if (!empty($marchandise['type_id']) && !empty($marchandise['poids'])) {
                                $typeId = (int)$marchandise['type_id'];
                                if (!in_array($typeId, $types_deja_ajoutes)) {
                                    // Récupérer le statut actuel du bateau pour définir le mouvement
                                    $stmtStat = $db->prepare("SELECT statut FROM bateaux WHERE id = ?");
                                    $stmtStat->execute([$bateau_id]);
                                    $bateauStatut = $stmtStat->fetchColumn() ?: 'entree';
                                    $stmt = $db->prepare("INSERT INTO marchandises_bateaux (bateau_id, type_marchandise_id, mouvement, poids, quantite) VALUES (?, ?, ?, ?, ?)");
                                    $stmt->execute([$bateau_id, $typeId, $bateauStatut, $marchandise['poids'], $marchandise['quantite'] ?? 1]);
                                    $types_deja_ajoutes[] = $typeId;
                                }
                            }
                        }
                    }
                    
                    // Ajouter les nouveaux passagers
                    if (isset($_POST['passagers']) && is_array($_POST['passagers'])) {
                        $num = 1;
                        foreach ($_POST['passagers'] as $passager) {
                            if ($passager['poids'] !== '' && $passager['poids'] !== null) {
                                $stmt = $db->prepare("INSERT INTO passagers_bateaux (bateau_id, numero_passager, poids_marchandises) VALUES (?, ?, ?)");
                                $stmt->execute([$bateau_id, $num, $passager['poids']]);
                            }
                            $num++;
                        }
                    }
                    
                    $ref = $immatriculationValue ? "$nom - $immatriculationValue" : $nom;
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Modification Bateau', "Bateau modifié: $ref"]);
                    
                    $success = "Bateau modifié avec succès";
                    header('Location: dashboard.php');
                    exit();
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

// Récupération des données du bateau à modifier
$bateau_edit = null;
$marchandises_edit = [];
$passagers_edit = [];
if (isset($_GET['bateau_id']) && !empty($_GET['bateau_id'])) {
    $bateau_id = (int)$_GET['bateau_id'];
    
    // Récupérer les données du bateau (entrée ou sortie)
    $stmt = $db->prepare("SELECT * FROM bateaux WHERE id = ?");
    $stmt->execute([$bateau_id]);
    $bateau_edit = $stmt->fetch();
    
    if ($bateau_edit) {
        // Récupérer les marchandises existantes
        $stmt = $db->prepare("SELECT mb.*, tm.nom as type_marchandise FROM marchandises_bateaux mb LEFT JOIN types_marchandises tm ON mb.type_marchandise_id = tm.id WHERE mb.bateau_id = ?");
        $stmt->execute([$bateau_id]);
        $marchandises_edit = $stmt->fetchAll();
        
        // Récupérer les passagers existants
        $stmt = $db->prepare("SELECT * FROM passagers_bateaux WHERE bateau_id = ? ORDER BY numero_passager");
        $stmt->execute([$bateau_id]);
        $passagers_edit = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrer Bateau - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100" <?= $bujumburaId ? 'data-bujumbura-id="' . htmlspecialchars((string)$bujumburaId) . '"' : '' ?> >
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
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-purple-200 hover:bg-purple-800 hover:text-white transition duration-200">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="enregistrer.php" class="flex items-center px-4 py-3 text-white bg-purple-800">
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
                <h1 class="text-3xl font-bold text-gray-900">Enregistrer un Bateau</h1>
                <p class="text-gray-600 mt-2">Enregistrer l'entrée ou la sortie d'un bateau</p>
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

            <div class="bg-white rounded-lg shadow p-6">
                <form id="bateauForm" method="POST">
                    <input type="hidden" name="action" value="<?= $bateau_edit ? 'update_bateau' : 'add_bateau' ?>">
                    <?php if ($bateau_edit): ?>
                    <input type="hidden" name="bateau_id" value="<?= $bateau_edit['id'] ?>">
                    <?php endif; ?>
                    
                    <!-- Type de mouvement -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Type de Mouvement</h3>
                        <div class="flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" name="statut" value="entree" <?= !$bateau_edit || $bateau_edit['statut'] === 'entree' ? 'checked' : '' ?> class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300" onchange="toggleMarchandises()">
                                <span class="ml-2 text-sm text-gray-900">Entrée</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="statut" value="sortie" <?= $bateau_edit && $bateau_edit['statut'] === 'sortie' ? 'checked' : '' ?> class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300" onchange="toggleMarchandises()">
                                <span class="ml-2 text-sm text-gray-900">Sortie</span>
                            </label>
                        </div>
                        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <label id="entree_vide_wrapper" class="flex items-center">
                                <input type="checkbox" id="entree_vide" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300" onchange="updateVideUI()">
                                <span class="ml-2 text-sm text-gray-900">Bateau venu à vide (Entrée)</span>
                            </label>
                            <label id="sortie_vide_wrapper" class="flex items-center">
                                <input type="checkbox" id="sortie_vide" class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300" onchange="updateVideUI()">
                                <span class="ml-2 text-sm text-gray-900">Bateau sortant à vide (Sortie)</span>
                            </label>
                        </div>
                    </div>

                    <!-- Informations de base -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations du Bateau</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Type de Bateau *</label>
                                <select name="type_bateau_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">Sélectionner un type</option>
                                    <?php foreach ($types_bateaux as $type): ?>
                                    <option value="<?= $type['id'] ?>" data-type-nom="<?= htmlspecialchars(strtolower($type['nom'])) ?>" <?= $bateau_edit && $bateau_edit['type_bateau_id'] == $type['id'] ? 'selected' : '' ?>><?= htmlspecialchars($type['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Nom du Bateau *</label>
                                <input type="text" name="nom" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" placeholder="Ex: Titanic, Queen Mary..." value="<?= $bateau_edit ? htmlspecialchars($bateau_edit['nom']) : '' ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Immatriculation</label>
                                <input type="text" name="immatriculation" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" placeholder="Ex: FR123456" value="<?= $bateau_edit ? htmlspecialchars($bateau_edit['immatriculation'] ?? '') : '' ?>">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Capitaine *</label>
                                <input type="text" name="capitaine" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" placeholder="Nom du capitaine" value="<?= $bateau_edit ? htmlspecialchars($bateau_edit['capitaine']) : '' ?>">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Agence</label>
                                <input type="text" name="agence" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" placeholder="Agence du bateau" value="<?= $bateau_edit ? htmlspecialchars($bateau_edit['agence'] ?? '') : '' ?>">
                            </div>

                            <div class="grid grid-cols-3 gap-4 md:col-span-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Hauteur (m)</label>
                                    <input type="number" step="0.01" min="0" name="hauteur" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" value="<?= $bateau_edit ? $bateau_edit['hauteur'] : '' ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Longueur (m)</label>
                                    <input type="number" step="0.01" min="0" name="longueur" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" value="<?= $bateau_edit ? $bateau_edit['longueur'] : '' ?>">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Largeur (m)</label>
                                    <input type="number" step="0.01" min="0" name="largeur" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" value="<?= $bateau_edit ? $bateau_edit['largeur'] : '' ?>">
                                </div>
                            </div>
                            
                            <div id="origine-wrapper">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Port d'Origine (Entrée)</label>
                                <select name="port_origine_id" id="port_origine_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">Sélectionner un port</option>
                                    <?php foreach ($ports as $port): ?>
                                    <?php if (strtoupper($port['nom']) !== 'BUJUMBURA'): ?>
                                    <option value="<?= $port['id'] ?>" <?= $bateau_edit && $bateau_edit['port_origine_id'] == $port['id'] ? 'selected' : '' ?>><?= htmlspecialchars($port['nom'] . ' (' . $port['pays'] . ')') ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="destination-wrapper">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Port de Destination (Sortie)</label>
                                <select name="port_destination_id" id="port_destination_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                    <option value="">Sélectionner un port</option>
                                    <?php foreach ($ports as $port): ?>
                                    <?php if (strtoupper($port['nom']) !== 'BUJUMBURA'): ?>
                                    <option value="<?= $port['id'] ?>" <?= $bateau_edit && $bateau_edit['port_destination_id'] == $port['id'] ? 'selected' : '' ?>><?= htmlspecialchars($port['nom'] . ' (' . $port['pays'] . ')') ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Marchandises -->
                    <div id="marchandises-section" class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Marchandises Transportées</h3>
                        <div id="marchandises-container">
                            <?php if ($bateau_edit && !empty($marchandises_edit)): ?>
                                <?php foreach (array_values($marchandises_edit) as $idx => $m): ?>
                                <div class="marchandise-item border border-gray-200 rounded-lg p-4 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Type de Marchandise</label>
                                            <select name="marchandises[<?= $idx ?>][type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                                <option value="">Sélectionner</option>
                                                <?php foreach ($types_marchandises as $type): ?>
                                                <option value="<?= $type['id'] ?>" <?= (int)$type['id'] === (int)$m['type_marchandise_id'] ? 'selected' : '' ?>><?= htmlspecialchars($type['nom']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantité</label>
                                            <input type="number" name="marchandises[<?= $idx ?>][quantite]" min="1" value="<?= (int)($m['quantite'] ?? 1) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Poids (kg)</label>
                                            <input type="number" name="marchandises[<?= $idx ?>][poids]" min="0" step="0.01" value="<?= htmlspecialchars((string)($m['poids'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus-border-purple-500" placeholder="Poids total">
                                        </div>
                                    </div>
                                    <button type="button" onclick="removeMarchandise(this)" class="mt-2 text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="marchandise-item border border-gray-200 rounded-lg p-4 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Type de Marchandise</label>
                                            <select name="marchandises[0][type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                                <option value="">Sélectionner</option>
                                                <?php foreach ($types_marchandises as $type): ?>
                                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['nom']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Quantité</label>
                                            <input type="number" name="marchandises[0][quantite]" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Poids (kg)</label>
                                            <input type="number" name="marchandises[0][poids]" min="0" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus-border-purple-500" placeholder="Poids total">
                                        </div>
                                    </div>
                                    <button type="button" onclick="removeMarchandise(this)" class="mt-2 text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" id="add-marchandise-btn" onclick="addMarchandise()" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
                            <i class="fas fa-plus mr-2"></i>Ajouter une Marchandise
                        </button>
                    </div>

                    <!-- Passagers (affiché si type = passager) -->
                    <div id="passagers-section" class="mb-8 hidden">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Passagers (type Passager)</h3>
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nombre de passagers</label>
                            <input type="number" id="nombre_passagers" min="0" value="<?= ($bateau_edit && !empty($passagers_edit)) ? count($passagers_edit) : 0 ?>" class="w-full md:w-64 px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" onchange="buildPassagers()">
                        </div>
                        <div id="passagers-container"></div>
                    </div>

                    <!-- Boutons -->
                    <div class="flex justify-end space-x-4">
                        <a href="dashboard.php" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Annuler
                        </a>
                        <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        let marchandiseCount = <?= ($bateau_edit && !empty($marchandises_edit)) ? count($marchandises_edit) : 1 ?>;
        // Mémorise le statut précédent pour réattribuer logiquement origine/destination lors du basculement
        let previousStatut = (document.querySelector('input[name="statut"]:checked')?.value) || 'entree';

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function toggleMarchandises() {
            // Les marchandises peuvent être enregistrées à l'entrée comme à la sortie
            const section = document.getElementById('marchandises-section');
            section.style.display = 'block';

            // Règle provenance/destination masquée + réaffectation logique
            const statutEntree = document.querySelector('input[name="statut"][value="entree"]').checked;
            const origineWrapper = document.getElementById('origine-wrapper');
            const destinationWrapper = document.getElementById('destination-wrapper');
            const origineSelect = document.getElementById('port_origine_id');
            const destinationSelect = document.getElementById('port_destination_id');
            const bujumburaId = String(parseInt(document.body.getAttribute('data-bujumbura-id') || '0', 10) || '');

            // Sauvegarder les valeurs avant basculement
            const currentOrigine = origineSelect ? String(origineSelect.value || '') : '';
            const currentDestination = destinationSelect ? String(destinationSelect.value || '') : '';

            if (statutEntree) {
                // Mode Entrée: destination = BUJUMBURA (fixe), origine libre
                if (destinationSelect) {
                    if (bujumburaId) destinationSelect.value = bujumburaId;
                    destinationSelect.disabled = true;
                }
                if (origineSelect) {
                    origineSelect.disabled = false;
                    // Si on vient de Sortie -> Entrée, et que l'ancienne destination était externe, la reprendre comme origine
                    if (previousStatut === 'sortie') {
                        if (currentDestination && (!bujumburaId || currentDestination !== bujumburaId)) {
                            origineSelect.value = currentDestination;
                        }
                    }
                }
            } else {
                // Mode Sortie: origine = BUJUMBURA (fixe), destination libre
                if (origineSelect) {
                    if (bujumburaId) origineSelect.value = bujumburaId;
                    origineSelect.disabled = true;
                }
                if (destinationSelect) {
                    destinationSelect.disabled = false;
                    // Si on vient de Entrée -> Sortie, et que l'ancienne origine était externe, la reprendre comme destination
                    if (previousStatut === 'entree') {
                        if (currentOrigine && (!bujumburaId || currentOrigine !== bujumburaId)) {
                            destinationSelect.value = currentOrigine;
                        } else {
                            // Si pas d'origine externe, vider pour forcer le choix
                            destinationSelect.value = '';
                        }
                    }
                }
            }

            // Afficher le bon checkbox 'vide' et appliquer l'état
            updateVideUI();

            // Mémoriser le nouveau statut
            previousStatut = statutEntree ? 'entree' : 'sortie';
        }

        function addMarchandise() {
            const container = document.getElementById('marchandises-container');
            const newItem = document.createElement('div');
            newItem.className = 'marchandise-item border border-gray-200 rounded-lg p-4 mb-4';
            newItem.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type de Marchandise</label>
                        <select name="marchandises[${marchandiseCount}][type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                            <option value="">Sélectionner</option>
                            <?php foreach ($types_marchandises as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Quantité</label>
                        <input type="number" name="marchandises[${marchandiseCount}][quantite]" min="1" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Poids (kg)</label>
                        <input type="number" name="marchandises[${marchandiseCount}][poids]" min="0" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus-border-purple-500" placeholder="Poids total">
                    </div>
                </div>
                <button type="button" onclick="removeMarchandise(this)" class="mt-2 text-red-600 hover:text-red-800 text-sm">
                    <i class="fas fa-trash mr-1"></i>Supprimer
                </button>
            `;
            container.appendChild(newItem);
            marchandiseCount++;
            enforceUniqueMarchandises();
            updateMarchandiseOptions();
        }

        function removeMarchandise(button) {
            button.closest('.marchandise-item').remove();
            enforceUniqueMarchandises();
            updateMarchandiseOptions();
        }

        function buildPassagers() {
            const container = document.getElementById('passagers-container');
            const n = parseInt(document.getElementById('nombre_passagers').value || '0', 10);
            // Sauvegarder les valeurs actuelles avant de reconstruire
            const current = Array.from(container.querySelectorAll('input[name^="passagers"][name$="[poids]"]'))
                .map(inp => inp.value);
            container.innerHTML = '';
            for (let i = 0; i < n; i++) {
                const div = document.createElement('div');
                div.className = 'border border-gray-200 rounded-lg p-4 mb-4';
                div.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Passager #${i+1} - Poids des marchandises (kg)</label>
                            <input type="number" name="passagers[${i}][poids]" min="0" step="0.01" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-purple-500 focus:border-purple-500" placeholder="Poids">
                        </div>
                    </div>
                `;
                container.appendChild(div);
            }
            // Restaurer depuis valeurs courantes sinon depuis pré-remplissage
            const inputs = container.querySelectorAll('input[name^="passagers"][name$="[poids]"]');
            for (let i = 0; i < inputs.length; i++) {
                if (current[i] && current[i] !== '') {
                    inputs[i].value = current[i];
                } else if (window.prePassagers && window.prePassagers[i] != null) {
                    inputs[i].value = window.prePassagers[i];
                }
            }
        }

        // Afficher/masquer la section passagers en fonction du type choisi
        const typeSelect = document.querySelector('select[name="type_bateau_id"]');
        function togglePassagers() {
            const selected = typeSelect.options[typeSelect.selectedIndex];
            const typeNom = (selected && selected.getAttribute('data-type-nom')) || '';
            const isPassager = typeNom.includes('passager');
            const statutEntree = document.querySelector('input[name="statut"][value="entree"]').checked;
            const entreeVide = document.getElementById('entree_vide');
            const sortieVide = document.getElementById('sortie_vide');
            const isVide = statutEntree ? (entreeVide && entreeVide.checked) : (sortieVide && sortieVide.checked);
            const passagersSection = document.getElementById('passagers-section');

            // Si "vide" est coché, on cache toujours les passagers
            if (isVide) {
                passagersSection.classList.add('hidden');
                const nbPassInput = document.getElementById('nombre_passagers');
                if (nbPassInput) {
                    nbPassInput.value = '0';
                    const passContainer = document.getElementById('passagers-container');
                    if (passContainer) passContainer.innerHTML = '';
                }
                return;
            }

            // Sinon, afficher selon le type
            passagersSection.classList.toggle('hidden', !isPassager);
        }
        typeSelect.addEventListener('change', togglePassagers);
        togglePassagers();

        // Validation du formulaire (respecte le choix 'vide' et l'unicité des types)
        document.getElementById('bateauForm').addEventListener('submit', function(e) {
            const statutEntree = document.querySelector('input[name="statut"][value="entree"]');
            const typeSelect = document.querySelector('select[name="type_bateau_id"]');
            const typeNom = typeSelect.options[typeSelect.selectedIndex].getAttribute('data-type-nom') || '';
            const isPassager = typeNom.includes('passager');
            const entreeVide = document.getElementById('entree_vide');
            const sortieVide = document.getElementById('sortie_vide');
            const isVide = statutEntree.checked ? (entreeVide && entreeVide.checked) : (sortieVide && sortieVide.checked);

            // Exiger au moins une marchandise seulement si non-vide et non-passager
            if (!isVide && !isPassager) {
                const marchandises = document.querySelectorAll('.marchandise-item');
                let hasValidMarchandise = false;
                const seen = new Set();

                marchandises.forEach(function(item) {
                    const sel = item.querySelector('select[name*="type_id"]');
                    const poidsInput = item.querySelector('input[name*="poids"]');
                    if (sel && sel.value) {
                        if (!seen.has(sel.value) && poidsInput && poidsInput.value) {
                            seen.add(sel.value);
                            hasValidMarchandise = true;
                        }
                    }
                });

                if (!hasValidMarchandise) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Information manquante',
                        text: "Veuillez renseigner au moins une marchandise valide ou cocher l'option 'vide'."
                    });
                }
            }

            // Si 'vide' est coché, empêcher toute présence de marchandises ou passagers
            if (isVide) {
                const marchCount = document.querySelectorAll('.marchandise-item').length;
                const nbPassInput = document.getElementById('nombre_passagers');
                const nbPass = nbPassInput ? parseInt(nbPassInput.value || '0', 10) : 0;
                if (marchCount > 0 || nbPass > 0) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning',
                        title: 'Bateau à vide',
                        text: "Un bateau à vide ne peut pas avoir de marchandises ni de passagers. Veuillez vider ces sections."
                    });
                }
            }
        });

        function updateVideUI() {
            const statutEntree = document.querySelector('input[name="statut"][value="entree"]').checked;
            const entreeWrapper = document.getElementById('entree_vide_wrapper');
            const sortieWrapper = document.getElementById('sortie_vide_wrapper');
            const entreeVide = document.getElementById('entree_vide');
            const sortieVide = document.getElementById('sortie_vide');
            const section = document.getElementById('marchandises-section');
            const addBtn = document.getElementById('add-marchandise-btn');
            const container = document.getElementById('marchandises-container');
            const passagersSection = document.getElementById('passagers-section');
            const nbPassInput = document.getElementById('nombre_passagers');

            // Afficher le checkbox approprié
            entreeWrapper.style.display = statutEntree ? 'flex' : 'none';
            sortieWrapper.style.display = statutEntree ? 'none' : 'flex';

            const videChecked = statutEntree ? (entreeVide && entreeVide.checked) : (sortieVide && sortieVide.checked);
            if (videChecked) {
                section.style.display = 'none';
                if (container) container.innerHTML = '';
                marchandiseCount = 1;
                // Aussi bloquer passagers
                if (passagersSection) passagersSection.classList.add('hidden');
                if (nbPassInput) {
                    nbPassInput.value = '0';
                    const passContainer = document.getElementById('passagers-container');
                    if (passContainer) passContainer.innerHTML = '';
                }
            } else {
                section.style.display = 'block';
                // Réafficher passagers selon type
                const selected = document.querySelector('select[name="type_bateau_id"]').options[document.querySelector('select[name="type_bateau_id"]').selectedIndex];
                const typeNom = (selected && selected.getAttribute('data-type-nom')) || '';
                const isPassager = typeNom.includes('passager');
                if (passagersSection) passagersSection.classList.toggle('hidden', !isPassager);
            }
        }

        // Empêcher les doublons sans masquer les options (sécurité)
        function enforceUniqueMarchandises() {
            const selects = Array.from(document.querySelectorAll('#marchandises-container select[name*="[type_id]"]'));
            const used = new Set();
            let warned = false;
            selects.forEach(sel => {
                const val = sel.value;
                if (val) {
                    if (used.has(val)) {
                        sel.value = '';
                        if (!warned) {
                            warned = true;
                            Swal.fire({ icon: 'warning', title: 'Type dupliqué', text: 'Chaque type de marchandise ne peut être sélectionné qu\'une seule fois.' });
                        }
                    } else {
                        used.add(val);
                    }
                }
            });
        }

        // Masquer les options déjà sélectionnées dans les autres listes
        function updateMarchandiseOptions() {
            const selects = Array.from(document.querySelectorAll('#marchandises-container select[name*="[type_id]"]'));
            const selectedValues = new Set(selects.map(s => s.value).filter(Boolean));
            selects.forEach(sel => {
                const current = sel.value;
                const options = Array.from(sel.options);
                options.forEach(opt => {
                    if (!opt.value) return; // placeholder
                    const shouldDisable = selectedValues.has(opt.value) && opt.value !== current;
                    opt.disabled = shouldDisable;
                    // Garder visible même si désactivé pour informer l'utilisateur
                    opt.hidden = false;
                });
            });
        }

        // Détection des changements pour maintenir l'unicité
        document.getElementById('marchandises-container').addEventListener('change', function(e) {
            if (e.target && e.target.matches('select[name*="[type_id]"]')) {
                enforceUniqueMarchandises();
                updateMarchandiseOptions();
            }
        });

        // Initialiser l'état des ports et sections selon le statut et les valeurs actuelles
        window.addEventListener('load', function() {
            try { toggleMarchandises(); } catch (e) {}
            try { updateVideUI(); } catch (e) {}
        });

        // Détection des changements pour maintenir l'unicité
        document.getElementById('marchandises-container').addEventListener('change', function(e) {
            if (e.target && e.target.matches('select[name*="[type_id]"]')) {
                enforceUniqueMarchandises();
                updateMarchandiseOptions();
            }
        });

        toggleMarchandises();
        updateVideUI();
        enforceUniqueMarchandises();
        updateMarchandiseOptions();
        (function(){
            var pre = <?= json_encode(array_map(function($p){return isset($p['poids_marchandises'])?$p['poids_marchandises']:($p['poids']??null);}, $passagers_edit ?? [])) ?>;
            if (Array.isArray(pre) && pre.length>0) {
                var nInput = document.getElementById('nombre_passagers');
                if (nInput) {
                    nInput.value = String(pre.length);
                    buildPassagers();
                    var cont = document.getElementById('passagers-container');
                    if (cont) {
                        var inputs = cont.querySelectorAll('input[name^="passagers"][name$="[poids]"]');
                        for (var i=0;i<inputs.length && i<pre.length;i++) { inputs[i].value = pre[i]; }
                    }
                }
                window.prePassagers = pre;
            }
        })();
    </script>
</body>
</html>

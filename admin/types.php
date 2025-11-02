<?php
require_once '../includes/auth_check.php';
checkRole(['admin']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Message de succès
$success_message = $_SESSION['success_message'] ?? null;
if ($success_message) {
    unset($_SESSION['success_message']);
}

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $action = $_POST['action'] ?? '';
    $nom = trim($_POST['nom'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    try {
        switch ($action) {
            case 'add':
                if ($nom) {
                    if ($type === 'port') {
                        $pays = trim($_POST['pays'] ?? '');
                        if ($pays) {
                            $stmt = $db->prepare("INSERT INTO ports (nom, pays) VALUES (?, ?)");
                            $stmt->execute([$nom, $pays]);
                        }
                    } else {
                        $table = $type === 'marchandise' ? 'types_marchandises' : 
                                 ($type === 'camion' ? 'types_camions' : 'types_bateaux');
                        $stmt = $db->prepare("INSERT INTO $table (nom) VALUES (?)");
                        $stmt->execute([$nom]);
                    }
                    
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Ajout Type', "Ajout du type $type: $nom"]);
                    $success = "Type ajouté avec succès";
                }
                break;
                
            case 'edit':
                $id = $_POST['id'] ?? 0;
                if ($nom && $id) {
                    if ($type === 'port') {
                        $pays = trim($_POST['pays'] ?? '');
                        $stmt = $db->prepare("UPDATE ports SET nom = ?, pays = ? WHERE id = ?");
                        $stmt->execute([$nom, $pays, $id]);
                    } else {
                        $table = $type === 'marchandise' ? 'types_marchandises' : 
                                 ($type === 'camion' ? 'types_camions' : 'types_bateaux');
                        $stmt = $db->prepare("UPDATE $table SET nom = ? WHERE id = ?");
                        $stmt->execute([$nom, $id]);
                    }
                    
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Modification Type', "Modification du type $type: $nom"]);
                    $success = "Type modifié avec succès";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'] ?? 0;
                // Récupérer le nom avant suppression pour le log
                if ($type === 'port') {
                    $get = $db->prepare("SELECT nom FROM ports WHERE id = ?");
                    $get->execute([$id]);
                    $rowLog = $get->fetch();
                    $toDelete = $db->prepare("DELETE FROM ports WHERE id = ?");
                } else {
                    $table = $type === 'marchandise' ? 'types_marchandises' : 
                             ($type === 'camion' ? 'types_camions' : 'types_bateaux');
                    $get = $db->prepare("SELECT nom FROM $table WHERE id = ?");
                    $get->execute([$id]);
                    $rowLog = $get->fetch();
                    $toDelete = $db->prepare("DELETE FROM $table WHERE id = ?");
                }
                $toDelete->execute([$id]);
                
                $nomSupprime = $rowLog && isset($rowLog['nom']) ? $rowLog['nom'] : (string)$id;
                $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                $logStmt->execute([$user['id'], 'Suppression Type', "Suppression du type $type: $nomSupprime"]);
                $success = "Type supprimé avec succès";
                break;
        }
        if (isset($success)) {
            $_SESSION['success_message'] = $success;
        }
        header('Location: types.php');
        exit();
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupération des données
$marchandises = $db->query("SELECT * FROM types_marchandises ORDER BY nom")->fetchAll();
$camions = $db->query("SELECT * FROM types_camions ORDER BY nom")->fetchAll();
$bateaux = $db->query("SELECT * FROM types_bateaux ORDER BY nom")->fetchAll();
$ports = $db->query("SELECT * FROM ports WHERE UPPER(nom) != UPPER('BUJUMBURA') ORDER BY nom ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Types - Port de BUJUMBURA</title>
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
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="types.php" class="flex items-center px-4 py-3 text-white bg-blue-800">
                <i class="fas fa-tags mr-3"></i>
                Gestion des Types
            </a>
            
            <a href="users.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
                <i class="fas fa-users mr-3"></i>
                Gestion des Utilisateurs
            </a>
            
            <a href="logs.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
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
                <h1 class="text-3xl font-bold text-gray-900">Gestion des Types</h1>
                <p class="text-gray-600 mt-2">Gérer les types de marchandises, camions, bateaux et ports</p>
            </div>

            <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= htmlspecialchars($success_message) ?>
            </div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="bg-white rounded-lg shadow">
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex space-x-8 px-6">
                        <button onclick="showTab('marchandises')" class="tab-button py-4 px-1 border-b-2 border-blue-500 text-blue-600 font-medium">
                            <i class="fas fa-box mr-2"></i>Marchandises
                        </button>
                        <button onclick="showTab('camions')" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium">
                            <i class="fas fa-truck mr-2"></i>Camions
                        </button>
                        <button onclick="showTab('bateaux')" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium">
                            <i class="fas fa-ship mr-2"></i>Bateaux
                        </button>
                        <button onclick="showTab('ports')" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 font-medium">
                            <i class="fas fa-map-marker-alt mr-2"></i>Ports
                        </button>
                    </nav>
                </div>

                <div class="p-6">
                    <!-- Marchandises -->
                    <div id="marchandises" class="tab-content">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Types de Marchandises</h3>
                            <button onclick="openModal('marchandise')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Ajouter
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($marchandises as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item['nom']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <button onclick="editItem('marchandise', <?= $item['id'] ?>, '<?= htmlspecialchars($item['nom']) ?>')" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                            <button onclick="deleteItem('marchandise', <?= $item['id'] ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Camions -->
                    <div id="camions" class="tab-content hidden">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Types de Camions</h3>
                            <button onclick="openModal('camion')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Ajouter
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($camions as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item['nom']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <button onclick="editItem('camion', <?= $item['id'] ?>, '<?= htmlspecialchars($item['nom']) ?>')" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                            <button onclick="deleteItem('camion', <?= $item['id'] ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Bateaux -->
                    <div id="bateaux" class="tab-content hidden">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Types de Bateaux</h3>
                            <button onclick="openModal('bateau')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Ajouter
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($bateaux as $item): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item['nom']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <button onclick="editItem('bateau', <?= $item['id'] ?>, '<?= htmlspecialchars($item['nom']) ?>')" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                            <button onclick="deleteItem('bateau', <?= $item['id'] ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Ports -->
                    <div id="ports" class="tab-content hidden">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Ports</h3>
                            <button onclick="openModal('port')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                <i class="fas fa-plus mr-2"></i>Ajouter
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nom</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pays</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($ports as $item): ?>
                                    <?php if (strtoupper($item['nom']) !== 'BUJUMBURA'): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($item['nom']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?= htmlspecialchars($item['pays']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <button onclick="editItem('port', <?= $item['id'] ?>, '<?= htmlspecialchars($item['nom']) ?>', '<?= htmlspecialchars($item['pays']) ?>')" class="text-blue-600 hover:text-blue-900">
                                                <i class="fas fa-edit"></i> Modifier
                                            </button>
                                            <button onclick="deleteItem('port', <?= $item['id'] ?>)" class="text-red-600 hover:text-red-900">
                                                <i class="fas fa-trash"></i> Supprimer
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div id="modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900" id="modalTitle">Ajouter un type</h3>
                </div>
                
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="add" id="modalAction">
                    <input type="hidden" name="type" id="modalType">
                    <input type="hidden" name="id" id="modalId">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                        <input type="text" name="nom" id="modalNom" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div id="modalPaysSection" class="mb-6 hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Pays</label>
                        <input type="text" name="pays" id="modalPays" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded hover:bg-gray-300">
                            Annuler
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700" id="modalSubmitBtn">
                            Ajouter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function showTab(tabName) {
            // Masquer tous les contenus
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.add('hidden');
            });
            
            // Désactiver tous les boutons
            document.querySelectorAll('.tab-button').forEach(button => {
                button.classList.remove('border-blue-500', 'text-blue-600');
                button.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Afficher le contenu sélectionné
            document.getElementById(tabName).classList.remove('hidden');
            
            // Activer le bouton sélectionné
            event.target.classList.remove('border-transparent', 'text-gray-500');
            event.target.classList.add('border-blue-500', 'text-blue-600');
        }

        function openModal(type) {
            document.getElementById('modalAction').value = 'add';
            document.getElementById('modalType').value = type;
            document.getElementById('modalId').value = '';
            document.getElementById('modalNom').value = '';
            document.getElementById('modalPays').value = '';
            document.getElementById('modalTitle').textContent = `Ajouter un type de ${type}`;
            document.getElementById('modalSubmitBtn').textContent = 'Ajouter';
            
            // Afficher le champ pays seulement pour les ports
            const paysSection = document.getElementById('modalPaysSection');
            if (type === 'port') {
                paysSection.classList.remove('hidden');
            } else {
                paysSection.classList.add('hidden');
            }
            
            document.getElementById('modal').classList.remove('hidden');
        }

        function editItem(type, id, nom, pays = '') {
            document.getElementById('modalAction').value = 'edit';
            document.getElementById('modalType').value = type;
            document.getElementById('modalId').value = id;
            document.getElementById('modalNom').value = nom;
            document.getElementById('modalPays').value = pays;
            document.getElementById('modalTitle').textContent = `Modifier le type de ${type}`;
            document.getElementById('modalSubmitBtn').textContent = 'Modifier';
            
            // Afficher le champ pays seulement pour les ports
            const paysSection = document.getElementById('modalPaysSection');
            if (type === 'port') {
                paysSection.classList.remove('hidden');
            } else {
                paysSection.classList.add('hidden');
            }
            
            document.getElementById('modal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('modal').classList.add('hidden');
        }

        function deleteItem(type, id) {
            Swal.fire({
                title: 'Êtes-vous sûr ?',
                text: "Vous ne pourrez pas annuler cette action !",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Oui, supprimer !',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="type" value="${type}">
                        <input type="hidden" name="id" value="${id}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>

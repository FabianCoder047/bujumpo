<?php
require_once '../includes/auth_check.php';
checkRole(['admin']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add':
                $nom = trim($_POST['nom'] ?? '');
                $prenom = trim($_POST['prenom'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $role = $_POST['role'] ?? '';
                
                if ($nom && $prenom && $email && $role) {
                    // Vérifier si l'email existe déjà
                    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
                    $checkStmt->execute([$email]);
                    if ($checkStmt->rowCount() > 0) {
                        $error = "Un utilisateur avec cet email existe déjà.";
                        break;
                    }

                    // Mot de passe par défaut selon le rôle et obligation de changement à la 1ère connexion
                    $defaultPassword = getDefaultPasswordForRole($role);
                    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

                    $stmt = $db->prepare("INSERT INTO users (nom, prenom, email, password, role, first_login) VALUES (?, ?, ?, ?, ?, FALSE)");
                    $stmt->execute([$nom, $prenom, $email, $hashedPassword, $role]);
                    
                    // Log sans afficher le mot de passe
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Création Utilisateur', "Création de l'utilisateur: $email (Rôle: $role)"]);
                    
                    $success = "Utilisateur créé avec succès.";
                }
                break;
                
            case 'update':
                $userId = $_POST['user_id'] ?? 0;
                $nom = trim($_POST['nom'] ?? '');
                $prenom = trim($_POST['prenom'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $role = $_POST['role'] ?? '';
                
                if ($userId && $nom && $prenom && $email && $role) {
                    // Vérifier si l'email existe déjà pour un autre utilisateur
                    $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $checkStmt->execute([$email, $userId]);
                    if ($checkStmt->rowCount() > 0) {
                        $error = "Un utilisateur avec cet email existe déjà.";
                        break;
                    }
                    
                    // Empêcher la modification du rôle de l'admin principal (ID 1)
                    if ($userId == 1 && $role != 'admin') {
                        $error = "Impossible de modifier le rôle de l'administrateur principal.";
                        break;
                    }
                    
                    $stmt = $db->prepare("UPDATE users SET nom = ?, prenom = ?, email = ?, role = ? WHERE id = ?");
                    $stmt->execute([$nom, $prenom, $email, $role, $userId]);
                    
                    // Journaliser l'action (utiliser l'email au lieu de l'ID)
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Modification Utilisateur', "Modification de l'utilisateur: $email"]);
                    
                    $success = "Utilisateur mis à jour avec succès.";
                }
                break;
                
            case 'reset_password':
                $userId = $_POST['user_id'] ?? 0;
                // Récupérer le rôle de l'utilisateur pour appliquer le mot de passe par défaut correspondant
                $stmt = $db->prepare("SELECT role, email FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $row = $stmt->fetch();
                if ($row) {
                    $defaultPassword = getDefaultPasswordForRole($row['role']);
                    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password = ?, first_login = TRUE WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                    // Log sans afficher le mot de passe (utiliser l'email au lieu de l'ID)
                $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Reset Mot de passe', "Reset mot de passe pour utilisateur: {$row['email']}"]);
                
                    $success = "Mot de passe réinitialisé.";
                }
                break;
                
            case 'delete':
                $userId = $_POST['user_id'] ?? 0;
                if ($userId != $user['id']) { // Empêcher la suppression de soi-même
                    // Empêcher la suppression de l'admin principal (ID 1)
                    $stmt = $db->prepare("SELECT id, role, email FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $target = $stmt->fetch();
                    if ($target) {
                        if ((int)$target['id'] === 1 && $target['role'] === 'admin') {
                            $error = "Suppression refusée: admin principal";
                            break;
                        }
                        // Supprimer d'abord les logs liés à cet utilisateur pour éviter les contraintes
                        $delLogs = $db->prepare("DELETE FROM logs WHERE user_id = ?");
                        $delLogs->execute([$userId]);
                        // Supprimer l'utilisateur
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                        // Journaliser l'action
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Suppression Utilisateur', "Suppression de l'utilisateur: {$target['email']}"]);
                    }
                }
                break;
        }
        if (!headers_sent()) {
        header('Location: users.php');
        exit();
        }
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Fonction pour générer un mot de passe temporaire
function generateTempPassword() {
    return 'Temp' . rand(1000, 9999);
}

// Mot de passe par défaut par rôle
function getDefaultPasswordForRole($role) {
    switch ($role) {
        case 'admin':
            return 'admin123';
        case 'autorite':
            return 'autorite123';
        case 'EnregistreurEntreeRoute':
            return 'EnregistreurEntreeRoute123';
        case 'EnregistreurSortieRoute':
            return 'EnregistreurSortieRoute123';
        case 'peseur':
            return 'peseur123';
        case 'EnregistreurBateaux':
            return 'EnregistreurBateaux123';
        case 'douanier':
            return 'douanier123';
        default:
            return 'user123';
    }
}

// Récupération des utilisateurs
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs - Port de BUJUMBURA</title>
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
            
            <a href="types.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
                <i class="fas fa-tags mr-3"></i>
                Gestion des Types
            </a>
            
            <a href="users.php" class="flex items-center px-4 py-3 text-white bg-blue-800">
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
                <h1 class="text-3xl font-bold text-gray-900">Gestion des Utilisateurs</h1>
                <p class="text-gray-600 mt-2">Créer et gérer les comptes utilisateurs du système</p>
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

            <!-- Bouton Ajouter Utilisateur -->
            <div class="mb-6">
                <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    <i class="fas fa-user-plus mr-2"></i>Nouvel Utilisateur
                </button>
            </div>

            <!-- Tableau des utilisateurs -->
            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-[1000px] divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Utilisateur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rôle</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Créé le</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($u['email']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                    <?php
                                    $roleClass = 'bg-gray-100 text-gray-800';
                                    switch($u['role']) {
                                        case 'admin':
                                            $roleClass = 'bg-red-100 text-red-800';
                                            break;
                                        case 'autorite':
                                            $roleClass = 'bg-purple-100 text-purple-800';
                                            break;
                                        case 'douanier':
                                            $roleClass = 'bg-orange-100 text-orange-800';
                                            break;
                                        case 'EnregistreurEntreeRoute':
                                            $roleClass = 'bg-green-100 text-green-800';
                                            break;
                                        case 'EnregistreurSortieRoute':
                                            $roleClass = 'bg-yellow-100 text-yellow-800';
                                            break;
                                        case 'peseur':
                                        case 'EnregistreurBateaux':
                                            $roleClass = 'bg-blue-100 text-blue-800';
                                            break;
                                    }
                                    echo $roleClass;
                                    ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= date('d/m/Y H:i', strtotime($u['created_at'])) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                <button onclick="editUser(<?= $u['id'] ?>, '<?= addslashes($u['prenom']) ?>', '<?= addslashes($u['nom']) ?>', '<?= $u['email'] ?>', '<?= $u['role'] ?>')" class="text-blue-600 hover:text-blue-900 mr-2">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if (!($u['id'] == 1 && $u['role'] === 'admin')): ?>
                                <button onclick="deleteUser(<?= $u['id'] ?>)" class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal Ajouter Utilisateur -->
    <div id="addUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Nouvel Utilisateur</h3>
                </div>
                
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                            <input type="text" name="nom" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                            <input type="text" name="prenom" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                        <select name="role" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Sélectionner un rôle</option>
                            <option value="admin">Administrateur</option>
                            <option value="autorite">Autorité</option>
                            <option value="douanier">Douanier</option>
                            <option value="EnregistreurEntreeRoute">Enregistreur Entree Route</option>
                            <option value="EnregistreurSortieRoute">Enregistreur Sortie Route</option>
                            <option value="peseur">Peseur</option>
                            <option value="EnregistreurBateaux">Enregistreur Bateaux</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="closeModal('addUserModal')" class="px-4 py-2 text-gray-700 bg-gray-200 rounded hover:bg-gray-300">
                            Annuler
                        </button>
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Créer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Modifier Utilisateur -->
    <div id="editUserModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-900">Modifier l'utilisateur</h3>
                </div>
                
                <form method="POST" class="p-6">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Prénom</label>
                            <input type="text" name="prenom" id="edit_prenom" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Nom</label>
                            <input type="text" name="nom" id="edit_nom" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" id="edit_email" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Rôle</label>
                        <select name="role" id="edit_role" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                            <option value="admin">Administrateur</option>
                            <option value="autorite">Autorité</option>
                            <option value="douanier">Douanier</option>
                            <option value="EnregistreurEntreeRoute">Enregistreur Entree Route</option>
                            <option value="EnregistreurSortieRoute">Enregistreur Sortie Route</option>
                            <option value="peseur">Peseur</option>
                            <option value="EnregistreurBateaux">Enregistreur Bateaux</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-between">
                        <div class="space-x-3">
                            <button type="button" onclick="closeModal('editUserModal')" class="px-4 py-2 text-gray-700 bg-gray-200 rounded hover:bg-gray-300">
                                Annuler
                            </button>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                Enregistrer
                            </button>
                        </div>
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

        function openModal() {
            document.getElementById('addUserModal').classList.remove('hidden');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.add('hidden');
        }

        function editUser(id, prenom, nom, email, role) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_prenom').value = prenom;
            document.getElementById('edit_nom').value = nom;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('editUserModal').classList.remove('hidden');
        }

        function resetPassword() {
            const userId = document.getElementById('edit_user_id').value;
            if (confirm('Êtes-vous sûr de vouloir réinitialiser le mot de passe de cet utilisateur ? Un mot de passe par défaut sera défini selon son rôle.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }


        function deleteUser(userId) {
            Swal.fire({
                title: 'Supprimer l\'utilisateur ?',
                text: "Cette action est irréversible !",
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
                        <input type="hidden" name="user_id" value="${userId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>

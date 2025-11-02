<?php
require_once '../includes/auth_check.php';
checkRole(['EnregistreurEntreeRoute']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Edition: charger un camion existant si camion_id est passé
$isEdit = false;
$camion = null;
$camion_marchandises = [];
if (isset($_GET['camion_id']) && ctype_digit($_GET['camion_id'])) {
	$camionId = (int)$_GET['camion_id'];
	$stmt = $db->prepare("SELECT * FROM camions WHERE id = ?");
	$stmt->execute([$camionId]);
	$camion = $stmt->fetch();
	if ($camion) {
		$isEdit = true;
		$stmt = $db->prepare("SELECT mc.id, mc.type_marchandise_id FROM marchandises_camions mc WHERE mc.camion_id = ? ORDER BY mc.id");
		$stmt->execute([$camionId]);
		$camion_marchandises = $stmt->fetchAll();
	}
}

// Récupération des données
$types_camions = $db->query("SELECT * FROM types_camions ORDER BY nom")->fetchAll();
$types_marchandises = $db->query("SELECT * FROM types_marchandises ORDER BY nom")->fetchAll();
$ports = $db->query("SELECT * FROM ports ORDER BY nom")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enregistrer Camion - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
                <p class="text-green-300 text-sm font-medium">Vigile Entrée</p>
            </div>
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-green-200 hover:bg-green-800 hover:text-white transition duration-200">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="enregistrer.php" class="flex items-center px-4 py-3 text-white bg-green-800">
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
                        <p class="text-sm text-gray-500">Vigile d'Entrée</p>
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
                <h1 class="text-3xl font-bold text-gray-900"><?= $isEdit ? 'Modifier un Camion' : 'Enregistrer un Camion' ?></h1>
                <p class="text-gray-600 mt-2"><?= $isEdit ? 'Mettre à jour les informations du camion' : "Enregistrer l'entrée d'un nouveau camion au port" ?></p>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <form id="camionForm" method="POST" action="dashboard.php">
                    <input type="hidden" name="action" value="<?= $isEdit ? 'update_camion' : 'add_camion' ?>">
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="camion_id" value="<?= (int)$camion['id'] ?>">
                    <?php endif; ?>
                    
                    <!-- Informations de base -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations du Camion</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Type de Camion *</label>
                                <select name="type_camion_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                                    <option value="">Sélectionner un type</option>
                                    <?php foreach ($types_camions as $type): ?>
                                    <option value="<?= $type['id'] ?>" <?= $isEdit && (int)$camion['type_camion_id'] === (int)$type['id'] ? 'selected' : '' ?>><?= htmlspecialchars($type['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Marque *</label>
                                <input type="text" name="marque" value="<?= $isEdit ? htmlspecialchars($camion['marque']) : '' ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" placeholder="Ex: Mercedes, Volvo...">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Immatriculation *</label>
                                <input type="text" name="immatriculation" value="<?= $isEdit ? htmlspecialchars($camion['immatriculation']) : '' ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" placeholder="Ex: ABC-123-CD">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Chauffeur *</label>
                                <input type="text" name="chauffeur" value="<?= $isEdit ? htmlspecialchars($camion['chauffeur']) : '' ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" placeholder="Nom du chauffeur">
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Agence *</label>
                                <input type="text" name="agence" value="<?= $isEdit ? htmlspecialchars($camion['agence']) : '' ?>" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" placeholder="Nom de l'agence de transport">
                            </div>
                        </div>
                    </div>

                    <!-- Provenance / Destinataire / T1 -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Informations d'Origine</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Provenance (Port) *</label>
                                <select name="provenance_port_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                                    <option value="">Sélectionner un port</option>
                                    <?php foreach ($ports as $p): ?>
                                    <?php if (strtoupper($p['nom']) !== 'BUJUMBURA'): ?>
                                    <option value="<?= $p['id'] ?>" <?= $isEdit && isset($camion['provenance_port_id']) && (int)$camion['provenance_port_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nom']) ?> (<?= htmlspecialchars($p['pays']) ?>)</option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Destinataire *</label>
                                <input type="text" name="destinataire" value="<?= $isEdit ? htmlspecialchars($camion['destinataire'] ?? '') : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" placeholder="Société ou personne">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">T1</label>
                                <input type="text" name="t1" value="<?= $isEdit ? htmlspecialchars($camion['t1'] ?? '') : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" placeholder="N° T1 (optionnel)">
                            </div>
                        </div>
                    </div>

                    <!-- Statut de chargement -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Statut de Chargement</h3>
                        <div class="flex items-center">
                            <input type="checkbox" id="est_charge" name="est_charge" class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded" onchange="toggleMarchandises()" <?= $isEdit && (int)$camion['est_charge'] === 1 ? 'checked' : '' ?>>
                            <label for="est_charge" class="ml-2 block text-sm text-gray-900">
                                Le camion est chargé de marchandises
                            </label>
                        </div>
                    </div>

                    <!-- Marchandises -->
                    <div id="marchandises-section" class="mb-8 <?= $isEdit && (int)$camion['est_charge'] === 1 ? '' : 'hidden' ?>">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Marchandises Transportées (sans quantités)</h3>
                        <div id="marchandises-container">
                            <?php if ($isEdit && !empty($camion_marchandises)): ?>
                                <?php foreach ($camion_marchandises as $idx => $m): ?>
                                <div class="marchandise-item border border-gray-200 rounded-lg p-4 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Type de Marchandise</label>
                                            <select name="marchandises[<?= $idx ?>][type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" onchange="updateSelectOptions()">
                                                <option value="">Sélectionner</option>
                                                <?php foreach ($types_marchandises as $type): ?>
                                                <option value="<?= $type['id'] ?>" <?= (int)$m['type_marchandise_id'] === (int)$type['id'] ? 'selected' : '' ?>><?= htmlspecialchars($type['nom']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" onclick="removeMarchandise(this)" class="mt-2 text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas a-trash mr-1"></i>Supprimer
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="marchandise-item border border-gray-200 rounded-lg p-4 mb-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Type de Marchandise</label>
                                            <select name="marchandises[0][type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" onchange="updateSelectOptions()">
                                                <option value="">Sélectionner</option>
                                                <?php foreach ($types_marchandises as $type): ?>
                                                <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['nom']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <button type="button" onclick="removeMarchandise(this)" class="mt-2 text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" onclick="addMarchandise()" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            <i class="fas fa-plus mr-2"></i>Ajouter une Marchandise
                        </button>
                    </div>

                    <!-- Boutons -->
                    <div class="flex justify-end space-x-4">
                        <a href="dashboard.php" class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                            Annuler
                        </a>
                        <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                            <i class="fas fa-save mr-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        let marchandiseCount = <?= $isEdit ? max(1, count($camion_marchandises)) : 1 ?>;
        
        // Initialiser les options au chargement
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectOptions();
        });

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function toggleMarchandises() {
            const checkbox = document.getElementById('est_charge');
            const section = document.getElementById('marchandises-section');
            
            if (checkbox.checked) {
                section.classList.remove('hidden');
            } else {
                section.classList.add('hidden');
            }
        }

        function addMarchandise() {
            const container = document.getElementById('marchandises-container');
            const newItem = document.createElement('div');
            newItem.className = 'marchandise-item border border-gray-200 rounded-lg p-4 mb-4';
            newItem.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Type de Marchandise</label>
                        <select name="marchandises[${marchandiseCount}][type_id]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500" onchange="updateSelectOptions()">
                            <option value="">Sélectionner</option>
                            <?php foreach ($types_marchandises as $type): ?>
                            <option value="<?= $type['id'] ?>"><?= htmlspecialchars($type['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <button type="button" onclick="removeMarchandise(this)" class="mt-2 text-red-600 hover:text-red-800 text-sm">
                    <i class="fas fa-trash mr-1"></i>Supprimer
                </button>
            `;
            container.appendChild(newItem);
            marchandiseCount++;
            updateSelectOptions();
        }

        function removeMarchandise(button) {
            button.closest('.marchandise-item').remove();
            updateSelectOptions();
        }

        function updateSelectOptions() {
            // Collecter tous les types sélectionnés
            const selectedTypes = new Set();
            const selects = document.querySelectorAll('.marchandise-item select[name*="type_id"]');
            
            selects.forEach(select => {
                if (select.value) {
                    selectedTypes.add(select.value);
                }
            });
            
            // Mettre à jour toutes les options
            selects.forEach(select => {
                const currentValue = select.value;
                const options = select.querySelectorAll('option');
                
                options.forEach(option => {
                    if (option.value === '') {
                        option.disabled = false; // Toujours permettre "Sélectionner"
                    } else if (selectedTypes.has(option.value) && option.value !== currentValue) {
                        option.disabled = true; // Désactiver si déjà sélectionné ailleurs
                        option.style.color = '#999';
                    } else {
                        option.disabled = false; // Réactiver si disponible
                        option.style.color = '';
                    }
                });
            });
        }

        // Validation du formulaire
        document.getElementById('camionForm').addEventListener('submit', function(e) {
            const estCharge = document.getElementById('est_charge').checked;
            const marchandises = document.querySelectorAll('.marchandise-item');
            
            if (estCharge && marchandises.length > 0) {
                let hasValidMarchandise = false;
                let typesUtilises = [];
                let doublonTrouve = false;
                
                marchandises.forEach(function(item) {
                    const typeSelect = item.querySelector('select[name*="type_id"]');
                    
                    if (typeSelect.value) {
                        hasValidMarchandise = true;
                        
                        // Vérifier les doublons
                        if (typesUtilises.includes(typeSelect.value)) {
                            doublonTrouve = true;
                        } else {
                            typesUtilises.push(typeSelect.value);
                        }
                    }
                });
                
                if (doublonTrouve) {
                    e.preventDefault();
                    Swal.fire({ icon: 'warning', title: 'Doublon', text: "Un type de marchandise ne peut pas être sélectionné plusieurs fois." });
                    return;
                }
                
                if (!hasValidMarchandise) {
                    e.preventDefault();
                    Swal.fire({ icon: 'warning', title: 'Information manquante', text: "Veuillez renseigner au moins une marchandise valide si le camion est chargé." });
                }
            }
        });
    </script>
</body>
</html>

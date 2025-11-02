<?php
require_once '../includes/auth_check.php';
checkRole(['EnregistreurSortieRoute']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'valider_sortie':
                $camion_id = $_POST['camion_id'] ?? '';
                
                if ($camion_id) {
                    // Vérifier la surcharge avant validation
                    $check = $db->prepare("SELECT ptav, ptac, total_poids_marchandises AS poids_total, surcharge FROM pesages WHERE camion_id = ? ORDER BY date_pesage DESC LIMIT 1");
                    $check->execute([$camion_id]);
                    $last = $check->fetch(PDO::FETCH_ASSOC);
                    if ($last) {
                        $ptav = isset($last['ptav']) ? (float)$last['ptav'] : 0.0;
                        $ptac = isset($last['ptac']) ? (float)$last['ptac'] : 0.0;
                        $poidsTotal = isset($last['poids_total']) ? (float)$last['poids_total'] : 0.0;
                        $chargeAutorisee = max($ptac - $ptav, 0.0);
                        $isOver = (isset($last['surcharge']) && (int)$last['surcharge'] === 1) || ($chargeAutorisee > 0 && $poidsTotal > $chargeAutorisee);
                        if ($isOver) {
                            $depassement = $chargeAutorisee > 0 ? ($poidsTotal - $chargeAutorisee) : 0.0;
                            $error = "Surcharge détectée: le poids chargé (" . number_format($poidsTotal, 2, '.', '') . " kg) dépasse la charge autorisée (" . number_format($chargeAutorisee, 2, '.', '') . " kg). Validation de sortie bloquée.";
                            break; // ne pas poursuivre la validation
                        }
                    }
                    // Mettre à jour la date de sortie
                    $stmt = $db->prepare("UPDATE camions SET date_sortie = NOW(), statut = 'sortie' WHERE id = ?");
                    $stmt->execute([$camion_id]);
                    
                    // Récupérer immatriculation pour le log
                    $imm = null;
                    $s = $db->prepare("SELECT immatriculation FROM camions WHERE id = ?");
                    $s->execute([$camion_id]);
                    $r = $s->fetch();
                    if ($r) { $imm = $r['immatriculation']; }
                    $ref = $imm ?: (string)$camion_id;
                    $logStmt = $db->prepare("INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)");
                    $logStmt->execute([$user['id'], 'Validation Sortie', "Sortie validée pour camion: $ref"]);
                    
                    $success = "Sortie validée avec succès";
                }
                break;
        }
        if (!isset($error)) {
            header('Location: dashboard.php');
            exit();
        }
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupération des données
// Camions autorisés à sortir
// Éviter les doublons en prenant le dernier pesage par camion.
// Si la colonne mouvement existe, ne garder que les pesages de 'sortie'.
$hasMouvement = false;
try {
    $col = $db->query("SHOW COLUMNS FROM pesages LIKE 'mouvement'")->fetch();
    $hasMouvement = $col ? true : false;
} catch (Exception $e) { /* ignore */ }

if ($hasMouvement) {
    $sql = "
        SELECT c.*, tc.nom as type_camion, p.ptav, p.ptac, p.ptra, p.date_pesage
        FROM camions c
        LEFT JOIN types_camions tc ON c.type_camion_id = tc.id
        INNER JOIN (
            SELECT camion_id, MAX(date_pesage) AS max_dp
            FROM pesages
            WHERE mouvement = 'sortie'
            GROUP BY camion_id
        ) lp ON lp.camion_id = c.id
        LEFT JOIN pesages p ON p.camion_id = c.id AND p.date_pesage = lp.max_dp
        WHERE c.statut = 'sortie' AND c.date_sortie IS NULL
        ORDER BY p.date_pesage DESC
    ";
} else {
    $sql = "
        SELECT c.*, tc.nom as type_camion, p.ptav, p.ptac, p.ptra, p.date_pesage
        FROM camions c
        LEFT JOIN types_camions tc ON c.type_camion_id = tc.id
        LEFT JOIN (
            SELECT camion_id, MAX(date_pesage) AS max_dp
            FROM pesages
            GROUP BY camion_id
        ) lp ON lp.camion_id = c.id
        LEFT JOIN pesages p ON p.camion_id = c.id AND p.date_pesage = lp.max_dp
        WHERE c.statut = 'sortie' AND c.date_sortie IS NULL
        ORDER BY p.date_pesage DESC
    ";
}

$camions_autorises = $db->query($sql)->fetchAll();

// Statistiques
$stats = [];
$stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE statut = 'sortie' AND date_sortie IS NULL");
$stats['autorises'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE DATE(date_sortie) = CURDATE()");
$stats['sorties_aujourdhui'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE statut = 'entree' AND est_charge = 1");
$stats['en_attente_pesage'] = $stmt->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Vigile Sortie - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-yellow-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-yellow-800">
            <i class="fas fa-anchor text-2xl mr-2"></i>
            <span class="text-xl font-bold">Port de BUJUMBURA</span>
        </div>
        
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <p class="text-yellow-300 text-sm font-medium">Enregistreur Sortie Route</p>
            </div>
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-white bg-yellow-800">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="historique.php" class="flex items-center px-4 py-3 text-yellow-200 hover:bg-yellow-800 hover:text-white transition duration-200">
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
                        <p class="text-sm text-gray-500">Enregistreur Sortie Route</p>
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
                <h1 class="text-3xl font-bold text-gray-900">Dashboard Enregistreur Sortie Route</h1>
                <p class="text-gray-600 mt-2">Validation des sorties de camions</p>
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
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Autorisés à Sortir</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['autorises'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-sign-out-alt text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Sorties Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['sorties_aujourdhui'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">En Attente Pesage</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['en_attente_pesage'] ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Camions autorisés à sortir -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Camions Autorisés à Sortir</h2>
                
                <?php if (empty($camions_autorises)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-truck text-4xl text-gray-400 mb-4"></i>
                    <p class="text-gray-500">Aucun camion autorisé à sortir pour le moment</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Camion</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chauffeur</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entrée</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pesage</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($camions_autorises as $camion): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($camion['marque']) ?></div>
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($camion['immatriculation']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($camion['type_camion']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($camion['chauffeur']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d/m/Y H:i', strtotime($camion['date_entree'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $camion['date_pesage'] ? date('d/m/Y H:i', strtotime($camion['date_pesage'])) : 'Non pesé' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                    <button onclick="voirDetails(<?= $camion['id'] ?>)" class="text-blue-600 hover:text-blue-900">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                    <button onclick="validerSortie(<?= $camion['id'] ?>)" class="text-green-600 hover:text-green-900">
                                        <i class="fas fa-check-circle"></i> Valider Sortie
                                    </button>
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
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <h3 class="text-lg font-semibold text-gray-900">Détails du Camion</h3>
                </div>
                
                <div id="detailsContent" class="p-6 overflow-y-auto flex-grow">
                    <!-- Contenu dynamique -->
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function voirDetails(camionId) {
            fetch(`api/camion-details.php?id=${camionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const camion = data.camion;
                        const pesage = data.pesage;
                        const marchandises = data.marchandises;
                        const statutLabel = camion.date_sortie ? 'Sortie validée' : (camion.statut === 'sortie' ? 'En attente de validation (Enregistreur Sortie)' : (camion.statut ? camion.statut.charAt(0).toUpperCase()+camion.statut.slice(1) : '—'));
                        
                        document.getElementById('detailsContent').innerHTML = `
                            <div class="mb-6">
                                <h4 class="text-md font-semibold text-gray-900 mb-2">${camion.marque} - ${camion.immatriculation}</h4>
                                <p class="text-sm text-gray-600">Chauffeur: ${camion.chauffeur} | Agence: ${camion.agence}</p>
                                <p class="text-sm text-gray-600">Statut: ${statutLabel}</p>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Type de Camion</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${camion.type_camion}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date d'Entrée</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${new Date(camion.date_entree).toLocaleString('fr-FR')}</div>
                                </div>
                            </div>
                            
                            ${pesage ? `
                            <div class="mb-6">
                                <h4 class="text-md font-semibold text-gray-900 mb-4">Données de Pesage</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">PTAV (kg)</label>
                                        <div class="px-3 py-2 bg-gray-100 rounded-md">${pesage.ptav}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">PTAC (kg)</label>
                                        <div class="px-3 py-2 bg-gray-100 rounded-md">${pesage.ptac}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">PTRA (kg)</label>
                                        <div class="px-3 py-2 bg-gray-100 rounded-md">${pesage.ptra}</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Charge à l'Essieu (kg)</label>
                                        <div class="px-3 py-2 bg-gray-100 rounded-md">${pesage.charge_essieu || 'N/A'}</div>
                                    </div>
                                </div>
                                <p class="text-sm text-gray-500 mt-2">Pesé le: ${new Date(pesage.date_pesage).toLocaleString('fr-FR')}</p>
                            </div>
                            ` : ''}
                            
                            ${marchandises.length > 0 ? `
                            <div class="mb-6">
                                <h4 class="text-md font-semibold text-gray-900 mb-4">Marchandises</h4>
                                <div class="space-y-2">
                                    ${marchandises.map(m => `
                                        <div class="flex justify-between items-center py-2 border-b">
                                            <span class="text-sm text-gray-900">${m.type_marchandise}</span>
                                            <span class="text-sm text-gray-600">${m.quantite} unités</span>
                                            <span class="text-sm font-medium text-gray-900">${m.poids ? m.poids + ' kg' : 'Non pesé'}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            ` : ''}
                            
                            <div class="flex justify-end space-x-3">
                                <button type="button" onclick="closeDetailsModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded hover:bg-gray-300">
                                    Fermer
                                </button>
                                <form method="POST" action="dashboard.php" class="inline">
                                    <input type="hidden" name="action" value="valider_sortie">
                                    <input type="hidden" name="camion_id" value="${camionId}">
                                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                        <i class="fas fa-check-circle mr-2"></i>Valider Sortie
                                    </button>
                                </form>
                            </div>
                        `;
                        
                        document.getElementById('detailsModal').classList.remove('hidden');
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    Swal.fire({ icon: 'error', title: 'Erreur', text: "Erreur lors du chargement des détails du camion" });
                });
        }

        function validerSortie(camionId) {
            Swal.fire({
                title: 'Valider la sortie ?',
                text: 'Confirmez la validation de sortie de ce camion.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Oui, valider',
                cancelButtonText: 'Annuler'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'dashboard.php';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="valider_sortie">
                        <input type="hidden" name="camion_id" value="${camionId}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
    </script>
</body>
</html>

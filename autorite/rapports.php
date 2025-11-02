<?php
require_once '../includes/auth_check.php';
checkRole(['autorite']);
require_once '../config/database.php';

$user = getCurrentUser();
// Charger la liste des types de marchandises pour filtrer
$db = getDB();
$types = [];
try {
    $stmt = $db->query("SELECT id, nom FROM types_marchandises ORDER BY nom");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $types = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-red-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-red-800">
            <i class="fas fa-anchor text-2xl mr-2"></i>
            <span class="text-xl font-bold">Port de BUJUMBURA</span>
        </div>
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <p class="text-red-300 text-sm font-medium">Autorités</p>
            </div>
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-white hover:bg-red-800">
                <i class="fas fa-chart-bar mr-3"></i>
                Statistiques
            </a>
            <a href="rapports.php" class="flex items-center px-4 py-3 text-white bg-red-800">
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
                        <p class="text-sm text-gray-500">Autorité</p>
                    </div>
                    <a href="../auth/logout.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- Contenu -->
        <main class="p-6">
            <!-- Modal vide -->
            <div id="emptyModal" class="fixed inset-0 bg-black/40 hidden z-50 flex items-center justify-center">
                <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
                    <div class="px-5 py-4 border-b flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 w-full text-center">Résultat vide</h3>
                        <button id="emptyModalClose" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="px-5 py-4">
                        <p class="text-sm text-gray-700 text-center">Aucune donnée trouvée pour la période personnalisée sélectionnée. Veuillez ajuster les dates et réessayer.</p>
                    </div>
                    <div class="px-5 py-3 border-t text-center">
                        <button id="emptyModalOk" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded">OK</button>
                    </div>
                </div>
            </div>
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Rapports</h1>
                <p class="text-gray-600 mt-2">Générez et consultez des statistiques détaillées</p>
            </div>

            <!-- Rapport par type spécifique -->
            <div class="mt-8 mb-10 grid grid-cols-1 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapport par type spécifique</h3>
                    <p class="text-gray-600 text-sm mb-4">Sélectionnez un type de marchandise et une période</p>
                    <form id="customTypeForm" method="get" action="api/export_rapport.php" target="_blank" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                        <input type="hidden" name="report" value="tonnage_type" />
                        <input type="hidden" name="scope" value="custom" />
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600 mb-1">Type de marchandise</label>
                            <select name="type_id" class="w-full border rounded px-3 py-2" required>
                                <option value="" disabled selected>Choisir un type</option>
                                <?php foreach ($types as $t): ?>
                                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600 mb-1">Début</label>
                            <input name="start" type="date" class="w-full border rounded px-3 py-2" required />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600 mb-1">Fin</label>
                            <input name="end" type="date" class="w-full border rounded px-3 py-2" required />
                        </div>
                        <div class="md:col-span-6 flex items-center gap-3 flex-wrap mt-1">
                            <button name="format" value="pdf" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                            <button name="format" value="xlsx" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-excel mr-2"></i>Excel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapport rapide</h3>
                    <p class="text-gray-600 text-sm mb-4">Tonnage par type — Mois courant</p>
                    <div class="flex gap-3">
                        <a target="_blank" href="api/export_rapport.php?report=tonnage_type&scope=month&format=pdf" class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded">
                            <i class="fas fa-file-pdf mr-2"></i> PDF
                        </a>
                        <a target="_blank" href="api/export_rapport.php?report=tonnage_type&scope=month&format=xlsx" class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
                            <i class="fas fa-file-excel mr-2"></i> Excel
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapport rapide</h3>
                    <p class="text-gray-600 text-sm mb-4">Tonnage par type — Année courante</p>
                    <div class="flex gap-3">
                        <a target="_blank" href="api/export_rapport.php?report=tonnage_type&scope=year&format=pdf" class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded">
                            <i class="fas fa-file-pdf mr-2"></i> PDF
                        </a>
                        <a target="_blank" href="api/export_rapport.php?report=tonnage_type&scope=year&format=xlsx" class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
                            <i class="fas fa-file-excel mr-2"></i> Excel
                        </a>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapport personnalisé</h3>
                    <p class="text-gray-600 text-sm mb-4">Tonnage par type — Période au choix</p>
                    <form id="customTonnageForm" method="get" action="api/export_rapport.php" target="_blank" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                        <input type="hidden" name="report" value="tonnage_type" />
                        <input type="hidden" name="scope" value="custom" />
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600 mb-1">Début</label>
                            <input name="start" type="date" class="w-full border rounded px-3 py-2" required />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-600 mb-1">Fin</label>
                            <input name="end" type="date" class="w-full border rounded px-3 py-2" required />
                        </div>
                        <div class="md:col-span-2 flex items-end gap-3 flex-wrap">
                            <button name="format" value="pdf" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                            <button name="format" value="xlsx" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-excel mr-2"></i>Excel</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapports détaillés — Camions</h3>
                    <p class="text-gray-600 text-sm mb-4">Entrés et sortis avec détails</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-800 mb-2">Entrés</p>
                            <form method="get" action="api/export_rapport.php" target="_blank" class="flex items-center gap-2 flex-wrap">
                                <input type="hidden" name="report" value="camions_entree" />
                                <label class="text-sm text-gray-700">Période</label>
                                <select name="scope" class="border rounded px-2 py-1 text-sm">
                                    <option value="month">Mois courant</option>
                                    <option value="year">Année courante</option>
                                </select>
                                <button name="format" value="pdf" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                                <button name="format" value="xlsx" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-excel mr-2"></i>Excel</button>
                            </form>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800 mb-2">Sortis</p>
                            <form method="get" action="api/export_rapport.php" target="_blank" class="flex items-center gap-2 flex-wrap">
                                <input type="hidden" name="report" value="camions_sortie" />
                                <label class="text-sm text-gray-700">Période</label>
                                <select name="scope" class="border rounded px-2 py-1 text-sm">
                                    <option value="month">Mois courant</option>
                                    <option value="year">Année courante</option>
                                </select>
                                <button name="format" value="pdf" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                                <button name="format" value="xlsx" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-excel mr-2"></i>Excel</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapports détaillés — Bateaux</h3>
                    <p class="text-gray-600 text-sm mb-4">Entrés et sortis avec détails</p>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm font-medium text-gray-800 mb-2">Entrés</p>
                            <form method="get" action="api/export_rapport.php" target="_blank" class="flex items-center gap-2 flex-wrap">
                                <input type="hidden" name="report" value="bateaux_entree" />
                                <label class="text-sm text-gray-700">Période</label>
                                <select name="scope" class="border rounded px-2 py-1 text-sm">
                                    <option value="month">Mois courant</option>
                                    <option value="year">Année courante</option>
                                </select>
                                <button name="format" value="pdf" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                                <button name="format" value="xlsx" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-excel mr-2"></i>Excel</button>
                            </form>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800 mb-2">Sortis</p>
                            <form method="get" action="api/export_rapport.php" target="_blank" class="flex items-center gap-2 flex-wrap">
                                <input type="hidden" name="report" value="bateaux_sortie" />
                                <label class="text-sm text-gray-700">Période</label>
                                <select name="scope" class="border rounded px-2 py-1 text-sm">
                                    <option value="month">Mois courant</option>
                                    <option value="year">Année courante</option>
                                </select>
                                <button name="format" value="pdf" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                                <button name="format" value="xlsx" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-excel mr-2"></i>Excel</button>
                            </form>
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
        }

        // Pré-check du rapport personnalisé: si vide, afficher modal et bloquer
        (function () {
            const form = document.getElementById('customTonnageForm');
            if (!form) return;
            const modal = document.getElementById('emptyModal');
            const closeBtns = [document.getElementById('emptyModalClose'), document.getElementById('emptyModalOk')];
            const hideModal = () => modal.classList.add('hidden');
            closeBtns.forEach(btn => btn && btn.addEventListener('click', hideModal));

            form.addEventListener('submit', async (e) => {
                try {
                    e.preventDefault();
                    // Capture clicked button format (pdf/xlsx) before custom submit
                    const submitter = e.submitter;
                    const fmt = (submitter && submitter.name === 'format' && submitter.value) ? submitter.value : 'pdf';
                    const start = form.querySelector('input[name="start"]').value;
                    const end = form.querySelector('input[name="end"]').value;
                    if (!start || !end) { form.submit(); return; }
                    const url = new URL(form.action, window.location.origin);
                    url.searchParams.set('report', 'tonnage_type');
                    url.searchParams.set('scope', 'custom');
                    url.searchParams.set('start', start);
                    url.searchParams.set('end', end);
                    url.searchParams.set('check', '1');
                    const resp = await fetch(url.toString(), { credentials: 'same-origin' });
                    if (!resp.ok) { modal.classList.remove('hidden'); return; }
                    const data = await resp.json();
                    if (data && data.empty) {
                        modal.classList.remove('hidden');
                        return;
                    }
                    // Ensure format is preserved when submitting programmatically
                    let fmtInput = form.querySelector('input[name="format"]');
                    if (!fmtInput) {
                        fmtInput = document.createElement('input');
                        fmtInput.type = 'hidden';
                        fmtInput.name = 'format';
                        form.appendChild(fmtInput);
                    }
                    fmtInput.value = fmt;
                    form.submit();
                } catch (err) {
                    // En cas d'erreur réseau, laisser passer la soumission pour ne pas bloquer
                    form.submit();
                }
            });
        })();

        // Pré-check du rapport par type spécifique
        (function () {
            const form = document.getElementById('customTypeForm');
            if (!form) return;
            const modal = document.getElementById('emptyModal');
            const closeBtns = [document.getElementById('emptyModalClose'), document.getElementById('emptyModalOk')];
            const hideModal = () => modal.classList.add('hidden');
            closeBtns.forEach(btn => btn && btn.addEventListener('click', hideModal));

            form.addEventListener('submit', async (e) => {
                try {
                    e.preventDefault();
                    // Capture clicked button format (pdf/xlsx) before custom submit
                    const submitter = e.submitter;
                    const fmt = (submitter && submitter.name === 'format' && submitter.value) ? submitter.value : 'pdf';
                    const start = form.querySelector('input[name="start"]').value;
                    const end = form.querySelector('input[name="end"]').value;
                    const typeId = form.querySelector('select[name="type_id"]').value;
                    if (!start || !end || !typeId) { form.submit(); return; }
                    const url = new URL(form.action, window.location.origin);
                    url.searchParams.set('report', 'tonnage_type');
                    url.searchParams.set('scope', 'custom');
                    url.searchParams.set('start', start);
                    url.searchParams.set('end', end);
                    url.searchParams.set('type_id', typeId);
                    url.searchParams.set('check', '1');
                    const resp = await fetch(url.toString(), { credentials: 'same-origin' });
                    if (!resp.ok) { modal.classList.remove('hidden'); return; }
                    const data = await resp.json();
                    if (data && data.empty) { modal.classList.remove('hidden'); return; }
                    // Ensure format is preserved when submitting programmatically
                    let fmtInput = form.querySelector('input[name="format"]');
                    if (!fmtInput) {
                        fmtInput = document.createElement('input');
                        fmtInput.type = 'hidden';
                        fmtInput.name = 'format';
                        form.appendChild(fmtInput);
                    }
                    fmtInput.value = fmt;
                    form.submit();
                } catch (err) {
                    form.submit();
                }
            });
        })();
    </script>
</body>
</html>

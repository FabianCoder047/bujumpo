<?php
require_once '../includes/auth_check.php';
checkRole(['admin']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Statistiques générales (alignées avec le dashboard Autorité)
$stats = [];

// Camions
$stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE DATE(date_entree) = CURDATE()");
$stats['camions_entrees_aujourdhui'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE DATE(date_sortie) = CURDATE()");
$stats['camions_sorties_aujourdhui'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM camions WHERE date_sortie IS NULL");
$stats['camions_port'] = $stmt->fetch()['total'];

// Bateaux
$stmt = $db->query("SELECT COUNT(*) as total FROM bateaux WHERE DATE(date_entree) = CURDATE()");
$stats['bateaux_entrees_aujourdhui'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bateaux WHERE DATE(date_sortie) = CURDATE()");
$stats['bateaux_sorties_aujourdhui'] = $stmt->fetch()['total'];

$stmt = $db->query("SELECT COUNT(*) as total FROM bateaux WHERE date_sortie IS NULL");
$stats['bateaux_port'] = $stmt->fetch()['total'];

// Pesages
$stmt = $db->query("SELECT COUNT(*) as total FROM pesages WHERE DATE(date_pesage) = CURDATE()");
$stats['pesages_aujourdhui'] = $stmt->fetch()['total'];

// Répartitions (Entrées vs Sorties) sur période
$start_input = isset($_GET['start']) ? $_GET['start'] : date('Y-01-01');
$end_input = isset($_GET['end']) ? $_GET['end'] : date('Y-12-31');
try { $start_date = new DateTime($start_input); } catch (Exception $e) { $start_date = new DateTime(date('Y-01-01')); }
try { $end_date = new DateTime($end_input); } catch (Exception $e) { $end_date = new DateTime(date('Y-12-31')); }
if ($end_date < $start_date) { $end_date = clone $start_date; }
$start_ts = $start_date->format('Y-m-d 00:00:00');
$end_ts = $end_date->format('Y-m-d 23:59:59');

// Mois de la période
$period = new DatePeriod(
    new DateTime($start_date->format('Y-m-01')),
    new DateInterval('P1M'),
    (new DateTime($end_date->format('Y-m-01')))->modify('+1 month')
);
$monthLabels = [];
foreach ($period as $dt) { $monthLabels[] = $dt->format('Y-m'); }

// Camions par mois
$stmt = $db->prepare("SELECT DATE_FORMAT(date_entree, '%Y-%m') m, COUNT(*) c FROM camions WHERE date_entree BETWEEN ? AND ? GROUP BY m");
$stmt->execute([$start_ts, $end_ts]);
$camions_entree_map = [];
foreach ($stmt->fetchAll() as $r) { $camions_entree_map[$r['m']] = (int)$r['c']; }

$stmt = $db->prepare("SELECT DATE_FORMAT(date_sortie, '%Y-%m') m, COUNT(*) c FROM camions WHERE date_sortie BETWEEN ? AND ? GROUP BY m");
$stmt->execute([$start_ts, $end_ts]);
$camions_sortie_map = [];
foreach ($stmt->fetchAll() as $r) { $camions_sortie_map[$r['m']] = (int)$r['c']; }

// Bateaux par mois
$stmt = $db->prepare("SELECT DATE_FORMAT(b.date_entree, '%Y-%m') m, COUNT(DISTINCT UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''),'/', ''),'.',''),'_',''))) c FROM bateaux b WHERE b.date_entree BETWEEN ? AND ? AND (b.date_sortie IS NULL OR b.date_entree < b.date_sortie) GROUP BY m");
$stmt->execute([$start_ts, $end_ts]);
$bateaux_entree_map = [];
foreach ($stmt->fetchAll() as $r) { $bateaux_entree_map[$r['m']] = (int)$r['c']; }

$stmt = $db->prepare("SELECT DATE_FORMAT(b.date_sortie, '%Y-%m') m, COUNT(DISTINCT UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''),'/', ''),'.',''),'_',''))) c FROM bateaux b WHERE b.date_sortie BETWEEN ? AND ? GROUP BY m");
$stmt->execute([$start_ts, $end_ts]);
$bateaux_sortie_map = [];
foreach ($stmt->fetchAll() as $r) { $bateaux_sortie_map[$r['m']] = (int)$r['c']; }

$series_camions_entrees = [];
$series_camions_sorties = [];
$series_bateaux_entrees = [];
$series_bateaux_sorties = [];
foreach ($monthLabels as $m) {
    $series_camions_entrees[] = $camions_entree_map[$m] ?? 0;
    $series_camions_sorties[] = $camions_sortie_map[$m] ?? 0;
    $series_bateaux_entrees[] = $bateaux_entree_map[$m] ?? 0;
    $series_bateaux_sorties[] = $bateaux_sortie_map[$m] ?? 0;
}

// Année cible pour graphes annuels
$year_for_annual = ($start_date->format('Y') === $end_date->format('Y')) ? (int)$start_date->format('Y') : (int)date('Y');
$annualMonthLabels = [];
for ($m = 1; $m <= 12; $m++) { $annualMonthLabels[] = sprintf('%04d-%02d', $year_for_annual, $m); }

// Séries annuelles
$stmt = $db->prepare("SELECT DATE_FORMAT(date_entree, '%Y-%m') m, COUNT(*) c FROM camions WHERE YEAR(date_entree) = ? GROUP BY m");
$stmt->execute([$year_for_annual]);
$camions_entree_year_map = [];
foreach ($stmt->fetchAll() as $r) { $camions_entree_year_map[$r['m']] = (int)$r['c']; }

$stmt = $db->prepare("SELECT DATE_FORMAT(date_sortie, '%Y-%m') m, COUNT(*) c FROM camions WHERE YEAR(date_sortie) = ? GROUP BY m");
$stmt->execute([$year_for_annual]);
$camions_sortie_year_map = [];
foreach ($stmt->fetchAll() as $r) { $camions_sortie_year_map[$r['m']] = (int)$r['c']; }

$stmt = $db->prepare("SELECT DATE_FORMAT(b.date_entree, '%Y-%m') m, COUNT(DISTINCT UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''),'/', ''),'.',''),'_',''))) c FROM bateaux b WHERE YEAR(b.date_entree) = ? AND (b.date_sortie IS NULL OR b.date_entree < b.date_sortie) GROUP BY m");
$stmt->execute([$year_for_annual]);
$bateaux_entree_year_map = [];
foreach ($stmt->fetchAll() as $r) { $bateaux_entree_year_map[$r['m']] = (int)$r['c']; }

$stmt = $db->prepare("SELECT DATE_FORMAT(b.date_sortie, '%Y-%m') m, COUNT(DISTINCT UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''),'/', ''),'.',''),'_',''))) c FROM bateaux b WHERE YEAR(b.date_sortie) = ? GROUP BY m");
$stmt->execute([$year_for_annual]);
$bateaux_sortie_year_map = [];
foreach ($stmt->fetchAll() as $r) { $bateaux_sortie_year_map[$r['m']] = (int)$r['c']; }

$series_camions_entrees_annee = [];
$series_camions_sorties_annee = [];
$series_bateaux_entrees_annee = [];
$series_bateaux_sorties_annee = [];
foreach ($annualMonthLabels as $m) {
    $series_camions_entrees_annee[] = $camions_entree_year_map[$m] ?? 0;
    $series_camions_sorties_annee[] = $camions_sortie_year_map[$m] ?? 0;
    $series_bateaux_entrees_annee[] = $bateaux_entree_year_map[$m] ?? 0;
    $series_bateaux_sorties_annee[] = $bateaux_sortie_year_map[$m] ?? 0;
}

// Totaux période
$stmt = $db->prepare("SELECT COUNT(*) t FROM camions WHERE date_entree BETWEEN ? AND ?");
$stmt->execute([$start_ts, $end_ts]);
$total_camions_entree_periode = (int)$stmt->fetch()['t'];

$stmt = $db->prepare("SELECT COUNT(*) t FROM camions WHERE date_sortie BETWEEN ? AND ?");
$stmt->execute([$start_ts, $end_ts]);
$total_camions_sortie_periode = (int)$stmt->fetch()['t'];
$stmt = $db->prepare("SELECT COUNT(DISTINCT UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''),'/', ''),'.',''),'_',''))) t FROM bateaux b WHERE b.date_entree BETWEEN ? AND ? AND (b.date_sortie IS NULL OR b.date_entree < b.date_sortie)");
$stmt->execute([$start_ts, $end_ts]);
$total_bateaux_entree_periode = (int)$stmt->fetch()['t'];
$stmt = $db->prepare("SELECT COUNT(DISTINCT UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''),'/', ''),'.',''),'_',''))) t FROM bateaux b WHERE b.date_sortie BETWEEN ? AND ?");
$stmt->execute([$start_ts, $end_ts]);
$total_bateaux_sortie_periode = (int)$stmt->fetch()['t'];

// Répartition par type pour le mois courant (ignorer les filtres de recherche)
$month_start = date('Y-m-01 00:00:00');
$month_end = date('Y-m-t 23:59:59');
$month_type_map = [];
// Camions - Entrée (mois)
$stmt = $db->prepare("SELECT tm.nom t, SUM(mc.poids) s
    FROM marchandises_camions mc
    JOIN types_marchandises tm ON tm.id = mc.type_marchandise_id
    JOIN camions c ON c.id = mc.camion_id
    WHERE (mc.mouvement IS NULL OR mc.mouvement = 'entree')
      AND mc.poids IS NOT NULL
      AND c.date_entree BETWEEN ? AND ?
    GROUP BY t");
$stmt->execute([$month_start, $month_end]);
foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($month_type_map[$t])) $month_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $month_type_map[$t]['entree'] += $s; }
// Camions - Sortie (mois)
$stmt = $db->prepare("SELECT tm.nom t, SUM(mc.poids) s
    FROM marchandises_camions mc
    JOIN types_marchandises tm ON tm.id = mc.type_marchandise_id
    JOIN camions c ON c.id = mc.camion_id
    WHERE mc.mouvement = 'sortie'
      AND mc.poids IS NOT NULL
      AND c.date_sortie BETWEEN ? AND ?
    GROUP BY t");
$stmt->execute([$month_start, $month_end]);
foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($month_type_map[$t])) $month_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $month_type_map[$t]['sortie'] += $s; }
// Bateaux - Entrée (mois)
$stmt = $db->prepare("SELECT tm.nom t, SUM(mb.poids) s FROM marchandises_bateaux mb JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id WHERE mb.mouvement='entree' AND mb.poids IS NOT NULL AND mb.created_at BETWEEN ? AND ? GROUP BY t");
$stmt->execute([$month_start, $month_end]);
foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($month_type_map[$t])) $month_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $month_type_map[$t]['entree'] += $s; }
// Bateaux - Sortie (mois)
$stmt = $db->prepare("SELECT tm.nom t, SUM(mb.poids) s FROM marchandises_bateaux mb JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id WHERE mb.mouvement='sortie' AND mb.poids IS NOT NULL AND mb.created_at BETWEEN ? AND ? GROUP BY t");
$stmt->execute([$month_start, $month_end]);
foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($month_type_map[$t])) $month_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $month_type_map[$t]['sortie'] += $s; }

// Préparer séries pour Chart.js (mois)
ksort($month_type_map, SORT_NATURAL | SORT_FLAG_CASE);
$month_type_labels = array_keys($month_type_map);
$month_type_entrees = array_map(fn($v)=>round((float)$v['entree'],2), array_values($month_type_map));
$month_type_sorties = array_map(fn($v)=>round((float)$v['sortie'],2), array_values($month_type_map));

// Répartition par type pour l'année courante (ignorer les filtres de recherche)
$current_year = (int)date('Y');
$year_type_map = [];
// Camions - Entrée (année)
$stmt = $db->prepare("SELECT tm.nom t, SUM(mc.poids) s
    FROM marchandises_camions mc
    JOIN types_marchandises tm ON tm.id = mc.type_marchandise_id
    JOIN camions c ON c.id = mc.camion_id
    WHERE (mc.mouvement IS NULL OR mc.mouvement = 'entree')
      AND mc.poids IS NOT NULL
      AND YEAR(c.date_entree) = ?
    GROUP BY t");
$stmt->execute([$current_year]);
foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($year_type_map[$t])) $year_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $year_type_map[$t]['entree'] += $s; }
// Camions - Sortie (année)
$stmt = $db->prepare("SELECT tm.nom t, SUM(mc.poids) s
    FROM marchandises_camions mc
    JOIN types_marchandises tm ON tm.id = mc.type_marchandise_id
    JOIN camions c ON c.id = mc.camion_id
    WHERE mc.mouvement = 'sortie'
      AND mc.poids IS NOT NULL
      AND YEAR(c.date_sortie) = ?
    GROUP BY t");
$stmt->execute([$current_year]);
foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($year_type_map[$t])) $year_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $year_type_map[$t]['sortie'] += $s; }
// Bateaux - Entrée (année)
$stmt = $db->prepare("SELECT tm.nom t, SUM(mb.poids) s FROM marchandises_bateaux mb JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id WHERE mb.mouvement='entree' AND mb.poids IS NOT NULL AND YEAR(mb.created_at)=? GROUP BY t");
$stmt->execute([$current_year]);
foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($year_type_map[$t])) $year_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $year_type_map[$t]['entree'] += $s; }
// Bateaux - Sortie (année)
$stmt = $db->prepare("SELECT tm.nom t, SUM(mb.poids) s FROM marchandises_bateaux mb JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id WHERE mb.mouvement='sortie' AND mb.poids IS NOT NULL AND YEAR(mb.created_at)=? GROUP BY t");
$stmt->execute([$current_year]);
foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($year_type_map[$t])) $year_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $year_type_map[$t]['sortie'] += $s; }

// Préparer séries pour Chart.js (année)
ksort($year_type_map, SORT_NATURAL | SORT_FLAG_CASE);
$year_type_labels = array_keys($year_type_map);
$year_type_entrees = array_map(fn($v)=>round((float)$v['entree'],2), array_values($year_type_map));
$year_type_sorties = array_map(fn($v)=>round((float)$v['sortie'],2), array_values($year_type_map));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Administration - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100">
    <!-- Sidebar -->
    <div class="fixed inset-y-0 left-0 w-64 bg-blue-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-blue-800">
            <i class="fas fa-anchor text-2xl mr-2"></i>
            <span class="text-xl font-semibold">Port de Bujumbura</span>
        </div>
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <p class="text-blue-300 text-sm font-medium">Administration</p>
            </div>
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-white bg-blue-800">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            
            <a href="types.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
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
        <main class="p-6">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Dashboard Administration</h1>
                <p class="text-gray-600 mt-2">Statistiques et surveillance du port</p>
            </div>

            <form class="bg-white rounded-lg shadow p-6 mb-8 grid grid-cols-1 md:grid-cols-4 gap-4" method="get">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Début</label>
                    <input type="date" name="start" value="<?= htmlspecialchars($start_date->format('Y-m-d')) ?>" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Fin</label>
                    <input type="date" name="end" value="<?= htmlspecialchars($end_date->format('Y-m-d')) ?>" class="w-full border rounded px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Raccourci Année</label>
                    <select id="yearSelect" class="w-full border rounded px-3 py-2">
                        <?php for ($y = (int)date('Y') + 1; $y >= (int)date('Y') - 5; $y--) : ?>
                            <option value="<?= $y ?>" <?= ($start_date->format('Y') == $y && $end_date->format('Y') == $y && $start_date->format('m-d')=='01-01' && $end_date->format('m-d')=='12-31') ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded">Appliquer</button>
                </div>
            </form>

            <!-- Statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-truck text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Camions Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['camions_entrees_aujourdhui'] ?></p>
                            <p class="text-xs text-gray-500">Entrées</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-ship text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Bateaux Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['bateaux_entrees_aujourdhui'] ?></p>
                            <p class="text-xs text-gray-500">Entrées</p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                            <i class="fas fa-weight-hanging text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Pesages Aujourd'hui</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['pesages_aujourdhui'] ?></p>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-anchor text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Au Port</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $stats['camions_port'] + $stats['bateaux_port'] ?></p>
                            <p class="text-xs text-gray-500">Véhicules</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-truck text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Camions Période</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $total_camions_entree_periode ?></p>
                            <p class="text-xs text-gray-500">Entrées</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-green-100 text-green-600">
                            <i class="fas fa-truck-loading text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Camions Période</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $total_camions_sortie_periode ?></p>
                            <p class="text-xs text-gray-500">Sorties</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-ship text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Bateaux Période</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $total_bateaux_entree_periode ?></p>
                            <p class="text-xs text-gray-500">Entrées</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-ship text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <p class="text-sm font-medium text-gray-600">Bateaux Période</p>
                            <p class="text-2xl font-semibold text-gray-900"><?= $total_bateaux_sortie_periode ?></p>
                            <p class="text-xs text-gray-500">Sorties</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Graphiques -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Camions par Mois (Entrées vs Sorties)</h3>
                    <canvas id="camionsMonthlyChart" width="400" height="200"></canvas>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Bateaux par Mois (Entrées vs Sorties)</h3>
                    <canvas id="bateauxMonthlyChart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Répartition Tonnage - Mois courant (par type)</h3>
                    <canvas id="monthTypeChart" height="240"></canvas>
                </div>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Répartition Tonnage - Année courante (par type)</h3>
                    <canvas id="yearTypeChart" height="240"></canvas>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        }

        const yearSelect = document.getElementById('yearSelect');
        if (yearSelect) {
            yearSelect.addEventListener('change', () => {
                const y = yearSelect.value;
                const start = document.querySelector('input[name="start"]');
                const end = document.querySelector('input[name="end"]');
                if (start && end) {
                    start.value = `${y}-01-01`;
                    end.value = `${y}-12-31`;
                }
            });
        }

        // Données injectées depuis PHP
        const months = <?= json_encode($monthLabels) ?>;
        const dataCamionsEntree = <?= json_encode($series_camions_entrees) ?>;
        const dataCamionsSortie = <?= json_encode($series_camions_sorties) ?>;
        const dataBateauxEntree = <?= json_encode($series_bateaux_entrees) ?>;
        const dataBateauxSortie = <?= json_encode($series_bateaux_sorties) ?>;

        const annualMonths = <?= json_encode($annualMonthLabels) ?>;
        const yearLabel = <?= json_encode($year_for_annual) ?>;
        const dataCamionsEntreeAnnee = <?= json_encode($series_camions_entrees_annee) ?>;
        const dataCamionsSortieAnnee = <?= json_encode($series_camions_sorties_annee) ?>;
        const dataBateauxEntreeAnnee = <?= json_encode($series_bateaux_entrees_annee) ?>;
        const dataBateauxSortieAnnee = <?= json_encode($series_bateaux_sorties_annee) ?>;

        // Graphiques mensuels
        const camionsCtx = document.getElementById('camionsMonthlyChart');
        if (camionsCtx) {
            new Chart(camionsCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        { label: 'Entrées', data: dataCamionsEntree, backgroundColor: 'rgba(34, 197, 94, 0.7)' },
                        { label: 'Sorties', data: dataCamionsSortie, backgroundColor: 'rgba(234, 179, 8, 0.7)' }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
            });
        }

        const bateauxCtx = document.getElementById('bateauxMonthlyChart');
        if (bateauxCtx) {
            new Chart(bateauxCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [
                        { label: 'Entrées', data: dataBateauxEntree, backgroundColor: 'rgba(59, 130, 246, 0.7)' },
                        { label: 'Sorties', data: dataBateauxSortie, backgroundColor: 'rgba(147, 51, 234, 0.7)' }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
            });
        }

        // Graphes de répartition par type
        const monthTypeLabels = <?= json_encode(array_values($month_type_labels)) ?>;
        const monthTypeEntrees = <?= json_encode(array_values($month_type_entrees)) ?>;
        const monthTypeSorties = <?= json_encode(array_values($month_type_sorties)) ?>;

        const yearTypeLabels = <?= json_encode(array_values($year_type_labels)) ?>;
        const yearTypeEntrees = <?= json_encode(array_values($year_type_entrees)) ?>;
        const yearTypeSorties = <?= json_encode(array_values($year_type_sorties)) ?>;

        const monthTypeCanvas = document.getElementById('monthTypeChart');
        if (monthTypeCanvas) {
            new Chart(monthTypeCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: monthTypeLabels,
                    datasets: [
                        { label: 'Entrées', data: monthTypeEntrees, backgroundColor: 'rgba(34,197,94,0.7)' },
                        { label: 'Sorties', data: monthTypeSorties, backgroundColor: 'rgba(234,179,8,0.7)' }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
            });
        }

        const yearTypeCanvas = document.getElementById('yearTypeChart');
        if (yearTypeCanvas) {
            new Chart(yearTypeCanvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: yearTypeLabels,
                    datasets: [
                        { label: 'Entrées', data: yearTypeEntrees, backgroundColor: 'rgba(59,130,246,0.7)' },
                        { label: 'Sorties', data: yearTypeSorties, backgroundColor: 'rgba(147,51,234,0.7)' }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
            });
        }
    </script>
</body>
</html>

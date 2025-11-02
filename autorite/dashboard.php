<?php
require_once '../includes/auth_check.php';
checkRole(['autorite']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Statistiques générales
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

// Filtres
$mode = isset($_GET['mode']) && in_array($_GET['mode'], ['tous','camion','bateau'], true) ? $_GET['mode'] : 'tous';
$mouvement = isset($_GET['mouvement']) && in_array($_GET['mouvement'], ['tous','entree','sortie'], true) ? $_GET['mouvement'] : 'tous';
$typeId = isset($_GET['type_id']) && $_GET['type_id'] !== '' ? (int)$_GET['type_id'] : null;

// Répartitions (Entrées vs Sorties) sur jour, mois, année courants
// Jour (totaux camions + bateaux)
$rep_jour_camions_entrees = (int)$db->query("SELECT COUNT(*) t FROM camions WHERE DATE(date_entree)=CURDATE()")->fetch()['t'];
$rep_jour_bateaux_entrees = (int)$db->query("SELECT COUNT(*) t FROM bateaux WHERE DATE(date_entree)=CURDATE()")->fetch()['t'];
$rep_jour_camions_sorties = (int)$db->query("SELECT COUNT(*) t FROM camions WHERE DATE(date_sortie)=CURDATE()")->fetch()['t'];
$rep_jour_bateaux_sorties = (int)$db->query("SELECT COUNT(*) t FROM bateaux WHERE DATE(date_sortie)=CURDATE()")->fetch()['t'];
$rep_jour_entrees = $rep_jour_camions_entrees + $rep_jour_bateaux_entrees;
$rep_jour_sorties = $rep_jour_camions_sorties + $rep_jour_bateaux_sorties;
// Mois courant
$rep_mois_camions_entrees = (int)$db->query("SELECT COUNT(*) t FROM camions WHERE YEAR(date_entree)=YEAR(CURDATE()) AND MONTH(date_entree)=MONTH(CURDATE())")->fetch()['t'];
$rep_mois_bateaux_entrees = (int)$db->query("SELECT COUNT(*) t FROM bateaux WHERE YEAR(date_entree)=YEAR(CURDATE()) AND MONTH(date_entree)=MONTH(CURDATE())")->fetch()['t'];
$rep_mois_camions_sorties = (int)$db->query("SELECT COUNT(*) t FROM camions WHERE YEAR(date_sortie)=YEAR(CURDATE()) AND MONTH(date_sortie)=MONTH(CURDATE())")->fetch()['t'];
$rep_mois_bateaux_sorties = (int)$db->query("SELECT COUNT(*) t FROM bateaux WHERE YEAR(date_sortie)=YEAR(CURDATE()) AND MONTH(date_sortie)=MONTH(CURDATE())")->fetch()['t'];
$rep_mois_entrees = $rep_mois_camions_entrees + $rep_mois_bateaux_entrees;
$rep_mois_sorties = $rep_mois_camions_sorties + $rep_mois_bateaux_sorties;
// Année courante
$rep_annee_camions_entrees = (int)$db->query("SELECT COUNT(*) t FROM camions WHERE YEAR(date_entree)=YEAR(CURDATE())")->fetch()['t'];
$rep_annee_bateaux_entrees = (int)$db->query("SELECT COUNT(*) t FROM bateaux WHERE YEAR(date_entree)=YEAR(CURDATE())")->fetch()['t'];
$rep_annee_camions_sorties = (int)$db->query("SELECT COUNT(*) t FROM camions WHERE YEAR(date_sortie)=YEAR(CURDATE())")->fetch()['t'];
$rep_annee_bateaux_sorties = (int)$db->query("SELECT COUNT(*) t FROM bateaux WHERE YEAR(date_sortie)=YEAR(CURDATE())")->fetch()['t'];
$rep_annee_entrees = $rep_annee_camions_entrees + $rep_annee_bateaux_entrees;
$rep_annee_sorties = $rep_annee_camions_sorties + $rep_annee_bateaux_sorties;

// Filtres période
$start_input = isset($_GET['start']) ? $_GET['start'] : date('Y-01-01');
$end_input = isset($_GET['end']) ? $_GET['end'] : date('Y-12-31');
try {
    $start_date = new DateTime($start_input);
} catch (Exception $e) {
    $start_date = new DateTime(date('Y-01-01'));
}
try {
    $end_date = new DateTime($end_input);
} catch (Exception $e) {
    $end_date = new DateTime(date('Y-12-31'));
}
if ($end_date < $start_date) {
    $end_date = clone $start_date;
}
$start_ts = $start_date->format('Y-m-d 00:00:00');
$end_ts = $end_date->format('Y-m-d 23:59:59');

// Mois de la période
$period = new DatePeriod(
    new DateTime($start_date->format('Y-m-01')),
    new DateInterval('P1M'),
    (new DateTime($end_date->format('Y-m-01')))->modify('+1 month')
);
$monthLabels = [];
foreach ($period as $dt) {
    $monthLabels[] = $dt->format('Y-m');
}

// Camions par mois (respecte mode/type)
$camions_entree_map = [];
$camions_sortie_map = [];
if ($mode === 'tous' || $mode === 'camion') {
    $typeFilterExists = $typeId ? " AND EXISTS (SELECT 1 FROM marchandises_camions mc WHERE mc.camion_id=c.id AND mc.type_marchandise_id=?)" : "";
    $stmt = $db->prepare("SELECT DATE_FORMAT(c.date_entree, '%Y-%m') m, COUNT(*) c FROM camions c WHERE c.date_entree BETWEEN ? AND ?" . $typeFilterExists . " GROUP BY m");
    $params = [$start_ts, $end_ts]; if ($typeId) { $params[] = $typeId; }
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) { $camions_entree_map[$r['m']] = (int)$r['c']; }

    $stmt = $db->prepare("SELECT DATE_FORMAT(c.date_sortie, '%Y-%m') m, COUNT(*) c FROM camions c WHERE c.date_sortie BETWEEN ? AND ?" . $typeFilterExists . " GROUP BY m");
    $params = [$start_ts, $end_ts]; if ($typeId) { $params[] = $typeId; }
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) { $camions_sortie_map[$r['m']] = (int)$r['c']; }
}

// Bateaux par mois (respecte mode/type)
$bateaux_entree_map = [];
$bateaux_sortie_map = [];
if ($mode === 'tous' || $mode === 'bateau') {
    $typeFilterExistsB = $typeId ? " AND EXISTS (SELECT 1 FROM marchandises_bateaux mb WHERE mb.bateau_id=b.id AND mb.type_marchandise_id=?)" : "";
    $stmt = $db->prepare("SELECT DATE_FORMAT(b.date_entree, '%Y-%m') m, COUNT(DISTINCT UPPER(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''))) c FROM bateaux b WHERE b.date_entree BETWEEN ? AND ? AND (b.date_sortie IS NULL OR b.date_entree < b.date_sortie)" . $typeFilterExistsB . " GROUP BY m");
    $params = [$start_ts, $end_ts]; if ($typeId) { $params[] = $typeId; }
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) { $bateaux_entree_map[$r['m']] = (int)$r['c']; }

    $stmt = $db->prepare("SELECT DATE_FORMAT(b.date_sortie, '%Y-%m') m, COUNT(DISTINCT UPPER(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''))) c FROM bateaux b WHERE b.date_sortie BETWEEN ? AND ?" . $typeFilterExistsB . " GROUP BY m");
    $params = [$start_ts, $end_ts]; if ($typeId) { $params[] = $typeId; }
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $r) { $bateaux_sortie_map[$r['m']] = (int)$r['c']; }
}

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

// Année cible pour les graphes annuels (s'adapte à la recherche si même année sélectionnée)
$year_for_annual = ($start_date->format('Y') === $end_date->format('Y')) ? (int)$start_date->format('Y') : (int)date('Y');
$annualMonthLabels = [];
for ($m = 1; $m <= 12; $m++) {
    $annualMonthLabels[] = sprintf('%04d-%02d', $year_for_annual, $m);
}

// Séries annuelles (par mois de l'année sélectionnée)
$stmt = $db->prepare("SELECT DATE_FORMAT(date_entree, '%Y-%m') m, COUNT(*) c FROM camions WHERE YEAR(date_entree) = ? GROUP BY m");
$stmt->execute([$year_for_annual]);
$camions_entree_year_map = [];
foreach ($stmt->fetchAll() as $r) { $camions_entree_year_map[$r['m']] = (int)$r['c']; }

$stmt = $db->prepare("SELECT DATE_FORMAT(date_sortie, '%Y-%m') m, COUNT(*) c FROM camions WHERE YEAR(date_sortie) = ? GROUP BY m");
$stmt->execute([$year_for_annual]);
$camions_sortie_year_map = [];
foreach ($stmt->fetchAll() as $r) { $camions_sortie_year_map[$r['m']] = (int)$r['c']; }

$stmt = $db->prepare("SELECT DATE_FORMAT(b.date_entree, '%Y-%m') m, COUNT(DISTINCT UPPER(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''))) c FROM bateaux b WHERE YEAR(b.date_entree) = ? AND (b.date_sortie IS NULL OR b.date_entree < b.date_sortie) GROUP BY m");
$stmt->execute([$year_for_annual]);
$bateaux_entree_year_map = [];
foreach ($stmt->fetchAll() as $r) { $bateaux_entree_year_map[$r['m']] = (int)$r['c']; }

$stmt = $db->prepare("SELECT DATE_FORMAT(b.date_sortie, '%Y-%m') m, COUNT(DISTINCT UPPER(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''))) c FROM bateaux b WHERE YEAR(b.date_sortie) = ? GROUP BY m");
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

// Totaux période (respecte mode et type)
$total_camions_entree_periode = 0;
$total_camions_sortie_periode = 0;
if ($mode === 'tous' || $mode === 'camion') {
    $typeFilterExists = $typeId ? " AND EXISTS (SELECT 1 FROM marchandises_camions mc WHERE mc.camion_id=c.id AND mc.type_marchandise_id=?)" : "";
    $stmt = $db->prepare("SELECT COUNT(*) t FROM camions c WHERE c.date_entree BETWEEN ? AND ?" . $typeFilterExists);
    $params = [$start_ts, $end_ts]; if ($typeId) { $params[] = $typeId; }
    $stmt->execute($params);
    $total_camions_entree_periode = (int)$stmt->fetch()['t'];
    $stmt = $db->prepare("SELECT COUNT(*) t FROM camions c WHERE c.date_sortie BETWEEN ? AND ?" . $typeFilterExists);
    $params = [$start_ts, $end_ts]; if ($typeId) { $params[] = $typeId; }
    $stmt->execute($params);
    $total_camions_sortie_periode = (int)$stmt->fetch()['t'];
}
$total_bateaux_entree_periode = 0;
$total_bateaux_sortie_periode = 0;
if ($mode === 'tous' || $mode === 'bateau') {
    $typeFilterExistsB = $typeId ? " AND EXISTS (SELECT 1 FROM marchandises_bateaux mb WHERE mb.bateau_id=b.id AND mb.type_marchandise_id=?)" : "";
    $stmt = $db->prepare("SELECT COUNT(DISTINCT UPPER(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''))) t FROM bateaux b WHERE b.date_entree BETWEEN ? AND ? AND (b.date_sortie IS NULL OR b.date_entree < b.date_sortie)" . $typeFilterExistsB);
    $params = [$start_ts, $end_ts]; if ($typeId) { $params[] = $typeId; }
    $stmt->execute($params);
    $total_bateaux_entree_periode = (int)$stmt->fetch()['t'];
    $stmt = $db->prepare("SELECT COUNT(DISTINCT UPPER(REPLACE(REPLACE(TRIM(b.immatriculation),' ',''),'-',''))) t FROM bateaux b WHERE b.date_sortie BETWEEN ? AND ?" . $typeFilterExistsB);
    $params = [$start_ts, $end_ts]; if ($typeId) { $params[] = $typeId; }
    $stmt->execute($params);
    $total_bateaux_sortie_periode = (int)$stmt->fetch()['t'];
}

// Répartition par type — Mois (respecte filtres et recherche)
if ($start_date->format('Y-m') === $end_date->format('Y-m')) {
    $month_start = $start_date->format('Y-m-01 00:00:00');
    $month_end = (new DateTime($start_date->format('Y-m-01')))->modify('last day of this month')->format('Y-m-d 23:59:59');
} else {
    $month_start = date('Y-m-01 00:00:00');
    $month_end = date('Y-m-t 23:59:59');
}
$month_type_map = [];
// Camions - Entrée/Sortie
if ($mode === 'tous' || $mode === 'camion') {
    if ($mouvement === 'tous' || $mouvement === 'entree') {
        $stmt = $db->prepare("SELECT tm.nom t, SUM(mc.poids) s
            FROM marchandises_camions mc
            JOIN types_marchandises tm ON tm.id = mc.type_marchandise_id
            JOIN camions c ON c.id = mc.camion_id
            WHERE (mc.mouvement IS NULL OR mc.mouvement = 'entree')
              AND mc.poids IS NOT NULL
              AND c.date_entree BETWEEN ? AND ?" . ($typeId ? " AND mc.type_marchandise_id = ?" : "") . "
            GROUP BY t");
        $params = [$month_start, $month_end]; if ($typeId) { $params[] = $typeId; }
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($month_type_map[$t])) $month_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $month_type_map[$t]['entree'] += $s; }
    }
    if ($mouvement === 'tous' || $mouvement === 'sortie') {
        $stmt = $db->prepare("SELECT tm.nom t, SUM(mc.poids) s
            FROM marchandises_camions mc
            JOIN types_marchandises tm ON tm.id = mc.type_marchandise_id
            JOIN camions c ON c.id = mc.camion_id
            WHERE mc.mouvement = 'sortie'
              AND mc.poids IS NOT NULL
              AND c.date_sortie BETWEEN ? AND ?" . ($typeId ? " AND mc.type_marchandise_id = ?" : "") . "
            GROUP BY t");
        $params = [$month_start, $month_end]; if ($typeId) { $params[] = $typeId; }
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($month_type_map[$t])) $month_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $month_type_map[$t]['sortie'] += $s; }
    }
}
// Bateaux - Entrée/Sortie
if ($mode === 'tous' || $mode === 'bateau') {
    if ($mouvement === 'tous' || $mouvement === 'entree') {
        $stmt = $db->prepare("SELECT tm.nom t, SUM(mb.poids) s FROM marchandises_bateaux mb JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id JOIN bateaux b ON b.id = mb.bateau_id WHERE mb.mouvement='entree' AND mb.poids IS NOT NULL AND b.date_entree BETWEEN ? AND ?" . ($typeId ? " AND mb.type_marchandise_id = ?" : "") . " GROUP BY t");
        $params = [$month_start, $month_end]; if ($typeId) { $params[] = $typeId; }
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($month_type_map[$t])) $month_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $month_type_map[$t]['entree'] += $s; }
    }
    if ($mouvement === 'tous' || $mouvement === 'sortie') {
        $stmt = $db->prepare("SELECT tm.nom t, SUM(mb.poids) s FROM marchandises_bateaux mb JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id JOIN bateaux b ON b.id = mb.bateau_id WHERE mb.mouvement='sortie' AND mb.poids IS NOT NULL AND b.date_sortie BETWEEN ? AND ?" . ($typeId ? " AND mb.type_marchandise_id = ?" : "") . " GROUP BY t");
        $params = [$month_start, $month_end]; if ($typeId) { $params[] = $typeId; }
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($month_type_map[$t])) $month_type_map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $month_type_map[$t]['sortie'] += $s; }
    }
}

// Préparer séries pour Chart.js (mois)
// Tri alphabétique des types (pas d'addition Entrée+Sortie)
ksort($month_type_map, SORT_NATURAL | SORT_FLAG_CASE);
$month_type_labels = array_keys($month_type_map);
$month_type_entrees = array_map(fn($v)=>round((float)$v['entree'],2), array_values($month_type_map));
$month_type_sorties = array_map(fn($v)=>round((float)$v['sortie'],2), array_values($month_type_map));

// Répartition par type pour l'année courante (ignorer les filtres de recherche)
$current_year = (int)date('Y');
$year_type_map = [];
// Camions - Entrée (année) — par année de date d'entrée
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
// Camions - Sortie (année) — par année de date de sortie
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
// Tri alphabétique des types (pas d'addition Entrée+Sortie)
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
    <title>Dashboard Autorités - Port de BUJUMBURA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-white bg-red-800">
                <i class="fas fa-chart-bar mr-3"></i>
                Statistiques
            </a>
            <a href="rapports.php" class="flex items-center px-4 py-3 text-white hover:bg-red-800">
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
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Dashboard Autorités</h1>
                <p class="text-gray-600 mt-2">Statistiques et surveillance du port</p>
            </div>

            <form class="bg-white rounded-lg shadow p-6 mb-8 grid grid-cols-1 md:grid-cols-6 gap-4" method="get">
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
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Mode</label>
                    <select name="mode" class="w-full border rounded px-3 py-2">
                        <option value="tous" <?= $mode==='tous'?'selected':''; ?>>Tous</option>
                        <option value="camion" <?= $mode==='camion'?'selected':''; ?>>Camions</option>
                        <option value="bateau" <?= $mode==='bateau'?'selected':''; ?>>Bateaux</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Mouvement</label>
                    <select name="mouvement" class="w-full border rounded px-3 py-2">
                        <option value="tous" <?= $mouvement==='tous'?'selected':''; ?>>Tous</option>
                        <option value="entree" <?= $mouvement==='entree'?'selected':''; ?>>Entrée</option>
                        <option value="sortie" <?= $mouvement==='sortie'?'selected':''; ?>>Sortie</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Type de marchandise</label>
                    <select name="type_id" class="w-full border rounded px-3 py-2">
                        <option value="">Tous</option>
                        <?php
                        try { $typesStmt = $db->query("SELECT id, nom FROM types_marchandises ORDER BY nom"); $typesList = $typesStmt->fetchAll(); } catch (Exception $e) { $typesList = []; }
                        foreach ($typesList as $t) { $sel = ($typeId === (int)$t['id']) ? 'selected' : ''; echo '<option value="' . (int)$t['id'] . '" ' . $sel . '>' . htmlspecialchars($t['nom']) . '</option>'; }
                        ?>
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Appliquer</button>
                    <a href="dashboard.php" class="px-4 py-2 bg-gray-100 text-gray-800 rounded border border-gray-300">Réinitialiser</a>
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
            sidebar.classList.toggle('-translate-x-full');
        }

        const yearSelect = document.getElementById('yearSelect');
        if (yearSelect) {
            yearSelect.addEventListener('change', () => {
                const y = yearSelect.value;
                const start = document.querySelector('input[name="start"]');
                const end = document.querySelector('input[name="end"]');
                start.value = `${y}-01-01`;
                end.value = `${y}-12-31`;
            });
        }

        const months = <?= json_encode($monthLabels) ?>;
        let dataCamionsEntree = <?= json_encode($series_camions_entrees) ?>;
        let dataCamionsSortie = <?= json_encode($series_camions_sorties) ?>;
        let dataBateauxEntree = <?= json_encode($series_bateaux_entrees) ?>;
        let dataBateauxSortie = <?= json_encode($series_bateaux_sorties) ?>;
        const mvFilter = <?= json_encode($mouvement) ?>;
        const modeFilter = <?= json_encode($mode) ?>;
        if (mvFilter === 'entree') {
            dataCamionsSortie = dataCamionsSortie.map(() => 0);
            dataBateauxSortie = dataBateauxSortie.map(() => 0);
        } else if (mvFilter === 'sortie') {
            dataCamionsEntree = dataCamionsEntree.map(() => 0);
            dataBateauxEntree = dataBateauxEntree.map(() => 0);
        }
        if (modeFilter === 'camion') {
            dataBateauxEntree = dataBateauxEntree.map(() => 0);
            dataBateauxSortie = dataBateauxSortie.map(() => 0);
        } else if (modeFilter === 'bateau') {
            dataCamionsEntree = dataCamionsEntree.map(() => 0);
            dataCamionsSortie = dataCamionsSortie.map(() => 0);
        }

        const annualMonths = <?= json_encode($annualMonthLabels) ?>;
        const yearLabel = <?= json_encode($year_for_annual) ?>;
        const dataCamionsEntreeAnnee = <?= json_encode($series_camions_entrees_annee) ?>;
        const dataCamionsSortieAnnee = <?= json_encode($series_camions_sorties_annee) ?>;
        const dataBateauxEntreeAnnee = <?= json_encode($series_bateaux_entrees_annee) ?>;
        const dataBateauxSortieAnnee = <?= json_encode($series_bateaux_sorties_annee) ?>;

        const camionsCtx = document.getElementById('camionsMonthlyChart').getContext('2d');
        new Chart(camionsCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    { label: 'Entrées', data: dataCamionsEntree, backgroundColor: 'rgba(34, 197, 94, 0.7)' },
                    { label: 'Sorties', data: dataCamionsSortie, backgroundColor: 'rgba(234, 179, 8, 0.7)' }
                ]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { position: 'bottom' } }
            }
        });

        const bateauxCtx = document.getElementById('bateauxMonthlyChart').getContext('2d');
        new Chart(bateauxCtx, {
            type: 'bar',
            data: {
                labels: months,
                datasets: [
                    { label: 'Entrées', data: dataBateauxEntree, backgroundColor: 'rgba(59, 130, 246, 0.7)' },
                    { label: 'Sorties', data: dataBateauxSortie, backgroundColor: 'rgba(147, 51, 234, 0.7)' }
                ]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } },
                plugins: { legend: { position: 'bottom' } }
            }
        });

        // Graphes annuels Camions / Bateaux (s'adaptent à l'année sélectionnée) - protégés si canvases absents
        const camionsAnneeCanvas = document.getElementById('camionsAnneeChart');
        if (camionsAnneeCanvas) {
            const camionsAnneeCtx = camionsAnneeCanvas.getContext('2d');
            new Chart(camionsAnneeCtx, {
                type: 'bar',
                data: {
                    labels: annualMonths,
                    datasets: [
                        { label: `Entrées ${yearLabel}`, data: dataCamionsEntreeAnnee, backgroundColor: 'rgba(34, 197, 94, 0.7)' },
                        { label: `Sorties ${yearLabel}`, data: dataCamionsSortieAnnee, backgroundColor: 'rgba(234, 179, 8, 0.7)' }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
            });
        }

        const bateauxAnneeCanvas = document.getElementById('bateauxAnneeChart');
        if (bateauxAnneeCanvas) {
            const bateauxAnneeCtx = bateauxAnneeCanvas.getContext('2d');
            new Chart(bateauxAnneeCtx, {
                type: 'bar',
                data: {
                    labels: annualMonths,
                    datasets: [
                        { label: `Entrées ${yearLabel}`, data: dataBateauxEntreeAnnee, backgroundColor: 'rgba(59, 130, 246, 0.7)' },
                        { label: `Sorties ${yearLabel}`, data: dataBateauxSortieAnnee, backgroundColor: 'rgba(147, 51, 234, 0.7)' }
                    ]
                },
                options: { responsive: true, scales: { y: { beginAtZero: true } }, plugins: { legend: { position: 'bottom' } } }
            });
        }

        // Données PHP pour les graphes Mois courant / Année courante par type
        const monthTypeLabels = <?= json_encode(array_values($month_type_labels)) ?>;
        const monthTypeEntrees = <?= json_encode(array_values($month_type_entrees)) ?>;
        const monthTypeSorties = <?= json_encode(array_values($month_type_sorties)) ?>;

        const yearTypeLabels = <?= json_encode(array_values($year_type_labels)) ?>;
        const yearTypeEntrees = <?= json_encode(array_values($year_type_entrees)) ?>;
        const yearTypeSorties = <?= json_encode(array_values($year_type_sorties)) ?>;

        // Diagrammes: barres groupées Entrée/Sortie par type (mois courant)
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
                options: {
                    responsive: true,
                    scales: { x: { stacked: false }, y: { stacked: false, beginAtZero: true } },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }

        // Diagrammes: barres groupées Entrée/Sortie par type (année courante)
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
                options: {
                    responsive: true,
                    scales: { x: { stacked: false }, y: { stacked: false, beginAtZero: true } },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    </script>
</body>
</html>

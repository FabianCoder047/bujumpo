<?php
require_once '../includes/auth_check.php';
checkRole(['EnregistreurBateaux']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Filtres
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$type_bateau_id = isset($_GET['type_bateau_id']) ? trim($_GET['type_bateau_id']) : '';
$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Pagination
$per_page = isset($_GET['per_page']) && (int)$_GET['per_page'] > 0 ? min((int)$_GET['per_page'], 100) : 10;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Sélection de base
$select = "SELECT b.*, tb.nom AS type_bateau,
        po.nom AS port_origine, po.pays AS pays_origine,
        pd.nom AS port_destination, pd.pays AS pays_destination,
        (SELECT COUNT(*) FROM marchandises_bateaux mb WHERE mb.bateau_id = b.id) AS nb_marchandises,
        (SELECT COUNT(*) FROM passagers_bateaux pb WHERE pb.bateau_id = b.id) AS nb_passagers,
        (tb.nom LIKE '%passager%' OR tb.nom LIKE '%Passager%') AS est_passager
    FROM bateaux b
    LEFT JOIN types_bateaux tb ON b.type_bateau_id = tb.id
    LEFT JOIN ports po ON b.port_origine_id = po.id
    LEFT JOIN ports pd ON b.port_destination_id = pd.id";

$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(b.nom LIKE :q OR b.immatriculation LIKE :q OR b.capitaine LIKE :q)';
    $params[':q'] = "%$q%";
}
if ($type_bateau_id !== '') {
    $where[] = 'b.type_bateau_id = :type_bateau_id';
    $params[':type_bateau_id'] = $type_bateau_id;
}
if (in_array($statut, ['entree','sortie'], true)) {
    $where[] = 'b.statut = :statut';
    $params[':statut'] = $statut;
}
if ($date_from !== '') {
    $where[] = 'DATE(COALESCE(b.date_sortie, b.date_entree)) >= :date_from';
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $where[] = 'DATE(COALESCE(b.date_sortie, b.date_entree)) <= :date_to';
    $params[':date_to'] = $date_to;
}

$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

// Données pour selects
$typesBateaux = $db->query('SELECT id, nom FROM types_bateaux ORDER BY nom ASC')->fetchAll(PDO::FETCH_ASSOC);

function build_query(array $overrides = []): string {
    $params = $_GET;
    unset($params['page']);
    $params = array_merge($params, $overrides);
    return http_build_query($params);
}

// Exportations (PDF/XLSX) pour entrées/sorties (ignorer si msg=empty est présent pour permettre l'affichage)
if (isset($_GET['export']) && in_array($_GET['export'], ['pdf','excel'], true) && (!isset($_GET['msg']) || $_GET['msg'] !== 'empty')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    $which = (isset($_GET['which']) && $_GET['which'] === 'sortie') ? 'sortie' : 'entree';
    // L'export doit respecter les filtres de la page ; ajouter la contrainte "which" par-dessus
    $expWhere = $where;
    $expParams = $params;
    $expWhere[] = $which === 'entree' ? 'b.date_entree IS NOT NULL' : 'b.date_sortie IS NOT NULL';
    $expWhereSql = count($expWhere) ? (' WHERE ' . implode(' AND ', $expWhere)) : '';
    $sql = "SELECT b.*, tb.nom AS type_bateau, po.nom AS port_origine, pd.nom AS port_destination
            FROM bateaux b
            LEFT JOIN types_bateaux tb ON b.type_bateau_id = tb.id
            LEFT JOIN ports po ON b.port_origine_id = po.id
            LEFT JOIN ports pd ON b.port_destination_id = pd.id" . $expWhereSql . ' ORDER BY ' . ($which === 'entree' ? 'b.date_entree' : 'b.date_sortie') . ' ASC';
    $stmt = $db->prepare($sql);
    foreach ($expParams as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $qs = build_query([]);
        header('Location: historique.php?'.$qs.(strlen($qs)?'&':'').'msg=empty');
        exit;
    }

    if ($_GET['export'] === 'excel') {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['Type','Nom','Immatriculation','Capitaine','Agence','Hauteur (m)','Longueur (m)','Largeur (m)','Port origine','Port destination', ($which==='entree'?'Date entrée':'Date sortie')];
        $col = 1;
        foreach ($headers as $h) { $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1'; $sheet->setCellValue($addr, $h); $col++; }
        $lastHeaderCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle('A1:' . $lastHeaderCol . '1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '6B21A8']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ]);
        $rowNum = 2;
        foreach ($rows as $r) {
            $addr = function($c,$rN){return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c) . $rN;};
            $c = 1;
            $sheet->setCellValue($addr($c++, $rowNum), $r['type_bateau'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['nom'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['immatriculation'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['capitaine'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['agence'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['hauteur'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['longueur'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['largeur'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['port_origine'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $r['port_destination'] ?? '');
            $sheet->setCellValue($addr($c++, $rowNum), $which==='entree' ? ($r['date_entree'] ?? '') : ($r['date_sortie'] ?? ''));
            if ($rowNum % 2 === 0) { $sheet->getStyle('A'.$rowNum.':' . $lastHeaderCol . $rowNum)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F7'); }
            $rowNum++;
        }
        foreach (range('A',$lastHeaderCol) as $L) { $sheet->getColumnDimension($L)->setAutoSize(true); }
        $sheet->setAutoFilter('A1:' . $lastHeaderCol . '1');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="historique_bateaux_'.($which==='entree'?'entrees':'sorties').'.xlsx"');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } else { // pdf
        $title = 'Historique des Bateaux - ' . ($which==='entree'?'Entrées':'Sorties');
        $html = '<html><head><meta charset="utf-8"><style>
            body{font-family:DejaVu Sans, sans-serif; color:#111}
            .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
            .brand{font-size:14px;font-weight:700}
            .subtitle{font-size:12px;color:#555}
            table{width:100%;border-collapse:collapse;margin-top:10px;table-layout:auto}
            thead{display:table-header-group}
            tfoot{display:table-footer-group}
            tr{page-break-inside:avoid}
            th,td{border:1px solid #ddd;padding:5px;font-size:10px;vertical-align:top;word-wrap:break-word;white-space:normal}
            th{background:#f3f4f6;text-transform:uppercase}
            .muted{color:#666;font-size:10px;margin-top:6px}
        </style></head><body>';
        $html .= '<div class="header"><div><div class="brand">Port de BUJUMBURA</div><div class="subtitle">'.htmlspecialchars($title).'</div></div><div class="subtitle">'.date('Y-m-d H:i').'</div></div>';
        $html .= '<table><thead><tr><th>Type</th><th>Nom</th><th>Immat.</th><th>Capitaine</th><th>Agence</th><th>Hauteur (m)</th><th>Longueur (m)</th><th>Largeur (m)</th><th>Port origine</th><th>Port destination</th><th>'.($which==='entree'?'Date entrée':'Date sortie').'</th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr>'
                .'<td>'.htmlspecialchars((string)($r['type_bateau']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['nom']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['immatriculation']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['capitaine']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['agence']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['hauteur']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['longueur']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['largeur']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['port_origine']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($r['port_destination']??'')).'</td>'
                .'<td>'.htmlspecialchars((string)($which==='entree' ? ($r['date_entree']??'') : ($r['date_sortie']??''))).'</td>'
                .'</tr>';
        }
        $html .= '</tbody></table></body></html>';
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->stream('historique_bateaux_'.($which==='entree'?'entrees':'sorties').'.pdf', ['Attachment' => true]);
        exit;
    }
}

// Total et éléments avec filtres
$countSql = 'SELECT COUNT(*) FROM bateaux b LEFT JOIN types_bateaux tb ON b.type_bateau_id = tb.id LEFT JOIN ports po ON b.port_origine_id = po.id LEFT JOIN ports pd ON b.port_destination_id = pd.id' . $whereSql;
$countStmt = $db->prepare($countSql);
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

$sqlItems = $select . $whereSql . ' ORDER BY COALESCE(b.date_entree, b.date_sortie) DESC, b.id DESC LIMIT :limit OFFSET :offset';
$stmtItems = $db->prepare($sqlItems);
foreach ($params as $k => $v) { $stmtItems->bindValue($k, $v); }
$stmtItems->bindValue(':limit', (int)$per_page, PDO::PARAM_INT);
$stmtItems->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
$stmtItems->execute();
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les détails des passagers et des marchandises pour chaque bateau
foreach ($items as &$item) {
    // Détails des passagers
    $item['passagers'] = [];
    if (isset($item['est_passager']) && $item['est_passager']) {
        $stmt = $db->prepare("SELECT numero_passager, COALESCE(SUM(poids_marchandises), 0) as poids_total FROM passagers_bateaux WHERE bateau_id = ? GROUP BY numero_passager ORDER BY numero_passager");
        $stmt->execute([$item['id']]);
        $item['passagers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Détails des marchandises normales
    $item['marchandises'] = [];
    if (isset($item['est_passager']) && !$item['est_passager'] && $item['nb_marchandises'] > 0) {
        $stmt = $db->prepare("SELECT 
                                tm.nom as type_marchandise, 
                                COALESCE(SUM(mb.poids), 0) as poids_total, 
                                COUNT(*) as quantite,
                                COALESCE(SUM(mb.quantite), 0) as quantite_totale
                              FROM marchandises_bateaux mb
                              JOIN types_marchandises tm ON mb.type_marchandise_id = tm.id
                              WHERE mb.bateau_id = ?
                              GROUP BY tm.nom, tm.id
                              ORDER BY tm.nom");
        $stmt->execute([$item['id']]);
        $item['marchandises'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
unset($item);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - Vigile Maritime</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .min-w-1100 { min-width: 1100px; }
    </style>
    </head>
<body class="bg-gray-100">
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
            <a href="enregistrer.php" class="flex items-center px-4 py-3 text-purple-200 hover:bg-purple-800 hover:text-white transition duration-200">
                <i class="fas fa-plus mr-3"></i>
                Enregistrer Bateau
            </a>
            <a href="historique.php" class="flex items-center px-4 py-3 text-white bg-purple-800">
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
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></p>
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
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'empty'): ?>
            <div class="mb-4 p-4 rounded border border-purple-200 bg-purple-50 text-purple-800">
                <i class="fas fa-circle-info mr-2"></i>
                Aucune donnée correspondante pour les filtres sélectionnés. Veuillez ajuster les filtres et réessayer.
            </div>
            <?php endif; ?>
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Historique des Bateaux</h1>
                <p class="text-gray-600 mt-2">Derniers mouvements enregistrés</p>
            </div>

            <div class="bg-white rounded-lg shadow p-4 mb-4">
                <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-3">
                        <label class="block text-xs text-gray-600 mb-1">Recherche</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nom, immatriculation, capitaine" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs text-gray-600 mb-1">Type de bateau</label>
                        <select name="type_bateau_id" class="w-full border rounded px-3 py-2">
                            <option value="">Tous</option>
                            <?php foreach ($typesBateaux as $t): ?>
                                <option value="<?= htmlspecialchars($t['id']) ?>" <?= $type_bateau_id == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Statut</label>
                        <select name="statut" class="w-full border rounded px-3 py-2">
                            <option value="">Tous</option>
                            <option value="entree" <?= $statut==='entree'?'selected':'' ?>>Entrée</option>
                            <option value="sortie" <?= $statut==='sortie'?'selected':'' ?>>Sortie</option>
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs text-gray-600 mb-1">Du</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" class="w-full border rounded px-3 py-2" />
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-xs text-gray-600 mb-1">Au</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" class="w-full border rounded px-3 py-2" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Par page</label>
                        <select name="per_page" onchange="this.form.submit()" class="w-full border rounded px-2 py-2">
                            <?php foreach([10,25,50,100] as $pp): ?>
                                <option value="<?= $pp ?>" <?= $per_page==$pp?'selected':'' ?>><?= $pp ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-12 flex flex-wrap gap-2 justify-between items-center pt-2">
                        <div class="text-sm text-gray-600">Total: <?= number_format($total, 0, ',', ' ') ?> enregistrements</div>
                        <div class="flex flex-wrap gap-2">
                            <button class="bg-purple-700 text-white px-4 py-2 rounded whitespace-nowrap"><i class="fas fa-filter mr-2"></i>Filtrer</button>
                            <a href="historique.php" class="px-4 py-2 border rounded whitespace-nowrap"><i class="fas fa-rotate-left mr-2"></i>Réinitialiser</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Entrées -->
            <div class="bg-white rounded-lg shadow overflow-x-auto mb-8">
                <div class="px-6 pt-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Bateaux entrés</h2>
                    <div class="flex gap-2">
                        <a class="px-3 py-2 rounded bg-green-600 text-white" href="?export=excel&which=entree"><i class="fas fa-file-excel mr-2"></i>Excel</a>
                        <a class="px-3 py-2 rounded bg-red-600 text-white" href="?export=pdf&which=entree"><i class="fas fa-file-pdf mr-2"></i>PDF</a>
                    </div>
                </div>
                <table class="min-w-1100 divide-y divide-gray-200 mt-4">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bateau</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capitaine</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provenance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entrée</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $b): if (strtolower($b['statut'] ?? '') !== 'entree') continue; ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($b['nom']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($b['immatriculation'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($b['type_bateau'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($b['capitaine'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php
                                $po = $b['port_origine'] ? ($b['port_origine'].' ('.($b['pays_origine'] ?? '').')') : '';
                                echo htmlspecialchars($po ?: 'N/A');
                            ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php
                                $pd = $b['port_destination'] ? ($b['port_destination'].' ('.($b['pays_destination'] ?? '').')') : '';
                                echo htmlspecialchars($pd ?: 'BUJUMBURA');
                            ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $b['date_entree'] ? date('d/m/Y H:i', strtotime($b['date_entree'])) : '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-3">
                                <button onclick="showDetails(<?= (int)$b['id'] ?>)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i> Détails
                                </button>
                                <a href="enregistrer.php?bateau_id=<?= (int)$b['id'] ?>" class="text-purple-600 hover:text-purple-900">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sorties -->
            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <div class="px-6 pt-6 flex items-center justify-between">
                    <h2 class="text-xl font-semibold text-gray-900">Bateaux sortis</h2>
                    <div class="flex gap-2">
                        <a class="px-3 py-2 rounded bg-green-600 text-white" href="?export=excel&which=sortie"><i class="fas fa-file-excel mr-2"></i>Excel</a>
                        <a class="px-3 py-2 rounded bg-red-600 text-white" href="?export=pdf&which=sortie"><i class="fas fa-file-pdf mr-2"></i>PDF</a>
                    </div>
                </div>
                <table class="min-w-1100 divide-y divide-gray-200 mt-4">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bateau</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capitaine</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provenance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destination</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sortie</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $b): if (strtolower($b['statut'] ?? '') !== 'sortie') continue; ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($b['nom']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($b['immatriculation'] ?? '') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($b['type_bateau'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($b['capitaine'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php
                                $po = $b['port_origine'] ? ($b['port_origine'].' ('.($b['pays_origine'] ?? '').')') : '';
                                echo htmlspecialchars($po ?: 'BUJUMBURA');
                            ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php
                                $pd = $b['port_destination'] ? ($b['port_destination'].' ('.($b['pays_destination'] ?? '').')') : '';
                                echo htmlspecialchars($pd ?: 'N/A');
                            ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?= $b['date_sortie'] ? date('d/m/Y H:i', strtotime($b['date_sortie'])) : '-' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-3">
                                <button onclick="showDetails(<?= (int)$b['id'] ?>)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i> Détails
                                </button>
                                <a href="enregistrer.php?bateau_id=<?= (int)$b['id'] ?>" class="text-purple-600 hover:text-purple-900">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal Détails unifiée -->
    <div id="detailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 overflow-y-auto" onclick="closeDetailsModal()">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b border-gray-200 flex-shrink-0">
                    <h3 class="text-lg font-semibold text-gray-900">Détails du Bateau</h3>
                </div>
                <div id="detailsContent" class="p-6 overflow-y-auto flex-grow"></div>
            </div>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        function closeDetailsModal() {
            const modal = document.getElementById('detailsModal');
            if (modal) modal.classList.add('hidden');
        }

        // Afficher une modale unifiée avec les détails (passagers + marchandises)
        function showDetails(bateauId) {
            fetch(`api/bateau-details.php?id=${bateauId}`)
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        Swal.fire({ icon: 'error', title: 'Erreur', text: data.message || 'Chargement des détails impossible' });
                        return;
                    }
                    const { bateau, marchandises = [], passagers = [] } = data;
                    document.getElementById('detailsContent').innerHTML = `
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-900 mb-2">${bateau.nom} - ${bateau.immatriculation || ''}</h4>
                            <p class="text-sm text-gray-600">Capitaine: ${bateau.capitaine || '-'}</p>
                            <p class="text-sm text-gray-600">Agence: ${bateau.agence || '-'}</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Type de Bateau</label>
                                <div class="px-3 py-2 bg-gray-100 rounded-md">${bateau.type_bateau || '-'}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Date ${bateau.date_sortie ? "de Sortie" : "d'Entrée"}</label>
                                <div class="px-3 py-2 bg-gray-100 rounded-md">${new Date(bateau.date_sortie || bateau.date_entree).toLocaleString('fr-FR')}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Port Origine</label>
                                <div class="px-3 py-2 bg-gray-100 rounded-md">${bateau.port_origine || (bateau.statut==='sortie'?'BUJUMBURA':'N/A')}</div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Port Destination</label>
                                <div class="px-3 py-2 bg-gray-100 rounded-md">${bateau.port_destination || (bateau.statut==='entree'?'BUJUMBURA':'N/A')}</div>
                            </div>
                            <div>
                                <label class=\"block text-sm font-medium text-gray-700 mb-2\">Hauteur (m)</label>
                                <div class=\"px-3 py-2 bg-gray-100 rounded-md\">${(bateau.hauteur ?? '') !== '' ? bateau.hauteur : '-'}</div>
                            </div>
                            <div>
                                <label class=\"block text-sm font-medium text-gray-700 mb-2\">Longueur (m)</label>
                                <div class=\"px-3 py-2 bg-gray-100 rounded-md\">${(bateau.longueur ?? '') !== '' ? bateau.longueur : '-'}</div>
                            </div>
                            <div>
                                <label class=\"block text-sm font-medium text-gray-700 mb-2\">Largeur (m)</label>
                                <div class=\"px-3 py-2 bg-gray-100 rounded-md\">${(bateau.largeur ?? '') !== '' ? bateau.largeur : '-'}</div>
                            </div>
                        </div>
                        ${passagers.length > 0 ? `
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Passagers</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">N° Passager</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poids Marchandises (kg)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${(function(){ let total=0; return passagers.map((p,i)=>{ const w=parseFloat(p.poids_total)||0; total+=w; return `
                                        <tr class="${i%2===0?'bg-white':'bg-gray-50'}">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">Passager #${p.numero_passager}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${w.toFixed(2)} kg</td>
                                        </tr>`; }).join('') + `
                                        <tr class="bg-gray-50 font-semibold">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">TOTAL</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${total.toFixed(2)} kg</td>
                                        </tr>`; })()}
                                    </tbody>
                                </table>
                            </div>
                        </div>` : ''}
                        ${marchandises.length > 0 ? `
                        <div class="mb-6">
                            <h4 class="text-md font-semibold text-gray-900 mb-4">Marchandises</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Poids (kg)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${(function(){ let totalP=0, totalQ=0; return marchandises.map((m,i)=>{ const q=parseInt(m.quantite)||0; const p=parseFloat(m.poids)||0; totalQ+=q; totalP+=p; return `
                                        <tr class="${i%2===0?'bg-white':'bg-gray-50'}">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${m.type_marchandise || 'Non spécifié'}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${q}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${p.toFixed(2)} kg</td>
                                        </tr>`; }).join('') + `
                                        <tr class="bg-gray-50 font-semibold">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">TOTAL</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${totalQ}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${totalP.toFixed(2)} kg</td>
                                        </tr>`; })()}
                                    </tbody>
                                </table>
                            </div>
                        </div>` : ''}
                        <div class="flex justify-end">
                            <button type="button" onclick="closeDetailsModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded hover:bg-gray-300">Fermer</button>
                        </div>
                    `;
                    const modal = document.getElementById('detailsModal');
                    if (modal) modal.classList.remove('hidden');
                })
                .catch(() => Swal.fire({ icon: 'error', title: 'Erreur', text: 'Impossible de charger les détails' }));
        }
    </script>
</body>
</html>



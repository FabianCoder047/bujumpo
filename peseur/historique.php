<?php
require_once '../includes/auth_check.php';
checkRole(['peseur']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

// Filtres
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$type_camion_id = isset($_GET['type_camion_id']) ? trim($_GET['type_camion_id']) : '';
$mouvement = isset($_GET['mouvement']) ? trim($_GET['mouvement']) : '';
$surcharge = isset($_GET['surcharge']) ? trim($_GET['surcharge']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Tri
$allowedSort = [
    'date' => 'p.date_pesage',
    'surcharge' => 'p.surcharge',
    'poids' => 'p.total_poids_marchandises',
    'ptav' => 'p.ptav',
    'ptac' => 'p.ptac',
    'ptra' => 'p.ptra',
    'charge' => 'p.charge_essieu',
    'marque' => 'c.marque',
    'immatriculation' => 'c.immatriculation',
    'type' => 'tc.nom',
    'mouvement' => 'mouvement',
];
$sort = isset($_GET['sort']) && isset($allowedSort[$_GET['sort']]) ? $_GET['sort'] : 'date';
$dir = isset($_GET['dir']) && strtolower($_GET['dir']) === 'asc' ? 'asc' : 'desc';

// Pagination
$per_page = isset($_GET['per_page']) && (int)$_GET['per_page'] > 0 ? min((int)$_GET['per_page'], 100) : 10;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Requête de base
// Détecter si pesages.mouvement existe pour privilégier le mouvement enregistré
$hasMouvement = false;
try {
    $col = $db->query("SHOW COLUMNS FROM pesages LIKE 'mouvement'")->fetch();
    $hasMouvement = $col ? true : false;
} catch (Exception $e) { /* ignore */ }

$select = "SELECT p.*, c.marque, c.immatriculation, tc.nom as type_camion, "
    . ($hasMouvement
        ? "COALESCE(p.mouvement, CASE WHEN c.date_sortie IS NOT NULL AND p.date_pesage >= c.date_sortie THEN 'sortie' ELSE 'entree' END) AS mouvement"
        : "CASE WHEN c.date_sortie IS NOT NULL AND p.date_pesage >= c.date_sortie THEN 'sortie' ELSE 'entree' END AS mouvement")
    . "
    FROM pesages p
    LEFT JOIN camions c ON p.camion_id = c.id
    LEFT JOIN types_camions tc ON c.type_camion_id = tc.id";
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(c.immatriculation LIKE :q OR c.marque LIKE :q)';
    $params[':q'] = "%$q%";
}
if ($type_camion_id !== '') {
    $where[] = 'c.type_camion_id = :type_camion_id';
    $params[':type_camion_id'] = $type_camion_id;
}
if ($surcharge === 'oui') {
    $where[] = '(p.surcharge IS NOT NULL AND p.surcharge <> 0)';
} elseif ($surcharge === 'non') {
    $where[] = '(p.surcharge IS NULL OR p.surcharge = 0)';
}
if ($mouvement === 'entree') {
    if ($hasMouvement) {
        $where[] = "(p.mouvement = 'entree' OR (p.mouvement IS NULL AND (c.date_sortie IS NULL OR p.date_pesage < c.date_sortie)))";
    } else {
        $where[] = '(c.date_sortie IS NULL OR p.date_pesage < c.date_sortie)';
    }
} elseif ($mouvement === 'sortie') {
    if ($hasMouvement) {
        $where[] = "(p.mouvement = 'sortie' OR (p.mouvement IS NULL AND c.date_sortie IS NOT NULL AND p.date_pesage >= c.date_sortie))";
    } else {
        $where[] = '(c.date_sortie IS NOT NULL AND p.date_pesage >= c.date_sortie)';
    }
}
if ($date_from !== '') {
    $where[] = 'DATE(p.date_pesage) >= :date_from';
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $where[] = 'DATE(p.date_pesage) <= :date_to';
    $params[':date_to'] = $date_to;
}

$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

// Exporter au format CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $sqlExport = $select . $whereSql . ' ORDER BY ' . $allowedSort[$sort] . ' ' . strtoupper($dir);
    $stmt = $db->prepare($sqlExport);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $qs = build_query([]);
        header('Location: historique.php?'.$qs.(strlen($qs)?'&':'').'msg=empty');
        exit;
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=historique_pesages.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Camion', 'Immatriculation', 'Type', 'PTAV', 'PTAC', 'PTRA', 'Charge Essieu', 'Poids Marchandises', 'Surcharge', 'Date']);
    foreach ($rows as $p) {
        fputcsv($output, [
            $p['marque'],
            $p['immatriculation'],
            $p['type_camion'],
            $p['ptav'],
            $p['ptac'],
            $p['ptra'],
            $p['charge_essieu'],
            $p['total_poids_marchandises'] ?? 0,
            !empty($p['surcharge']) ? 'Oui' : 'Non',
            date('d/m/Y H:i', strtotime($p['date_pesage'])),
        ]);
    }
    fclose($output);
    exit;
}

// Exporter au format Excel (XLSX)
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $sqlExport = $select . $whereSql . ' ORDER BY ' . $allowedSort[$sort] . ' ' . strtoupper($dir);
    $stmt = $db->prepare($sqlExport);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $headers = ['Camion','Immatriculation','Type','PTAV','PTAC','PTRA','Charge Essieu','Poids Marchandises','Surcharge','Mouvement','Date'];
    $col = 1;
    foreach ($headers as $h) {
        $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
        $sheet->setCellValue($addr, $h);
        $col++;
    }

    // Style de l'en-tête
    $headerStyle = [
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E3A8A']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ];
    $sheet->getStyle('A1:K1')->applyFromArray($headerStyle);

    $rowNum = 2;
    foreach ($rows as $p) {
        $col = 1;
        $addr = function($colIndex, $rowIndex) { return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex; };
        $sheet->setCellValue($addr($col++, $rowNum), $p['marque']);
        $sheet->setCellValue($addr($col++, $rowNum), $p['immatriculation']);
        $sheet->setCellValue($addr($col++, $rowNum), $p['type_camion']);
        $sheet->setCellValue($addr($col++, $rowNum), (float)$p['ptav']);
        $sheet->setCellValue($addr($col++, $rowNum), (float)$p['ptac']);
        $sheet->setCellValue($addr($col++, $rowNum), (float)$p['ptra']);
        $sheet->setCellValue($addr($col++, $rowNum), (float)$p['charge_essieu']);
        $sheet->setCellValue($addr($col++, $rowNum), (float)($p['total_poids_marchandises'] ?? 0));
        $isSurcharge = !empty($p['surcharge']);
        $sheet->setCellValue($addr($col++, $rowNum), $isSurcharge ? 'Oui' : 'Non');
        $sheet->setCellValue($addr($col++, $rowNum), $p['mouvement']);
        $sheet->setCellValue($addr($col++, $rowNum), date('d/m/Y H:i', strtotime($p['date_pesage'])));

        // Style des lignes : zébrage et surbrillance en cas de surcharge
        $rowRange = 'A'.$rowNum.':K'.$rowNum;
        if ($isSurcharge) {
            $sheet->getStyle($rowRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
        } elseif ($rowNum % 2 === 0) {
            $sheet->getStyle($rowRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F7');
        }
        $rowNum++;
    }
    foreach (range('A','K') as $colLetter) { $sheet->getColumnDimension($colLetter)->setAutoSize(true); }
    $sheet->setAutoFilter('A1:K1');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="historique_pesages.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// Exporter au format PDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $sqlExport = $select . $whereSql . ' ORDER BY ' . $allowedSort[$sort] . ' ' . strtoupper($dir);
    $stmt = $db->prepare($sqlExport);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $qs = build_query([]);
        header('Location: historique.php?'.$qs.(strlen($qs)?'&':'').'msg=empty');
        exit;
    }
    $title = 'Historique des Pesages';
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
        .surcharge{background:#fee2e2}
    </style></head><body>';
    $html .= '<div class="header"><div><div class="brand">Port de BUJUMBURA</div><div class="subtitle">' . htmlspecialchars($title) . '</div></div><div class="subtitle">' . date('Y-m-d H:i') . '</div></div>';
    $html .= '<table><thead><tr><th>Camion</th><th>Immatriculation</th><th>Type</th><th>PTAV</th><th>PTAC</th><th>PTRA</th><th>Charge Essieu</th><th>Poids Marchandises</th><th>Surcharge</th><th>Mouvement</th><th>Date</th></tr></thead><tbody>';
    foreach ($rows as $p) {
        $rowClass = !empty($p['surcharge']) ? ' class="surcharge"' : '';
        $html .= '<tr'.$rowClass.'>'
            .'<td>'.htmlspecialchars($p['marque']).'</td>'
            .'<td>'.htmlspecialchars($p['immatriculation']).'</td>'
            .'<td>'.htmlspecialchars($p['type_camion']).'</td>'
            .'<td>'.number_format((float)$p['ptav'],0,',',' ').'</td>'
            .'<td>'.number_format((float)$p['ptac'],0,',',' ').'</td>'
            .'<td>'.number_format((float)$p['ptra'],0,',',' ').'</td>'
            .'<td>'.number_format((float)$p['charge_essieu'],0,',',' ').'</td>'
            .'<td>'.number_format((float)($p['total_poids_marchandises'] ?? 0),0,',',' ').'</td>'
            .'<td>'.(!empty($p['surcharge']) ? 'Oui' : 'Non').'</td>'
            .'<td>'.htmlspecialchars($p['mouvement']).'</td>'
            .'<td>'.date('d/m/Y H:i', strtotime($p['date_pesage'])).'</td>'
            .'</tr>';
    }
    $html .= '</tbody></table></body></html>';
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('historique_pesages.pdf', ['Attachment' => true]);
    exit;
}

// Compter le total
$countSql = 'SELECT COUNT(*) FROM pesages p LEFT JOIN camions c ON p.camion_id = c.id LEFT JOIN types_camions tc ON c.type_camion_id = tc.id' . $whereSql;
$countStmt = $db->prepare($countSql);
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

// Récupérer les données de la page
$sql = $select . $whereSql . ' ORDER BY ' . $allowedSort[$sort] . ' ' . strtoupper($dir) . ', p.id DESC LIMIT ' . (int)$per_page . ' OFFSET ' . (int)$offset;
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les types de camions pour le filtre
$typesCamions = $db->query('SELECT id, nom FROM types_camions ORDER BY nom ASC')->fetchAll(PDO::FETCH_ASSOC);

// Aide pour construire la chaîne de requête en conservant les filtres
function build_query(array $overrides = []): string {
    $params = $_GET;
    unset($params['page']);
    $params = array_merge($params, $overrides);
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique Pesages - Peseur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    <div class="fixed inset-y-0 left-0 w-64 bg-blue-900 text-white transform -translate-x-full lg:translate-x-0 transition-transform duration-200 ease-in-out" id="sidebar">
        <div class="flex items-center justify-center h-16 bg-blue-800">
            <i class="fas fa-anchor text-2xl mr-2"></i>
            <span class="text-xl font-bold">Port de BUJUMBURA</span>
        </div>
        <nav class="mt-8">
            <div class="px-4 mb-4">
                <p class="text-blue-300 text-sm font-medium">Peseur</p>
            </div>
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
                <i class="fas fa-tachometer-alt mr-3"></i>
                Dashboard
            </a>
            <a href="historique.php" class="flex items-center px-4 py-3 text-white bg-blue-800">
                <i class="fas fa-history mr-3"></i>
                Historique
            </a>
        </nav>
    </div>

    <div class="ml-0 lg:ml-64">
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="flex items-center justify-between px-6 py-4">
                <button class="lg:hidden text-gray-600 hover:text-gray-900" onclick="toggleSidebar()">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></p>
                        <p class="text-sm text-gray-500">Peseur</p>
                    </div>
                    <a href="../auth/logout.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                </div>
            </div>
        </header>

        <main class="p-6">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Historique des Pesages</h1>
                <p class="text-gray-600 mt-2">Derniers pesages enregistrés</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 mb-4">
                <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-3">
                        <label class="block text-xs text-gray-600 mb-1">Recherche</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Immatriculation, marque" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Type camion</label>
                        <select name="type_camion_id" class="w-full border rounded px-3 py-2">
                            <option value="">Tous</option>
                            <?php foreach ($typesCamions as $t): ?>
                                <option value="<?= htmlspecialchars($t['id']) ?>" <?= $type_camion_id == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Mouvement</label>
                        <select name="mouvement" class="w-full border rounded px-3 py-2">
                            <option value="">Tous</option>
                            <option value="entree" <?= $mouvement==='entree'?'selected':'' ?>>Entrée</option>
                            <option value="sortie" <?= $mouvement==='sortie'?'selected':'' ?>>Sortie</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Surcharge</label>
                        <select name="surcharge" class="w-full border rounded px-3 py-2">
                            <option value="">Toutes</option>
                            <option value="oui" <?= $surcharge==='oui'?'selected':'' ?>>Oui</option>
                            <option value="non" <?= $surcharge==='non'?'selected':'' ?>>Non</option>
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
                    <div class="md:col-span-1">
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
                            <button class="bg-blue-600 text-white px-4 py-2 rounded whitespace-nowrap"><i class="fas fa-filter mr-2"></i>Filtrer</button>
                            <a href="historique.php" class="px-4 py-2 border rounded whitespace-nowrap"><i class="fas fa-rotate-left mr-2"></i>Réinitialiser</a>
                            <a class="px-3 py-2 rounded bg-green-600 text-white whitespace-nowrap" href="?<?= htmlspecialchars(build_query(['export'=>'excel'])) ?>"><i class="fas fa-file-excel mr-2"></i>Excel</a>
                            <a class="px-3 py-2 rounded bg-red-600 text-white whitespace-nowrap" href="?<?= htmlspecialchars(build_query(['export'=>'pdf'])) ?>"><i class="fas fa-file-pdf mr-2"></i>PDF</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-[1000px] divide-y divide-gray-200">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'marque','dir'=> $sort==='marque'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">Camion</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'ptav','dir'=> $sort==='ptav'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">PTAV</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'ptac','dir'=> $sort==='ptac'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">PTAC</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'ptra','dir'=> $sort==='ptra'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">PTRA</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'charge','dir'=> $sort==='charge'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">Charge Essieu</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'poids','dir'=> $sort==='poids'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">Poids Marchandises</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase  whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'surcharge','dir'=> $sort==='surcharge'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">Surcharge</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'mouvement','dir'=> $sort==='mouvement'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">Mouvement</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap"><a href="?<?= htmlspecialchars(build_query(['sort'=>'date','dir'=> $sort==='date'&&$dir==='asc'?'desc':'asc','page'=>1])) ?>">Date</a></th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $p): ?>
                        <tr class="<?= !empty($p['surcharge']) ? 'bg-red-50' : '' ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= h($p['marque']) ?></div>
                                <div class="text-sm text-gray-500"><?= h($p['immatriculation']) ?></div>
                                <div class="text-xs text-gray-500"><?= h($p['type_camion']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format((float)$p['ptav'], 0, ',', ' ') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format((float)$p['ptac'], 0, ',', ' ') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format((float)$p['ptra'], 0, ',', ' ') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format((float)$p['charge_essieu'], 0, ',', ' ') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= number_format((float)($p['total_poids_marchandises'] ?? 0), 0, ',', ' ') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($p['surcharge']) ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                    <?= !empty($p['surcharge']) ? 'Oui' : 'Non' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $p['mouvement']==='sortie' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= h(ucfirst((string)($p['mouvement'] ?? ''))) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($p['date_pesage'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <button class="px-3 py-1 border rounded hover:bg-gray-50" onclick='modifierPesage(<?= (int)$p['id'] ?>, <?= (int)$p['camion_id'] ?>, <?= json_encode([
                                    'ptav'=>(float)$p['ptav'],
                                    'ptac'=>(float)$p['ptac'],
                                    'ptra'=>(float)$p['ptra'],
                                    'charge_essieu'=>(float)$p['charge_essieu'],
                                    'total_poids_marchandises'=>(float)($p['total_poids_marchandises'] ?? 0),
                                    'surcharge'=>(int)($p['surcharge'] ?? 0),
                                ]) ?>, <?= json_encode((string)$p['mouvement']) ?>)'><i class="fas fa-edit mr-1"></i>Modifier</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex justify-between items-center">
                <div class="text-sm text-gray-600">Page <?= $page ?> / <?= $total_pages ?></div>
                <div class="flex gap-2">
                    <?php $start = max(1, $page - 2); $end = min($total_pages, $page + 2); ?>
                    <?php if ($page > 1): ?>
                        <?php if ($start > 1): ?>
                            <a class="px-3 py-2 border rounded" href="?<?= htmlspecialchars(build_query(['page'=>1])) ?>">« Première</a>
                        <?php endif; ?>
                        <a class="px-3 py-2 border rounded" href="?<?= htmlspecialchars(build_query(['page'=>$page-1])) ?>">‹ Précédente</a>
                    <?php endif; ?>

                    <?php for ($i = $start; $i <= $end; $i++): ?>
                        <a class="px-3 py-2 border rounded <?= $i === $page ? 'bg-blue-700 text-white' : '' ?>" href="?<?= htmlspecialchars(build_query(['page'=>$i])) ?>"><?= $i ?></a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <?php if ($end < $total_pages): ?>
                            <a class="px-3 py-2 border rounded" href="?<?= htmlspecialchars(build_query(['page'=>$total_pages])) ?>">Dernière »</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        async function modifierPesage(pesageId, camionId, vals, mouvement) {
            const isSortie = String(mouvement || '').toLowerCase() === 'sortie';
            // Charger types de marchandises et détails du camion
            const [typesResp, detailsResp] = await Promise.all([
                fetch('api/get-marchandises.php'),
                // Important: pour un pesage de sortie, ne retourner que les marchandises liées à la sortie
                fetch('api/camion-details.php?id=' + encodeURIComponent(camionId) + (isSortie ? '&mode=sortie' : ''))
            ]);
            const types = await typesResp.json();
            const details = await detailsResp.json();
            // Préférer explicitement les marchandises de sortie lorsqu'elles existent
            const currentItems = (isSortie && details && Array.isArray(details.marchandises_sortie) && details.marchandises_sortie.length > 0)
                ? details.marchandises_sortie
                : ((details && Array.isArray(details.marchandises)) ? details.marchandises : []);

            const renderRows = (items) => items.map((it) => `
                <tr>
                    <td class="p-1">
                        <select class="w-full border rounded px-2 py-1 js-type">
                            ${types.map(t => `<option value="${t.id}" ${String(it.type_marchandise_id||'')===String(t.id)?'selected':''}>${t.nom}</option>`).join('')}
                        </select>
                    </td>
                    <td class="p-1"><input type="number" min="0" step="0.01" class="w-full border rounded px-2 py-1 js-poids" value="${it.poids??''}" placeholder="0.00"></td>
                    <td class="p-1"><input type="number" min="1" step="1" class="w-full border rounded px-2 py-1 js-qty" value="${it.quantite??1}" placeholder="1"></td>
                    <td class="p-1 text-right"><button type="button" class="px-2 py-1 text-red-600 hover:bg-red-50 rounded js-remove"><i class="fas fa-trash"></i></button></td>
                </tr>
            `).join('');

            const { value: formValues } = await Swal.fire({
                title: 'Modifier le pesage',
                html: `
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-left">
                        <div>
                            <label class="text-xs text-gray-600">PTAV</label>
                            <input id="ptav" type="number" min="0" step="0.01" class="swal2-input" style="width:100%;margin:0" value="${vals.ptav||0}" placeholder="0.00">
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">PTAC</label>
                            <input id="ptac" type="number" min="0" step="0.01" class="swal2-input" style="width:100%;margin:0" value="${vals.ptac||0}" placeholder="0.00">
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">PTRA</label>
                            <input id="ptra" type="number" min="0" step="0.01" class="swal2-input" style="width:100%;margin:0" value="${vals.ptra||0}" placeholder="0.00">
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Charge essieu</label>
                            <input id="charge_essieu" type="number" min="0" step="0.01" class="swal2-input" style="width:100%;margin:0" value="${vals.charge_essieu||0}" placeholder="0.00">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-600">Poids marchandises total</label>
                            <input id="total_poids_marchandises" type="number" min="0" step="0.01" class="swal2-input" style="width:100%;margin:0" value="${vals.total_poids_marchandises||0}" placeholder="0.00">
                        </div>
                        
                        <div class="sm:col-span-2">
                            <label class="text-xs text-gray-600">Marchandises</label>
                            <div class="border rounded">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50">
                                            <th class="text-left p-2">Type</th>
                                            <th class="text-left p-2">Poids</th>
                                            <th class="text-left p-2">Quantité</th>
                                            <th class="text-right p-2">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="march-rows">
                                        ${renderRows(currentItems)}
                                    </tbody>
                                </table>
                                <div class="p-2">
                                    <button type="button" id="btn-add-row" class="px-3 py-1 bg-gray-100 rounded hover:bg-gray-200"><i class="fas fa-plus mr-1"></i>Ajouter une ligne</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `,
                focusConfirm: false,
                didOpen: () => {
                    const tbody = document.getElementById('march-rows');
                    const btnAdd = document.getElementById('btn-add-row');

                    const updateTypeDisables = () => {
                        const selects = Array.from(tbody.querySelectorAll('select.js-type'));
                        const selectedVals = new Set(selects.map(s => s.value).filter(v => v));
                        selects.forEach(sel => {
                            const current = sel.value;
                            Array.from(sel.options).forEach(opt => {
                                if (!opt.value) return;
                                opt.disabled = selectedVals.has(opt.value) && opt.value !== current;
                            });
                        });
                    };

                    btnAdd.addEventListener('click', () => {
                        const empty = { type_marchandise_id: (types[0] ? types[0].id : ''), poids: '', quantite: 1 };
                        tbody.insertAdjacentHTML('beforeend', renderRows([empty]));
                        updateTypeDisables();
                    });

                    tbody.addEventListener('click', (e) => {
                        if (e.target.closest('.js-remove')) {
                            const tr = e.target.closest('tr');
                            if (tr) tr.remove();
                            updateTypeDisables();
                        }
                    });

                    tbody.addEventListener('change', (e) => {
                        if (e.target && e.target.classList.contains('js-type')) {
                            updateTypeDisables();
                        }
                    });

                    // Initial pass
                    updateTypeDisables();
                },
                preConfirm: () => {
                    const g = id => document.getElementById(id);
                    const toNum = v => {
                        const n = parseFloat((v||'').toString().replace(',', '.'));
                        return isNaN(n) ? NaN : n;
                    };
                    const data = {
                        ptav: toNum(g('ptav').value),
                        ptac: toNum(g('ptac').value),
                        ptra: toNum(g('ptra').value),
                        charge_essieu: toNum(g('charge_essieu').value),
                        total_poids_marchandises: toNum(g('total_poids_marchandises').value)
                    };
                    if ([data.ptav, data.ptac, data.ptra].some(v => isNaN(v))) {
                        Swal.showValidationMessage('PTAV, PTAC et PTRA doivent être des nombres valides');
                        return false;
                    }
                    if ([data.ptav, data.ptac, data.ptra, data.charge_essieu, data.total_poids_marchandises].some(v => v < 0)) {
                        Swal.showValidationMessage('Les valeurs ne peuvent pas être négatives');
                        return false;
                    }
                    const rows = Array.from(document.querySelectorAll('#march-rows tr'));
                    const items = rows.map(r => ({
                        type_marchandise_id: parseInt(r.querySelector('.js-type')?.value||'0', 10),
                        poids: toNum(r.querySelector('.js-poids')?.value),
                        quantite: parseInt(r.querySelector('.js-qty')?.value||'1', 10)
                    })).filter(it => !isNaN(it.type_marchandise_id) && (it.poids===0 || !isNaN(it.poids)) && it.quantite>0);
                    return { data, items };
                },
                showCancelButton: true,
                confirmButtonText: 'Enregistrer',
                cancelButtonText: 'Annuler',
                width: 750
            });

            if (!formValues) return;

            try {
                // Recalculer le total des poids à partir des lignes éditées et l'envoyer avec la mise à jour du pesage
                const totalFromItems = Array.isArray(formValues.items)
                    ? formValues.items.reduce((sum, it) => sum + (parseFloat((it.poids??0).toString().replace(',', '.')) || 0), 0)
                    : 0;
                formValues.data.total_poids_marchandises = totalFromItems;
                const resp = await fetch('api/update-pesage.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: pesageId, ...formValues.data })
                });
                const out = await resp.json();
                if (!out.success) throw new Error(out.message||'Echec de la mise à jour');

                const resp2 = await fetch('api/update-marchandises.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        camion_id: camionId,
                        items: formValues.items,
                        mouvement: isSortie ? 'sortie' : 'entree'
                    })
                });
                const out2 = await resp2.json();
                if (!out2.success) throw new Error(out2.message||'Echec de la mise à jour des marchandises');

                await Swal.fire({ icon: 'success', title: 'Pesage mis à jour', timer: 1200, showConfirmButton: false });
                location.reload();
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erreur', text: e.message||'Erreur lors de la mise à jour' });
            }
        }
    </script>
</body>
</html>




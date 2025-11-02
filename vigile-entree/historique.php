<?php
require_once '../includes/auth_check.php';
checkRole(['EnregistreurEntreeRoute']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Filtres
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$type_camion_id = isset($_GET['type_camion_id']) ? trim($_GET['type_camion_id']) : '';
$est_charge = isset($_GET['est_charge']) ? trim($_GET['est_charge']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Pagination
$per_page = isset($_GET['per_page']) && (int)$_GET['per_page'] > 0 ? min((int)$_GET['per_page'], 100) : 10;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Requête de base
$select = "SELECT c.*, tc.nom as type_camion, p.nom as nom_port
    FROM camions c
    LEFT JOIN types_camions tc ON c.type_camion_id = tc.id
    LEFT JOIN ports p ON c.provenance_port_id = p.id";
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
if ($est_charge === 'oui') {
    $where[] = 'c.est_charge = 1';
} elseif ($est_charge === 'non') {
    $where[] = 'c.est_charge = 0';
}
if ($date_from !== '') {
    $where[] = 'DATE(c.date_entree) >= :date_from';
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $where[] = 'DATE(c.date_entree) <= :date_to';
    $params[':date_to'] = $date_to;
}

$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

// Exportations
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $sqlExport = $select . $whereSql . ' ORDER BY c.date_entree DESC';
    $stmt = $db->prepare($sqlExport);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $qs = build_query([]);
        header('Location: historique.php?'.$qs.(strlen($qs)?'&':'').'msg=empty');
        exit;
    }

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $headers = ['Marque','Immatriculation','Type','Provenance','Destinataire','T1','Chargé','Date Entrée','Statut'];
    $col = 1;
    foreach ($headers as $h) {
        $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
        $sheet->setCellValue($addr, $h);
        $col++;
    }
    $sheet->getStyle('A1:I1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => '065F46']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    $rowNum = 2;
    foreach ($rows as $c) {
        $col = 1;
        $addr = function($colIndex, $rowIndex) { return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex; };
        $sheet->setCellValue($addr($col++, $rowNum), $c['marque']);
        $sheet->setCellValue($addr($col++, $rowNum), $c['immatriculation']);
        $sheet->setCellValue($addr($col++, $rowNum), $c['type_camion']);
        $sheet->setCellValue($addr($col++, $rowNum), $c['nom_port']);
        $sheet->setCellValue($addr($col++, $rowNum), $c['destinataire']);
        $sheet->setCellValue($addr($col++, $rowNum), $c['t1']);
        $sheet->setCellValue($addr($col++, $rowNum), $c['est_charge'] ? 'Oui' : 'Non');
        $sheet->setCellValue($addr($col++, $rowNum), date('d/m/Y H:i', strtotime($c['date_entree'])));
        $sheet->setCellValue($addr($col++, $rowNum), ucfirst($c['statut']));
        if ($rowNum % 2 === 0) {
            $sheet->getStyle('A'.$rowNum.':I'.$rowNum)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F7');
        }
        $rowNum++;
    }
    foreach (range('A','I') as $colLetter) { $sheet->getColumnDimension($colLetter)->setAutoSize(true); }
    $sheet->setAutoFilter('A1:I1');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="historique_entrees.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $sqlExport = $select . $whereSql . ' ORDER BY c.date_entree DESC';
    $stmt = $db->prepare($sqlExport);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) {
        $qs = build_query([]);
        header('Location: historique.php?'.$qs.(strlen($qs)?'&':'').'msg=empty');
        exit;
    }
    $title = 'Historique des Camions - Entrées';
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
    $html .= '<div class="header"><div><div class="brand">Port de BUJUMBURA</div><div class="subtitle">' . htmlspecialchars($title) . '</div></div><div class="subtitle">' . date('Y-m-d H:i') . '</div></div>';
    $html .= '<table><thead><tr><th>Camion</th><th>Immatriculation</th><th>Type</th><th>Provenance</th><th>Destinataire</th><th>T1</th><th>Chargé</th><th>Date Entrée</th><th>Statut</th></tr></thead><tbody>';
    foreach ($rows as $c) {
        $html .= '<tr>'
            .'<td>'.htmlspecialchars($c['marque']).'</td>'
            .'<td>'.htmlspecialchars($c['immatriculation']).'</td>'
            .'<td>'.htmlspecialchars($c['type_camion']).'</td>'
            .'<td>'.htmlspecialchars($c['nom_port']).'</td>'
            .'<td>'.htmlspecialchars($c['destinataire']).'</td>'
            .'<td>'.htmlspecialchars($c['t1']).'</td>'
            .'<td>'.($c['est_charge'] ? 'Oui' : 'Non').'</td>'
            .'<td>'.date('d/m/Y H:i', strtotime($c['date_entree'])).'</td>'
            .'<td>'.htmlspecialchars(ucfirst($c['statut'])).'</td>'
            .'</tr>';
    }
    $html .= '</tbody></table></body></html>';
    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream('historique_entrees.pdf', ['Attachment' => true]);
    exit;
}

// Compter total et récupérer page
$countSql = 'SELECT COUNT(*) FROM camions c LEFT JOIN types_camions tc ON c.type_camion_id = tc.id LEFT JOIN ports p ON c.provenance_port_id = p.id' . $whereSql;
$countStmt = $db->prepare($countSql);
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

$sql = $select . $whereSql . ' ORDER BY c.date_entree DESC LIMIT ' . (int)$per_page . ' OFFSET ' . (int)$offset;
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Données pour selects
$typesCamions = $db->query('SELECT id, nom FROM types_camions ORDER BY nom ASC')->fetchAll(PDO::FETCH_ASSOC);

function build_query(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    unset($params['page']);
    return http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique - Vigile Entrée</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
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
            <a href="enregistrer.php" class="flex items-center px-4 py-3 text-green-200 hover:bg-green-800 hover:text-white transition duration-200">
                <i class="fas fa-plus mr-3"></i>
                Enregistrer Camion
            </a>
            <a href="historique.php" class="flex items-center px-4 py-3 text-white bg-green-800">
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
                        <p class="text-sm text-gray-500">Vigile d'Entrée</p>
                    </div>
                    <a href="../auth/logout.php" class="text-gray-600 hover:text-gray-900">
                        <i class="fas fa-sign-out-alt text-xl"></i>
                    </a>
                </div>
            </div>
        </header>

        <main class="p-6">
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'empty'): ?>
            <div class="mb-4 p-4 rounded border border-green-200 bg-green-50 text-green-800">
                <i class="fas fa-circle-info mr-2"></i>
                Aucune donnée correspondante pour les filtres sélectionnés. Veuillez ajuster les filtres et réessayer.
            </div>
            <?php endif; ?>
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Historique des Camions</h1>
                <p class="text-gray-600 mt-2">Dernières entrées enregistrées</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 mb-4">
                <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-3">
                        <label class="block text-xs text-gray-600 mb-1">Recherche</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Immatriculation, marque" class="w-full border rounded px-3 py-2">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-xs text-gray-600 mb-1">Type camion</label>
                        <select name="type_camion_id" class="w-full border rounded px-3 py-2">
                            <option value="">Tous</option>
                            <?php foreach ($typesCamions as $t): ?>
                                <option value="<?= htmlspecialchars($t['id']) ?>" <?= $type_camion_id == $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-600 mb-1">Chargé</label>
                        <select name="est_charge" class="w-full border rounded px-3 py-2">
                            <option value="">Tous</option>
                            <option value="oui" <?= $est_charge==='oui'?'selected':'' ?>>Oui</option>
                            <option value="non" <?= $est_charge==='non'?'selected':'' ?>>Non</option>
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
                            <button class="bg-green-700 text-white px-4 py-2 rounded whitespace-nowrap"><i class="fas fa-filter mr-2"></i>Filtrer</button>
                            <a href="historique.php" class="px-4 py-2 border rounded whitespace-nowrap"><i class="fas fa-rotate-left mr-2"></i>Réinitialiser</a>
                            <a class="px-3 py-2 rounded bg-green-600 text-white whitespace-nowrap" href="?<?= htmlspecialchars(build_query(['export'=>'excel'])) ?>"><i class="fas fa-file-excel mr-2"></i>Excel</a>
                            <a class="px-3 py-2 rounded bg-red-600 text-white whitespace-nowrap" href="?<?= htmlspecialchars(build_query(['export'=>'pdf'])) ?>"><i class="fas fa-file-pdf mr-2"></i>PDF</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-[1200px] divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Camion</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Provenance</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destinataire</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">T1</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chargé</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Entrée</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($items as $c): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($c['marque']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($c['immatriculation']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($c['type_camion']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= !empty($c['nom_port']) ? htmlspecialchars($c['nom_port']) : '-' ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= isset($c['destinataire']) ? htmlspecialchars($c['destinataire']) : '-' ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= isset($c['t1']) ? htmlspecialchars($c['t1']) : '-' ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $c['est_charge'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= $c['est_charge'] ? 'Oui' : 'Non' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($c['date_entree'])) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $c['statut'] === 'entree' ? 'bg-yellow-100 text-yellow-800' : ($c['statut'] === 'en_pesage' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800') ?>">
                                    <?= ucfirst($c['statut']) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="enregistrer.php?camion_id=<?= $c['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i> Modifier
                                </a>
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
                        <a class="px-3 py-2 border rounded <?= $i === $page ? 'bg-green-700 text-white' : '' ?>" href="?<?= htmlspecialchars(build_query(['page'=>$i])) ?>"><?= $i ?></a>
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
    </script>
</body>
</html>



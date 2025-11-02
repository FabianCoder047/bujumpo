<?php
require_once '../includes/auth_check.php';
checkRole(['EnregistreurSortieRoute']);
require_once '../config/database.php';

$user = getCurrentUser();
$db = getDB();

// Filtres
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$type_camion_id = isset($_GET['type_camion_id']) ? trim($_GET['type_camion_id']) : '';
$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

// Pagination
$per_page = isset($_GET['per_page']) && (int)$_GET['per_page'] > 0 ? min((int)$_GET['per_page'], 100) : 10;
$page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $per_page;

// Requête de base
$select = "SELECT c.*, tc.nom as type_camion
    FROM camions c
    LEFT JOIN types_camions tc ON c.type_camion_id = tc.id";
$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(c.immatriculation LIKE :q OR c.marque LIKE :q OR c.chauffeur LIKE :q OR c.agence LIKE :q)';
    $params[':q'] = "%$q%";
}
if ($type_camion_id !== '') {
    $where[] = 'c.type_camion_id = :type_camion_id';
    $params[':type_camion_id'] = $type_camion_id;
}
if (in_array($statut, ['entree','en_pesage','sortie'], true)) {
    $where[] = 'c.statut = :statut';
    $params[':statut'] = $statut;
}
if ($date_from !== '') {
    $where[] = 'DATE(COALESCE(c.date_sortie, c.date_entree)) >= :date_from';
    $params[':date_from'] = $date_from;
}
if ($date_to !== '') {
    $where[] = 'DATE(COALESCE(c.date_sortie, c.date_entree)) <= :date_to';
    $params[':date_to'] = $date_to;
}

$whereSql = count($where) ? (' WHERE ' . implode(' AND ', $where)) : '';

// Exportations
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $sqlExport = $select . $whereSql . ' ORDER BY (c.date_sortie IS NULL), COALESCE(c.date_sortie, c.date_entree) DESC, c.id DESC';
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
    $headers = ['Marque','Immatriculation','Type','Chauffeur','Agence','Entrée','Sortie','Statut'];
    $col = 1;
    foreach ($headers as $h) {
        $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
        $sheet->setCellValue($addr, $h);
        $col++;
    }
    // Style entête
    $sheet->getStyle('A1:H1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'B45309']],
        'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
    ]);
    $rowNum = 2;
    foreach ($rows as $c) {
        $col = 1;
        $addr = function($colIndex, $rowIndex) { return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex) . $rowIndex; };
        $sheet->setCellValue($addr($col++, $rowNum), $c['marque']);
        $sheet->setCellValue($addr($col++, $rowNum), $c['immatriculation']);
        $sheet->setCellValue($addr($col++, $rowNum), $c['type_camion'] ?? '');
        $sheet->setCellValue($addr($col++, $rowNum), $c['chauffeur'] ?? '');
        $sheet->setCellValue($addr($col++, $rowNum), $c['agence'] ?? '');
        $sheet->setCellValue($addr($col++, $rowNum), $c['date_entree'] ? date('d/m/Y H:i', strtotime($c['date_entree'])) : '—');
        $sheet->setCellValue($addr($col++, $rowNum), $c['date_sortie'] ? date('d/m/Y H:i', strtotime($c['date_sortie'])) : '—');
        $sheet->setCellValue($addr($col++, $rowNum), ucfirst($c['statut']));
        if ($rowNum % 2 === 0) {
            $sheet->getStyle('A'.$rowNum.':H'.$rowNum)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F7');
        }
        $rowNum++;
    }
    foreach (range('A','H') as $colLetter) { $sheet->getColumnDimension($colLetter)->setAutoSize(true); }
    $sheet->setAutoFilter('A1:H1');
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="historique_sorties.xlsx"');
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    require_once __DIR__ . '/../vendor/autoload.php';
    $sqlExport = $select . $whereSql . ' ORDER BY (c.date_sortie IS NULL), COALESCE(c.date_sortie, c.date_entree) DESC';
    $stmt = $db->prepare($sqlExport);
    foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $title = 'Historique des Camions - Sorties';
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
    $html .= '<table><thead><tr><th>Marque</th><th>Immatriculation</th><th>Type</th><th>Chauffeur</th><th>Agence</th><th>Entrée</th><th>Sortie</th><th>Statut</th></tr></thead><tbody>';
    foreach ($rows as $c) {
        $html .= '<tr>'
            .'<td>'.htmlspecialchars($c['marque']).'</td>'
            .'<td>'.htmlspecialchars($c['immatriculation']).'</td>'
            .'<td>'.htmlspecialchars($c['type_camion'] ?? '').'</td>'
            .'<td>'.htmlspecialchars($c['chauffeur'] ?? '').'</td>'
            .'<td>'.htmlspecialchars($c['agence'] ?? '').'</td>'
            .'<td>'.($c['date_entree'] ? date('d/m/Y H:i', strtotime($c['date_entree'])) : '—').'</td>'
            .'<td>'.($c['date_sortie'] ? date('d/m/Y H:i', strtotime($c['date_sortie'])) : '—').'</td>'
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
    $dompdf->stream('historique_sorties.pdf', ['Attachment' => true]);
    exit;
}

// Compter total et récupérer page
$countSql = 'SELECT COUNT(*) FROM camions c LEFT JOIN types_camions tc ON c.type_camion_id = tc.id' . $whereSql;
$countStmt = $db->prepare($countSql);
foreach ($params as $k => $v) { $countStmt->bindValue($k, $v); }
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

$sql = $select . $whereSql . ' ORDER BY (c.date_sortie IS NULL), COALESCE(c.date_sortie, c.date_entree) DESC, c.id DESC LIMIT ' . (int)$per_page . ' OFFSET ' . (int)$offset;
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Données pour selects
$typesCamions = $db->query('SELECT id, nom FROM types_camions ORDER BY nom ASC')->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Historique - Vigile Sortie</title>
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
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-yellow-200 hover:bg-yellow-800 hover:text-white transition duration-200">
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
                        <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($user['prenom'].' '.$user['nom']) ?></p>
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
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'empty'): ?>
            <div class="mb-4 p-4 rounded border border-yellow-200 bg-yellow-50 text-yellow-800">
                <i class="fas fa-circle-info mr-2"></i>
                Aucune donnée correspondante pour les filtres sélectionnés. Veuillez ajuster les filtres et réessayer.
            </div>
            <?php endif; ?>
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Historique des Camions</h1>
                <p class="text-gray-600 mt-2">Dernières sorties et passages en poste</p>
            </div>

            <div class="bg-white rounded-lg shadow p-4 mb-4">
                <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-3">
                        <label class="block text-xs text-gray-600 mb-1">Recherche</label>
                        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Immatriculation, marque, chauffeur, agence" class="w-full border rounded px-3 py-2">
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
                        <label class="block text-xs text-gray-600 mb-1">Statut</label>
                        <select name="statut" class="w-full border rounded px-3 py-2">
                            <option value="">Tous</option>
                            <option value="entree" <?= $statut==='entree'?'selected':'' ?>>Entrée</option>
                            <option value="en_pesage" <?= $statut==='en_pesage'?'selected':'' ?>>En pesage</option>
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
                            <button class="bg-yellow-700 text-white px-4 py-2 rounded whitespace-nowrap"><i class="fas fa-filter mr-2"></i>Filtrer</button>
                            <a href="historique.php" class="px-4 py-2 border rounded whitespace-nowrap"><i class="fas fa-rotate-left mr-2"></i>Réinitialiser</a>
                            <a class="px-3 py-2 rounded bg-green-600 text-white whitespace-nowrap" href="?<?= htmlspecialchars(build_query(['export'=>'excel'])) ?>"><i class="fas fa-file-excel mr-2"></i>Excel</a>
                            <a class="px-3 py-2 rounded bg-red-600 text-white whitespace-nowrap" href="?<?= htmlspecialchars(build_query(['export'=>'pdf'])) ?>"><i class="fas fa-file-pdf mr-2"></i>PDF</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-lg shadow overflow-x-auto">
                <table class="min-w-[1100px] divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Camion</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Chauffeur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agence</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Entrée</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sortie</th>
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
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($c['type_camion'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($c['chauffeur'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($c['agence'] ?? '-') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $c['date_entree'] ? date('d/m/Y H:i', strtotime($c['date_entree'])) : '—' ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $c['date_sortie'] ? date('d/m/Y H:i', strtotime($c['date_sortie'])) : '—' ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php 
                                    $isValidated = !empty($c['date_sortie']);
                                    $statusText = $isValidated ? 'Sortie validée' : ($c['statut'] === 'sortie' ? 'En attente de validation (Enregistreur Sortie)' : ucfirst($c['statut']));
                                    $badgeClass = $isValidated ? 'bg-green-100 text-green-800' : ($c['statut'] === 'en_pesage' ? 'bg-blue-100 text-blue-800' : ($c['statut'] === 'sortie' ? 'bg-yellow-100 text-yellow-800' : 'bg-yellow-100 text-yellow-800'));
                                ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $badgeClass ?>">
                                    <?= htmlspecialchars($statusText) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <button onclick="voirDetails(<?= $c['id'] ?>)" class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-eye"></i> Détails
                                </button>
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
                        <a class="px-3 py-2 border rounded <?= $i === $page ? 'bg-yellow-700 text-white' : '' ?>" href="?<?= htmlspecialchars(build_query(['page'=>$i])) ?>"><?= $i ?></a>
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
                                <p class="text-sm text-gray-600">Chauffeur: ${camion.chauffeur || ''} | Agence: ${camion.agence || ''}</p>
                                <p class="text-sm text-gray-600">Statut: ${statutLabel}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4 mb-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Type de Camion</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${camion.type_camion || ''}</div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Date d'Entrée</label>
                                    <div class="px-3 py-2 bg-gray-100 rounded-md">${camion.date_entree ? new Date(camion.date_entree).toLocaleString('fr-FR') : '—'}</div>
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

                            <div class="flex justify-end">
                                <button type="button" onclick="closeDetailsModal()" class="px-4 py-2 text-gray-700 bg-gray-200 rounded hover:bg-gray-300">Fermer</button>
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

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }
    </script>
</body>
</html>






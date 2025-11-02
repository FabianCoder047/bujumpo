<?php
require_once '../../includes/auth_check.php';
checkRole(['admin']);
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$db = getDB();

$report = $_GET['report'] ?? 'tonnage_type';
$scope = $_GET['scope'] ?? 'month'; // month|year|custom
$format = $_GET['format'] ?? 'pdf'; // pdf|xlsx
$start = isset($_GET['start']) ? ($_GET['start'] . ' 00:00:00') : null;
$end = isset($_GET['end']) ? ($_GET['end'] . ' 23:59:59') : null;
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$checkOnly = isset($_GET['check']) && $_GET['check'] == '1';
// Optional specific merchandise type filter
$typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : null;
// Optional filters: movement and mode for aggregation
$mouvement = isset($_GET['mouvement']) && in_array($_GET['mouvement'], ['entree','sortie','tous'], true) ? $_GET['mouvement'] : 'tous';
$mode = isset($_GET['mode']) && in_array($_GET['mode'], ['camion','bateau','tous'], true) ? $_GET['mode'] : 'tous';

// Scope builder
function build_scope($scope, $start, $end, $year, $prefix, &$params) {
    if ($scope === 'month') {
        $start = date('Y-m-01 00:00:00');
        $end = date('Y-m-t 23:59:59');
        $params = [$start, $end];
        return "$prefix BETWEEN ? AND ?";
    } elseif ($scope === 'year') {
        $params = [$year];
        return "YEAR($prefix)=?";
    } else { // custom
        if (!$start || !$end) {
            $start = date('Y-m-01 00:00:00');
            $end = date('Y-m-t 23:59:59');
        }
        $params = [$start, $end];
        return "$prefix BETWEEN ? AND ?";
    }
}

function fetch_tonnage_type($db, $scope, $start, $end, $year, $typeId = null, $mouvement = 'tous', $mode = 'tous') {
    $map = [];
    if ($scope === 'month') {
        $start = date('Y-m-01 00:00:00');
        $end = date('Y-m-t 23:59:59');
        $whereMC = 'mc.created_at BETWEEN ? AND ?';
        $whereMB = 'mb.created_at BETWEEN ? AND ?';
        $params = [$start, $end];
    } elseif ($scope === 'year') {
        $whereMC = 'YEAR(mc.created_at)=?';
        $whereMB = 'YEAR(mb.created_at)=?';
        $params = [$year];
    } else { // custom
        if (!$start || !$end) {
            $start = date('Y-m-01 00:00:00');
            $end = date('Y-m-t 23:59:59');
        }
        $whereMC = 'mc.created_at BETWEEN ? AND ?';
        $whereMB = 'mb.created_at BETWEEN ? AND ?';
        $params = [$start, $end];
    }

    $includeCamions = ($mode === 'tous' || $mode === 'camion');
    $includeBateaux = ($mode === 'tous' || $mode === 'bateau');
    $doEntree = ($mouvement === 'tous' || $mouvement === 'entree');
    $doSortie = ($mouvement === 'tous' || $mouvement === 'sortie');

    if ($includeCamions && $doEntree) {
        $sql = "SELECT tm.nom t, SUM(mc.poids) s FROM marchandises_camions mc JOIN types_marchandises tm ON tm.id=mc.type_marchandise_id WHERE mc.mouvement='entree' AND mc.poids IS NOT NULL AND $whereMC" . ($typeId ? " AND tm.id=?" : "") . " GROUP BY t";
        $stmt = $db->prepare($sql);
        $stmt->execute($typeId ? array_merge($params, [$typeId]) : $params);
        foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($map[$t])) $map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $map[$t]['entree'] += $s; }
    }
    if ($includeCamions && $doSortie) {
        $sql = "SELECT tm.nom t, SUM(mc.poids) s FROM marchandises_camions mc JOIN types_marchandises tm ON tm.id=mc.type_marchandise_id WHERE mc.mouvement='sortie' AND mc.poids IS NOT NULL AND $whereMC" . ($typeId ? " AND tm.id=?" : "") . " GROUP BY t";
        $stmt = $db->prepare($sql);
        $stmt->execute($typeId ? array_merge($params, [$typeId]) : $params);
        foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($map[$t])) $map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $map[$t]['sortie'] += $s; }
    }
    if ($includeBateaux && $doEntree) {
        $sql = "SELECT tm.nom t, SUM(mb.poids) s FROM marchandises_bateaux mb JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id WHERE mb.mouvement='entree' AND mb.poids IS NOT NULL AND $whereMB" . ($typeId ? " AND tm.id=?" : "") . " GROUP BY t";
        $stmt = $db->prepare($sql);
        $stmt->execute($typeId ? array_merge($params, [$typeId]) : $params);
        foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($map[$t])) $map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $map[$t]['entree'] += $s; }
    }
    if ($includeBateaux && $doSortie) {
        $sql = "SELECT tm.nom t, SUM(mb.poids) s FROM marchandises_bateaux mb JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id WHERE mb.mouvement='sortie' AND mb.poids IS NOT NULL AND $whereMB" . ($typeId ? " AND tm.id=?" : "") . " GROUP BY t";
        $stmt = $db->prepare($sql);
        $stmt->execute($typeId ? array_merge($params, [$typeId]) : $params);
        foreach ($stmt->fetchAll() as $r) { $t=$r['t']; $s=(float)$r['s']; if(!isset($map[$t])) $map[$t] = ['entree'=>0.0,'sortie'=>0.0]; $map[$t]['sortie'] += $s; }
    }

    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
    return $map;
}

function fetch_camions($db, $scope, $start, $end, $year, $which, $typeId = null) {
    // $which: 'entree' or 'sortie'
    $params = [];
    $cond = build_scope($scope, $start, $end, $year, $which === 'entree' ? 'c.date_entree' : 'c.date_sortie', $params);
    $sql = "SELECT c.id, tc.nom AS type_camion, c.marque, c.immatriculation, c.chauffeur, c.agence,
                   p.nom AS provenance, c.destinataire, c.est_charge,
                   c.date_entree, c.date_sortie,
                   (SELECT COALESCE(SUM(mc.poids),0) FROM marchandises_camions mc WHERE mc.camion_id = c.id AND mc.mouvement='entree') AS poids_entree,
                   (SELECT COALESCE(SUM(mc.poids),0) FROM marchandises_camions mc WHERE mc.camion_id = c.id AND mc.mouvement='sortie') AS poids_sortie
            FROM camions c
            LEFT JOIN types_camions tc ON tc.id = c.type_camion_id
            LEFT JOIN ports p ON p.id = c.provenance_port_id
            WHERE $cond AND c." . ($which === 'entree' ? "date_entree IS NOT NULL" : "date_sortie IS NOT NULL") . (
                $typeId ? " AND EXISTS (SELECT 1 FROM marchandises_camions mc2 WHERE mc2.camion_id=c.id AND mc2.type_marchandise_id = :type_filter)" : ""
            ) . "
            ORDER BY " . ($which === 'entree' ? 'c.date_entree' : 'c.date_sortie') . " ASC";
    $stmt = $db->prepare($sql);
    if ($typeId) {
        $bindParams = $params;
        // named param must be bound separately when mixed with positional
        foreach ($bindParams as $i => $v) { /* keep */ }
        $stmt->execute(array_merge($params, []));
        $stmt->bindValue(':type_filter', $typeId, PDO::PARAM_INT);
    }
    if (!$typeId) {
        $stmt->execute($params);
    } else {
        // When mixing positional and named params, re-prepare with manual binding
        $idx = 1;
        $stmt = $db->prepare(str_replace(':type_filter', '?', $sql));
        $execParams = $params;
        $execParams[] = $typeId;
        $stmt->execute($execParams);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetch_bateaux($db, $scope, $start, $end, $year, $which, $typeId = null) {
    $params = [];
    $cond = build_scope($scope, $start, $end, $year, $which === 'entree' ? 'b.date_entree' : 'b.date_sortie', $params);
    $sql = "SELECT b.id, tb.nom AS type_bateau, b.nom, b.immatriculation, b.capitaine, b.agence,
                   b.hauteur, b.longueur, b.largeur,
                   po.nom AS port_origine, pd.nom AS port_destination,
                   b.date_entree, b.date_sortie,
                   (SELECT COALESCE(SUM(mb.poids),0) FROM marchandises_bateaux mb WHERE mb.bateau_id = b.id AND mb.mouvement='entree') AS poids_entree,
                   (SELECT COALESCE(SUM(mb.poids),0) FROM marchandises_bateaux mb WHERE mb.bateau_id = b.id AND mb.mouvement='sortie') AS poids_sortie
            FROM bateaux b
            LEFT JOIN types_bateaux tb ON tb.id = b.type_bateau_id
            LEFT JOIN ports po ON po.id = b.port_origine_id
            LEFT JOIN ports pd ON pd.id = b.port_destination_id
            WHERE $cond AND b." . ($which === 'entree' ? "date_entree IS NOT NULL" : "date_sortie IS NOT NULL") . (
                $typeId ? " AND EXISTS (SELECT 1 FROM marchandises_bateaux mb2 WHERE mb2.bateau_id=b.id AND mb2.type_marchandise_id = :type_filter)" : ""
            ) . "
            ORDER BY " . ($which === 'entree' ? 'b.date_entree' : 'b.date_sortie') . " ASC";
    $stmt = $db->prepare($sql);
    if ($typeId) {
        $stmt = $db->prepare(str_replace(':type_filter', '?', $sql));
        $execParams = $params;
        $execParams[] = $typeId;
        $stmt->execute($execParams);
    } else {
        $stmt->execute($params);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Router
if ($report === 'tonnage_type') {
    $data = fetch_tonnage_type($db, $scope, $start, $end, $year, $typeId, $mouvement, $mode);
    $title = 'Tonnage par type' . ($typeId ? ' (type spécifique)' : '') . ' - ' . ($scope === 'month' ? 'Mois courant' : ($scope === 'year' ? ('Année ' . $year) : ('Période ' . substr($start,0,10) . ' au ' . substr($end,0,10))));
} elseif (in_array($report, ['camions_entree','camions_sortie','bateaux_entree','bateaux_sortie'], true)) {
    $which = str_contains($report, 'entree') ? 'entree' : 'sortie';
    $isCamion = str_starts_with($report, 'camions');
    $data = $isCamion ? fetch_camions($db, $scope, $start, $end, $year, $which, $typeId) : fetch_bateaux($db, $scope, $start, $end, $year, $which, $typeId);
    $title = ($isCamion ? 'Camions ' : 'Bateaux ') . ($which === 'entree' ? 'entrés' : 'sortis') . ' - ' . ($scope === 'month' ? 'Mois courant' : ($scope === 'year' ? ('Année ' . $year) : ('Période ' . substr($start,0,10) . ' au ' . substr($end,0,10))));
} elseif ($report === 'frais_transit') {
    $data = fetch_frais_transit($db, $scope, $start, $end, $year);
    $title = 'Frais de transit (entrées) - ' . ($scope === 'month' ? 'Mois courant' : ($scope === 'year' ? ('Année ' . $year) : ('Période ' . substr($start,0,10) . ' au ' . substr($end,0,10))));
} else {
    http_response_code(400);
    echo 'Report inconnu';
    exit;
}

// Pré-check: si check=1, renvoyer uniquement s'il y a des données
if ($checkOnly) {
    $has = false;
    if ($report === 'tonnage_type' && is_array($data)) {
        foreach ($data as $row) {
            if ((($row['entree'] ?? 0) > 0) || (($row['sortie'] ?? 0) > 0)) { $has = true; break; }
        }
    } elseif (is_array($data)) {
        $has = count($data) > 0;
    }
    header('Content-Type: application/json');
    echo json_encode(['hasData' => $has]);
    exit;
}

// If custom scope and no data
if ($scope === 'custom') {
    $isEmpty = false;
    if ($report === 'tonnage_type') {
        $isEmpty = empty($data);
    } else {
        $isEmpty = is_array($data) ? (count($data) === 0) : true;
    }
    if ($checkOnly) {
        header('Content-Type: application/json');
        echo json_encode(['empty' => $isEmpty]);
        exit;
    }
    if ($isEmpty) {
        http_response_code(400);
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><title>Rapport vide</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#f9fafb;color:#111;padding:24px} .card{max-width:640px;margin:32px auto;background:#fff;border:1px solid #e5e7eb;border-radius:8px;box-shadow:0 1px 2px rgba(0,0,0,0.05)} .inner{padding:20px} h1{font-size:18px;margin:0 0 8px} p{margin:4px 0 0;color:#374151}</style></head><body><div class="card"><div class="inner"><h1>Résultat vide</h1><p>Aucune donnée trouvée pour la période personnalisée sélectionnée. Veuillez ajuster les dates et réessayer.</p></div></div></body></html>';
        exit;
    }
}

if ($format === 'xlsx') {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Rapport');
    $sheet->setCellValue('A1', 'Port de BUJUMBURA');
    $sheet->setCellValue('A2', $title);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet->getStyle('A2')->getFont()->setBold(true);

    if ($report === 'tonnage_type') {
        $headers = ['Type', 'Entrée (kg)', 'Sortie (kg)'];
        $sheet->fromArray($headers, NULL, 'A4');
        $row = 5;
        foreach ($data as $type => $vals) {
            $sheet->setCellValue('A' . $row, $type);
            $sheet->setCellValue('B' . $row, $vals['entree']);
            $sheet->setCellValue('C' . $row, $vals['sortie']);
            $row++;
        }
        $fileName = 'rapport_tonnage.xlsx';
    } elseif (str_starts_with($report, 'camions')) {
        $headers = ['Type', 'Marque', 'Immatriculation', 'Chauffeur', 'Agence', 'Provenance', 'Destinataire', 'Chargé', 'Date entrée', 'Date sortie', 'Poids Entrée (kg)', 'Poids Sortie (kg)'];
        $sheet->fromArray($headers, NULL, 'A4');
        $row = 5;
        foreach ($data as $r) {
            $sheet->fromArray([
                $r['type_camion'], $r['marque'], $r['immatriculation'], $r['chauffeur'], $r['agence'], $r['provenance'], $r['destinataire'], $r['est_charge'] ? 'Oui' : 'Non', $r['date_entree'], $r['date_sortie'], $r['poids_entree'], $r['poids_sortie']
            ], NULL, 'A' . $row);
            $row++;
        }
        $fileName = 'rapport_camions.xlsx';
    } elseif (str_starts_with($report, 'bateaux')) {
        $headers = ['Type', 'Nom', 'Immatriculation', 'Capitaine', 'Agence', 'Hauteur (m)', 'Longueur (m)', 'Largeur (m)', 'Port origine', 'Port destination', 'Date entrée', 'Date sortie', 'Poids Entrée (kg)', 'Poids Sortie (kg)'];
        $sheet->fromArray($headers, NULL, 'A4');
        $row = 5;
        foreach ($data as $r) {
            $sheet->fromArray([
                $r['type_bateau'], $r['nom'], ($r['immatriculation'] ?? ''), $r['capitaine'], ($r['agence'] ?? ''), ($r['hauteur'] ?? ''), ($r['longueur'] ?? ''), ($r['largeur'] ?? ''), $r['port_origine'], $r['port_destination'], $r['date_entree'], $r['date_sortie'], $r['poids_entree'], $r['poids_sortie']
            ], NULL, 'A' . $row);
            $row++;
        }
        $fileName = 'rapport_bateaux.xlsx';
    } elseif ($report === 'frais_transit') {
        $headers = ['Voie', 'Référence', 'Partie', 'Date entrée', 'THC', 'Magasinage', 'Droits de douane', 'Surestaries', 'Total', 'Etat'];
        $sheet->fromArray($headers, NULL, 'A4');
        $row = 5;
        foreach ($data as $r) {
            $hasFees = ($r['thc'] !== null) || ($r['magasinage'] !== null) || ($r['droits_douane'] !== null) || ($r['surestaries'] !== null);
            $etat = $hasFees ? 'APPLIQUÉ' : 'AUCUN FRAIS DE TRANSIT';
            $sheet->fromArray([
                $r['type'], $r['ident'], ($r['partie'] ?? ''), $r['date_ref'],
                ($r['thc'] ?? 0), ($r['magasinage'] ?? 0), ($r['droits_douane'] ?? 0), ($r['surestaries'] ?? 0), ($r['total'] ?? 0),
                $etat
            ], NULL, 'A' . $row);
            $row++;
        }
        $fileName = 'rapport_frais_douane.xlsx';
    }

    // Enhanced table styling (align with peseur exports)
    $lastCol = $sheet->getHighestColumn();
    $lastRow = $sheet->getHighestRow();

    // Header styling: bold white text on blue background and centered
    $headerRange = 'A4:' . $lastCol . '4';
    $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle($headerRange)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('1E3A8A');
    $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    // Borders
    $sheet->getStyle('A4:' . $lastCol . $lastRow)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Auto size columns
    foreach (range('A', $lastCol) as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

    // AutoFilter on header row
    $sheet->setAutoFilter($headerRange);

    // Zebra striping for data rows
    for ($r = 5; $r <= $lastRow; $r++) {
        if ($r % 2 === 0) {
            $sheet->getStyle('A' . $r . ':' . $lastCol . $r)
                  ->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                  ->getStartColor()->setRGB('F7F7F7');
        }
    }

    // Column-specific number formats
    if ($report === 'tonnage_type') {
        // B, C are numeric values
        $sheet->getStyle('B5:B' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('C5:C' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    } elseif (str_starts_with($report, 'camions')) {
        // Dates are columns I (9) and J (10)
        $sheet->getStyle('I5:I' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        $sheet->getStyle('J5:J' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
    } elseif (str_starts_with($report, 'bateaux')) {
        // Dates are columns K (11) and L (12) after adding dimensions
        $sheet->getStyle('K5:K' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
        $sheet->getStyle('L5:L' . $lastRow)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');
    } elseif ($report === 'frais_transit') {
        // Numeric columns: E..I
        $sheet->getStyle('E5:I' . $lastRow)->getNumberFormat()->setFormatCode('#,##0.00');
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// PDF
$html = '<html><head><style>
body{font-family:DejaVu Sans, sans-serif; color:#111}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.brand{font-size:14px;font-weight:700}
.subtitle{font-size:12px;color:#555}
table{width:100%;border-collapse:collapse;margin-top:10px;table-layout:auto}
thead{display:table-header-group}
tfoot{display:table-footer-group}
tr{page-break-inside:avoid}
th,td{border:1px solid #ddd;padding:5px;font-size:10px;vertical-align:top;white-space:nowrap}
th{background:#f3f4f6;text-transform:uppercase}
.muted{color:#666;font-size:10px;margin-top:6px}
</style></head><body>';
$html .= '<div class="header"><div><div class="brand">Port de BUJUMBURA</div><div class="subtitle">' . htmlspecialchars($title) . '</div></div><div class="subtitle">' . date('Y-m-d H:i') . '</div></div>';

if ($report === 'tonnage_type') {
    $html .= '<table><thead><tr><th>Type de marchandise</th><th>Entrée (kg)</th><th>Sortie (kg)</th></tr></thead><tbody>';
    foreach ($data as $type => $vals) {
        $html .= '<tr><td>' . htmlspecialchars($type) . '</td><td>' . number_format((float)$vals['entree'], 2, ',', ' ') . '</td><td>' . number_format((float)$vals['sortie'], 2, ',', ' ') . '</td></tr>';
    }
    $html .= '</tbody></table>';
} elseif (str_starts_with($report, 'camions')) {
    $html .= '<table><thead><tr><th>Type</th><th>Marque</th><th>Immat.</th><th>Chauffeur</th><th>Agence</th><th>Provenance</th><th>Destinataire</th><th>Chargé</th><th>Date entrée</th><th>Date sortie</th><th>Poids Entrée (kg)</th><th>Poids Sortie (kg)</th></tr></thead><tbody>';
    foreach ($data as $r) {
        $html .= '<tr>'
            . '<td>' . htmlspecialchars((string)$r['type_camion']) . '</td>'
            . '<td>' . htmlspecialchars((string)$r['marque']) . '</td>'
            . '<td>' . htmlspecialchars((string)$r['immatriculation']) . '</td>'
            . '<td>' . htmlspecialchars((string)$r['chauffeur']) . '</td>'
            . '<td>' . htmlspecialchars((string)$r['agence']) . '</td>'
            . '<td>' . htmlspecialchars((string)$r['provenance']) . '</td>'
            . '<td>' . htmlspecialchars((string)$r['destinataire']) . '</td>'
            . '<td>' . ((int)$r['est_charge'] ? 'Oui' : 'Non') . '</td>'
            . '<td>' . htmlspecialchars((string)$r['date_entree']) . '</td>'
            . '<td>' . htmlspecialchars((string)$r['date_sortie']) . '</td>'
            . '<td>' . number_format((float)$r['poids_entree'], 2, ',', ' ') . '</td>'
            . '<td>' . number_format((float)$r['poids_sortie'], 2, ',', ' ') . '</td>'
            . '</tr>';
    }
    $html .= '</tbody></table>';
} else { // bateaux
    if ($report === 'frais_transit') {
        $html .= '<table><thead><tr><th>Voie</th><th>Référence</th><th>Partie</th><th>Date entrée</th><th>THC</th><th>Magasinage</th><th>Droits</th><th>Surestaries</th><th>Total</th><th>Etat</th></tr></thead><tbody>';
        foreach ($data as $r) {
            $hasFees = ($r['thc'] !== null) || ($r['magasinage'] !== null) || ($r['droits_douane'] !== null) || ($r['surestaries'] !== null);
            $etat = $hasFees ? 'APPLIQUÉ' : 'AUCUN FRAIS DE TRANSIT';
            $html .= '<tr>'
                . '<td>' . htmlspecialchars((string)$r['type']) . '</td>'
                . '<td>' . htmlspecialchars((string)$r['ident']) . '</td>'
                . '<td>' . htmlspecialchars((string)($r['partie'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string)$r['date_ref']) . '</td>'
                . '<td>' . number_format((float)($r['thc'] ?? 0), 2, ',', ' ') . '</td>'
                . '<td>' . number_format((float)($r['magasinage'] ?? 0), 2, ',', ' ') . '</td>'
                . '<td>' . number_format((float)($r['droits_douane'] ?? 0), 2, ',', ' ') . '</td>'
                . '<td>' . number_format((float)($r['surestaries'] ?? 0), 2, ',', ' ') . '</td>'
                . '<td>' . number_format((float)($r['total'] ?? 0), 2, ',', ' ') . '</td>'
                . '<td>' . $etat . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html .= '<table><thead><tr><th>Type</th><th>Nom</th><th>Immat.</th><th>Capitaine</th><th>Agence</th><th>Hauteur (m)</th><th>Longueur (m)</th><th>Largeur (m)</th><th>Port origine</th><th>Port destination</th><th>Date entrée</th><th>Date sortie</th><th>Poids Entrée (kg)</th><th>Poids Sortie (kg)</th></tr></thead><tbody>';
        foreach ($data as $r) {
            $html .= '<tr>'
                . '<td>' . htmlspecialchars((string)$r['type_bateau']) . '</td>'
                . '<td>' . htmlspecialchars((string)$r['nom']) . '</td>'
                . '<td>' . htmlspecialchars((string)($r['immatriculation'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string)$r['capitaine']) . '</td>'
                . '<td>' . htmlspecialchars((string)($r['agence'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string)($r['hauteur'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string)($r['longueur'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string)($r['largeur'] ?? '')) . '</td>'
                . '<td>' . htmlspecialchars((string)$r['port_origine']) . '</td>'
                . '<td>' . htmlspecialchars((string)$r['port_destination']) . '</td>'
                . '<td>' . htmlspecialchars((string)$r['date_entree']) . '</td>'
                . '<td>' . htmlspecialchars((string)$r['date_sortie']) . '</td>'
                . '<td>' . number_format((float)$r['poids_entree'], 2, ',', ' ') . '</td>'
                . '<td>' . number_format((float)$r['poids_sortie'], 2, ',', ' ') . '</td>'
                . '</tr>';
        }
        $html .= '</tbody></table>';
    }
}

$html .= '</body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
// Use landscape for detailed wide tables
if (str_starts_with($report, 'camions') || str_starts_with($report, 'bateaux')) {
    $dompdf->setPaper('A4', 'landscape');
} else {
    $dompdf->setPaper('A4', 'portrait');
}
$dompdf->render();
$dompdf->stream('rapport_douane.pdf', ['Attachment' => true]);
exit;

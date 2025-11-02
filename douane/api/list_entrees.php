<?php
require_once '../../includes/auth_check.php';
checkRole(['douanier']);
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $db = getDB();

    // Scope: month/year/custom with optional dates; defaults to current month
    $scope = isset($_GET['scope']) ? strtolower($_GET['scope']) : 'month';
    $start = isset($_GET['start']) ? ($_GET['start'] . ' 00:00:00') : null;
    $end = isset($_GET['end']) ? ($_GET['end'] . ' 23:59:59') : null;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
    // Pagination
    $perPage = isset($_GET['per_page']) && (int)$_GET['per_page'] > 0 ? min((int)$_GET['per_page'], 100) : 10;
    $page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;

    $paramsC = [];
    $paramsB = [];
    if ($scope === 'year') {
        $condC = 'YEAR(c.date_entree)=?';
        $condB = 'YEAR(b.date_entree)=?';
        $paramsC = [$year];
        $paramsB = [$year];
    } elseif ($scope === 'custom' && $start && $end) {
        $condC = 'c.date_entree BETWEEN ? AND ?';
        $condB = 'b.date_entree BETWEEN ? AND ?';
        $paramsC = [$start, $end];
        $paramsB = [$start, $end];
    } else { // month default
        $mStart = date('Y-m-01 00:00:00');
        $mEnd = date('Y-m-t 23:59:59');
        $condC = 'c.date_entree BETWEEN ? AND ?';
        $condB = 'b.date_entree BETWEEN ? AND ?';
        $paramsC = [$mStart, $mEnd];
        $paramsB = [$mStart, $mEnd];
    }

    // Camions entries with merchandise lines and existing fees
    $sqlC = "SELECT c.id, c.immatriculation, c.chauffeur, c.agence, c.date_entree,
                    ft.thc, ft.magasinage, ft.droits_douane, ft.surestaries,
                    tm.nom AS marchandise, mc.poids, mc.quantite
             FROM camions c
             LEFT JOIN frais_transit ft ON ft.type='camion' AND ft.ref_id=c.id AND ft.mouvement='entree'
             LEFT JOIN marchandises_camions mc ON mc.camion_id=c.id AND (mc.mouvement='entree' OR mc.mouvement IS NULL)
             LEFT JOIN types_marchandises tm ON tm.id=mc.type_marchandise_id
             WHERE $condC AND c.date_entree IS NOT NULL
             ORDER BY c.date_entree DESC, c.id DESC
             LIMIT 500";
    $stmtC = $db->prepare($sqlC);
    $stmtC->execute($paramsC);
    $rowsC = $stmtC->fetchAll(PDO::FETCH_ASSOC);

    // Group by camion id
    $camions = [];
    foreach ($rowsC as $r) {
        $id = (int)$r['id'];
        if (!isset($camions[$id])) {
            $camions[$id] = [
                'type' => 'camion',
                'ref_id' => $id,
                'ident' => $r['immatriculation'],
                'partie' => $r['chauffeur'],
                'date_entree' => $r['date_entree'],
                'agence' => $r['agence'],
                'fees' => [
                    'thc' => $r['thc'],
                    'magasinage' => $r['magasinage'],
                    'droits_douane' => $r['droits_douane'],
                    'surestaries' => $r['surestaries']
                ],
                'marchandises' => []
            ];
        }
        if ($r['marchandise'] !== null) {
            $camions[$id]['marchandises'][] = [
                'type' => $r['marchandise'],
                'poids' => $r['poids'],
                'quantite' => $r['quantite']
            ];
        }
    }

    // Bateaux entries
    $sqlB = "SELECT b.id, COALESCE(b.immatriculation, b.nom) AS ident, b.capitaine, b.date_entree,
                    ft.thc, ft.magasinage, ft.droits_douane, ft.surestaries,
                    tm.nom AS marchandise, mb.poids, mb.quantite
             FROM bateaux b
             LEFT JOIN frais_transit ft ON ft.type='bateau' AND ft.ref_id=b.id AND ft.mouvement='entree'
             LEFT JOIN marchandises_bateaux mb ON mb.bateau_id=b.id AND (mb.mouvement='entree' OR mb.mouvement IS NULL)
             LEFT JOIN types_marchandises tm ON tm.id=mb.type_marchandise_id
             WHERE $condB AND b.date_entree IS NOT NULL
             ORDER BY b.date_entree DESC, b.id DESC
             LIMIT 500";
    $stmtB = $db->prepare($sqlB);
    $stmtB->execute($paramsB);
    $rowsB = $stmtB->fetchAll(PDO::FETCH_ASSOC);

    $bateaux = [];
    foreach ($rowsB as $r) {
        $id = (int)$r['id'];
        if (!isset($bateaux[$id])) {
            $bateaux[$id] = [
                'type' => 'bateau',
                'ref_id' => $id,
                'ident' => $r['ident'],
                'partie' => $r['capitaine'],
                'date_entree' => $r['date_entree'],
                'fees' => [
                    'thc' => $r['thc'],
                    'magasinage' => $r['magasinage'],
                    'droits_douane' => $r['droits_douane'],
                    'surestaries' => $r['surestaries']
                ],
                'marchandises' => []
            ];
        }
        if ($r['marchandise'] !== null) {
            $bateaux[$id]['marchandises'][] = [
                'type' => $r['marchandise'],
                'poids' => $r['poids'],
                'quantite' => $r['quantite']
            ];
        }
    }

    $data = array_values($camions);
    $data = array_merge($data, array_values($bateaux));

    // order by date_entree desc
    usort($data, function($a, $b) {
        return strcmp((string)$b['date_entree'], (string)$a['date_entree']);
    });

    $total = count($data);
    $totalPages = max(1, (int)ceil($total / $perPage));
    if ($page > $totalPages) { $page = $totalPages; }
    $offset = ($page - 1) * $perPage;
    $paged = array_slice($data, $offset, $perPage);

    echo json_encode([
        'success' => true,
        'scope' => $scope,
        'count' => $total,
        'total_pages' => $totalPages,
        'page' => $page,
        'per_page' => $perPage,
        'items' => array_values($paged)
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

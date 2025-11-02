<?php
require_once '../includes/auth_check.php';
checkRole(['admin']);
require_once '../config/database.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Dompdf\Dompdf;

$user = getCurrentUser();
$db = getDB();

// Créer le dossier reports s'il n'existe pas
if (!file_exists('../reports')) {
    mkdir('../reports', 0777, true);
}

// Charger la liste des types de marchandises pour filtrer (aligné avec Autorité)
$types = [];
try {
    $stmt = $db->query("SELECT id, nom FROM types_marchandises ORDER BY nom");
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $types = [];
}

// Nouveau: Tonnage par mouvement (entrées/sorties), scindé par bateaux et camions
function getTonnageParMouvement($db, $mouvement = 'entree', $start_date = null, $end_date = null) {
    $dateColumnBateaux = $mouvement === 'sortie' ? 'b.date_sortie' : 'b.date_entree';
    $dateColumnCamions = $mouvement === 'sortie' ? 'c.date_sortie' : 'c.date_entree';

    $condsB = ["mb.mouvement = :mv"];
    $condsC = ["mc.mouvement = :mv"];
    $params = [':mv' => $mouvement];

    if ($mouvement === 'sortie') {
        $condsB[] = 'b.date_sortie IS NOT NULL';
        $condsC[] = 'c.date_sortie IS NOT NULL';
    }
    if ($start_date) {
        $condsB[] = "$dateColumnBateaux >= :start_b";
        $condsC[] = "$dateColumnCamions >= :start_c";
        $params[':start_b'] = $start_date;
        $params[':start_c'] = $start_date;
    }
    if ($end_date) {
        $end_full = $end_date . ' 23:59:59';
        $condsB[] = "$dateColumnBateaux <= :end_b";
        $condsC[] = "$dateColumnCamions <= :end_c";
        $params[':end_b'] = $end_full;
        $params[':end_c'] = $end_full;
    }

    $whereB = 'WHERE ' . implode(' AND ', $condsB);
    $whereC = 'WHERE ' . implode(' AND ', $condsC);

    $sqlB = "SELECT COALESCE(SUM(mb.poids),0) AS tonnage_bateaux
             FROM marchandises_bateaux mb
             JOIN bateaux b ON b.id = mb.bateau_id
             $whereB";
    $sqlC = "SELECT COALESCE(SUM(mc.poids),0) AS tonnage_camions
             FROM marchandises_camions mc
             JOIN camions c ON c.id = mc.camion_id
             $whereC";

    $stmtB = $db->prepare($sqlB);
    foreach ($params as $k => $v) { $stmtB->bindValue($k, $v); }
    $stmtB->execute();
    $resB = $stmtB->fetch(PDO::FETCH_ASSOC);

    $stmtC = $db->prepare($sqlC);
    foreach ($params as $k => $v) { $stmtC->bindValue($k, $v); }
    $stmtC->execute();
    $resC = $stmtC->fetch(PDO::FETCH_ASSOC);

    $tb = (float)($resB['tonnage_bateaux'] ?? 0);
    $tc = (float)($resC['tonnage_camions'] ?? 0);
    return [
        'tonnage_bateaux' => $tb,
        'tonnage_camions' => $tc,
        'total' => $tb + $tc,
    ];
}

// Traitement de la génération des rapports
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    $report_type = $_POST['report_type'];
    $format = $_POST['format'];
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    
    $filename = 'rapport_' . $report_type . '_' . date('Y-m-d_H-i-s');
    
    switch ($report_type) {
        case 'tonnage_par_type':
            $data = getTonnageParType($db, $start_date, $end_date);
            $title = "Tonnage par type de marchandise";
            $columns = ['Type de marchandise', 'Entrée (kg)', 'Sortie (kg)', 'Total (kg)'];
            break;
        case 'marchandises_entrantes':
            $data = getMarchandisesEntrantes($db, $start_date, $end_date);
            $title = "Rapport des marchandises entrantes";
            $columns = ['ID', 'Type de marchandise', 'Quantité', 'Unité', 'Date d\'entrée', 'Voie', 'N° Bateau/Camion'];
            break;
            
        case 'marchandises_sortantes':
            $data = getMarchandisesSortantes($db, $start_date, $end_date);
            $title = "Rapport des marchandises sortantes";
            $columns = ['ID', 'Type de marchandise', 'Quantité', 'Unité', 'Date de sortie', 'Voie', 'N° Bateau/Camion'];
            break;
            
        case 'camions':
            $data = getRapportCamions($db, $start_date, $end_date);
            $title = "Rapport des mouvements de camions";
            $columns = ['ID', 'Plaque d\'immatriculation', 'Chauffeur', 'Date d\'entrée', 'Date de sortie', 'Statut', 'Type de marchandise', 'Quantité'];
            break;
            
        case 'bateaux':
            $data = getRapportBateaux($db, $start_date, $end_date);
            $title = "Rapport des mouvements de bateaux";
            $columns = ['ID', 'Nom du bateau', 'Pavillon', 'Date d\'entrée', 'Date de sortie', 'Statut', 'Type de marchandise', 'Quantité'];
            break;
    }
    
    if ($format === 'excel') {
        generateExcel($data, $columns, $title, $filename);
    } else {
        generatePDF($data, $columns, $title, $filename);
    }
}

// Fonctions pour récupérer les données
function getMarchandisesEntrantes($db, $start_date = null, $end_date = null) {
    // Requête pour les marchandises des bateaux
    $sql_bateaux = "SELECT 
        mb.id, 
        tm.nom as type_marchandise, 
        mb.poids as quantite, 
        'kg' as unite, 
        b.date_entree,
        'Maritime' as voie,
        b.immatriculation as numero_vehicule
    FROM marchandises_bateaux mb
    JOIN bateaux b ON mb.bateau_id = b.id
    JOIN types_marchandises tm ON mb.type_marchandise_id = tm.id
    WHERE 1=1";
    
    // Requête pour les marchandises des camions
    $sql_camions = "SELECT 
        mc.id, 
        tm.nom as type_marchandise, 
        mc.poids as quantite, 
        'kg' as unite, 
        c.date_entree,
        'Terrestre' as voie,
        c.immatriculation as numero_vehicule
    FROM marchandises_camions mc
    JOIN camions c ON mc.camion_id = c.id
    JOIN types_marchandises tm ON mc.type_marchandise_id = tm.id
    WHERE 1=1";
    
    $params = [];
    
    // Filtrage par date pour les bateaux
    if ($start_date) {
        $sql_bateaux .= " AND b.date_entree >= ?";
        $sql_camions .= " AND c.date_entree >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql_bateaux .= " AND b.date_entree <= ?";
        $sql_camions .= " AND c.date_entree <= ?";
        $params[] = $end_date . ' 23:59:59';
    }
    
    // Exécution et fusion des résultats
    $stmt_bateaux = $db->prepare($sql_bateaux);
    $stmt_bateaux->execute($params);
    $result_bateaux = $stmt_bateaux->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_camions = $db->prepare($sql_camions);
    $stmt_camions->execute($params);
    $result_camions = $stmt_camions->fetchAll(PDO::FETCH_ASSOC);
    
    return array_merge($result_bateaux, $result_camions);
}

function getTonnageParType($db, $start_date = null, $end_date = null) {
    $paramsMC = [];
    $paramsMB = [];
    $whereMC = '1=1';
    $whereMB = '1=1';
    if ($start_date) {
        $whereMC .= ' AND mc.created_at >= ?';
        $whereMB .= ' AND mb.created_at >= ?';
        $paramsMC[] = $start_date . ' 00:00:00';
        $paramsMB[] = $start_date . ' 00:00:00';
    }
    if ($end_date) {
        $whereMC .= ' AND mc.created_at <= ?';
        $whereMB .= ' AND mb.created_at <= ?';
        $paramsMC[] = $end_date . ' 23:59:59';
        $paramsMB[] = $end_date . ' 23:59:59';
    }

    // Agrégation camions
    $sqlMC = "SELECT tm.nom AS type, 
                     SUM(CASE WHEN mc.mouvement='entree' THEN mc.poids ELSE 0 END) AS entree,
                     SUM(CASE WHEN mc.mouvement='sortie' THEN mc.poids ELSE 0 END) AS sortie
              FROM marchandises_camions mc
              JOIN types_marchandises tm ON tm.id = mc.type_marchandise_id
              WHERE mc.poids IS NOT NULL AND $whereMC
              GROUP BY tm.nom";
    $stmtMC = $db->prepare($sqlMC);
    $stmtMC->execute($paramsMC);
    $map = [];
    foreach ($stmtMC->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $t = $r['type'];
        if (!isset($map[$t])) { $map[$t] = ['entree' => 0.0, 'sortie' => 0.0]; }
        $map[$t]['entree'] += (float)$r['entree'];
        $map[$t]['sortie'] += (float)$r['sortie'];
    }

    // Agrégation bateaux
    $sqlMB = "SELECT tm.nom AS type, 
                     SUM(CASE WHEN mb.mouvement='entree' THEN mb.poids ELSE 0 END) AS entree,
                     SUM(CASE WHEN mb.mouvement='sortie' THEN mb.poids ELSE 0 END) AS sortie
              FROM marchandises_bateaux mb
              JOIN types_marchandises tm ON tm.id = mb.type_marchandise_id
              WHERE mb.poids IS NOT NULL AND $whereMB
              GROUP BY tm.nom";
    $stmtMB = $db->prepare($sqlMB);
    $stmtMB->execute($paramsMB);
    foreach ($stmtMB->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $t = $r['type'];
        if (!isset($map[$t])) { $map[$t] = ['entree' => 0.0, 'sortie' => 0.0]; }
        $map[$t]['entree'] += (float)$r['entree'];
        $map[$t]['sortie'] += (float)$r['sortie'];
    }

    ksort($map, SORT_NATURAL | SORT_FLAG_CASE);

    // Transformer en lignes pour l'export générique
    $rows = [];
    foreach ($map as $type => $vals) {
        $entree = (float)$vals['entree'];
        $sortie = (float)$vals['sortie'];
        $rows[] = [$type, $entree, $sortie, $entree + $sortie];
    }
    return $rows;
}

function getMarchandisesSortantes($db, $start_date = null, $end_date = null) {
    // Requête pour les marchandises des bateaux
    $sql_bateaux = "SELECT 
        mb.id, 
        tm.nom as type_marchandise, 
        mb.poids as quantite, 
        'kg' as unite, 
        b.date_sortie as date_entree,
        'Maritime' as voie,
        b.immatriculation as numero_vehicule
    FROM marchandises_bateaux mb
    JOIN bateaux b ON mb.bateau_id = b.id
    JOIN types_marchandises tm ON mb.type_marchandise_id = tm.id
    WHERE b.date_sortie IS NOT NULL";
    
    // Requête pour les marchandises des camions
    $sql_camions = "SELECT 
        mc.id, 
        tm.nom as type_marchandise, 
        mc.poids as quantite, 
        'kg' as unite, 
        c.date_sortie as date_entree,
        'Terrestre' as voie,
        c.immatriculation as numero_vehicule
    FROM marchandises_camions mc
    JOIN camions c ON mc.camion_id = c.id
    JOIN types_marchandises tm ON mc.type_marchandise_id = tm.id
    WHERE c.date_sortie IS NOT NULL";
    
    $params = [];
    
    // Filtrage par date pour les bateaux
    if ($start_date) {
        $sql_bateaux .= " AND b.date_sortie >= ?";
        $sql_camions .= " AND c.date_sortie >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $sql_bateaux .= " AND b.date_sortie <= ?";
        $sql_camions .= " AND c.date_sortie <= ?";
        $params[] = $end_date . ' 23:59:59';
    }
    
    // Exécution et fusion des résultats
    $stmt_bateaux = $db->prepare($sql_bateaux);
    $stmt_bateaux->execute($params);
    $result_bateaux = $stmt_bateaux->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt_camions = $db->prepare($sql_camions);
    $stmt_camions->execute($params);
    $result_camions = $stmt_camions->fetchAll(PDO::FETCH_ASSOC);
    
    return array_merge($result_bateaux, $result_camions);
}

function getRapportCamions($db, $start_date = null, $end_date = null) {
    $sql = "SELECT 
        c.id, 
        c.immatriculation, 
        c.chauffeur, 
        c.date_entree, 
        c.date_sortie,
        CASE 
            WHEN c.date_sortie IS NULL THEN 'Dans le port' 
            ELSE 'Sorti' 
        END as statut,
        GROUP_CONCAT(DISTINCT tm.nom SEPARATOR ', ') as types_marchandises,
        COALESCE(SUM(mc.poids), 0) as quantite_totale
    FROM camions c
    LEFT JOIN marchandises_camions mc ON mc.camion_id = c.id
    LEFT JOIN types_marchandises tm ON mc.type_marchandise_id = tm.id";
    
    $where = [];
    $params = [];
    
    if ($start_date) {
        $where[] = "(c.date_entree >= ? OR ? IS NULL)";
        $params[] = $start_date;
        $params[] = $start_date;
    }
    if ($end_date) {
        $where[] = "(c.date_entree <= ? OR ? IS NULL)";
        $params[] = $end_date . ' 23:59:59';
        $params[] = $end_date;
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $sql .= " GROUP BY c.id, c.immatriculation, c.chauffeur, c.date_entree, c.date_sortie";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getRapportBateaux($db, $start_date = null, $end_date = null) {
    $sql = "SELECT 
        b.id, 
        b.immatriculation as nom_bateau, 
        b.pavillon, 
        b.date_entree, 
        b.date_sortie,
        CASE 
            WHEN b.date_sortie IS NULL THEN 'Dans le port' 
            ELSE 'Sorti' 
        END as statut,
        GROUP_CONCAT(DISTINCT tm.nom SEPARATOR ', ') as types_marchandises,
        COALESCE(SUM(mb.poids), 0) as quantite_totale
    FROM bateaux b
    LEFT JOIN marchandises_bateaux mb ON mb.bateau_id = b.id
    LEFT JOIN types_marchandises tm ON mb.type_marchandise_id = tm.id";
    
    $where = [];
    $params = [];
    
    if ($start_date) {
        $where[] = "(b.date_entree >= ? OR ? IS NULL)";
        $params[] = $start_date;
        $params[] = $start_date;
    }
    if ($end_date) {
        $where[] = "(b.date_entree <= ? OR ? IS NULL)";
        $params[] = $end_date . ' 23:59:59';
        $params[] = $end_date;
    }
    
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    $sql .= " GROUP BY b.id, b.numero, b.pavillon, b.date_entree, b.date_sortie";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fonctions pour les statistiques
function getTonnageTotal($db, $start_date = null, $end_date = null) {
    $sql = "SELECT 
        COALESCE(SUM(mb.poids), 0) as tonnage_bateaux,
        COALESCE(SUM(mc.poids), 0) as tonnage_camions
    FROM 
        (SELECT id FROM bateaux WHERE 1=1 
         " . ($start_date ? " AND date_entree >= :start_date " : "") . "
         " . ($end_date ? " AND date_entree <= :end_date " : "") . 
        ") b
    LEFT JOIN marchandises_bateaux mb ON b.id = mb.bateau_id
    CROSS JOIN 
        (SELECT id FROM camions WHERE 1=1 
         " . ($start_date ? " AND date_entree >= :start_date2 " : "") . "
         " . ($end_date ? " AND date_entree <= :end_date2 " : "") . 
        ") c
    LEFT JOIN marchandises_camions mc ON c.id = mc.camion_id";
    
    $stmt = $db->prepare($sql);
    if ($start_date) {
        $stmt->bindValue(':start_date', $start_date);
        $stmt->bindValue(':start_date2', $start_date);
    }
    if ($end_date) {
        $stmt->bindValue(':end_date', $end_date . ' 23:59:59');
        $stmt->bindValue(':end_date2', $end_date . ' 23:59:59');
    }
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getStatsParCategorie($db, $start_date = null, $end_date = null) {
    $sql = "SELECT 
        tm.nom as categorie,
        COALESCE(SUM(mb.poids), 0) as quantite_bateaux,
        COALESCE(SUM(mc.poids), 0) as quantite_camions,
        'kg' as unite
    FROM types_marchandises tm
    LEFT JOIN marchandises_bateaux mb ON tm.id = mb.type_marchandise_id
        " . ($start_date || $end_date ? " AND EXISTS (SELECT 1 FROM bateaux b WHERE b.id = mb.bateau_id " . 
        ($start_date ? " AND b.date_entree >= :start_date " : "") . 
        ($end_date ? " AND b.date_entree <= :end_date " : "") . 
        ")" : "") . "
    LEFT JOIN marchandises_camions mc ON tm.id = mc.type_marchandise_id
        " . ($start_date || $end_date ? " AND EXISTS (SELECT 1 FROM camions c WHERE c.id = mc.camion_id " . 
        ($start_date ? " AND c.date_entree >= :start_date2 " : "") . 
        ($end_date ? " AND c.date_entree <= :end_date2 " : "") . 
        ")" : "") . "
    GROUP BY tm.id, tm.nom
    HAVING quantite_bateaux > 0 OR quantite_camions > 0";
    
    $stmt = $db->prepare($sql);
    
    if ($start_date) {
        $stmt->bindValue(':start_date', $start_date);
        $stmt->bindValue(':start_date2', $start_date);
    }
    if ($end_date) {
        $end_date_full = $end_date . ' 23:59:59';
        $stmt->bindValue(':end_date', $end_date_full);
        $stmt->bindValue(':end_date2', $end_date_full);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatsParCategorieParMouvement($db, $mouvement = 'entree', $start_date = null, $end_date = null) {
    $dateColumnBateaux = $mouvement === 'sortie' ? 'b.date_sortie' : 'b.date_entree';
    $dateColumnCamions = $mouvement === 'sortie' ? 'c.date_sortie' : 'c.date_entree';

    $condsB = [];
    $condsC = [];
    $params = [];

    if ($mouvement === 'sortie') {
        $condsB[] = 'b.date_sortie IS NOT NULL';
        $condsC[] = 'c.date_sortie IS NOT NULL';
    }
    if ($start_date) {
        $condsB[] = "$dateColumnBateaux >= :start_b";
        $condsC[] = "$dateColumnCamions >= :start_c";
        $params[':start_b'] = $start_date;
        $params[':start_c'] = $start_date;
    }
    if ($end_date) {
        $end_full = $end_date . ' 23:59:59';
        $condsB[] = "$dateColumnBateaux <= :end_b";
        $condsC[] = "$dateColumnCamions <= :end_c";
        $params[':end_b'] = $end_full;
        $params[':end_c'] = $end_full;
    }

    $whereB = !empty($condsB) ? 'WHERE ' . implode(' AND ', $condsB) : '';
    $whereC = !empty($condsC) ? 'WHERE ' . implode(' AND ', $condsC) : '';

    $sql = "SELECT 
                tm.nom AS categorie,
                COALESCE(bq.quantite_bateaux, 0) AS quantite_bateaux,
                COALESCE(cq.quantite_camions, 0) AS quantite_camions,
                'kg' AS unite
            FROM types_marchandises tm
            LEFT JOIN (
                SELECT mb.type_marchandise_id AS type_id, SUM(COALESCE(mb.poids,0)) AS quantite_bateaux
                FROM marchandises_bateaux mb
                JOIN bateaux b ON b.id = mb.bateau_id
                " . ($whereB ? str_replace('WHERE', 'WHERE mb.mouvement = :mv AND', $whereB) : 'WHERE mb.mouvement = :mv') . "
                GROUP BY mb.type_marchandise_id
            ) bq ON bq.type_id = tm.id
            LEFT JOIN (
                SELECT mc.type_marchandise_id AS type_id, SUM(COALESCE(mc.poids,0)) AS quantite_camions
                FROM marchandises_camions mc
                JOIN camions c ON c.id = mc.camion_id
                " . ($whereC ? str_replace('WHERE', 'WHERE mc.mouvement = :mv AND', $whereC) : 'WHERE mc.mouvement = :mv') . "
                GROUP BY mc.type_marchandise_id
            ) cq ON cq.type_id = tm.id
            WHERE COALESCE(bq.quantite_bateaux, 0) > 0 OR COALESCE(cq.quantite_camions, 0) > 0
            ORDER BY tm.nom";

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':mv', $mouvement);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStatsMouvements($db, $start_date = null, $end_date = null) {
    $where = [];
    $params = [];
    
    if ($start_date) {
        $where[] = "date_entree >= ?";
        $params[] = $start_date;
    }
    if ($end_date) {
        $where[] = "date_entree <= ?";
        $params[] = $end_date . ' 23:59:59';
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Stats camions
    $sql_camions = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN date_sortie IS NULL THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN date_sortie IS NOT NULL THEN 1 ELSE 0 END) as termines
    FROM camions $whereClause";
    
    $stmt = $db->prepare($sql_camions);
    $stmt->execute($params);
    $stats_camions = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Stats bateaux
    $sql_bateaux = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN date_sortie IS NULL THEN 1 ELSE 0 END) as en_cours,
        SUM(CASE WHEN date_sortie IS NOT NULL THEN 1 ELSE 0 END) as termines
    FROM bateaux $whereClause";
    
    $stmt = $db->prepare($sql_bateaux);
    $stmt->execute($params);
    $stats_bateaux = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'camions' => $stats_camions,
        'bateaux' => $stats_bateaux
    ];
}

// Fonctions de génération de rapports
function generateExcel($data, $columns, $title, $filename) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Titre
    $sheet->setCellValue('A1', $title);
    $sheet->mergeCells('A1:' . chr(64 + count($columns)) . '1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal('center');
    
    // En-têtes
    $col = 'A';
    foreach ($columns as $column) {
        $sheet->setCellValue($col . '3', $column);
        $sheet->getStyle($col . '3')->getFont()->setBold(true);
        $col++;
    }
    
    // Données
    $row = 4;
    foreach ($data as $item) {
        $col = 'A';
        foreach ($item as $value) {
            $sheet->setCellValue($col . $row, $value);
            $col++;
        }
        $row++;
    }
    
    // Ajuster la largeur des colonnes
    foreach (range('A', $col) as $columnID) {
        $sheet->getColumnDimension($columnID)->setAutoSize(true);
    }
    
    // Enregistrer le fichier
    $writer = new Xlsx($spreadsheet);
    $filepath = '../reports/' . $filename . '.xlsx';
    $writer->save($filepath);
    
    // Télécharger le fichier
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . basename($filepath) . '"');
    header('Cache-Control: max-age=0');
    readfile($filepath);
    exit;
}

function generatePDF($data, $columns, $title, $filename) {
    // Vérifier si Dompdf est correctement chargé
    if (!class_exists('Dompdf\Dompdf')) {
        die('Erreur: La classe Dompdf n\'a pas pu être chargée. Vérifiez que vous avez bien exécuté "composer require dompdf/dompdf"');
    }
    
    // Vérifier si des données sont présentes
    if (empty($data)) {
        die('Aucune donnée à afficher dans le rapport.');
    }
    
    // Vérifier et créer le dossier temporaire si nécessaire
    $tempDir = sys_get_temp_dir() . '/dompdf';
    if (!file_exists($tempDir)) {
        if (!mkdir($tempDir, 0777, true)) {
            die("Impossible de créer le dossier temporaire: $tempDir");
        }
    }
    
    // Vérifier les permissions du dossier temporaire
    if (!is_writable($tempDir)) {
        die("Le dossier temporaire n'est pas accessible en écriture: $tempDir");
    }
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . htmlspecialchars($title) . '</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10pt; }
            h1 { text-align: center; color: #2c5282; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #2c5282; color: white; text-align: left; padding: 8px; }
            td { border: 1px solid #ddd; padding: 8px; }
            tr:nth-child(even) { background-color: #f2f2f2; }
            .footer { margin-top: 20px; text-align: right; font-size: 9pt; color: #666; }
        </style>
    </head>
    <body>
        <h1>' . htmlspecialchars($title) . '</h1>
        <p>Généré le ' . date('d/m/Y à H:i:s') . '</p>
        <table>
            <thead>
                <tr>';
    
    // En-têtes
    foreach ($columns as $column) {
        $html .= '<th>' . htmlspecialchars($column ?? '') . '</th>';
    }
    
    $html .= '</tr>
            </thead>
            <tbody>';
// Données
    foreach ($data as $row) {
        $html .= '<tr>';
        foreach ($row as $cell) {
            $html .= '<td>' . htmlspecialchars($cell ?? '') . '</td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</tbody>
        </table>
        <div class="footer">
            Gestion du Port de Bujumbura - Tous droits réservés
        </div>
    </body>
    </html>';
    
    try {
        // Créer une instance de Dompdf avec des options
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('tempDir', $tempDir);
        $options->set('logOutputFile', $tempDir . '/dompdf_log.txt');
        
        $dompdf = new \Dompdf\Dompdf($options);
        
        // Configurer le contexte de flux pour éviter les problèmes de protocole
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        $dompdf->setHttpContext($context);
        
        // Charger le HTML
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        
        // Rendre le PDF
        $dompdf->render();
        
        // Vérifier s'il y a des erreurs
        if (file_exists($tempDir . '/dompdf_log.txt')) {
            $logContent = file_get_contents($tempDir . '/dompdf_log.txt');
            if (!empty($logContent)) {
                error_log('Erreurs Dompdf: ' . $logContent);
            }
        }
        
        // Envoyer les en-têtes appropriées
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        
        // Envoyer le PDF au navigateur
        echo $dompdf->output();
        exit;
    } catch (Exception $e) {
        die('Erreur lors de la génération du PDF: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapports - Administration - Gestion du Port de Bujumbura</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            
            <a href="dashboard.php" class="flex items-center px-4 py-3 text-blue-200 hover:bg-blue-800 hover:text-white transition duration-200">
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
            
            <a href="rapports.php" class="flex items-center px-4 py-3 text-white bg-blue-800">
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
            <div class="max-w-7xl mx-auto">
                <div class="mb-8">
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Génération de rapports</h1>
                    <p class="text-gray-600">Générez des rapports détaillés sur les activités du port</p>
                </div>

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
                            <button id="emptyModalOk" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">OK</button>
                        </div>
                    </div>
                </div>

                <!-- Rapports rapides tonnage par type -->
                <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapport rapide</h3>
                        <p class="text-gray-600 text-sm mb-4">Tonnage par type — Mois courant</p>
                        <form method="get" action="api/export_rapport.php" target="_blank" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                            <input type="hidden" name="report" value="tonnage_type" />
                            <input type="hidden" name="scope" value="month" />
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-600 mb-1">Mode</label>
                                <select name="mode" class="w-full border rounded px-3 py-2">
                                    <option value="tous">Tous</option>
                                    <option value="camion">Camions</option>
                                    <option value="bateau">Bateaux</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-600 mb-1">Mouvement</label>
                                <select name="mouvement" class="w-full border rounded px-3 py-2">
                                    <option value="tous">Tous</option>
                                    <option value="entree">Entrée</option>
                                    <option value="sortie">Sortie</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-600 mb-1">Type</label>
                                <select name="type_id" class="w-full border rounded px-3 py-2">
                                    <option value="">Tous</option>
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-6 flex items-center gap-3 flex-wrap mt-1">
                                <button name="format" value="pdf" class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded">
                                    <i class="fas fa-file-pdf mr-2"></i> PDF
                                </button>
                                <button name="format" value="xlsx" class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
                                    <i class="fas fa-file-excel mr-2"></i> Excel
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapport rapide</h3>
                        <p class="text-gray-600 text-sm mb-4">Tonnage par type — Année courante</p>
                        <form method="get" action="api/export_rapport.php" target="_blank" class="grid grid-cols-1 md:grid-cols-6 gap-3">
                            <input type="hidden" name="report" value="tonnage_type" />
                            <input type="hidden" name="scope" value="year" />
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-600 mb-1">Mode</label>
                                <select name="mode" class="w-full border rounded px-3 py-2">
                                    <option value="tous">Tous</option>
                                    <option value="camion">Camions</option>
                                    <option value="bateau">Bateaux</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-600 mb-1">Mouvement</label>
                                <select name="mouvement" class="w-full border rounded px-3 py-2">
                                    <option value="tous">Tous</option>
                                    <option value="entree">Entrée</option>
                                    <option value="sortie">Sortie</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm text-gray-600 mb-1">Type</label>
                                <select name="type_id" class="w-full border rounded px-3 py-2">
                                    <option value="">Tous</option>
                                    <?php foreach ($types as $t): ?>
                                        <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="md:col-span-6 flex items-center gap-3 flex-wrap mt-1">
                                <button name="format" value="pdf" class="inline-flex items-center px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded">
                                    <i class="fas fa-file-pdf mr-2"></i> PDF
                                </button>
                                <button name="format" value="xlsx" class="inline-flex items-center px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
                                    <i class="fas fa-file-excel mr-2"></i> Excel
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white rounded-lg shadow p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-1">Rapport personnalisé</h3>
                        <p class="text-gray-600 text-sm mb-4">Tonnage par type — Période au choix</p>
                        <form id="customTonnageForm" method="get" action="api/export_rapport.php" target="_blank" class="space-y-4 js-check-export">
                            <input type="hidden" name="report" value="tonnage_type" />
                            <input type="hidden" name="scope" value="custom" />
                            <!-- Row 1: Dates -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Début</label>
                                    <input name="start" type="date" class="w-full border rounded px-3 py-2" required />
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Fin</label>
                                    <input name="end" type="date" class="w-full border rounded px-3 py-2" required />
                                </div>
                            </div>
                            <!-- Row 2: Filtres -->
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Mode</label>
                                    <select name="mode" class="w-full border rounded px-3 py-2">
                                        <option value="tous">Tous</option>
                                        <option value="camion">Camions</option>
                                        <option value="bateau">Bateaux</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Mouvement</label>
                                    <select name="mouvement" class="w-full border rounded px-3 py-2">
                                        <option value="tous">Tous</option>
                                        <option value="entree">Entrée</option>
                                        <option value="sortie">Sortie</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm text-gray-600 mb-1">Type</label>
                                    <select name="type_id" class="w-full border rounded px-3 py-2">
                                        <option value="">Tous</option>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <!-- Row 3: Boutons -->
                            <div class="flex items-center gap-3 justify-end">
                                <button name="format" value="pdf" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                                <button name="format" value="xlsx" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-excel mr-2"></i>Excel</button>
                            </div>
                        </form>
                    </div>
                </div>

                

                <!-- Rapports détaillés — Camions et Bateaux -->
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
                                        <option value="custom">Personnalisée</option>
                                    </select>
                                    <input type="date" name="start" class="border rounded px-2 py-1 text-sm" />
                                    <input type="date" name="end" class="border rounded px-2 py-1 text-sm" />
                                    <label class="text-sm text-gray-700">Type</label>
                                    <select name="type_id" class="border rounded px-2 py-1 text-sm">
                                        <option value="">Tous</option>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                        <?php endforeach; ?>
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
                                        <option value="custom">Personnalisée</option>
                                    </select>
                                    <input type="date" name="start" class="border rounded px-2 py-1 text-sm" />
                                    <input type="date" name="end" class="border rounded px-2 py-1 text-sm" />
                                    <label class="text-sm text-gray-700">Type</label>
                                    <select name="type_id" class="border rounded px-2 py-1 text-sm">
                                        <option value="">Tous</option>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                        <?php endforeach; ?>
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
                                        <option value="custom">Personnalisée</option>
                                    </select>
                                    <input type="date" name="start" class="border rounded px-2 py-1 text-sm" />
                                    <input type="date" name="end" class="border rounded px-2 py-1 text-sm" />
                                    <label class="text-sm text-gray-700">Type</label>
                                    <select name="type_id" class="border rounded px-2 py-1 text-sm">
                                        <option value="">Tous</option>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                        <?php endforeach; ?>
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
                                        <option value="custom">Personnalisée</option>
                                    </select>
                                    <input type="date" name="start" class="border rounded px-2 py-1 text-sm" />
                                    <input type="date" name="end" class="border rounded px-2 py-1 text-sm" />
                                    <label class="text-sm text-gray-700">Type</label>
                                    <select name="type_id" class="border rounded px-2 py-1 text-sm">
                                        <option value="">Tous</option>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nom']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button name="format" value="pdf" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-pdf mr-2"></i>PDF</button>
                                    <button name="format" value="xlsx" class="px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded inline-flex items-center whitespace-nowrap"><i class="fas fa-file-excel mr-2"></i>Excel</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php
                // Récupération des statistiques
                $start_date = $_POST['start_date'] ?? null;
                $end_date = $_POST['end_date'] ?? null;
                
                $tonnage_entree = getTonnageParMouvement($db, 'entree', $start_date, $end_date);
                $tonnage_sortie = getTonnageParMouvement($db, 'sortie', $start_date, $end_date);
                $stats_categories_entrees = getStatsParCategorieParMouvement($db, 'entree', $start_date, $end_date);
                $stats_categories_sorties = getStatsParCategorieParMouvement($db, 'sortie', $start_date, $end_date);
                $mouvements = getStatsMouvements($db, $start_date, $end_date);
                
                ?>
                
                <div class="mt-8 bg-white shadow rounded-lg p-6">
                    <h2 class="text-lg font-medium text-gray-900 mb-6">Aperçu des statistiques</h2>
                    
                    <!-- Filtres de période -->
                    <form method="post" class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="font-medium text-gray-900 mb-3">Filtrer par période</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date de début</label>
                                <input type="date" name="start_date" value="<?= $start_date !== null ? htmlspecialchars($start_date) : '' ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Date de fin</label>
                                <input type="date" name="end_date" value="<?= $end_date !== null ? htmlspecialchars($end_date) : '' ?>" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div class="flex items-end">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Appliquer
                                </button>
                                <?php if ($start_date || $end_date): ?>
                                <a href="?" class="ml-2 text-sm text-gray-600 hover:text-gray-900">Réinitialiser</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Aperçu du tonnage (Entrées) -->
                    <div class="mb-8">
                        <h3 class="text-md font-medium text-gray-900 mb-3">Tonnage (Entrées)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-blue-800">Total</p>
                                <p class="text-2xl font-bold text-blue-900"><?= number_format($tonnage_entree['total'] ?? 0, 0, ',', ' ') ?> kg</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-green-800">Par bateaux</p>
                                <p class="text-2xl font-bold text-green-900"><?= number_format($tonnage_entree['tonnage_bateaux'] ?? 0, 0, ',', ' ') ?> kg</p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-purple-800">Par camions</p>
                                <p class="text-2xl font-bold text-purple-900"><?= number_format($tonnage_entree['tonnage_camions'] ?? 0, 0, ',', ' ') ?> kg</p>
                            </div>
                        </div>
                    </div>

                    <!-- Aperçu du tonnage (Sorties) -->
                    <div class="mb-8">
                        <h3 class="text-md font-medium text-gray-900 mb-3">Tonnage (Sorties)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-blue-800">Total</p>
                                <p class="text-2xl font-bold text-blue-900"><?= number_format($tonnage_sortie['total'] ?? 0, 0, ',', ' ') ?> kg</p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-green-800">Par bateaux</p>
                                <p class="text-2xl font-bold text-green-900"><?= number_format($tonnage_sortie['tonnage_bateaux'] ?? 0, 0, ',', ' ') ?> kg</p>
                            </div>
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <p class="text-sm font-medium text-purple-800">Par camions</p>
                                <p class="text-2xl font-bold text-purple-900"><?= number_format($tonnage_sortie['tonnage_camions'] ?? 0, 0, ',', ' ') ?> kg</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistiques par catégorie - Entrées -->
                    <div class="mb-8">
                        <h3 class="text-md font-medium text-gray-900 mb-1">Répartition par catégorie de marchandises (Entrées)</h3>
                        <p class="text-sm text-gray-500 mb-3">Somme des poids par catégories pour les marchandises entrantes.</p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catégorie</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité (bateaux)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité (camions)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unité</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($stats_categories_entrees as $categorie): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($categorie['categorie']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($categorie['quantite_bateaux'], 0, ',', ' ') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($categorie['quantite_camions'], 0, ',', ' ') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?= number_format(($categorie['quantite_bateaux'] ?? 0) + ($categorie['quantite_camions'] ?? 0), 0, ',', ' ') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($categorie['unite']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Statistiques par catégorie - Sorties -->
                    <div class="mb-8">
                        <h3 class="text-md font-medium text-gray-900 mb-1">Répartition par catégorie de marchandises (Sorties)</h3>
                        <p class="text-sm text-gray-500 mb-3">Somme des poids par catégories pour les marchandises sortantes.</p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catégorie</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité (bateaux)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantité (camions)</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unité</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($stats_categories_sorties as $categorie): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($categorie['categorie']) ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($categorie['quantite_bateaux'], 0, ',', ' ') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($categorie['quantite_camions'], 0, ',', ' ') ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?= number_format(($categorie['quantite_bateaux'] ?? 0) + ($categorie['quantite_camions'] ?? 0), 0, ',', ' ') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($categorie['unite']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    (function(){
        function serialize(form){
            const params = [];
            new FormData(form).forEach((v,k)=>{ if(v!==null) params.push(encodeURIComponent(k)+'='+encodeURIComponent(v)); });
            return params.join('&');
        }
        document.querySelectorAll('form.js-check-export').forEach(function(form){
            form.addEventListener('submit', async function(e){
                e.preventDefault();
                const btn = e.submitter; // PDF or XLSX
                if (!btn) return;
                const format = btn.getAttribute('value') || 'pdf';
                const base = form.getAttribute('action');
                const qs = serialize(form);
                const url = base + '?' + qs + '&format=' + encodeURIComponent(format) + '&check=1';
                try {
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const data = await res.json();
                    if (!data || !data.hasData) {
                        alert('Aucune donnée pour cette recherche. Le rapport serait vide.');
                        return;
                    }
                    // open actual export
                    const finalUrl = base + '?' + qs + '&format=' + encodeURIComponent(format);
                    window.open(finalUrl, '_blank');
                } catch(err){
                    alert('Impossible de vérifier le rapport. Réessayez.');
                }
            });
        });
    })();
    </script>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
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
                    // Capture clicked button format (pdf/xlsx)
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
                    // Ensure format is preserved on submit
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
                    // Capture clicked button format (pdf/xlsx)
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
                    // Ensure format is preserved on submit
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

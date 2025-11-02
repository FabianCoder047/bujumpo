<?php
require_once '../../includes/auth_check.php';
checkRole(['EnregistreurBateaux']);
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du bateau requis']);
    exit();
}

$bateauId = $_GET['id'];

try {
    $db = getDB();
    
    // Récupérer les détails du bateau
    $stmt = $db->prepare("
        SELECT b.*, tb.nom as type_bateau, 
               po.nom as port_origine, pd.nom as port_destination
        FROM bateaux b 
        LEFT JOIN types_bateaux tb ON b.type_bateau_id = tb.id
        LEFT JOIN ports po ON b.port_origine_id = po.id
        LEFT JOIN ports pd ON b.port_destination_id = pd.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bateauId]);
    $bateau = $stmt->fetch();
    
    if (!$bateau) {
        echo json_encode(['success' => false, 'message' => 'Bateau non trouvé']);
        exit();
    }
    
    // Récupérer les marchandises
    $stmt = $db->prepare("
        SELECT mb.*, tm.nom as type_marchandise 
        FROM marchandises_bateaux mb 
        LEFT JOIN types_marchandises tm ON mb.type_marchandise_id = tm.id 
        WHERE mb.bateau_id = ?
    ");
    $stmt->execute([$bateauId]);
    $marchandises = $stmt->fetchAll();
    
    // Récupérer les détails des passagers avec le poids de leurs marchandises
    $stmt = $db->prepare("
        SELECT 
            MIN(pb.id) as id,
            pb.numero_passager,
            COALESCE(SUM(pb.poids_marchandises), 0) as poids_total
        FROM passagers_bateaux pb 
        WHERE pb.bateau_id = ?
        GROUP BY pb.numero_passager
        ORDER BY pb.numero_passager
    ");
    $stmt->execute([$bateauId]);
    $passagers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'bateau' => $bateau,
        'marchandises' => $marchandises,
        'passagers' => $passagers
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>

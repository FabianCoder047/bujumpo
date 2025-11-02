<?php
require_once '../../includes/auth_check.php';
checkRole(['EnregistreurSortieRoute']);
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du camion requis']);
    exit();
}

$camionId = $_GET['id'];

try {
    $db = getDB();
    
    // Récupérer les détails du camion
    $stmt = $db->prepare("
        SELECT c.*, tc.nom as type_camion 
        FROM camions c 
        LEFT JOIN types_camions tc ON c.type_camion_id = tc.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$camionId]);
    $camion = $stmt->fetch();
    
    if (!$camion) {
        echo json_encode(['success' => false, 'message' => 'Camion non trouvé']);
        exit();
    }
    
    // Récupérer le pesage
    $stmt = $db->prepare("SELECT * FROM pesages WHERE camion_id = ? ORDER BY date_pesage DESC LIMIT 1");
    $stmt->execute([$camionId]);
    $pesage = $stmt->fetch();
    
    // Récupérer les marchandises de sortie agrégées par type (quantité/poids total)
    $stmt = $db->prepare("
        SELECT 
            tm.nom AS type_marchandise,
            SUM(COALESCE(mc.quantite, 0)) AS quantite,
            SUM(COALESCE(mc.poids, 0)) AS poids
        FROM marchandises_camions mc
        LEFT JOIN types_marchandises tm ON mc.type_marchandise_id = tm.id
        WHERE mc.camion_id = ? AND mc.mouvement = 'sortie'
        GROUP BY mc.type_marchandise_id, tm.nom
        ORDER BY tm.nom ASC
    ");
    $stmt->execute([$camionId]);
    $marchandises = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'camion' => $camion,
        'pesage' => $pesage,
        'marchandises' => $marchandises
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>

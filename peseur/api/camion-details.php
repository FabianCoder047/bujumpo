<?php
require_once '../../includes/auth_check.php';
checkRole(['peseur']);
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID du camion requis']);
    exit();
}

$camionId = $_GET['id'];
$mode = isset($_GET['mode']) ? strtolower(trim((string)$_GET['mode'])) : '';

try {
    $db = getDB();
    
    // Récupérer les détails du camion avec les informations de pesage
    // Première requête pour vérifier si le camion existe
    $stmt = $db->prepare("SELECT id, immatriculation, chauffeur, destinataire, type_camion_id FROM camions WHERE id = ?");
    $stmt->execute([$camionId]);
    $camion = $stmt->fetch();
    
    if (!$camion) {
        echo json_encode(['success' => false, 'message' => 'Camion non trouvé']);
        exit();
    }
    
    // Récupérer le type de camion
    $stmt = $db->prepare("SELECT nom FROM types_camions WHERE id = ?");
    $stmt->execute([$camion['type_camion_id']]);
    $typeCamion = $stmt->fetch();
    $camion['type_camion'] = $typeCamion ? $typeCamion['nom'] : 'Non spécifié';
    
    // Récupérer le dernier pesage
    $stmt = $db->prepare("
        SELECT 
            ptav, 
            ptac, 
            ptra, 
            charge_essieu,
            total_poids_marchandises as poids_total,
            surcharge,
            date_pesage
        FROM pesages 
        WHERE camion_id = ? 
        ORDER BY date_pesage DESC 
        LIMIT 1
    ");
    $stmt->execute([$camionId]);
    $pesage = $stmt->fetch();
    
    // Calculer les valeurs dérivées
    if ($pesage) {
        $camion['ptav'] = (float)$pesage['ptav'];
        $camion['ptac'] = (float)$pesage['ptac'];
        $camion['ptra'] = (float)$pesage['ptra'];
        $camion['charge_essieu'] = (float)$pesage['charge_essieu'];
        $camion['poids_total_pese'] = (float)$pesage['poids_total'];
        $camion['charge_autorisee'] = $camion['ptac'] - $camion['ptav'];
        $camion['depassement'] = $camion['poids_total_pese'] - $camion['charge_autorisee'];
        $camion['est_surcharge'] = isset($pesage['surcharge']) ? (int)$pesage['surcharge'] : 0;
    } else {
        $camion['ptav'] = 0;
        $camion['ptac'] = 0;
        $camion['ptra'] = 0;
        $camion['charge_essieu'] = 0;
        $camion['poids_total_pese'] = 0;
        $camion['charge_autorisee'] = 0;
        $camion['depassement'] = 0;
        $camion['est_surcharge'] = 0;
    }
    // Suppression des lignes en double qui causaient une erreur
    
    // Récupérer les marchandises liées au pesage (entrée): exclure uniquement les lignes de mouvement 'sortie'
    $stmt = $db->prepare("
        SELECT mc.*, tm.nom as type_marchandise 
        FROM marchandises_camions mc 
        LEFT JOIN types_marchandises tm ON mc.type_marchandise_id = tm.id 
        WHERE mc.camion_id = ? 
          AND (mc.mouvement IS NULL OR mc.mouvement = 'entree')
        ORDER BY mc.id ASC
    ");
    $stmt->execute([$camionId]);
    $marchandises = $stmt->fetchAll();

    // Récupérer aussi les marchandises destinées à la sortie (pour distinction claire)
    $stmt = $db->prepare("
        SELECT mc.*, tm.nom as type_marchandise
        FROM marchandises_camions mc
        LEFT JOIN types_marchandises tm ON mc.type_marchandise_id = tm.id
        WHERE mc.camion_id = ?
          AND mc.mouvement = 'sortie'
        ORDER BY mc.id ASC
    ");
    $stmt->execute([$camionId]);
    $marchandises_sortie = $stmt->fetchAll();
    
    // Vérifier si le camion a des données de pesage
    $hasPesage = !empty($pesage);
    
    $response = [
        'success' => true,
        'camion' => $camion,
        'hasPesage' => $hasPesage,
        'pesage' => $pesage ?: null,
        'marchandises' => $marchandises,
        'marchandises_sortie' => $marchandises_sortie
    ];
    
    error_log('Réponse de l\'API: ' . print_r($response, true));
    
    // Envoyer la réponse
    header('Content-Type: application/json');
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>

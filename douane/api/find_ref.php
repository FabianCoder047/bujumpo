<?php
require_once '../../includes/auth_check.php';
checkRole(['douanier']);
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    $type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
    $ident = isset($_GET['ident']) ? trim($_GET['ident']) : '';
    $mouvement = isset($_GET['mouvement']) ? strtolower(trim($_GET['mouvement'])) : 'entree';
    if (!in_array($type, ['camion','bateau'], true) || $ident === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètres invalides']);
        exit;
    }
    if (!in_array($mouvement, ['entree','sortie'], true)) { $mouvement = 'entree'; }

    $db = getDB();

    if ($type === 'camion') {
        $stmt = $db->prepare("SELECT id, immatriculation, chauffeur, agence, date_entree, date_sortie FROM camions WHERE immatriculation = ?");
        $stmt->execute([$ident]);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Camion introuvable']); exit; }
        if ($mouvement === 'entree' && empty($row['date_entree'])) { echo json_encode(['success' => false, 'message' => 'Ce camion n\'a pas d\'entrée']); exit; }
        if ($mouvement === 'sortie' && empty($row['date_sortie'])) { echo json_encode(['success' => false, 'message' => 'Ce camion n\'a pas de sortie']); exit; }
        $label = 'Camion ' . $row['immatriculation'] . ' — chauffeur ' . $row['chauffeur'];
        echo json_encode(['success' => true, 'ref_id' => (int)$row['id'], 'label' => $label]);
        exit;
    } else {
        $stmt = $db->prepare("SELECT id, COALESCE(immatriculation, nom) AS ident, capitaine, date_entree, date_sortie FROM bateaux WHERE (immatriculation = ? OR nom = ?) LIMIT 1");
        $stmt->execute([$ident, $ident]);
        $row = $stmt->fetch();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Bateau introuvable']); exit; }
        if ($mouvement === 'entree' && empty($row['date_entree'])) { echo json_encode(['success' => false, 'message' => 'Ce bateau n\'a pas d\'entrée']); exit; }
        if ($mouvement === 'sortie' && empty($row['date_sortie'])) { echo json_encode(['success' => false, 'message' => 'Ce bateau n\'a pas de sortie']); exit; }
        $label = 'Bateau ' . $row['ident'] . ' — capitaine ' . $row['capitaine'];
        echo json_encode(['success' => true, 'ref_id' => (int)$row['id'], 'label' => $label]);
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}

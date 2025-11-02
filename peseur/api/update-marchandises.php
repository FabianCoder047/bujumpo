<?php
require_once '../../includes/auth_check.php';
checkRole(['peseur']);
require_once '../../config/database.php';

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);
    if (!is_array($input)) { $input = []; }

    $camion_id = isset($input['camion_id']) ? (int)$input['camion_id'] : 0;
    $items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];
    $mouvement = isset($input['mouvement']) ? strtolower(trim((string)$input['mouvement'])) : '';

    if ($camion_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'camion_id invalide']);
        exit;
    }

    $db = getDB();

    // Détecter si la colonne 'mouvement' existe sur marchandises_camions
    $hasMouvementCol = false;
    try {
        $col = $db->query("SHOW COLUMNS FROM marchandises_camions LIKE 'mouvement'")->fetch();
        $hasMouvementCol = $col ? true : false;
    } catch (Exception $e) { /* ignore */ }

    // Normaliser le mouvement si fourni et colonne existante
    if ($mouvement !== '' && !in_array($mouvement, ['entree','sortie'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "mouvement invalide (attendu: 'entree' ou 'sortie')"]);
        exit;
    }

    // Si colonne mouvement existe et mouvement non fourni, considérer 'entree' par défaut
    if ($hasMouvementCol && $mouvement === '') {
        $mouvement = 'entree';
    }

    // Vérifier que le camion existe
    $stmt = $db->prepare('SELECT id FROM camions WHERE id = ?');
    $stmt->execute([$camion_id]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Camion introuvable']);
        exit;
    }

    // Valider les items
    $clean = [];
    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $type_id = isset($it['type_marchandise_id']) ? (int)$it['type_marchandise_id'] : 0;
        $poids = isset($it['poids']) ? (float)$it['poids'] : 0.0;
        $quantite = isset($it['quantite']) ? (int)$it['quantite'] : 1;
        if ($type_id <= 0 || $quantite <= 0 || $poids < 0) continue;
        $clean[] = [
            'type_marchandise_id' => $type_id,
            'poids' => $poids,
            'quantite' => $quantite,
        ];
    }

    $db->beginTransaction();

    // Supprimer les existants (scopé par mouvement si supporté)
    if ($hasMouvementCol) {
        if ($mouvement === 'sortie') {
            $del = $db->prepare("DELETE FROM marchandises_camions WHERE camion_id = ? AND mouvement = 'sortie'");
            $del->execute([$camion_id]);
        } else { // entree
            $del = $db->prepare("DELETE FROM marchandises_camions WHERE camion_id = ? AND (mouvement = 'entree' OR mouvement IS NULL)");
            $del->execute([$camion_id]);
        }
    } else {
        // Pas de colonne mouvement: on supprime tout (comportement legacy)
        $del = $db->prepare('DELETE FROM marchandises_camions WHERE camion_id = ?');
        $del->execute([$camion_id]);
    }

    // Réinsérer les nouveaux (si fournis)
    if (!empty($clean)) {
        if ($hasMouvementCol) {
            // Toujours insérer avec mouvement explicite ('entree' ou 'sortie')
            $ins = $db->prepare("INSERT INTO marchandises_camions (camion_id, type_marchandise_id, mouvement, poids, quantite) VALUES (?, ?, ?, ?, ?)");
            foreach ($clean as $row) {
                $ins->execute([$camion_id, $row['type_marchandise_id'], ($mouvement ?: 'entree'), $row['poids'], $row['quantite']]);
            }
        } else {
            $ins = $db->prepare('INSERT INTO marchandises_camions (camion_id, type_marchandise_id, poids, quantite) VALUES (?, ?, ?, ?)');
            foreach ($clean as $row) {
                $ins->execute([$camion_id, $row['type_marchandise_id'], $row['poids'], $row['quantite']]);
            }
        }
    }

    // Journaliser
    $user = getCurrentUser();
    $log = $db->prepare('INSERT INTO logs (user_id, action, details) VALUES (?, ?, ?)');
    $log->execute([$user['id'], 'Modification Marchandises Camion', 'camion_id='.$camion_id.'; mouvement=' . ($mouvement ?: 'all') . '; items='.json_encode($clean, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);

    $db->commit();

    echo json_encode(['success' => true, 'message' => 'Marchandises mises à jour', 'count' => count($clean)]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) { $db->rollBack(); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: '.$e->getMessage()]);
}

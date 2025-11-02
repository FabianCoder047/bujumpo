<?php
header('Content-Type: application/json');

// Vérifier si la requête est une requête POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

// Récupérer les données JSON de la requête
$input = json_decode(file_get_contents('php://input'), true);

// Vérifier si les données sont valides
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
    exit();
}

// Valider les données requises
$requiredFields = ['action', 'camion_id', 'retour_vide', 'port_destination'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || $input[$field] === '') {
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => "Le champ $field est requis"]);
        exit();
    }
}

try {
    // Inclure la configuration de la base de données
    require_once __DIR__ . '/../../config/database.php';
    
    // Obtenir une instance de PDO
    $db = getDB();
    
    // Démarrer une transaction
    $db->beginTransaction();
    
    // Mettre à jour les informations du camion (mise en attente de validation de sortie)
    // Note: pas de colonne dédiée pour port_destination ou détails marchandises;
    // on les ajoute dans observations_sortie si fournis.
    $observations = isset($input['observations']) ? trim((string)$input['observations']) : '';
    $portId = isset($input['port_destination']) ? (int)$input['port_destination'] : null;
    $portNote = $portId ? ('Port destination ID: ' . $portId) : '';

    // Construire un récapitulatif des marchandises si non retour à vide
    $marchandisesNote = '';
    if (empty($input['retour_vide']) && !empty($input['marchandises']) && is_array($input['marchandises'])) {
        $items = [];
        foreach ($input['marchandises'] as $m) {
            $nom = isset($m['nom']) ? trim((string)$m['nom']) : '';
            $poids = isset($m['poids']) ? (float)$m['poids'] : 0;
            if ($nom !== '' && $poids > 0) {
                // format: Nom - 123.45 kg
                $items[] = $nom . ' - ' . number_format($poids, 2, '.', '') . ' kg';
            }
        }
        if (!empty($items)) {
            $marchandisesNote = 'Marchandises: ' . implode(', ', $items);
        }
    } else if (!empty($input['retour_vide'])) {
        // Si retour à vide, s'assurer qu'aucune marchandise 'sortie' n'est enregistrée
        $del = $db->prepare("DELETE FROM marchandises_camions WHERE camion_id = ? AND mouvement = 'sortie'");
        $del->execute([(int)$input['camion_id']]);
    }

    $obsParts = array_filter([$observations, $portNote, $marchandisesNote], function($v){ return (string)$v !== ''; });
    $obsFinal = trim(implode(' | ', $obsParts));

    // Placer le camion au statut 'sortie' (en attente de validation par l'Enregistreur). Ne pas fixer date_sortie ici.
    $stmt = $db->prepare("UPDATE camions SET statut = 'sortie', observations_sortie = ?, retour_vide = ? WHERE id = ?");
    $stmt->execute([
        $obsFinal,
        !empty($input['retour_vide']) ? 1 : 0,
        $input['camion_id']
    ]);

    // Calculer le poids total en sortie d'après les marchandises fournies
    // Poids saisi côté UI = poids total par ligne (pas par unité), on somme donc les poids saisis
    $totalPoidsSortie = null; // null = non fourni
    if (!empty($input['retour_vide'])) {
        $totalPoidsSortie = 0.0;
    } elseif (!empty($input['marchandises']) && is_array($input['marchandises'])) {
        $totalPoidsSortie = 0.0;
        foreach ($input['marchandises'] as $m) {
            $poidsRaw = isset($m['poids']) ? (string)$m['poids'] : '';
            $poidsStr = str_replace(',', '.', trim($poidsRaw));
            $poids = ($poidsStr !== '' && is_numeric($poidsStr)) ? (float)$poidsStr : 0.0;
            $totalPoidsSortie += $poids;
        }
    }

    // Enregistrer un pesage de sortie systématiquement
    $camionId = (int)$input['camion_id'];
    // Vérifier présence de la colonne 'mouvement'
    $hasMouvement = false;
    try {
        $col = $db->query("SHOW COLUMNS FROM pesages LIKE 'mouvement'")->fetch();
        $hasMouvement = $col ? true : false;
    } catch (Exception $e) { /* ignore */ }

    // Récupérer dernière mesure connue
    $lastVals = [
        'ptav' => 0,
        'ptac' => 0,
        'ptra' => 0,
        'charge_essieu' => 0,
        'total_poids_marchandises' => 0,
        'surcharge' => 0,
    ];
    $ps = $db->prepare("SELECT ptav, ptac, ptra, charge_essieu, total_poids_marchandises, surcharge FROM pesages WHERE camion_id = ? ORDER BY date_pesage DESC LIMIT 1");
    $ps->execute([$camionId]);
    if ($row = $ps->fetch(PDO::FETCH_ASSOC)) {
        $lastVals = array_merge($lastVals, $row);
    }

    $ptavRef = isset($lastVals['ptav']) ? (float)$lastVals['ptav'] : 0.0;
    $ptacRef = isset($lastVals['ptac']) ? (float)$lastVals['ptac'] : 0.0;
    $ptraRef = isset($lastVals['ptra']) ? (float)$lastVals['ptra'] : 0.0;
    if ($ptavRef > 0 && $ptacRef > 0 && !($ptacRef > $ptavRef)) {
        if ($db && $db->inTransaction()) { $db->rollBack(); }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Contrôle refusé: PTAC doit être strictement supérieur au PTAV.']);
        exit();
    }
    if ($ptraRef > 0 && ($ptavRef > 0 || $ptacRef > 0) && !($ptraRef > $ptavRef && $ptraRef > $ptacRef)) {
        if ($db && $db->inTransaction()) { $db->rollBack(); }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Contrôle refusé: PTRA doit être strictement supérieur au PTAV et au PTAC.']);
        exit();
    }

    // Utiliser le total de sortie calculé si fourni (y compris 0 pour retour_vide);
    // sinon garder la dernière valeur (ou 0)
    if ($totalPoidsSortie !== null) {
        $lastVals['total_poids_marchandises'] = $totalPoidsSortie;
    }
    // Calculer la surcharge en fonction de la charge utile autorisée (PTAC - PTAV)
    $ptacRef = isset($lastVals['ptac']) ? (float)$lastVals['ptac'] : 0.0;
    $ptavRef = isset($lastVals['ptav']) ? (float)$lastVals['ptav'] : 0.0;
    $chargeAutorisee = max($ptacRef - $ptavRef, 0.0);
    $poidsCharge = (float)$lastVals['total_poids_marchandises'];
    $estRetourVide = !empty($input['retour_vide']);
    // Surcharge si PTAC est connu (>0), retour non vide, et poids > charge autorisée (même si charge autorisée = 0)
    $isOverloaded = (!$estRetourVide && $ptacRef > 0 && $poidsCharge > $chargeAutorisee);
    $lastVals['surcharge'] = $isOverloaded ? 1 : 0;

    // Blocage: si surcharge détectée, annuler la transaction et renvoyer une erreur
    if ($isOverloaded) {
        // Retourner une erreur bloquante
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Surcharge détectée: le poids chargé (' . number_format($poidsCharge, 2, '.', '') . " kg) dépasse la charge autorisée (" . number_format($chargeAutorisee, 2, '.', '') . " kg). Sortie bloquée."
        ]);
        exit();
    }

    if ($hasMouvement) {
        $ins = $db->prepare("INSERT INTO pesages (camion_id, ptav, ptac, ptra, charge_essieu, total_poids_marchandises, surcharge, mouvement, date_pesage) VALUES (?, ?, ?, ?, ?, ?, ?, 'sortie', NOW())");
        $ins->execute([
            $camionId,
            (float)$lastVals['ptav'],
            (float)$lastVals['ptac'],
            (float)$lastVals['ptra'],
            (float)$lastVals['charge_essieu'],
            (float)$lastVals['total_poids_marchandises'],
            (int)$lastVals['surcharge'],
        ]);
    } else {
        $ins = $db->prepare("INSERT INTO pesages (camion_id, ptav, ptac, ptra, charge_essieu, total_poids_marchandises, surcharge, date_pesage) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $ins->execute([
            $camionId,
            (float)$lastVals['ptav'],
            (float)$lastVals['ptac'],
            (float)$lastVals['ptra'],
            (float)$lastVals['charge_essieu'],
            (float)$lastVals['total_poids_marchandises'],
            (int)$lastVals['surcharge'],
        ]);
    }

    // Enregistrer au sens propre les marchandises de sortie (lignes distinctes avec mouvement='sortie')
    $savedLines = [];
    if (empty($input['retour_vide']) && !empty($input['marchandises']) && is_array($input['marchandises'])) {
        // Effacer les anciennes lignes 'sortie' pour ce camion afin d'éviter les doublons lors des modifications
        $del = $db->prepare("DELETE FROM marchandises_camions WHERE camion_id = ? AND mouvement = 'sortie'");
        $del->execute([(int)$input['camion_id']]);
        foreach ($input['marchandises'] as $m) {
            // Accepte soit type_id direct, soit nom pour résolution
            $typeId = null;
            if (isset($m['type_marchandise_id']) && (int)$m['type_marchandise_id'] > 0) {
                $typeId = (int)$m['type_marchandise_id'];
            } elseif (isset($m['type_id']) && (int)$m['type_id'] > 0) {
                $typeId = (int)$m['type_id'];
            } else if (!empty($m['nom'])) {
                $stmtType = $db->prepare("SELECT id FROM types_marchandises WHERE UPPER(nom) = UPPER(?) LIMIT 1");
                $stmtType->execute([trim((string)$m['nom'])]);
                $rowType = $stmtType->fetch();
                if ($rowType) { $typeId = (int)$rowType['id']; }
            }
            if (!$typeId) { continue; }

            $poids = null;
            if (isset($m['poids']) && $m['poids'] !== '') {
                $poidsStr = str_replace(',', '.', (string)$m['poids']);
                $poids = is_numeric($poidsStr) ? (float)$poidsStr : null;
            }
            // Quantité: obligatoire et > 0 lorsqu'il ne s'agit pas d'un retour à vide
            if (!isset($m['quantite']) || $m['quantite'] === '') {
                throw new Exception("La quantité est requise pour chaque marchandise.");
            }
            $qStr = str_replace(',', '.', (string)$m['quantite']);
            if (!is_numeric($qStr) || (float)$qStr <= 0) {
                throw new Exception("Quantité invalide: la quantité doit être > 0.");
            }
            $quantite = (float)$qStr;

            $ins = $db->prepare("INSERT INTO marchandises_camions (camion_id, type_marchandise_id, mouvement, poids, quantite) VALUES (?, ?, 'sortie', ?, ?)");
            $ins->execute([(int)$input['camion_id'], $typeId, $poids, (int)round($quantite)]);
            $savedLines[] = [
                'type_marchandise_id' => $typeId,
                'quantite' => (int)round($quantite),
                'poids' => $poids
            ];
        }
    }

    // Valider la transaction
    $db->commit();
    
    // Répondre avec succès
    echo json_encode([
        'success' => true,
        'message' => 'Camion autorisé à sortir (en attente de validation)',
        'saved_marchandises' => $savedLines
    ]);
    
} catch (PDOException $e) {
    // En cas d'erreur, annuler la transaction
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Erreur lors de l\'enregistrement de la sortie',
        'error' => $e->getMessage()
    ]);
}

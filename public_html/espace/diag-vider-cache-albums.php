<?php
/*
 * DIAGNOSTIC TEMPORAIRE — à supprimer une fois la cause trouvée. Vide le
 * cache disque de infos-albums.php (15 minutes, voir CACHE_DUREE_SECONDES)
 * pour forcer un vrai nouvel appel à l'API Google Drive au prochain
 * chargement, plutôt que d'attendre l'expiration naturelle du cache —
 * utile pour vérifier tout de suite si un correctif (partage Drive, etc.)
 * a bien pris effet. Protégé par un secret dans l'URL, comme les
 * diagnostics temporaires précédents (appelé par un script externe, pas
 * par un adhérent connecté).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

const DIAG_SECRET = 'ba0551506f815f0c8a71ffa5b2ace98b6f4a4b009b3516a4';

if (($_GET['secret'] ?? '') !== DIAG_SECRET) {
    http_response_code(404);
    echo json_encode(['erreur' => 'introuvable']);
    exit;
}

$dossier = __DIR__ . '/inc';
$supprimes = [];
foreach (glob($dossier . '/.cache-albums-*.json') ?: [] as $fichier) {
    if (@unlink($fichier)) {
        $supprimes[] = basename($fichier);
    }
}

echo json_encode(['ok' => true, 'fichiers_supprimes' => $supprimes]);

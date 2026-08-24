<?php
/*
 * Diagnostic temporaire (à supprimer) : le dossier configuré est-il
 * visible par la clé API elle-même ? N'expose jamais la clé.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$config  = require __DIR__ . '/espace/inc/config.local.php';
$cle_api = (string) ($config['google_drive_cle_api'] ?? '');
$dossier = (string) ($config['google_drive_dossier_id'] ?? '');

// Exactement la même requête que le code réel, pour voir la réponse brute
// (le code réel n'affiche jamais ça, seulement le nombre de photos).
$q = "('" . $dossier . "' in parents) and trashed = false and (mimeType contains 'image/' or mimeType = 'application/vnd.google-apps.folder')";
$url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query([
    'q'                         => $q,
    'fields'                    => 'files(id,name,mimeType)',
    'orderBy'                   => 'name',
    'pageSize'                  => 1000,
    'supportsAllDrives'         => 'true',
    'includeItemsFromAllDrives' => 'true',
    'key'                       => $cle_api,
]);
$contexte = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
$brut = @file_get_contents($url, false, $contexte);
echo json_encode(['q' => $q, 'reponse' => json_decode($brut !== false ? $brut : 'null', true)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

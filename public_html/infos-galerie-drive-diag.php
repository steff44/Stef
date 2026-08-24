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

$url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($dossier) . '?'
    . http_build_query(['fields' => 'id,name,mimeType,shared,permissions', 'supportsAllDrives' => 'true', 'key' => $cle_api]);
$contexte = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true]]);
$brut = @file_get_contents($url, false, $contexte);
echo $brut !== false ? $brut : json_encode(['erreur' => 'appel échoué']);

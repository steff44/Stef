<?php
/*
 * Point d'accès PUBLIC, en lecture seule, à la prochaine sortie ou réunion
 * à venir (table sorties) — alimente le bandeau dépliant de l'accueil
 * (index.html, choix explicite de l'utilisateur, 23/08/2026). Même principe
 * que infos-club.php / infos-galerie-club.php : connexion à la base
 * autonome plutôt que espace/inc/db.php (dont l'échec affiche une page
 * d'erreur HTML, inadaptée ici), pour rester debout même si le reste de
 * l'espace adhérents est cassé.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// Assez court pour qu'une sortie tout juste ajoutée apparaisse vite sur
// l'accueil, assez long pour ne pas interroger la base à chaque visite.
header('Cache-Control: public, max-age=300');

$chemin = __DIR__ . '/espace/inc/config.local.php';
if (!is_file($chemin)) {
    http_response_code(503);
    echo '{}';
    exit;
}

$config = require $chemin;

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['hote'], $config['base']),
        $config['utilisateur'],
        $config['mot_de_passe'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Assure que la table sorties existe, sans dépendre d'une visite
    // préalable d'une page de l'espace adhérents.
    require_once __DIR__ . '/espace/inc/migration.php';
    appliquer_migrations($pdo);

    $sortie = $pdo->query(
        "SELECT titre, categorie, lieu, description, debut
           FROM sorties
          WHERE debut >= NOW()
          ORDER BY debut ASC
          LIMIT 1"
    )->fetch();
} catch (PDOException $e) {
    error_log('infos-prochaine-sortie.php — base injoignable : ' . $e->getMessage());
    http_response_code(503);
    echo '{}';
    exit;
}

if (!$sortie) {
    echo '{}';
    exit;
}

echo json_encode([
    'titre'       => $sortie['titre'],
    'categorie'   => $sortie['categorie'],
    'lieu'        => $sortie['lieu'],
    'description' => $sortie['description'],
    // Format ISO (T au lieu de l'espace) : plus fiable à parser en
    // JavaScript (new Date(...)) que le format MySQL brut, notamment sur
    // Safari.
    'debut_iso'   => str_replace(' ', 'T', (string) $sortie['debut']),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

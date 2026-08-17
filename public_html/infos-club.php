<?php
/*
 * Point d'accès PUBLIC, en lecture seule, aux coordonnées du club (adresse,
 * téléphone, e-mail, texte de présentation). Ce sont des informations déjà
 * visibles de tous sur le site : aucune connexion n'est requise ici.
 *
 * Appelé par js/main.js sur les pages statiques, pour qu'un responsable
 * puisse changer ces textes depuis l'espace adhérents (parametres.php) sans
 * qu'un développeur ait à modifier le HTML de chaque page. Volontairement
 * autonome plutôt que de réutiliser espace/inc/db.php : en cas de panne, ce
 * dernier affiche une page d'erreur HTML via page_erreur() — inadapté à un
 * point d'accès censé renvoyer du JSON, et silencieusement ignoré par le
 * script qui l'appelle.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// Assez court pour qu'une modification faite par un responsable arrive vite
// sur le site, assez long pour ne pas interroger la base à chaque affichage.
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

    // Assure que la table existe (et ses valeurs par défaut), sans dépendre
    // d'une visite préalable d'une page de l'espace adhérents : ce point
    // d'accès doit fonctionner seul, y compris juste après un déploiement.
    require_once __DIR__ . '/espace/inc/migration.php';
    appliquer_migrations($pdo);

    $lignes = $pdo->query('SELECT cle, valeur FROM parametres_site')->fetchAll();
} catch (PDOException $e) {
    error_log('infos-club.php — base injoignable : ' . $e->getMessage());
    http_response_code(503);
    echo '{}';
    exit;
}

$donnees = [];
foreach ($lignes as $ligne) {
    $donnees[$ligne['cle']] = $ligne['valeur'];
}

echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

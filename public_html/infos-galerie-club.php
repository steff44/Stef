<?php
/*
 * Point d'accès PUBLIC, en lecture seule, aux photos de la Galerie du Club
 * (espace/galerie-club.php) : titre, catégorie, nom affiché, note. Ces
 * photos sont déposées par des adhérents connectés, mais deviennent
 * publiques une fois en ligne — c'est ce qui peuple la page Galerie
 * (galerie.html), ouverte à tous sans connexion.
 *
 * Même principe que infos-club.php : connexion à la base autonome plutôt que
 * espace/inc/db.php (dont l'échec affiche une page d'erreur HTML, inadaptée
 * ici), pour rester debout même si le reste de l'espace adhérents est cassé.
 *
 * Renvoie aussi la vraie liste des catégories (`categories_galerie`, la même
 * que Réglages du site et Galerie du Club) à côté des photos — piège
 * rencontré le 27/08/2026 : la page publique construisait ses pastilles de
 * filtre à partir de CLUB_DATA.themes (js/data.js), une liste figée au
 * moment du dernier déploiement. Un responsable qui renomme ou supprime une
 * catégorie depuis Réglages voyait alors la page publique garder l'ancien
 * nom indéfiniment (jamais mis à jour tant que personne ne retouche
 * data.js), pendant que Galerie du Club, elle, lisait déjà la table en
 * direct. Servir la liste ici permet à js/main.js de reconstruire ses
 * pastilles à partir de la même source de vérité que les deux autres pages.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
// Assez court pour qu'une photo déposée par un adhérent arrive vite sur la
// page publique, assez long pour ne pas interroger la base à chaque visite.
header('Cache-Control: public, max-age=300');

$chemin = __DIR__ . '/espace/inc/config.local.php';
if (!is_file($chemin)) {
    http_response_code(503);
    echo json_encode(['photos' => [], 'categories' => []]);
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

    // Assure que les tables existent (et leurs catégories par défaut), sans
    // dépendre d'une visite préalable d'une page de l'espace adhérents.
    require_once __DIR__ . '/espace/inc/migration.php';
    require_once __DIR__ . '/espace/inc/galerie_categories.php';
    appliquer_migrations($pdo);

    $lignes = $pdo->query(
        "SELECT p.id, p.titre, p.description, p.nom_affiche, p.cree_le,
                a.nom AS auteur, c.nom AS categorie
           FROM photos_club p
           LEFT JOIN adherents a ON a.id = p.depose_par
           LEFT JOIN categories_galerie c ON c.id = p.categorie_id
          ORDER BY p.cree_le DESC"
    )->fetchAll();

    $categories = array_values(categories_galerie($pdo));
} catch (PDOException $e) {
    error_log('infos-galerie-club.php — base injoignable : ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['photos' => [], 'categories' => []]);
    exit;
}

$photos = [];
foreach ($lignes as $ligne) {
    $photos[] = [
        'id'         => (int) $ligne['id'],
        'titre'      => $ligne['titre'],
        'description' => $ligne['description'],
        'auteur'     => $ligne['nom_affiche'] ?: ($ligne['auteur'] ?: 'Adhérent retiré'),
        // « Autres » plutôt qu'une chaîne vide si la catégorie a été
        // supprimée depuis le dépôt de la photo (categorie_id repassé NULL).
        'categorie'  => $ligne['categorie'] ?: 'Autres',
        'image'      => 'espace/telecharger.php?type=galerie_club&id=' . (int) $ligne['id'],
    ];
}

echo json_encode(
    ['photos' => $photos, 'categories' => $categories],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

<?php
/*
 * Catégories de la Galerie du Club — table `categories_galerie` (voir
 * schema.sql), modifiables par un responsable depuis parametres.php.
 * Contrairement aux documents, une seule liste à plat : pas de rubriques.
 */

declare(strict_types=1);

// Catégories dans l'ordre d'affichage : [id => nom].
function categories_galerie(PDO $pdo): array
{
    static $categories = null;
    if ($categories !== null) {
        return $categories;
    }

    $categories = [];
    foreach ($pdo->query('SELECT id, nom FROM categories_galerie ORDER BY ordre, id')->fetchAll() as $ligne) {
        $categories[(int) $ligne['id']] = $ligne['nom'];
    }

    return $categories;
}

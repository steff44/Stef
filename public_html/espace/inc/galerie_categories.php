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

// Une photo peut appartenir à plusieurs catégories (choix explicite de
// l'utilisatrice, 09/09/2026) — l'appartenance réelle vit dans une table de
// jointure, une par galerie (voir schema.sql), jamais dans le seul
// categorie_id de photos_club/photos_privees. $table vaut
// 'photos_club_categories' ou 'photos_privees_categories'.

// [photo_id => [categorie_id, ...]] pour TOUTES les photos de la table
// donnée en un seul aller-retour — à appeler une fois après avoir chargé la
// liste des photos, plutôt qu'une requête par photo.
function categories_par_photo(PDO $pdo, string $table): array
{
    $parPhoto = [];
    foreach ($pdo->query("SELECT photo_id, categorie_id FROM {$table}")->fetchAll() as $ligne) {
        $parPhoto[(int) $ligne['photo_id']][] = (int) $ligne['categorie_id'];
    }
    return $parPhoto;
}

// Catégories d'une seule photo (ajout au Club depuis la Galerie privée,
// copie exacte des catégories d'origine — voir galerie.php).
function categories_dune_photo(PDO $pdo, string $table, int $photo_id): array
{
    $requete = $pdo->prepare("SELECT categorie_id FROM {$table} WHERE photo_id = ?");
    $requete->execute([$photo_id]);
    return array_map('intval', $requete->fetchAll(PDO::FETCH_COLUMN));
}

// Fixe l'appartenance d'une photo à un ensemble de catégories — remplace
// entièrement les catégories déjà enregistrées (d'abord un DELETE, jamais un
// simple ajout), pour servir aussi bien à l'INSERT initial de la photo (rien
// à supprimer, la photo est neuve) qu'à une modification ultérieure de ses
// catégories (choix explicite de l'utilisatrice, 09/09/2026 — voir l'action
// modifier_categories de galerie.php/galerie-club.php). $categorie_ids :
// identifiants déjà validés (voir categories_galerie() pour la liste
// autorisée).
function definir_categories_photo(PDO $pdo, string $table, int $photo_id, array $categorie_ids): void
{
    $pdo->prepare("DELETE FROM {$table} WHERE photo_id = ?")->execute([$photo_id]);
    $inserer = $pdo->prepare("INSERT IGNORE INTO {$table} (photo_id, categorie_id) VALUES (?, ?)");
    foreach (array_unique($categorie_ids) as $categorie_id) {
        $inserer->execute([$photo_id, (int) $categorie_id]);
    }
}

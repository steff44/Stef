<?php
/*
 * Rubriques et catégories de classement des documents du club — tables
 * `rubriques_documents` et `categories_documents` (voir schema.sql),
 * modifiables par un responsable depuis parametres.php (choix explicite de
 * l'utilisateur, 20/08/2026 : remplace la constante RUBRIQUES_DOCUMENTS
 * figée dans le code, qu'un responsable ne pouvait pas renommer ni
 * compléter sans intervention sur le dépôt). Semées une seule fois avec la
 * classification par défaut par la migration (voir
 * RUBRIQUES_DOCUMENTS_PAR_DEFAUT dans inc/migration.php).
 */

declare(strict_types=1);

/*
 * Rubriques avec leurs catégories, dans l'ordre d'affichage. Chargées une
 * seule fois par page — appelée aussi bien par documents.php (plusieurs
 * fois, une par rubrique) que par parametres.php.
 *
 * Forme : [rubrique_id => ['nom' => ..., 'categories' => [categorie_id => nom]]]
 */
function rubriques_documents(PDO $pdo): array
{
    static $rubriques = null;
    if ($rubriques !== null) {
        return $rubriques;
    }

    $rubriques = [];
    // Toutes les rubriques d'abord, pour que celles sans catégorie
    // apparaissent aussi (jointure externe : LEFT JOIN, pas INNER JOIN).
    foreach ($pdo->query('SELECT id, nom FROM rubriques_documents ORDER BY ordre, id')->fetchAll() as $ligne) {
        $rubriques[(int) $ligne['id']] = ['nom' => $ligne['nom'], 'categories' => []];
    }
    foreach ($pdo->query('SELECT id, rubrique_id, nom FROM categories_documents ORDER BY ordre, id')->fetchAll() as $ligne) {
        $rubrique_id = (int) $ligne['rubrique_id'];
        if (isset($rubriques[$rubrique_id])) {
            $rubriques[$rubrique_id]['categories'][(int) $ligne['id']] = $ligne['nom'];
        }
    }

    return $rubriques;
}

// Rubrique et libellés d'une catégorie donnée — null si l'identifiant ne
// correspond à aucune catégorie connue (supprimée entre-temps, ou jamais
// valide). Sert à la fois à valider un categorie_id posté et à afficher son
// libellé sans reparcourir rubriques_documents() à la main.
function categorie_document(PDO $pdo, int $categorie_id): ?array
{
    foreach (rubriques_documents($pdo) as $rubrique_id => $rubrique) {
        if (isset($rubrique['categories'][$categorie_id])) {
            return [
                'rubrique_id'   => $rubrique_id,
                'rubrique_nom'  => $rubrique['nom'],
                'categorie_nom' => $rubrique['categories'][$categorie_id],
            ];
        }
    }
    return null;
}

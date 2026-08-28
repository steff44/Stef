<?php
/*
 * Export de la liste des adhérents en fichier Excel (.xlsx), réservé au
 * responsable (choix explicite de l'utilisateur, 28/08/2026 : « disponible
 * pour l'administrateur ») — jamais un éditeur, contrairement à la gestion
 * des comptes elle-même (adherents.php, ouverte aux deux rôles). Reprend
 * tous les champs saisis à l'inscription, y compris ceux ajoutés le même
 * jour (adresse, boîtier).
 *
 * Pas de page HTML ici : ce script ne fait que construire le fichier et le
 * servir en téléchargement, comme telecharger.php.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';
require_once __DIR__ . '/inc/xlsx.php';

exige_administrateur();
$pdo = base_de_donnees();

$membres = $pdo->query(
    'SELECT identifiant, nom, email, telephone, adresse, code_postal, ville, boitier,
            administrateur, editeur, actif, valide, cree_le, derniere_connexion
       FROM adherents
      ORDER BY nom'
)->fetchAll();

$entetes = [
    'Identifiant', 'Nom', 'E-mail', 'Téléphone', 'Adresse', 'Code postal', 'Ville',
    'Nom du boîtier', 'Rôle', 'Statut', 'Validé', 'Inscrit le', 'Dernière connexion',
];
// Largeur de chaque colonne (en caractères) : sans elle, Excel retombe sur
// sa largeur par défaut (8-9 caractères), bien trop étroite pour la
// plupart de ces champs — choix explicite de l'utilisateur, 28/08/2026
// (« formate le fichier Excel de façon à ce qu'il soit plus lisible »).
$largeurs = [14, 20, 26, 14, 26, 12, 16, 18, 16, 12, 10, 13, 20];

// « Inscrit le » et « Dernière connexion » en date courte (ex. 26-06-2026),
// pas la formulation longue utilisée ailleurs sur le site — choix explicite
// de l'utilisateur, 28/08/2026.
$date_courte = static fn(?string $date_sql): string => $date_sql ? date('d-m-Y', strtotime($date_sql)) : '';

$lignes = [];
foreach ($membres as $membre) {
    $roles = [];
    if ($membre['administrateur']) {
        $roles[] = 'Responsable';
    }
    if ($membre['editeur']) {
        $roles[] = 'Éditeur';
    }
    if (!$roles) {
        $roles[] = 'Adhérent';
    }

    $lignes[] = [
        $membre['identifiant'],
        $membre['nom'],
        $membre['email'] ?? '',
        $membre['telephone'] ?? '',
        $membre['adresse'] ?? '',
        $membre['code_postal'] ?? '',
        $membre['ville'] ?? '',
        $membre['boitier'] ?? '',
        implode(', ', $roles),
        $membre['actif'] ? 'Actif' : 'Désactivé',
        $membre['valide'] ? 'Oui' : 'Non',
        $date_courte($membre['cree_le']),
        $membre['derniere_connexion'] ? $date_courte($membre['derniere_connexion']) : 'Jamais',
    ];
}

$contenu = generer_xlsx(
    'Adhérents',
    'FOCAL CLUB TURBALLAIS',
    'Liste des adhérents — Export du ' . date('d-m-Y'),
    $entetes,
    $lignes,
    $largeurs
);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Length: ' . strlen($contenu));
header('Content-Disposition: attachment; filename="adherents-' . date('Y-m-d') . '.xlsx"');
// Liste nominative des adhérents : ni cache partagé, ni indexation.
header('Cache-Control: private, no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

echo $contenu;

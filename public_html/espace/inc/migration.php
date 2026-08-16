<?php
/*
 * Mise à niveau automatique du schéma.
 *
 * Pourquoi automatique ? Parce qu'il n'y a aucun autre moyen de la déclencher :
 * le sandbox n'a pas d'accès SSH, installation.php se verrouille dès le premier
 * compte créé, et une page de migration réservée aux responsables serait
 * inaccessible — le code neuf interroge les nouvelles colonnes DÈS la
 * connexion, qui échouerait donc avant d'avoir pu ouvrir cette page.
 *
 * Chaque étape est idempotente : on regarde si la colonne répond, on ne
 * l'ajoute que si elle manque. Aucune donnée n'est jamais touchée.
 */

declare(strict_types=1);

// Colonnes attendues sur `adherents`, avec leur définition SQL.
const COLONNES_ATTENDUES = [
    'derniere_activite' => 'DATETIME DEFAULT NULL',
    'deconnecte_le'     => 'DATETIME DEFAULT NULL',
];

function appliquer_migrations(PDO $pdo): void
{
    // Une seule fois par requête.
    static $deja_fait = false;
    if ($deja_fait) {
        return;
    }
    $deja_fait = true;

    // Témoin sur disque : une fois la base à jour, on ne l'interroge plus du
    // tout. S'il ne peut pas être écrit (droits), on refait simplement le
    // contrôle à chaque page — plus lent, mais toujours correct.
    $temoin = __DIR__ . '/.schema-a-jour';
    if (is_file($temoin) && trim((string) @file_get_contents($temoin)) === signature_schema()) {
        return;
    }

    foreach (COLONNES_ATTENDUES as $colonne => $definition) {
        if (colonne_absente($pdo, $colonne)) {
            try {
                $pdo->exec("ALTER TABLE adherents ADD COLUMN {$colonne} {$definition}");
            } catch (PDOException $e) {
                // Table pas encore créée : c'est une première installation,
                // schema.sql posera les colonnes lui-même. On ne pose pas le
                // témoin, la vérification sera refaite plus tard.
                error_log('Espace adhérents — migration ' . $colonne . ' : ' . $e->getMessage());
                return;
            }
        }
    }

    @file_put_contents($temoin, signature_schema());
}

/* Change dès qu'on ajoute une colonne : invalide le témoin des installations existantes. */
function signature_schema(): string
{
    return md5(implode('|', array_keys(COLONNES_ATTENDUES)));
}

/*
 * Test volontairement naïf mais valable pour MySQL comme pour SQLite : on
 * tente de lire la colonne. INFORMATION_SCHEMA aurait imposé du code
 * différent selon le moteur, et le banc d'essai hors ligne tourne sur SQLite.
 */
function colonne_absente(PDO $pdo, string $colonne): bool
{
    try {
        $pdo->query("SELECT {$colonne} FROM adherents LIMIT 1");
        return false;
    } catch (PDOException) {
        return true;
    }
}

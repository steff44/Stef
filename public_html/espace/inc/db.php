<?php
/*
 * Connexion à la base MySQL, partagée par toutes les pages de l'espace.
 */

declare(strict_types=1);

function base_de_donnees(): PDO
{
    // Une seule connexion par page, même si la fonction est appelée plusieurs fois.
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $chemin = __DIR__ . '/config.local.php';
    if (!is_file($chemin)) {
        // Message volontairement explicite : c'est l'erreur la plus probable
        // lors de la toute première installation.
        page_erreur(
            "Configuration absente",
            "Le fichier <code>espace/inc/config.local.php</code> n'existe pas encore. "
            . "Copiez <code>config.example.php</code> sous ce nom, dans le même dossier, "
            . "et renseignez-y les identifiants de la base MySQL."
        );
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
                // On veut de vraies requêtes préparées côté MySQL, pas une
                // simulation côté PHP : c'est ce qui protège des injections SQL.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    } catch (PDOException $e) {
        // Surtout ne pas afficher $e->getMessage() : il contient l'identifiant
        // et parfois le mot de passe de la base.
        error_log('Espace adhérents — connexion MySQL impossible : ' . $e->getMessage());
        page_erreur(
            "Base de données injoignable",
            "Les identifiants de <code>espace/inc/config.local.php</code> semblent incorrects, "
            . "ou la base n'est pas encore créée."
        );
    }

    // Met la base à niveau si des colonnes ont été ajoutées depuis la dernière
    // mise en ligne. Sans cela, le code neuf interrogerait des colonnes
    // absentes et l'espace deviendrait inaccessible après un déploiement.
    require_once __DIR__ . '/migration.php';
    appliquer_migrations($pdo);

    return $pdo;
}

/*
 * Page d'erreur autonome (elle ne dépend ni de la base ni de la session, pour
 * pouvoir s'afficher même quand tout le reste est cassé).
 */
function page_erreur(string $titre, string $message, int $code = 500): never
{
    http_response_code($code);
    $titre = htmlspecialchars($titre, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="fr">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$titre} — Focal Club Turballais</title>
    <link rel="stylesheet" href="../css/style.css">
    </head>
    <body>
      <main id="main">
        <section class="section">
          <div class="container">
            <div class="form-card" style="max-width:560px;margin:60px auto;">
              <h1 style="font-family:var(--font-heading);font-size:1.5rem;margin:0 0 14px;">{$titre}</h1>
              <p style="color:var(--text-muted);margin:0 0 20px;">{$message}</p>
              <a class="btn btn-ghost" href="../index.html">Retour au site</a>
            </div>
          </div>
        </section>
      </main>
    </body>
    </html>
    HTML;
    exit;
}

<?php
/*
 * En-tête et pied de page communs à l'espace adhérents, calqués sur le reste
 * du site (mêmes couleurs, même police, même barre de navigation).
 */

declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/*
 * Adresse de la feuille de style, suffixée par sa date de modification.
 *
 * Hostinger sert le CSS avec « cache-control: max-age=604800 » : sans ce
 * suffixe, un navigateur qui a déjà chargé la page garde l'ancienne feuille
 * pendant SEPT JOURS et ne voit aucune modification de mise en page. Le
 * suffixe change à chaque déploiement, ce qui force le rechargement — et lui
 * seul, les autres fichiers restant en cache.
 */
function lien_css(string $prefixe = '../'): string
{
    $chemin  = __DIR__ . '/../../css/style.css';
    $version = @filemtime($chemin) ?: '1';
    return $prefixe . 'css/style.css?v=' . $version;
}

function debut_page(string $titre, string $page_active = ''): void
{
    $adherent = adherent_connecte();
    $onglets  = [
        'index'     => ['Tableau de bord', 'index.php'],
        'galerie'   => ['Galerie privée',  'galerie.php'],
        'documents' => ['Documents',       'documents.php'],
        'agenda'    => ['Agenda',          'agenda.php'],
        'annuaire'  => ['Annuaire',        'annuaire.php'],
    ];
    if (est_administrateur()) {
        $onglets['adherents']   = ['Adhérents', 'adherents.php'];
        $onglets['parametres']  = ['Réglages du site', 'parametres.php'];
    }
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($titre) ?> — Espace adhérents</title>
<!-- L'espace adhérents n'a rien à faire dans les résultats de recherche. -->
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><circle cx=%2250%22 cy=%2250%22 r=%2245%22 fill=%22%23a855f7%22/></svg>">
<link rel="stylesheet" href="<?= e(lien_css()) ?>">
</head>
<body>
<a class="skip-link" href="#main">Aller au contenu</a>

<header class="site-header">
  <nav class="nav" aria-label="Navigation principale">
    <a class="logo" href="../index.html"><span class="logo-mark" aria-hidden="true"></span>Focal Club Turballais</a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="nav-links" aria-label="Ouvrir le menu">
      <span></span>
    </button>
    <ul class="nav-links" id="nav-links">
      <li><a href="../index.html">Accueil</a></li>
      <li><a href="../galerie.html">Galerie</a></li>
      <li><a href="../evenements.html">Événements</a></li>
      <li><a href="../membres.html">Le Club</a></li>
      <li><a href="../contact.html">Contact</a></li>
      <?php if ($adherent): ?>
        <li><a href="deconnexion.php">Se déconnecter</a></li>
      <?php else: ?>
        <li><a href="connexion.php" aria-current="page">Se Connecter</a></li>
      <?php endif; ?>
    </ul>
  </nav>
</header>

<main id="main">
<?php if ($adherent): ?>
  <section class="espace-barre">
    <div class="container">
      <div class="espace-identite">
        <span class="eyebrow">Espace adhérents</span>
        <p class="espace-bonjour">Bonjour <?= e($adherent['nom']) ?><?= est_administrateur() ? ' <span class="badge-admin">responsable</span>' : '' ?></p>
      </div>
      <ul class="espace-onglets">
        <?php foreach ($onglets as $cle => [$libelle, $lien]): ?>
          <li>
            <a href="<?= $lien ?>"<?= $cle === $page_active ? ' class="actif" aria-current="page"' : '' ?>><?= $libelle ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
<?php endif; ?>
    <?php
}

function fin_page(): void
{
    ?>
</main>

<footer class="site-footer">
  <div class="container">
    <p class="footer-bottom">© <?= date('Y') ?> Focal Club Turballais. Espace réservé aux adhérents.</p>
  </div>
</footer>

<script src="../js/main.js"></script>
</body>
</html>
    <?php
}

/*
 * Bandeau de titre en haut d'une page. $large aligne le titre sur le
 * conteneur élargi des pages à tableau — sans quoi le titre paraîtrait
 * décalé vers la droite par rapport au contenu.
 */
function titre_page(string $titre, string $chapeau = '', bool $large = false): void
{
    ?>
  <section class="gallery-header">
    <div class="container<?= $large ? ' container-large' : '' ?>">
      <h1 style="font-family:var(--font-heading);font-size:clamp(1.7rem,4vw,2.3rem);margin:0;"><?= e($titre) ?></h1>
      <?php if ($chapeau !== ''): ?>
        <p style="color:var(--text-muted);max-width:65ch;margin-top:10px;"><?= e($chapeau) ?></p>
      <?php endif; ?>
    </div>
  </section>
    <?php
}

/*
 * Date lisible en français : « samedi 12 septembre 2026 à 14h30 ».
 * On n'utilise pas strftime(), supprimé de PHP 8.1, ni IntlDateFormatter, qui
 * n'est pas garanti sur un hébergement mutualisé.
 */
function date_en_francais(string $date_sql, bool $avec_heure = true): string
{
    static $jours = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    static $mois  = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                     'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    $t = strtotime($date_sql);
    if ($t === false) {
        return $date_sql;
    }

    $texte = sprintf(
        '%s %d %s %d',
        $jours[(int) date('w', $t)],
        (int) date('j', $t),
        $mois[(int) date('n', $t)],
        (int) date('Y', $t)
    );

    if ($avec_heure) {
        // Construit à la main : date() échappe mal les caractères accentués.
        $texte .= ' à ' . date('G', $t) . 'h' . date('i', $t);
    }

    return $texte;
}

/*
 * Date compacte « 16/08/26 », pour les tableaux où la forme longue prendrait
 * toute la largeur. L'heure est renvoyée à part par heure_courte().
 */
function date_courte(?string $date_sql): string
{
    if ($date_sql === null || $date_sql === '') {
        return '';
    }
    $t = strtotime($date_sql);
    return $t === false ? (string) $date_sql : date('d/m/y', $t);
}

function heure_courte(?string $date_sql): string
{
    if ($date_sql === null || $date_sql === '') {
        return '';
    }
    $t = strtotime($date_sql);
    return $t === false ? '' : date('G', $t) . 'h' . date('i', $t);
}

/* Mois abrégé, pour la pastille de date de l'agenda. */
function mois_court(string $date_sql): string
{
    static $mois = ['', 'janv.', 'févr.', 'mars', 'avril', 'mai', 'juin',
                    'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
    $t = strtotime($date_sql);
    return $t === false ? '' : $mois[(int) date('n', $t)];
}

/* Taille de fichier lisible (Ko, Mo). */
function taille_lisible(int $octets): string
{
    if ($octets >= 1048576) {
        return number_format($octets / 1048576, 1, ',', ' ') . ' Mo';
    }
    if ($octets >= 1024) {
        return (string) round($octets / 1024) . ' Ko';
    }
    return $octets . ' o';
}

/* Message de confirmation ou d'erreur, transmis d'une page à l'autre. */
function definir_message(string $type, string $texte): void
{
    demarrer_session();
    $_SESSION['message'] = ['type' => $type, 'texte' => $texte];
}

function afficher_message(): void
{
    demarrer_session();
    if (empty($_SESSION['message'])) {
        return;
    }
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
    $classe = $message['type'] === 'succes' ? 'alerte-succes' : 'alerte-erreur';
    echo '<div class="alerte ' . $classe . '">' . e($message['texte']) . '</div>';
}

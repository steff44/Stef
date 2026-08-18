<?php
/*
 * Tableau de bord : point d'entrée de l'espace une fois connecté.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

$adherent = exige_connexion();
$pdo      = base_de_donnees();

// Prochaine sortie à venir.
$prochaine = $pdo->query(
    'SELECT id, titre, lieu, debut FROM sorties WHERE debut >= NOW() ORDER BY debut ASC LIMIT 1'
)->fetch();

$compteurs = [
    'photos'    => (int) $pdo->query('SELECT COUNT(*) FROM photos_privees')->fetchColumn(),
    'documents' => (int) $pdo->query('SELECT COUNT(*) FROM documents')->fetchColumn(),
    'sorties'   => (int) $pdo->query('SELECT COUNT(*) FROM sorties WHERE debut >= NOW()')->fetchColumn(),
    'adherents' => (int) $pdo->query('SELECT COUNT(*) FROM adherents WHERE actif = 1')->fetchColumn(),
];

debut_page("Tableau de bord", 'index');
titre_page("Bonjour " . $adherent['nom'], "Bienvenue dans l'espace réservé aux adhérents du club.");
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <?php if ($prochaine): ?>
    <div class="encart-sortie">
      <span class="eyebrow">Prochaine sortie</span>
      <h2 style="font-family:var(--font-heading);font-size:1.35rem;margin:6px 0 8px;"><?= e($prochaine['titre']) ?></h2>
      <p style="color:var(--text-muted);margin:0 0 16px;">
        <?= e(date_en_francais($prochaine['debut'])) ?>
        <?= $prochaine['lieu'] ? ' — ' . e($prochaine['lieu']) : '' ?>
      </p>
      <a class="btn btn-primary" href="agenda.php">Voir l'agenda</a>
    </div>
  <?php endif; ?>

  <div class="cards-grid" style="margin-top:28px;">
    <article class="feature-card">
      <div class="feature-icon" aria-hidden="true">📷</div>
      <h3>Galerie privée</h3>
      <p><?= $compteurs['photos'] ?> photo<?= $compteurs['photos'] > 1 ? 's' : '' ?> réservée<?= $compteurs['photos'] > 1 ? 's' : '' ?> aux adhérents.</p>
      <a class="btn btn-ghost" href="galerie.php">Ouvrir</a>
    </article>
    <article class="feature-card">
      <div class="feature-icon" aria-hidden="true">📄</div>
      <h3>Documents du club</h3>
      <p><?= $compteurs['documents'] ?> document<?= $compteurs['documents'] > 1 ? 's' : '' ?> à consulter ou télécharger.</p>
      <a class="btn btn-ghost" href="documents.php">Ouvrir</a>
    </article>
    <article class="feature-card">
      <div class="feature-icon" aria-hidden="true">📅</div>
      <h3>Agenda des sorties</h3>
      <p><?= $compteurs['sorties'] ?> sortie<?= $compteurs['sorties'] > 1 ? 's' : '' ?> à venir.</p>
      <a class="btn btn-ghost" href="agenda.php">Ouvrir</a>
    </article>
    <article class="feature-card">
      <div class="feature-icon" aria-hidden="true">👥</div>
      <h3>Annuaire</h3>
      <p><?= $compteurs['adherents'] ?> adhérent<?= $compteurs['adherents'] > 1 ? 's' : '' ?> dans le club.</p>
      <a class="btn btn-ghost" href="annuaire.php">Ouvrir</a>
    </article>
    <?php if (est_administrateur()): ?>
      <article class="feature-card">
        <div class="feature-icon" aria-hidden="true">🛠️</div>
        <h3>Adhérents</h3>
        <p>Gérer les comptes : créer, modifier, désactiver ou déconnecter à distance.</p>
        <a class="btn btn-ghost" href="adherents.php">Ouvrir</a>
      </article>
      <article class="feature-card">
        <div class="feature-icon" aria-hidden="true">⚙️</div>
        <h3>Réglages du site</h3>
        <p>Coordonnées du club affichées sur les pages publiques.</p>
        <a class="btn btn-ghost" href="parametres.php">Ouvrir</a>
      </article>
    <?php endif; ?>
  </div>
</div></section>
<?php
fin_page();

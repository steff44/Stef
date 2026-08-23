<?php
/*
 * Le Club — réservé aux adhérents connectés (choix explicite de
 * l'utilisateur, 18/08/2026). Remplace l'ancienne liste publique des
 * adhérents (membres.html, désormais une redirection vers cette page).
 *
 * Trois cartes : Documents du Club, Galerie Privée (réservée aux adhérents)
 * et Galerie du Club (ajoutée le 20/08/2026 — les photos que les adhérents
 * y déposent deviennent publiques, reprises sur galerie.html).
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

// Message dédié plutôt que le silence habituel de exige_connexion() : on
// veut qu'un visiteur non connecté comprenne pourquoi il atterrit sur la
// page de connexion en cliquant sur « Le Club ».
if (!adherent_connecte()) {
    definir_message('erreur', 'Adhérents seulement.');
}
exige_connexion();

debut_page("Le Club", 'le-club');
titre_page("Le Club", "Réservé aux adhérents du Focal Club Turballais.", false, true);
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>
  <div class="cards-grid">
    <article class="feature-card">
      <div class="feature-icon" aria-hidden="true">📄</div>
      <h3>Documents du Club</h3>
      <p>Comptes rendus, statuts, bulletins…</p>
      <a class="btn btn-ghost" href="documents.php">Ouvrir</a>
    </article>
    <article class="feature-card">
      <div class="feature-icon" aria-hidden="true">📷</div>
      <h3>Galerie Privée</h3>
      <p>Les photos réservées aux adhérents connectés.</p>
      <a class="btn btn-ghost" href="galerie.php">Ouvrir</a>
    </article>
    <article class="feature-card">
      <div class="feature-icon" aria-hidden="true">🖼️</div>
      <h3>Galerie du Club</h3>
      <p>Déposez vos photos par catégorie — elles apparaissent aussi sur la page Galerie, ouverte à tous.</p>
      <a class="btn btn-ghost" href="galerie-club.php">Ouvrir</a>
    </article>
  </div>
</div></section>
<?php
fin_page();

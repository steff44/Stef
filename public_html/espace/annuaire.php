<?php
/*
 * Annuaire : coordonnées des adhérents, visibles entre membres uniquement.
 * Chacun met à jour ses propres coordonnées et son mot de passe.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

$adherent = exige_connexion();
$pdo      = base_de_donnees();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    if (($_POST['action'] ?? '') === 'mot_de_passe') {
        $actuel = (string) ($_POST['actuel'] ?? '');
        $mot1   = (string) ($_POST['nouveau'] ?? '');
        $mot2   = (string) ($_POST['confirmation'] ?? '');

        $requete = $pdo->prepare('SELECT mot_de_passe FROM adherents WHERE id = ?');
        $requete->execute([$adherent['id']]);
        $hachage = (string) $requete->fetchColumn();

        if (!password_verify($actuel, $hachage)) {
            definir_message('erreur', "Votre mot de passe actuel est incorrect.");
        } elseif (mb_strlen($mot1) < 10) {
            definir_message('erreur', "Le nouveau mot de passe doit contenir au moins 10 caractères.");
        } elseif ($mot1 !== $mot2) {
            definir_message('erreur', "Les deux nouveaux mots de passe ne sont pas identiques.");
        } else {
            $pdo->prepare('UPDATE adherents SET mot_de_passe = ? WHERE id = ?')
                ->execute([password_hash($mot1, PASSWORD_DEFAULT), $adherent['id']]);
            definir_message('succes', "Mot de passe modifié.");
        }
    } else {
        $email     = trim((string) ($_POST['email'] ?? ''));
        $telephone = trim((string) ($_POST['telephone'] ?? ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            definir_message('erreur', "L'adresse e-mail n'est pas valide.");
        } else {
            $pdo->prepare('UPDATE adherents SET email = ?, telephone = ? WHERE id = ?')
                ->execute([$email ?: null, $telephone ?: null, $adherent['id']]);

            // La session garde une copie des coordonnées : on la rafraîchit.
            $_SESSION['adherent']['email']     = $email ?: null;
            $_SESSION['adherent']['telephone'] = $telephone ?: null;
            definir_message('succes', "Vos coordonnées ont été mises à jour.");
        }
    }

    header('Location: annuaire.php');
    exit;
}

$membres = $pdo->query(
    'SELECT id, nom, identifiant, email, telephone, administrateur
       FROM adherents WHERE actif = 1 ORDER BY nom'
)->fetchAll();

debut_page("Annuaire", 'annuaire');
titre_page("Annuaire des adhérents", "Ces coordonnées sont réservées aux membres du club : merci de ne pas les diffuser.");
?>
<section class="section"><div class="container">
  <?php afficher_message(); ?>

  <div class="members-grid">
    <?php foreach ($membres as $membre): ?>
      <article class="member-card annuaire-carte">
        <div class="member-body">
          <h3><?= e($membre['nom']) ?><?= $membre['administrateur'] ? ' <span class="badge-admin">responsable</span>' : '' ?></h3>
          <ul class="annuaire-contacts">
            <?php if ($membre['email']): ?>
              <li><a href="mailto:<?= e($membre['email']) ?>"><?= e($membre['email']) ?></a></li>
            <?php endif; ?>
            <?php if ($membre['telephone']): ?>
              <li><a href="tel:<?= e(preg_replace('/\s+/', '', $membre['telephone'])) ?>"><?= e($membre['telephone']) ?></a></li>
            <?php endif; ?>
            <?php if (!$membre['email'] && !$membre['telephone']): ?>
              <li class="annuaire-vide">Aucune coordonnée renseignée</li>
            <?php endif; ?>
          </ul>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

  <h2 class="titre-section" style="margin-top:44px;">Mes informations</h2>
  <div class="grille-deux">
    <form method="post" class="form-card">
      <?= champ_csrf() ?>
      <h3 style="font-family:var(--font-heading);font-size:1.1rem;margin:0 0 14px;">Mes coordonnées</h3>
      <div class="field">
        <label for="email">E-mail</label>
        <input type="email" id="email" name="email" value="<?= e($adherent['email'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="telephone">Téléphone</label>
        <input type="tel" id="telephone" name="telephone" value="<?= e($adherent['telephone'] ?? '') ?>">
      </div>
      <button type="submit" class="btn btn-primary">Enregistrer</button>
    </form>

    <form method="post" class="form-card">
      <?= champ_csrf() ?>
      <input type="hidden" name="action" value="mot_de_passe">
      <h3 style="font-family:var(--font-heading);font-size:1.1rem;margin:0 0 14px;">Changer mon mot de passe</h3>
      <div class="field">
        <label for="actuel">Mot de passe actuel</label>
        <input type="password" id="actuel" name="actuel" required autocomplete="current-password">
      </div>
      <div class="field">
        <label for="nouveau">Nouveau mot de passe (10 caractères minimum)</label>
        <input type="password" id="nouveau" name="nouveau" required minlength="10" autocomplete="new-password">
      </div>
      <div class="field">
        <label for="confirmation">Confirmer</label>
        <input type="password" id="confirmation" name="confirmation" required minlength="10" autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary">Modifier</button>
    </form>
  </div>
</div></section>
<?php
fin_page();

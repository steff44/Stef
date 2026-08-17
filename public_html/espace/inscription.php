<?php
/*
 * Inscription publique à l'espace adhérents : n'importe qui peut créer un
 * compte, qui est actif immédiatement (comme un compte créé par un
 * responsable depuis la page Adhérents). Sur validation, la personne est
 * connectée tout de suite via tenter_connexion(), pour ne pas lui demander
 * de ressaisir son mot de passe juste après l'avoir choisi.
 */

declare(strict_types=1);

require_once __DIR__ . '/inc/page.php';

$pdo = base_de_donnees();

// Déjà connecté : rien à faire ici.
if (adherent_connecte()) {
    header('Location: index.php');
    exit;
}

$erreurs = [];
$valeurs = [
    'pseudo'       => '',
    'nom'          => '',
    'prenom'       => '',
    'email'        => '',
    'telephone'    => '',
    'code_postal'  => '',
    'ville'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();

    foreach (array_keys($valeurs) as $champ) {
        $valeurs[$champ] = trim((string) ($_POST[$champ] ?? ''));
    }
    $mot1 = (string) ($_POST['mot_de_passe'] ?? '');
    $mot2 = (string) ($_POST['confirmation'] ?? '');

    if (!preg_match('/^[a-zA-Z0-9._-]{3,60}$/', $valeurs['pseudo'])) {
        $erreurs[] = "Le pseudo doit faire 3 à 60 caractères, sans espace ni accent (lettres, chiffres, point, tiret).";
    }
    if ($valeurs['nom'] === '') {
        $erreurs[] = "Le nom est obligatoire.";
    }
    if ($valeurs['prenom'] === '') {
        $erreurs[] = "Le prénom est obligatoire.";
    }
    if ($valeurs['email'] === '' || !filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = "L'adresse e-mail est obligatoire et doit être valide.";
    }
    if ($valeurs['code_postal'] !== '' && !preg_match('/^[0-9]{4,10}$/', $valeurs['code_postal'])) {
        $erreurs[] = "Le code postal ne doit contenir que des chiffres.";
    }
    if (mb_strlen($mot1) < 10) {
        $erreurs[] = "Le mot de passe doit contenir au moins 10 caractères.";
    }
    if ($mot1 !== $mot2) {
        $erreurs[] = "Les deux mots de passe ne sont pas identiques.";
    }

    // Le pseudo doit être libre — vérifié à part de la contrainte UNIQUE de la
    // base, pour renvoyer un message clair plutôt qu'une erreur SQL.
    if (!$erreurs) {
        $existe = $pdo->prepare('SELECT 1 FROM adherents WHERE identifiant = ?');
        $existe->execute([$valeurs['pseudo']]);
        if ($existe->fetchColumn()) {
            $erreurs[] = "Ce pseudo est déjà pris, choisissez-en un autre.";
        }
    }

    if (!$erreurs) {
        $creation = $pdo->prepare(
            'INSERT INTO adherents
                (identifiant, nom, email, telephone, code_postal, ville, mot_de_passe, administrateur, actif)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)'
        );
        $creation->execute([
            $valeurs['pseudo'],
            trim($valeurs['prenom'] . ' ' . $valeurs['nom']),
            $valeurs['email'],
            $valeurs['telephone'] !== '' ? $valeurs['telephone'] : null,
            $valeurs['code_postal'] !== '' ? $valeurs['code_postal'] : null,
            $valeurs['ville'] !== '' ? $valeurs['ville'] : null,
            password_hash($mot1, PASSWORD_DEFAULT),
        ]);

        // Connexion immédiate : on réutilise tenter_connexion() plutôt que de
        // reconstruire la session à la main, pour ne pas dupliquer sa logique
        // (session_regenerate_id, horodatages, etc.).
        tenter_connexion($valeurs['pseudo'], $mot1);

        definir_message('succes', "Bienvenue, " . $valeurs['prenom'] . " ! Votre compte est prêt.");
        header('Location: index.php');
        exit;
    }
}

debut_page("S'inscrire", 'inscription');
titre_page("Rejoindre le club", "Créez votre compte pour accéder à l'espace adhérents.");
?>
<section class="section"><div class="container">
  <div class="form-card" style="max-width:560px;margin:0 auto;">
    <?php foreach ($erreurs as $erreur): ?>
      <div class="alerte alerte-erreur"><?= e($erreur) ?></div>
    <?php endforeach; ?>

    <form method="post" autocomplete="off">
      <?= champ_csrf() ?>
      <div class="field-row">
        <div class="field">
          <label for="prenom">Prénom</label>
          <input type="text" id="prenom" name="prenom" required
                 value="<?= e($valeurs['prenom']) ?>">
        </div>
        <div class="field">
          <label for="nom">Nom</label>
          <input type="text" id="nom" name="nom" required
                 value="<?= e($valeurs['nom']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="pseudo">Pseudo (servira à vous connecter)</label>
        <input type="text" id="pseudo" name="pseudo" required
               value="<?= e($valeurs['pseudo']) ?>" placeholder="stephane">
      </div>
      <div class="field-row">
        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" required
                 value="<?= e($valeurs['email']) ?>">
        </div>
        <div class="field">
          <label for="telephone">Téléphone (facultatif)</label>
          <input type="tel" id="telephone" name="telephone"
                 value="<?= e($valeurs['telephone']) ?>">
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="code_postal">Code postal (facultatif)</label>
          <input type="text" id="code_postal" name="code_postal" inputmode="numeric"
                 value="<?= e($valeurs['code_postal']) ?>">
        </div>
        <div class="field">
          <label for="ville">Ville (facultatif)</label>
          <input type="text" id="ville" name="ville"
                 value="<?= e($valeurs['ville']) ?>">
        </div>
      </div>
      <div class="field">
        <label for="mot_de_passe">Mot de passe (10 caractères minimum)</label>
        <input type="password" id="mot_de_passe" name="mot_de_passe" required minlength="10">
      </div>
      <div class="field">
        <label for="confirmation">Confirmer le mot de passe</label>
        <input type="password" id="confirmation" name="confirmation" required minlength="10">
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;">S'inscrire</button>
      <p class="form-note">Déjà inscrit(e) ? <a href="connexion.php" style="text-decoration:underline;">Connectez-vous</a>.</p>
    </form>
  </div>
</div></section>
<?php
fin_page();

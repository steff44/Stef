<?php
/*
 * L'inscription vit désormais sur connexion.php (choix explicite de
 * l'utilisateur, 18/08/2026 : une seule page avec deux onglets, Connexion et
 * Inscription, plutôt que deux pages séparées). Ce fichier ne fait plus que
 * rediriger, pour ne pas casser les anciens liens et favoris — même principe
 * qu'evenements.html, membres.html et connexion.html.
 */

declare(strict_types=1);

header('Location: connexion.php?onglet=inscription');
exit;

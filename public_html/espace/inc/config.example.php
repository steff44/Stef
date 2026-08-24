<?php
/*
 * MODÈLE de configuration — ce fichier-ci ne contient aucun mot de passe.
 *
 * À FAIRE UNE SEULE FOIS, directement sur le serveur :
 *   1. hPanel → Gestionnaire de fichiers → public_html/espace/inc/
 *   2. Copier ce fichier sous le nom  config.local.php
 *   3. Y remplacer les quatre valeurs ci-dessous par celles de votre base
 *      MySQL (hPanel → Bases de données → MySQL)
 *
 * Pourquoi à la main ? Parce que le dépôt GitHub est PUBLIC : un mot de passe
 * de base de données qui y serait déposé deviendrait visible par tout le monde.
 * config.local.php reste donc uniquement sur le serveur, et le déploiement ne
 * l'écrase jamais (rsync sans --delete n'efface ni ne remplace ce qu'il ne
 * connaît pas).
 */

return [
    // Presque toujours 'localhost' chez Hostinger.
    'hote'         => 'localhost',

    // Ressemble à u912253694_focal
    'base'         => 'NOM_DE_LA_BASE',
    'utilisateur'  => 'UTILISATEUR_DE_LA_BASE',
    'mot_de_passe' => 'MOT_DE_PASSE_DE_LA_BASE',

    // Facultatif — voir infos-expo-2026.php : page « Expo 2026 », qui
    // affiche les photos conservées sur Google Drive (un dossier par
    // adhérent) sans les héberger sur ce serveur. Laisser tel quel
    // (chaînes vides) désactive simplement cette page. Voir
    // CLAUDE.md pour la procédure complète de création de la clé.
    'google_drive_cle_api'     => '',
    'google_drive_dossier_id'  => '',
];

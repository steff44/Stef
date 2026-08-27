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

    // Facultatif — voir infos-albums.php : page « Nos Sorties », qui affiche
    // les photos conservées sur Google Drive (un album par sortie, un
    // dossier par adhérent à l'intérieur) sans les héberger sur ce serveur.
    // Laisser vide désactive simplement cette page. Voir CLAUDE.md pour la
    // procédure complète de création de la clé.
    //
    // Seule la clé API vit ici : c'est un secret, il n'a rien à faire en
    // base ni dans une interface web. Les dossiers Drive, eux, se règlent
    // album par album depuis « Réglages du site » (depuis le 27/08/2026).
    // L'ancien réglage unique 'google_drive_dossier_id' n'est plus utilisé
    // par le code, mais a été lu automatiquement UNE FOIS lors de la mise à
    // jour, pour en faire le premier album « Expo 2026 » sans intervention —
    // sur une installation neuve, laisser cette clé absente ou vide.
    'google_drive_cle_api'     => '',
];

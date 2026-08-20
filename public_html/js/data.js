/* Données du club. Les photos de démonstration (adhérents et clichés
   fictifs créés au début de la construction du site) ont été retirées le
   20/08/2026, à la demande de l'utilisateur, maintenant que de vraies
   photos existent dans la Galerie du Club (espace/galerie-club.php) et
   s'affichent automatiquement sur la page Galerie et l'accueil — voir
   infos-galerie-club.php et js/main.js. Ne pas réintroduire de membres ou
   de photos fictifs ici : les catégories ci-dessous suffisent à générer les
   filtres de la page Galerie, même sans aucune photo pour l'instant. */
const CLUB_DATA = {
  nom: "Focal Club Turballais",
  accroche: "Capturer la lumière, partager le regard.",
  themes: [
    "Portrait",
    "Paysage",
    "Marais salants",
    "Nature",
    "Architecture",
    "Sport",
    "Photo de rue",
    "Macro / Proxi",
    "Voyage / Reportage",
    "Noir et Blanc",
    "Abstrait / Créatif"
  ],
  membres: []
};

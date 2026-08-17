/* Données du club — à modifier librement pour ajouter/retirer des adhérents ou des photos. */
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
  membres: [
    {
      id: "claire-martin",
      nom: "Claire Martin",
      role: "Présidente du club",
      bio: "Passionnée de photographie de paysage depuis quinze ans, Claire aime capturer la lumière du petit matin en montagne.",
      hue: 210,
      photos: [
        { titre: "Lever de soleil sur les Alpes", theme: "Paysage" },
        { titre: "Brume matinale en vallée", theme: "Paysage" },
        { titre: "Reflets sur le lac", theme: "Paysage" },
        { titre: "Sentier de randonnée", theme: "Paysage" },
        { titre: "Sommet enneigé", theme: "Paysage" },
        { titre: "Forêt de mélèzes", theme: "Paysage" }
      ]
    },
    {
      id: "youssef-benali",
      nom: "Youssef Benali",
      role: "Trésorier",
      bio: "Adepte du street photography, Youssef arpente les rues de la ville à la recherche d'instants de vie spontanés.",
      hue: 15,
      photos: [
        { titre: "Marché du samedi matin", theme: "Photo de rue" },
        { titre: "Passage piéton sous la pluie", theme: "Photo de rue" },
        { titre: "Terrasse de café", theme: "Photo de rue" },
        { titre: "Ombres sur le trottoir", theme: "Photo de rue" },
        { titre: "Vélo contre le mur", theme: "Photo de rue" },
        { titre: "Néons du soir", theme: "Photo de rue" }
      ]
    },
    {
      id: "elena-rossi",
      nom: "Elena Rossi",
      role: "Responsable des sorties",
      bio: "Elena se spécialise dans le portrait en lumière naturelle et organise les sorties photo mensuelles du club.",
      hue: 330,
      photos: [
        { titre: "Portrait à contre-jour", theme: "Portrait" },
        { titre: "Regard en lumière douce", theme: "Portrait" },
        { titre: "Sourire au parc", theme: "Portrait" },
        { titre: "Profil sur fond flou", theme: "Portrait" },
        { titre: "Mains et lumière", theme: "Portrait" },
        { titre: "Portrait en noir et blanc", theme: "Portrait" }
      ]
    },
    {
      id: "thomas-lefevre",
      nom: "Thomas Lefèvre",
      role: "Adhérent",
      bio: "Passionné de macrophotographie, Thomas explore l'infiniment petit dans son jardin et les parcs alentour.",
      hue: 140,
      photos: [
        { titre: "Goutte de rosée sur pétale", theme: "Macro / Proxi" },
        { titre: "Aile de papillon", theme: "Macro / Proxi" },
        { titre: "Texture d'écorce", theme: "Macro / Proxi" },
        { titre: "Fourmi sur une feuille", theme: "Macro / Proxi" },
        { titre: "Pistils de fleur", theme: "Macro / Proxi" },
        { titre: "Toile d'araignée givrée", theme: "Macro / Proxi" }
      ]
    },
    {
      id: "sofia-nguyen",
      nom: "Sofia Nguyen",
      role: "Adhérente",
      bio: "Sofia travaille principalement en noir et blanc argentique, avec une attention particulière aux contrastes et aux textures.",
      hue: 45,
      photos: [
        { titre: "Escalier en colimaçon", theme: "Noir et Blanc" },
        { titre: "Façade abandonnée", theme: "Noir et Blanc" },
        { titre: "Silhouette au port", theme: "Noir et Blanc" },
        { titre: "Vagues et rochers", theme: "Noir et Blanc" },
        { titre: "Fenêtre et ombre", theme: "Noir et Blanc" },
        { titre: "Ruelle pavée", theme: "Noir et Blanc" }
      ]
    },
    {
      id: "marc-dubois",
      nom: "Marc Dubois",
      role: "Adhérent",
      bio: "Marc se consacre à la photographie animalière et anime les ateliers d'initiation du club pour les nouveaux membres.",
      hue: 265,
      photos: [
        { titre: "Héron au bord de l'étang", theme: "Nature" },
        { titre: "Renard en lisière de forêt", theme: "Nature" },
        { titre: "Vol de mésanges", theme: "Nature" },
        { titre: "Biche au crépuscule", theme: "Nature" },
        { titre: "Écureuil roux", theme: "Nature" },
        { titre: "Libellule sur roseau", theme: "Nature" }
      ]
    }
  ]
};

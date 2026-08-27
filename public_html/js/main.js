(function () {
  "use strict";

  /* Échappe un texte avant de l'insérer via innerHTML — nécessaire dès que le
     texte peut contenir des caractères spéciaux HTML (&, <, >, "), ce qui est
     le cas de tout texte saisi par un adhérent (titre de photo, nom affiché…). */
  function echapperHtml(texte) {
    const span = document.createElement("span");
    span.textContent = texte;
    return span.innerHTML;
  }

  /* ---------- Navigation mobile ---------- */
  const toggle = document.querySelector(".nav-toggle");
  const links = document.querySelector(".nav-links");
  if (toggle && links) {
    toggle.addEventListener("click", function () {
      const open = links.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", String(open));
    });
    links.querySelectorAll("a").forEach(function (a) {
      a.addEventListener("click", function () {
        links.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      });
    });
  }

  /* ---------- Œil pour afficher/masquer un mot de passe ----------
     Générique : s'applique à tout input[type="password"] présent sur la
     page, quel que soit le formulaire (connexion, inscription,
     installation, changement de mot de passe dans l'annuaire) — pas
     besoin de le poser à la main sur chaque champ. */
  (function () {
    const iconeOeil =
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const iconeOeilBarre =
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a21.8 21.8 0 0 1 5.06-6.06"></path><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a21.8 21.8 0 0 1-3.22 4.62"></path><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>';

    document.querySelectorAll('input[type="password"]').forEach(function (champ) {
      const enveloppe = document.createElement("div");
      enveloppe.className = "champ-mot-de-passe";
      champ.parentNode.insertBefore(enveloppe, champ);
      enveloppe.appendChild(champ);

      const bouton = document.createElement("button");
      bouton.type = "button";
      bouton.className = "bouton-oeil";
      bouton.setAttribute("aria-label", "Afficher le mot de passe");
      bouton.innerHTML = iconeOeil;
      enveloppe.appendChild(bouton);

      bouton.addEventListener("click", function () {
        const visible = champ.type === "text";
        champ.type = visible ? "password" : "text";
        bouton.innerHTML = visible ? iconeOeil : iconeOeilBarre;
        bouton.setAttribute("aria-label", visible ? "Afficher le mot de passe" : "Masquer le mot de passe");
      });
    });
  })();

  /* ---------- Dépôt de fichiers : glissé-déposé fiable + avertissement de
     taille ----------
     Générique : s'applique à tout input[type="file"] présent sur la page.
     Deux correctifs, choix explicite de l'utilisateur, 26/08/2026 :

     1. Un <input type="file"> nu n'accepte un glissé-déposé que si le
        fichier atterrit exactement sur son petit bouton natif "Choisir des
        fichiers" — un dépôt n'importe où ailleurs dans le champ (le label,
        le texte d'aide, le padding autour) était ignoré, ce qui donnait
        l'impression que le glissé-déposé ne fonctionnait pas du tout. La
        zone de dépôt effective est donc élargie au .field entier qui
        contient l'input : les fichiers lâchés y sont réaffectés à
        champ.files via DataTransfer, puis un évènement "change" est
        déclenché pour que le reste du script (avertissement de taille)
        réagisse comme à une sélection normale. Un écouteur global
        dragover/drop — armé seulement quand le glissé transporte
        effectivement des fichiers (dataTransfer.types contient "Files"),
        pour ne jamais gêner un glissé de texte ordinaire ailleurs sur la
        page — empêche aussi le navigateur de quitter la page pour afficher
        le fichier si le dépôt rate malgré tout la zone.
     2. Avertissement immédiat si un fichier dépasse la taille maximale
        (input[data-taille-max], posé sur tout point de dépôt du site) :
        vérifié dès la sélection ou le dépôt, avant tout envoi au serveur —
        qui reste la vérification qui fait foi, ce n'est qu'un confort. En
        surimpression sur le champ entier, avec une croix pour le fermer
        sans avoir à choisir un autre fichier. */
  (function () {
    function estDepotDeFichiers(e) {
      return !!(e.dataTransfer && Array.from(e.dataTransfer.types || []).indexOf("Files") !== -1);
    }
    document.addEventListener("dragover", function (e) {
      if (estDepotDeFichiers(e)) e.preventDefault();
    });
    document.addEventListener("drop", function (e) {
      if (estDepotDeFichiers(e)) e.preventDefault();
    });

    document.querySelectorAll('input[type="file"]').forEach(function (champ) {
      const zone = champ.closest(".field") || champ;

      zone.addEventListener("dragover", function (e) {
        if (!estDepotDeFichiers(e)) return;
        e.preventDefault();
        zone.classList.add("field--survole");
      });
      zone.addEventListener("dragleave", function () {
        zone.classList.remove("field--survole");
      });
      zone.addEventListener("drop", function (e) {
        if (!estDepotDeFichiers(e)) return;
        e.preventDefault();
        zone.classList.remove("field--survole");
        if (!e.dataTransfer.files.length) return;

        if (!champ.multiple && e.dataTransfer.files.length > 1) {
          const dt = new DataTransfer();
          dt.items.add(e.dataTransfer.files[0]);
          champ.files = dt.files;
        } else {
          champ.files = e.dataTransfer.files;
        }
        champ.dispatchEvent(new Event("change", { bubbles: true }));
      });

      const tailleMax = champ.dataset.tailleMax ? parseInt(champ.dataset.tailleMax, 10) : 0;
      const avertissement = zone.querySelector("[data-avertissement-taille]");
      if (!tailleMax || !avertissement) return;

      // Texte et croix de fermeture, construits une seule fois : la croix
      // permet de fermer l'avertissement sans avoir à choisir un autre
      // fichier (choix explicite de l'utilisateur, 26/08/2026).
      const texte = document.createElement("span");
      avertissement.appendChild(texte);
      const fermer = document.createElement("button");
      fermer.type = "button";
      fermer.className = "form-avertissement-fermer";
      fermer.setAttribute("aria-label", "Fermer l'avertissement");
      fermer.textContent = "×";
      fermer.addEventListener("click", function () {
        avertissement.hidden = true;
      });
      avertissement.appendChild(fermer);

      champ.addEventListener("change", function () {
        const tropLourds = Array.from(champ.files).filter(function (fichier) {
          return fichier.size > tailleMax;
        });
        if (!tropLourds.length) {
          avertissement.hidden = true;
          texte.textContent = "";
          return;
        }
        const pluriel = tropLourds.length > 1;
        const noms = tropLourds
          .map(function (fichier) { return "« " + fichier.name + " »"; })
          .join(", ");
        texte.textContent =
          (pluriel ? "Ces photos dépassent " : "Cette photo dépasse ") +
          (champ.dataset.tailleMaxLisible || "la taille maximale") +
          (pluriel ? " et ne seront pas envoyées : " : " et ne sera pas envoyée : ") +
          noms + ".";
        avertissement.hidden = false;
      });
    });
  })();

  /* ---------- Menu déroulant "Espace Adhérent" ---------- */
  const dropdowns = document.querySelectorAll(".nav-dropdown");
  function closeDropdown(dropdown) {
    dropdown.classList.remove("is-open");
    dropdown.querySelector(".nav-dropdown-trigger").setAttribute("aria-expanded", "false");
  }
  dropdowns.forEach(function (dropdown) {
    const trigger = dropdown.querySelector(".nav-dropdown-trigger");
    trigger.addEventListener("click", function () {
      const willOpen = !dropdown.classList.contains("is-open");
      dropdowns.forEach(closeDropdown);
      if (willOpen) {
        dropdown.classList.add("is-open");
        trigger.setAttribute("aria-expanded", "true");
      }
    });
    dropdown.querySelectorAll(".nav-dropdown-menu a").forEach(function (a) {
      a.addEventListener("click", function () {
        closeDropdown(dropdown);
      });
    });
  });
  document.addEventListener("click", function (e) {
    dropdowns.forEach(function (dropdown) {
      if (!dropdown.contains(e.target)) closeDropdown(dropdown);
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") dropdowns.forEach(closeDropdown);
  });

  /* ---------- Contenu dynamique (coordonnées, présentation) ----------
     Un responsable peut modifier ces textes depuis l'espace adhérents
     (« Réglages du site »). Comme le reste du site est du HTML statique, ils
     sont chargés ici depuis infos-club.php et viennent remplacer le texte
     déjà présent dans la page. En cas d'échec — hors ligne, ou préversion
     GitHub Pages qui ne peut pas exécuter PHP — le texte figé dans le HTML
     reste affiché : c'est le repli voulu, pas une erreur à corriger. */
  (function () {
    const cibles = document.querySelectorAll("[data-contenu]");
    if (!cibles.length) return;

    fetch("infos-club.php")
      .then(function (reponse) {
        return reponse.ok ? reponse.json() : Promise.reject();
      })
      .then(function (donnees) {
        cibles.forEach(function (el) {
          const cle = el.dataset.contenu;
          const valeur = donnees[cle];
          if (typeof valeur !== "string" || valeur === "") return;
          el.textContent = valeur;
          if (el.tagName === "A" && cle === "telephone") {
            el.href = "tel:" + valeur.replace(/\s+/g, "");
          }
          if (el.tagName === "A" && cle === "email") {
            el.href = "mailto:" + valeur;
          }
        });

        const detailsAdresse = document.querySelector("[data-contenu-adresse-details]");
        if (detailsAdresse && donnees.adresse_rue && donnees.adresse_code_postal && donnees.adresse_ville) {
          detailsAdresse.textContent =
            donnees.adresse_rue + ", " + donnees.adresse_code_postal + " " + donnees.adresse_ville;
        }

        const lienMaps = document.querySelector("[data-contenu-maps]");
        if (lienMaps) {
          const requete = [donnees.nom_lieu, donnees.adresse_rue, donnees.adresse_code_postal, donnees.adresse_ville]
            .filter(Boolean)
            .join(" ");
          if (requete) {
            lienMaps.href = "https://www.google.com/maps/search/?api=1&query=" + encodeURIComponent(requete);
          }
        }

        const formulaire = document.querySelector("[data-contenu-mailto-action]");
        if (formulaire && donnees.email) {
          formulaire.action = "mailto:" + donnees.email;
        }
      })
      .catch(function () {});
  })();

  /* ---------- État de connexion sur les pages statiques ----------
     Les pages statiques ne savent jamais, au moment du déploiement, si le
     visiteur est connecté : sans ce correctif, le menu « {pseudo} connecté »
     revenait afficher « Espace Adhérent » dès qu'on quittait une page de
     l'espace adhérents pour une page publique, alors que la session, elle,
     restait bien active. On ne touche à rien si la page a déjà rendu l'état
     connecté côté serveur (page.php) — reconnaissable à .nav-dropdown-label,
     absente du rendu non connecté. */
  (function () {
    let dropdown = null;
    dropdowns.forEach(function (d) {
      if (
        !d.querySelector(".nav-dropdown-label") &&
        d.querySelector('a[href$="connexion.php"]')
      ) {
        dropdown = d;
      }
    });
    if (!dropdown) return;

    fetch("espace/statut-connexion.php")
      .then(function (reponse) {
        return reponse.ok ? reponse.json() : Promise.reject();
      })
      .then(function (donnees) {
        if (!donnees.connecte) return;

        const trigger = dropdown.querySelector(".nav-dropdown-trigger");
        const menu = dropdown.querySelector(".nav-dropdown-menu");
        if (!trigger || !menu) return;

        Array.from(trigger.childNodes).forEach(function (noeud) {
          if (noeud.nodeType === Node.TEXT_NODE) noeud.remove();
        });
        trigger.classList.add("nav-dropdown-trigger--icone");
        trigger.setAttribute("aria-label", "Ouvrir le menu Espace Adhérent");

        const lien = document.createElement("a");
        lien.className = "nav-dropdown-label";
        lien.href = "espace/index.php";
        lien.textContent = donnees.identifiant + " connecté";
        dropdown.insertBefore(lien, trigger);

        const badge = donnees.administrateur
          ? ' <span class="badge-admin">responsable</span>'
          : donnees.editeur
          ? ' <span class="badge-editeur">éditeur</span>'
          : "";
        const pagesAdmin =
          (donnees.administrateur || donnees.editeur
            ? '<li><a href="espace/adherents.php">Adhérents</a></li>'
            : "") +
          (donnees.administrateur
            ? '<li><a href="espace/parametres.php">Réglages du site</a></li>'
            : "");
        menu.innerHTML =
          '<li class="nav-dropdown-heading">Bonjour <strong>' + echapperHtml(donnees.nom) + "</strong>" + badge + "</li>" +
          '<li><a href="espace/deconnexion.php">Se déconnecter</a></li>' +
          '<li class="nav-dropdown-divider"></li>' +
          '<li><a href="espace/index.php">Tableau de bord</a></li>' +
          '<li><a href="espace/galerie.php">Galerie privée</a></li>' +
          '<li><a href="espace/galerie-club.php">Galerie du Club</a></li>' +
          '<li><a href="espace/documents.php">Documents</a></li>' +
          '<li><a href="espace/agenda.php">Agenda des sorties</a></li>' +
          '<li><a href="espace/sorties-a-venir.php">Sorties à venir</a></li>' +
          '<li><a href="espace/annuaire.php">Annuaire</a></li>' +
          '<li><a href="espace/le-club.php">Le Club</a></li>' +
          pagesAdmin;

        // Les liens ajoutés après coup n'ont pas les écouteurs posés plus
        // haut (fermeture du menu déroulant et du menu mobile au clic).
        const fermer = function () {
          closeDropdown(dropdown);
          if (links) links.classList.remove("is-open");
          if (toggle) toggle.setAttribute("aria-expanded", "false");
        };
        lien.addEventListener("click", fermer);
        menu.querySelectorAll("a").forEach(function (a) {
          a.addEventListener("click", fermer);
        });
      })
      .catch(function () {});
  })();

  /* ---------- Utilitaires ---------- */
  function initials(nom) {
    return nom
      .split(" ")
      .map(function (p) {
        return p.charAt(0);
      })
      .join("")
      .toUpperCase();
  }

  function photoGradient(hue, index) {
    const h1 = (hue + index * 11) % 360;
    const h2 = (hue + 40 + index * 7) % 360;
    return "linear-gradient(150deg, hsl(" + h1 + " 60% 42%), hsl(" + h2 + " 45% 18%))";
  }

  function avatarGradient(hue) {
    return "linear-gradient(135deg, hsl(" + hue + " 70% 62%), hsl(" + ((hue + 50) % 360) + " 55% 42%))";
  }

  /* Une vraie photo (Galerie du Club, champ "image") s'affiche telle quelle ;
     une photo de démonstration (js/data.js, sans champ "image") garde son
     dégradé de couleur généré. Guillemets simples autour de l'URL : la
     vignette insère ce résultat dans un attribut style="…" via innerHTML
     (voir buildPhotoCard) — des guillemets doubles y refermeraient
     prématurément l'attribut et videraient la vignette (constaté le
     20/08/2026 : les photos de la Galerie du Club n'affichaient aucune
     miniature, alors que le titre et la fiche s'affichaient normalement). */
  function photoBackground(photo) {
    return photo.image
      ? "center / cover no-repeat url('" + photo.image + "')"
      : photoGradient(photo.hue, photo.index);
  }

  /* Remplit le cadre de la lightbox avec la photo ENTIÈRE (24/08/2026 :
     le fond CSS « cover » recadrait tout ce qui n'était pas au format 4/3
     — les photos verticales étaient coupées en haut et en bas). Une vraie
     <img> plutôt qu'un fond : le cadre épouse alors la photo, donc la
     bordure et l'ombre entourent l'image elle-même, sans bandes vides
     autour. Le recadrage reste voulu sur les VIGNETTES (.photo-card, voir
     buildPhotoCard) : c'est ce qui leur donne des tailles uniformes.
     Partagée par les deux systèmes d'agrandissement du site (celui des
     pages publiques et celui des galeries de l'espace adhérents). */
  function poserPhotoAgrandie(frame, photo) {
    frame.innerHTML = "";
    // imageGrande : version 1920px, servie par infos-expo-2026.php pour
    // l'agrandissement seulement (la grille se contente de 1000px). Les
    // photos hébergées sur le serveur du club — Galerie du Club, galeries
    // de l'espace adhérents — n'ont qu'une seule taille, d'où le repli.
    const source = photo.imageGrande || photo.image;
    if (source) {
      frame.classList.remove("lightbox-frame--degrade");
      frame.style.background = "";
      const img = document.createElement("img");
      img.className = "lightbox-image";
      img.src = source;
      img.alt = photo.titre || "";
      frame.appendChild(img);
      return;
    }
    // Photo sans fichier : dégradé de repli, qui a besoin d'une taille
    // puisqu'il n'y a pas d'image pour donner ses dimensions au cadre.
    frame.classList.add("lightbox-frame--degrade");
    frame.style.background = photoGradient(photo.hue, photo.index);
  }

  /* ---------- Lightbox ---------- */
  const lightbox = document.querySelector("[data-lightbox]");
  let activePhotos = [];
  let activeIndex = 0;
  let diaporamaTimer = null;

  const ICONE_DIAPORAMA_JOUER =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polygon points="10 8 16 12 10 16 10 8"></polygon></svg>Diaporama';
  const ICONE_DIAPORAMA_PAUSE =
    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><line x1="10" y1="8" x2="10" y2="16"></line><line x1="14" y1="8" x2="14" y2="16"></line></svg>Pause';

  /* Reflète l'état marche/arrêt sur le bouton de la lightbox (si présent sur
     la page) : centralisé ici plutôt que dupliqué à chaque appelant, que le
     diaporama soit arrêté par ce bouton, par les flèches précédent/suivant,
     ou par la fermeture de la lightbox. Icône + texte plutôt qu'un simple
     caractère ▶/⏸ (26/08/2026, choix explicite de l'utilisateur) : le
     bouton reprend maintenant l'apparence de .diaporama-trigger (voir la
     règle CSS .lightbox-diaporama). */
  function reglerBoutonDiaporama(actif) {
    if (!lightbox) return;
    const bouton = lightbox.querySelector(".lightbox-diaporama");
    if (!bouton) return;
    bouton.classList.toggle("is-playing", actif);
    bouton.innerHTML = actif ? ICONE_DIAPORAMA_PAUSE : ICONE_DIAPORAMA_JOUER;
    bouton.setAttribute("aria-label", actif ? "Arrêter le diaporama" : "Lancer le diaporama");
  }

  function stopDiaporama() {
    if (diaporamaTimer) {
      clearInterval(diaporamaTimer);
      diaporamaTimer = null;
    }
    reglerBoutonDiaporama(false);
  }

  function startDiaporama() {
    stopDiaporama();
    diaporamaTimer = setInterval(function () {
      activeIndex = (activeIndex + 1) % activePhotos.length;
      renderLightbox();
    }, 3500);
    reglerBoutonDiaporama(true);
  }

  function openLightbox(photos, index) {
    if (!lightbox) return;
    activePhotos = photos;
    activeIndex = index;
    renderLightbox();
    lightbox.classList.add("is-open");
    document.body.style.overflow = "hidden";
    lightbox.querySelector(".lightbox-close").focus();
  }

  function closeLightbox() {
    if (!lightbox) return;
    stopDiaporama();
    lightbox.classList.remove("is-open");
    document.body.style.overflow = "";
  }

  function renderLightbox() {
    const photo = activePhotos[activeIndex];
    poserPhotoAgrandie(lightbox.querySelector(".lightbox-frame"), photo);
    lightbox.querySelector(".lightbox-title").textContent = photo.masquerTitreAgrandi ? "" : photo.titre;
    lightbox.querySelector(".lightbox-meta").textContent =
      [photo.masquerNomAgrandi ? null : photo.membreNom, photo.theme].filter(Boolean).join(" — ");
    const descriptionEl = lightbox.querySelector(".lightbox-description");
    if (descriptionEl) {
      descriptionEl.textContent = photo.description || "";
      descriptionEl.hidden = !photo.description;
    }
  }

  if (lightbox) {
    lightbox.querySelector(".lightbox-close").addEventListener("click", closeLightbox);
    const boutonDiaporama = lightbox.querySelector(".lightbox-diaporama");
    if (boutonDiaporama) {
      boutonDiaporama.addEventListener("click", function () {
        if (diaporamaTimer) {
          stopDiaporama();
        } else {
          startDiaporama();
        }
      });
    }
    lightbox.querySelector(".lightbox-prev").addEventListener("click", function () {
      stopDiaporama();
      activeIndex = (activeIndex - 1 + activePhotos.length) % activePhotos.length;
      renderLightbox();
    });
    lightbox.querySelector(".lightbox-next").addEventListener("click", function () {
      stopDiaporama();
      activeIndex = (activeIndex + 1) % activePhotos.length;
      renderLightbox();
    });
    lightbox.addEventListener("click", function (e) {
      if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener("keydown", function (e) {
      if (!lightbox.classList.contains("is-open")) return;
      if (e.key === "Escape") closeLightbox();
      if (e.key === "ArrowLeft") lightbox.querySelector(".lightbox-prev").click();
      if (e.key === "ArrowRight") lightbox.querySelector(".lightbox-next").click();
    });
  }

  function buildPhotoCard(photo, hue, membreNom, index, photosForLightbox, masquerTitreVignette) {
    const card = document.createElement("button");
    card.type = "button";
    card.className = "photo-card";
    card.setAttribute("aria-label", "Voir la photo : " + photo.titre);
    card.innerHTML =
      '<span class="photo-frame" style="background:' +
      photoBackground(photo) +
      '"></span>' +
      '<span class="photo-caption">' +
      (masquerTitreVignette ? "" : '<span class="title">' + echapperHtml(photo.titre) + "</span>") +
      '<span class="meta">' + echapperHtml([membreNom, photo.theme].filter(Boolean).join(" · ")) + "</span>" +
      "</span>";
    card.addEventListener("click", function () {
      const idx = photosForLightbox.findIndex(function (p) {
        return p.titre === photo.titre && p.membreNom === membreNom;
      });
      openLightbox(photosForLightbox, idx);
    });
    return card;
  }

  /* ---------- Page « Expo 2026 » : dossiers par adhérent ----------
     Choix explicite de l'utilisateur, 24/08/2026 : les photos Google Drive
     ne sont plus une section de galerie.html, mais leur propre page. Un
     dossier Drive = un adhérent ; sa carte porte la miniature de sa
     première photo. Au clic, la grille des dossiers laisse place à celle de
     ses photos (bascule de vue, pas de navigation : rien à recharger,
     l'appel à infos-expo-2026.php n'a lieu qu'une fois). Au clic sur une
     photo, la lightbox partagée s'ouvre, avec son bouton de diaporama.
     infos-expo-2026.php renvoie [] si la clé API/le dossier ne sont pas
     encore réglés dans config.local.php, ou en cas d'échec de l'appel
     (hors ligne, préversion GitHub Pages qui ne peut pas exécuter PHP) :
     la page affiche alors son message « aucune photo », jamais d'erreur. */
  (function () {
    const page = document.querySelector("[data-expo-page]");
    if (!page) return;

    const grilleDossiers = page.querySelector("[data-expo-dossiers]");
    const vuePhotos = page.querySelector("[data-expo-vue-photos]");
    const grillePhotos = page.querySelector("[data-expo-photos]");
    const titrePhotos = page.querySelector("[data-expo-titre-adherent]");
    const retour = page.querySelector("[data-expo-retour]");
    const vide = page.querySelector("[data-expo-vide]");
    const chargement = page.querySelector("[data-expo-chargement]");
    if (!grilleDossiers || !vuePhotos || !grillePhotos) return;

    function montrerDossiers() {
      vuePhotos.hidden = true;
      grilleDossiers.hidden = false;
      grillePhotos.innerHTML = "";
    }

    function ouvrirDossier(adherent) {
      grillePhotos.innerHTML = "";
      if (titrePhotos) titrePhotos.textContent = adherent.nom;

      // Mêmes champs que les autres appelants de buildPhotoCard : le nom de
      // l'adhérent tient lieu de « membre », il n'y a pas de thème ici.
      // masquerNomAgrandi : le nom du dossier reste sur la vignette (via
      // buildPhotoCard, indépendant de ce champ) mais ne doit pas
      // réapparaître dans la légende de la photo agrandie (choix explicite
      // de l'utilisateur, 25/08/2026) — seul renderLightbox() le lit.
      // masquerTitreAgrandi : même principe pour le titre (nom de fichier
      // brut, ex. « BRT-01_34X50_ »), également illisible une fois agrandi
      // (choix explicite de l'utilisateur, 25/08/2026).
      const photos = adherent.photos.map(function (p) {
        return {
          titre: p.titre, theme: "", membreNom: adherent.nom,
          image: p.image,               // 1000px, pour la vignette
          imageGrande: p.image_grande,  // 1920px, pour l'agrandissement
          hue: 0, index: 0,
          masquerNomAgrandi: true,
          masquerTitreAgrandi: true,
        };
      });
      // Le titre de chaque photo reprend le nom de son fichier tel
      // qu'envoyé par l'adhérent (ex. « mgl_1_1_62x33 ») : illisible sur
      // une vignette, donc masqué ici — seul le nom du dossier (déjà
      // affiché via membreNom) reste visible. Le titre garde son rôle
      // ailleurs (Galerie du Club, accueil), où il est saisi à la main.
      photos.forEach(function (photo) {
        grillePhotos.appendChild(buildPhotoCard(photo, 0, adherent.nom, 0, photos, true));
      });

      grilleDossiers.hidden = true;
      vuePhotos.hidden = false;
      vuePhotos.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    /* Carte de dossier : même habillage que .photo-card (vignette de la
       première photo, légende en incrustation), mais son clic ouvre le
       dossier au lieu de la lightbox. */
    function construireCarteDossier(adherent) {
      const carte = document.createElement("button");
      carte.type = "button";
      carte.className = "photo-card";
      carte.setAttribute("aria-label", "Voir les photos de " + adherent.nom);
      const nombre = adherent.photos.length;
      carte.innerHTML =
        '<span class="photo-frame" style="background:' +
        photoBackground({ image: adherent.vignette, hue: 0, index: 0 }) +
        '"></span>' +
        '<span class="photo-caption">' +
        '<span class="title">' + echapperHtml(adherent.nom) + "</span>" +
        '<span class="meta">' + nombre + (nombre > 1 ? " photos" : " photo") + "</span>" +
        "</span>";
      carte.addEventListener("click", function () {
        ouvrirDossier(adherent);
      });
      return carte;
    }

    if (retour) retour.addEventListener("click", montrerDossiers);

    fetch("infos-expo-2026.php")
      .then(function (reponse) { return reponse.ok ? reponse.json() : Promise.reject(); })
      .then(function (adherents) {
        if (chargement) chargement.hidden = true;
        if (!Array.isArray(adherents) || !adherents.length) {
          if (vide) vide.hidden = false;
          return;
        }
        adherents.forEach(function (adherent) {
          if (!adherent || !Array.isArray(adherent.photos) || !adherent.photos.length) return;
          grilleDossiers.appendChild(construireCarteDossier(adherent));
        });
      })
      .catch(function () {
        if (chargement) chargement.hidden = true;
        if (vide) vide.hidden = false;
      });
  })();

  /* ---------- Page d'accueil : bandeau « prochaine sortie / réunion » ----------
     Dépliant natif (<details>, voir css/style.css), rempli depuis
     infos-prochaine-sortie.php ; reste masqué (hidden en dur dans
     index.html) tant qu'aucune sortie à venir n'est disponible ou que
     l'appel échoue (hors ligne, préversion GitHub Pages qui ne peut pas
     exécuter PHP). */
  (function () {
    const bandeau = document.querySelector("[data-prochaine-sortie]");
    if (!bandeau) return;

    fetch("infos-prochaine-sortie.php")
      .then(function (reponse) { return reponse.ok ? reponse.json() : Promise.reject(); })
      .then(function (sortie) {
        if (!sortie || !sortie.titre) return;

        const debut = new Date(sortie.debut_iso);
        const dateLisible = isNaN(debut.getTime())
          ? ""
          : debut.toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" }) +
            " à " +
            debut.toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit" });

        const prefixe = sortie.categorie === "Réunion" ? "Prochaine réunion : " : "Prochaine sortie : ";
        bandeau.querySelector("[data-prochaine-sortie-resume]").innerHTML =
          prefixe + "<strong>" + echapperHtml(sortie.titre) + "</strong>" +
          (dateLisible ? " — " + echapperHtml(dateLisible) : "");

        let detail = "";
        if (sortie.lieu) detail += "<p>" + echapperHtml(sortie.lieu) + "</p>";
        if (sortie.description) detail += "<p>" + echapperHtml(sortie.description) + "</p>";
        detail += '<p><a href="espace/sorties-a-venir.php">Voir toutes les sorties à venir →</a></p>';
        bandeau.querySelector("[data-prochaine-sortie-detail]").innerHTML = detail;

        bandeau.hidden = false;
      })
      .catch(function () {});
  })();

  /* ---------- Page d'accueil : sélection de photos ----------
     Reprend les photos les plus récentes de la Galerie du Club (même point
     d'accès que la page Galerie, voir plus bas) — il n'y a plus de photos de
     démonstration depuis leur retrait le 20/08/2026. La section reste
     masquée (attribut `hidden` posé en dur dans index.html) tant qu'aucune
     photo réelle n'est encore disponible, plutôt que d'afficher une grille
     vide sous un titre. */
  const highlightSection = document.querySelector("[data-highlights-section]");
  const highlightGrid = document.querySelector("[data-highlights]");
  if (highlightSection && highlightGrid) {
    fetch("infos-galerie-club.php")
      .then(function (reponse) { return reponse.ok ? reponse.json() : Promise.reject(); })
      .then(function (donnees) {
        const photosClub = Array.isArray(donnees.photos) ? donnees.photos : [];
        if (!photosClub.length) return;

        // Déjà triées des plus récentes aux plus anciennes par l'API.
        const picked = photosClub.slice(0, 8).map(function (p) {
          return {
            titre: p.titre,
            theme: p.categorie,
            membreNom: p.auteur,
            image: p.image,
            hue: 0,
            index: 0,
          };
        });
        picked.forEach(function (photo) {
          highlightGrid.appendChild(
            buildPhotoCard(photo, photo.hue, photo.membreNom, photo.index, picked)
          );
        });
        highlightSection.hidden = false;
      })
      .catch(function () {});
  }

  /* ---------- Page adhérents ---------- */
  const membersGrid = document.querySelector("[data-members]");
  if (membersGrid) {
    CLUB_DATA.membres.forEach(function (m) {
      const card = document.createElement("a");
      card.className = "member-card";
      card.href = "galerie.html";
      card.innerHTML =
        '<span class="member-cover" style="display:block;background:' +
        photoGradient(m.hue, 2) +
        '"></span>' +
        '<span class="member-body">' +
        '<span class="member-avatar" style="background:' + avatarGradient(m.hue) + '">' +
        initials(m.nom) +
        "</span>" +
        "<h3>" + m.nom + "</h3>" +
        '<span class="member-role">' + m.role + "</span>" +
        '<span class="member-bio">' + m.bio + "</span>" +
        '<span class="member-link">Voir la galerie</span>' +
        "</span>";
      membersGrid.appendChild(card);
    });
  }

  /* ---------- Page galerie : toutes les photos, filtrées par thème ---------- */
  const galleryRoot = document.querySelector("[data-gallery-page]");
  if (galleryRoot) {
    const pool = [];
    CLUB_DATA.membres.forEach(function (m) {
      m.photos.forEach(function (p, i) {
        pool.push(Object.assign({}, p, { hue: m.hue, membreNom: m.nom, index: i }));
      });
    });

    const grid = document.querySelector("[data-photos]");
    const emptyMessage = document.querySelector("[data-gallery-empty]");
    const filtersRoot = document.querySelector("[data-theme-filters]");
    let currentTheme = "";

    function photosForCurrentTheme() {
      return currentTheme ? pool.filter(function (p) { return p.theme === currentTheme; }) : pool;
    }

    function renderGrid() {
      const filtered = photosForCurrentTheme();
      grid.innerHTML = "";
      filtered.forEach(function (photo) {
        grid.appendChild(buildPhotoCard(photo, photo.hue, photo.membreNom, photo.index, filtered));
      });
      if (emptyMessage) emptyMessage.hidden = filtered.length > 0;
    }

    const toutesLesPhotos = "Toutes";
    let themesConnus = [];

    function addThemeFilter(theme) {
      if (themesConnus.indexOf(theme) !== -1) return;
      themesConnus.push(theme);

      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "theme-filter";
      btn.textContent = theme;
      if (theme === toutesLesPhotos) btn.classList.add("is-active");
      btn.addEventListener("click", function () {
        filtersRoot.querySelectorAll(".theme-filter").forEach(function (b) {
          b.classList.remove("is-active");
        });
        btn.classList.add("is-active");
        currentTheme = theme === toutesLesPhotos ? "" : theme;
        renderGrid();
      });
      filtersRoot.appendChild(btn);
    }

    // Reconstruit les pastilles de filtre à partir de zéro — utilisé une
    // fois la vraie liste de catégories (celle de Réglages du site) reçue,
    // pour ne pas garder une pastille renommée/supprimée depuis, ni en
    // manquer une nouvelle (piège rencontré le 27/08/2026, voir plus bas).
    function rebuildThemeFilters(themes) {
      filtersRoot.innerHTML = "";
      themesConnus = [];
      currentTheme = "";
      [toutesLesPhotos].concat(themes).forEach(addThemeFilter);
    }

    rebuildThemeFilters(CLUB_DATA.themes);
    renderGrid();

    /* ---------- Vraies photos de la Galerie du Club (espace/galerie-club.php) ----------
       Ajoutées par-dessus les photos de démonstration une fois chargées : la
       grille et les filtres s'affichent donc immédiatement avec la démo, puis
       se complètent sans à-coup. Silencieusement ignoré si l'appel échoue
       (hors ligne, ou préversion GitHub Pages, qui ne peut pas exécuter PHP) —
       comme pour infos-club.php.

       Les pastilles de filtre sont reconstruites à partir de la vraie liste
       de catégories renvoyée par l'API (`donnees.categories`, la table
       `categories_galerie`), plutôt que simplement complétées par-dessus
       CLUB_DATA.themes : sinon une catégorie renommée ou supprimée depuis
       Réglages du site restait affichée indéfiniment sur cette page, alors
       que Galerie du Club (qui lit la table en direct) montrait déjà la
       bonne liste — piège signalé par l'utilisateur le 27/08/2026. */
    fetch("infos-galerie-club.php")
      .then(function (reponse) { return reponse.ok ? reponse.json() : Promise.reject(); })
      .then(function (donnees) {
        const photosClub = Array.isArray(donnees.photos) ? donnees.photos : [];

        photosClub.forEach(function (p) {
          pool.push({
            titre: p.titre,
            theme: p.categorie,
            description: p.description,
            membreNom: p.auteur,
            image: p.image,
            hue: 0,
            index: 0,
          });
        });

        if (Array.isArray(donnees.categories) && donnees.categories.length) {
          rebuildThemeFilters(donnees.categories);
        } else {
          photosClub.forEach(function (p) { addThemeFilter(p.categorie); });
        }
        renderGrid();
      })
      .catch(function () {});

    const diaporamaBtn = document.querySelector("[data-start-diaporama]");
    if (diaporamaBtn) {
      diaporamaBtn.addEventListener("click", function () {
        const filtered = photosForCurrentTheme();
        if (!filtered.length) return;
        openLightbox(filtered, 0);
        startDiaporama();
      });
    }
  }

  /* ---------- Boutons « page précédente » / « section précédente » /
     « retour en haut » / « aller en bas » ----------
     Génériques : posés sur toute page ayant au moins une <section>, qu'elle
     soit publique ou dans l'espace adhérents (page.php partage ce même
     script) — jamais besoin de les ajouter à la main sur une page neuve.
     « Page précédente » (choix explicite de l'utilisateur, 23/08/2026,
     history.back() — comme .lien-retour sur connexion.php/inscription.php,
     mais générique et flottant), « retour en haut » et « aller en bas »
     (25/08/2026, choix explicite de l'utilisateur — pendant de « retour en
     haut ») apparaissent dès qu'il y a une section ; « section précédente »
     n'a de sens qu'à partir de deux sections, comme sur
     `espace/documents.php`, une seule longue section mais qui a besoin de
     pouvoir remonter en haut. Toujours visibles dès le chargement de la
     page (choix explicite de l'utilisateur, 23/08/2026 — auparavant
     masqués tant qu'on n'avait pas défilé 60% de l'écran, ce qui les
     rendait difficiles à trouver). */
  (function () {
    const sections = Array.from(document.querySelectorAll("main section"));
    if (!sections.length) return;

    const nav = document.createElement("div");
    nav.className = "retour-nav";
    nav.innerHTML =
      '<button type="button" class="retour-bouton retour-precedente" aria-label="Revenir à la page précédente">' +
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>' +
      "</button>" +
      (sections.length >= 2
        ? '<button type="button" class="retour-bouton retour-section" aria-label="Remonter à la section précédente">' +
          '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"></polyline></svg>' +
          "</button>"
        : "") +
      '<button type="button" class="retour-bouton retour-haut" aria-label="Retour en haut de la page">' +
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 11 12 5 6 11"></polyline><polyline points="18 18 12 12 6 18"></polyline></svg>' +
      "</button>" +
      '<button type="button" class="retour-bouton retour-bas" aria-label="Aller tout en bas de la page">' +
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 13 12 19 18 13"></polyline><polyline points="6 6 12 12 18 6"></polyline></svg>' +
      "</button>";
    document.body.appendChild(nav);

    nav.querySelector(".retour-precedente").addEventListener("click", function () {
      history.back();
    });

    // Index de la section dont le haut est déjà dépassé (ou en vue) — la
    // dernière dont offsetTop est sous le décalage de l'en-tête collant.
    function sectionActuelle() {
      const seuil = window.scrollY + 90;
      let idx = 0;
      sections.forEach(function (s, i) {
        if (s.offsetTop <= seuil) idx = i;
      });
      return idx;
    }

    const boutonSection = nav.querySelector(".retour-section");
    if (boutonSection) {
      boutonSection.addEventListener("click", function () {
        const idx = sectionActuelle();
        if (idx > 0) {
          sections[idx - 1].scrollIntoView({ behavior: "smooth", block: "start" });
        } else {
          window.scrollTo({ top: 0, behavior: "smooth" });
        }
      });
    }

    nav.querySelector(".retour-haut").addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    nav.querySelector(".retour-bas").addEventListener("click", function () {
      window.scrollTo({ top: document.body.scrollHeight, behavior: "smooth" });
    });
  })();

  /* ---------- Agrandissement + diaporama (Galerie privée, Galerie du Club) ----------
     Générique : toute page portant des cartes .photo-card[data-titre] (voir
     espace/inc/photo-carte.php) et le bloc [data-lightbox] correspondant
     réutilise désormais le système partagé avec les pages publiques
     (openLightbox/renderLightbox/startDiaporama, définis plus haut) plutôt
     qu'une implémentation séparée — Galerie du Club (26/08/2026) gagne ainsi
     le bouton Diaporama et les pastilles de filtre par thème sans dupliquer
     cette logique ; galerie.php n'affiche ni l'un ni l'autre (pas de bouton
     [data-start-diaporama] ni de [data-filtres-galerie] dans son HTML) mais
     profite quand même du même agrandissement au clic. Seules les cartes
     VISIBLES comptent (offsetParent non nul) : une pastille de filtre
     masque des groupes entiers de cartes (.groupe-galerie[hidden]), qui ne
     doivent alors compter ni dans la navigation précédente/suivante ni dans
     le diaporama. Le texte complet (description comprise) vient des
     attributs data-*, jamais tronqué même si la vignette l'est visuellement
     en CSS. */
  (function () {
    const boite = document.querySelector("[data-lightbox]");
    if (!boite || !document.querySelector(".photo-card[data-titre]")) return;

    // Pas de membreNom/theme séparés ici (voir photo-carte.php) : le texte
    // affiché est déjà la légende complète prête à l'emploi — la caser dans
    // membreNom seul suffit à renderLightbox() (theme resté vide n'ajoute
    // rien à la jointure).
    function carteVersPhoto(carte) {
      return {
        titre: carte.dataset.titre || "",
        description: carte.dataset.description || "",
        membreNom: carte.dataset.meta || "",
        theme: null,
        image: carte.dataset.image || "",
      };
    }

    function cartesVisibles() {
      return Array.from(document.querySelectorAll(".photo-card[data-titre]")).filter(function (carte) {
        return carte.offsetParent !== null;
      });
    }

    document.querySelectorAll(".photo-card[data-titre]").forEach(function (carte) {
      carte.addEventListener("click", function (e) {
        // Un clic sur le formulaire Supprimer (en incrustation sur la carte)
        // ne doit jamais ouvrir l'agrandissement.
        if (e.target.closest("form")) return;
        const visibles = cartesVisibles();
        const index = visibles.indexOf(carte);
        if (index === -1) return;
        openLightbox(visibles.map(carteVersPhoto), index);
      });
    });

    const diaporamaBtn = document.querySelector("[data-start-diaporama]");
    if (diaporamaBtn) {
      diaporamaBtn.addEventListener("click", function () {
        const visibles = cartesVisibles();
        if (!visibles.length) return;
        openLightbox(visibles.map(carteVersPhoto), 0);
        startDiaporama();
      });
    }

    // Filtre par thème (Galerie du Club) : les photos sont déjà toutes
    // rendues côté serveur, groupées par catégorie — une pastille ne fait
    // qu'afficher/masquer les groupes déjà présents dans le DOM, sans
    // nouvel appel réseau (contrairement au filtre de la page publique).
    const zoneFiltres = document.querySelector("[data-filtres-galerie]");
    if (zoneFiltres) {
      const groupes   = Array.from(document.querySelectorAll(".groupe-galerie[data-categorie-id]"));
      const pastilles = Array.from(zoneFiltres.querySelectorAll(".theme-filter"));
      pastilles.forEach(function (pastille) {
        pastille.addEventListener("click", function () {
          pastilles.forEach(function (p) { p.classList.remove("is-active"); });
          pastille.classList.add("is-active");
          const cible = pastille.dataset.categorie || "";
          groupes.forEach(function (groupe) {
            groupe.hidden = cible !== "" && groupe.dataset.categorieId !== cible;
          });
        });
      });
    }
  })();

})();

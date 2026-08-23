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
          : "";
        const pagesAdmin = donnees.administrateur
          ? '<li><a href="espace/adherents.php">Adhérents</a></li>' +
            '<li><a href="espace/parametres.php">Réglages du site</a></li>'
          : "";
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

  /* ---------- Lightbox ---------- */
  const lightbox = document.querySelector("[data-lightbox]");
  let activePhotos = [];
  let activeIndex = 0;
  let diaporamaTimer = null;

  function stopDiaporama() {
    if (diaporamaTimer) {
      clearInterval(diaporamaTimer);
      diaporamaTimer = null;
    }
  }

  function startDiaporama() {
    stopDiaporama();
    diaporamaTimer = setInterval(function () {
      activeIndex = (activeIndex + 1) % activePhotos.length;
      renderLightbox();
    }, 3500);
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
    const frame = lightbox.querySelector(".lightbox-frame");
    frame.style.background = photoBackground(photo);
    lightbox.querySelector(".lightbox-title").textContent = photo.titre;
    lightbox.querySelector(".lightbox-meta").textContent =
      photo.membreNom + " — " + photo.theme;
    const descriptionEl = lightbox.querySelector(".lightbox-description");
    if (descriptionEl) {
      descriptionEl.textContent = photo.description || "";
      descriptionEl.hidden = !photo.description;
    }
  }

  if (lightbox) {
    lightbox.querySelector(".lightbox-close").addEventListener("click", closeLightbox);
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

  function buildPhotoCard(photo, hue, membreNom, index, photosForLightbox) {
    const card = document.createElement("button");
    card.type = "button";
    card.className = "photo-card";
    card.setAttribute("aria-label", "Voir la photo : " + photo.titre);
    card.innerHTML =
      '<span class="photo-frame" style="background:' +
      photoBackground(photo) +
      '"></span>' +
      '<span class="photo-caption">' +
      '<span class="title">' + echapperHtml(photo.titre) + "</span>" +
      '<span class="meta">' + echapperHtml(membreNom) + " · " + echapperHtml(photo.theme) + "</span>" +
      "</span>";
    card.addEventListener("click", function () {
      const idx = photosForLightbox.findIndex(function (p) {
        return p.titre === photo.titre && p.membreNom === membreNom;
      });
      openLightbox(photosForLightbox, idx);
    });
    return card;
  }

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
      .then(function (photosClub) {
        if (!Array.isArray(photosClub) || !photosClub.length) return;

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
    const themesConnus = [];

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

    [toutesLesPhotos].concat(CLUB_DATA.themes).forEach(addThemeFilter);
    renderGrid();

    /* ---------- Vraies photos de la Galerie du Club (espace/galerie-club.php) ----------
       Ajoutées par-dessus les photos de démonstration une fois chargées : la
       grille et les filtres s'affichent donc immédiatement avec la démo, puis
       se complètent sans à-coup. Silencieusement ignoré si l'appel échoue
       (hors ligne, ou préversion GitHub Pages, qui ne peut pas exécuter PHP) —
       comme pour infos-club.php. */
    fetch("infos-galerie-club.php")
      .then(function (reponse) { return reponse.ok ? reponse.json() : Promise.reject(); })
      .then(function (photosClub) {
        if (!Array.isArray(photosClub) || !photosClub.length) return;

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
          addThemeFilter(p.categorie);
        });
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

  /* ---------- Boutons « section précédente » / « retour en haut » ----------
     Génériques : posés sur toute page ayant au moins deux <section>, qu'elle
     soit publique ou dans l'espace adhérents (page.php partage ce même
     script) — jamais besoin de les ajouter à la main sur une page neuve. */
  (function () {
    const sections = Array.from(document.querySelectorAll("main section"));
    if (sections.length < 2) return;

    const nav = document.createElement("div");
    nav.className = "retour-nav";
    nav.innerHTML =
      '<button type="button" class="retour-bouton retour-section" aria-label="Remonter à la section précédente">' +
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"></polyline></svg>' +
      "</button>" +
      '<button type="button" class="retour-bouton retour-haut" aria-label="Retour en haut de la page">' +
      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 11 12 5 6 11"></polyline><polyline points="18 18 12 12 6 18"></polyline></svg>' +
      "</button>";
    document.body.appendChild(nav);

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

    nav.querySelector(".retour-section").addEventListener("click", function () {
      const idx = sectionActuelle();
      if (idx > 0) {
        sections[idx - 1].scrollIntoView({ behavior: "smooth", block: "start" });
      } else {
        window.scrollTo({ top: 0, behavior: "smooth" });
      }
    });

    nav.querySelector(".retour-haut").addEventListener("click", function () {
      window.scrollTo({ top: 0, behavior: "smooth" });
    });

    function actualiserVisibilite() {
      nav.classList.toggle("is-visible", window.scrollY > window.innerHeight * 0.6);
    }
    window.addEventListener("scroll", actualiserVisibilite, { passive: true });
    actualiserVisibilite();
  })();

  /* ---------- Agrandissement des photos (Galerie privée, Galerie du Club) ----------
     Générique : toute page portant des cartes .photo-card[data-titre] (voir
     espace/inc/photo-carte.php) et le bloc [data-lightbox] correspondant
     obtient l'agrandissement au clic, avec navigation précédente/suivante —
     la page publique Galerie a son propre système plus riche (diaporama) et
     n'est pas concernée, ses cartes ne portent pas ces attributs. Le texte
     complet (description comprise) vient des attributs data-*, jamais
     tronqué même si la vignette l'est visuellement en CSS. */
  (function () {
    const cartes = Array.from(document.querySelectorAll(".photo-card[data-titre]"));
    const boite = document.querySelector("[data-lightbox]");
    if (!cartes.length || !boite) return;

    const photos = cartes.map(function (carte) {
      return {
        titre: carte.dataset.titre || "",
        description: carte.dataset.description || "",
        meta: carte.dataset.meta || "",
        image: carte.dataset.image || "",
      };
    });

    let indexActif = 0;

    function afficher() {
      const photo = photos[indexActif];
      boite.querySelector(".lightbox-frame").style.background =
        "center / cover no-repeat url('" + photo.image + "')";
      boite.querySelector(".lightbox-title").textContent = photo.titre;
      boite.querySelector(".lightbox-meta").textContent = photo.meta;
      const descriptionEl = boite.querySelector(".lightbox-description");
      if (descriptionEl) {
        descriptionEl.textContent = photo.description;
        descriptionEl.hidden = !photo.description;
      }
    }

    function ouvrir(index) {
      indexActif = index;
      afficher();
      boite.classList.add("is-open");
      document.body.style.overflow = "hidden";
      boite.querySelector(".lightbox-close").focus();
    }

    function fermer() {
      boite.classList.remove("is-open");
      document.body.style.overflow = "";
    }

    cartes.forEach(function (carte, index) {
      carte.addEventListener("click", function (e) {
        // Un clic sur le formulaire Supprimer (en incrustation sur la carte)
        // ne doit jamais ouvrir l'agrandissement.
        if (e.target.closest("form")) return;
        ouvrir(index);
      });
    });

    boite.querySelector(".lightbox-close").addEventListener("click", fermer);
    boite.querySelector(".lightbox-prev").addEventListener("click", function () {
      indexActif = (indexActif - 1 + photos.length) % photos.length;
      afficher();
    });
    boite.querySelector(".lightbox-next").addEventListener("click", function () {
      indexActif = (indexActif + 1) % photos.length;
      afficher();
    });
    boite.addEventListener("click", function (e) {
      if (e.target === boite) fermer();
    });
    document.addEventListener("keydown", function (e) {
      if (!boite.classList.contains("is-open")) return;
      if (e.key === "Escape") fermer();
      if (e.key === "ArrowLeft") boite.querySelector(".lightbox-prev").click();
      if (e.key === "ArrowRight") boite.querySelector(".lightbox-next").click();
    });
  })();

})();

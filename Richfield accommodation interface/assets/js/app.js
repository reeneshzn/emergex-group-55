/* ==========================================================================
   Richfield Student Accommodation — client behaviour
   Shortlist, payment calculator, Leaflet map, scroll reveals, mobile nav.
   Plain ES2019. No build step, no framework, no dependencies except Leaflet.
   ========================================================================== */
(function () {
  "use strict";

  /* Data injected by PHP as window.RF = { listings, campuses, ... } */
  var RF = window.RF || {};
  var LISTINGS = RF.listings || {};
  var CAMPUSES = RF.campuses || {};

  var SHORTLIST_LIMIT = 4;
  var STORAGE_KEY = "rf-shortlist";

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function formatZar(amount) {
    return "R " + Math.round(amount).toLocaleString("en-ZA").replace(/,/g, " ");
  }

  /* ======================================================================
     Shortlist — localStorage backed, shared across pages

     [DB-7] DATABASE INTEGRATION POINT — OPTIONAL, ONLY IF THERE IS LOGIN
     ----------------------------------------------------------------------
     Saved rooms live in the browser only. They survive a reload but not a
     different device, and they are invisible to anyone on the admin side.

     Only worth moving to the database if students get accounts. Without a
     login there is no user to attach a shortlist to, and localStorage is
     genuinely the better answer — no rows, no cleanup, no personal data.

     If you do add it, the two functions to change are readShortlist() and
     writeShortlist() below. Everything else calls through them. Sketch:

       - readShortlist()  -> fetch the user's saved slugs on page load
       - writeShortlist() -> POST the change, and keep writing to
                             localStorage as well so the UI stays instant
                             and still works if the request fails

     Keep the 4-item cap (SHORTLIST_LIMIT) enforced server-side too — a
     client-side limit is a UI convenience, not a rule.
     ====================================================================== */

  function readShortlist() {
    try {
      var raw = window.localStorage.getItem(STORAGE_KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.filter(function (s) { return typeof s === "string"; }) : [];
    } catch (err) {
      // Private mode, blocked storage, or corrupt JSON. Session still works.
      return [];
    }
  }

  function writeShortlist(ids) {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(ids));
    } catch (err) { /* storage unavailable — in-memory only */ }
    shortlist = ids;
    renderShortlist();
  }

  var shortlist = readShortlist();

  function toggleShortlist(slug) {
    var i = shortlist.indexOf(slug);
    if (i > -1) {
      writeShortlist(shortlist.filter(function (s) { return s !== slug; }));
    } else if (shortlist.length < SHORTLIST_LIMIT) {
      writeShortlist(shortlist.concat([slug]));
    }
  }

  function renderShortlist() {
    var count = shortlist.length;

    var countEl = $("#shortlistCount");
    if (countEl) countEl.textContent = String(count);

    var trigger = $("#shortlistOpen");
    if (trigger) trigger.setAttribute("aria-label", "Open shortlist, " + count + " saved");

    var drawerCount = $("#drawerCount");
    if (drawerCount) drawerCount.textContent = String(count);

    /* Heart buttons on the current page */
    $$("[data-heart]").forEach(function (btn) {
      var slug = btn.getAttribute("data-heart");
      var saved = shortlist.indexOf(slug) > -1;
      var name = btn.getAttribute("data-name") || "this room";
      btn.setAttribute("aria-pressed", saved ? "true" : "false");
      btn.setAttribute("aria-label", (saved ? "Remove " + name + " from shortlist" : "Save " + name + " to shortlist"));
      var full = !saved && count >= SHORTLIST_LIMIT;
      btn.disabled = full;
      btn.title = full ? "Shortlist is full (4 maximum)" : "";
    });

    renderDrawer();
  }

  function renderDrawer() {
    var body = $("#drawerBody");
    var foot = $("#drawerFoot");
    if (!body) return;

    if (shortlist.length === 0) {
      body.innerHTML =
        '<div class="drawer-empty">' +
        '<svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21l7.7-7.7 1.1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>' +
        "<p>Nothing saved yet. Tap the heart on any room to compare it here.</p>" +
        '<a class="btn btn--outline btn--sm" href="search.php">Browse rooms</a>' +
        "</div>";
      if (foot) foot.hidden = true;
      return;
    }

    var rows = shortlist.map(function (slug) {
      var l = LISTINGS[slug];
      if (!l) return "";
      return (
        '<div class="drawer-row">' +
        '<div class="drawer-row__thumb">' + (l.thumb || "") + "</div>" +
        '<div class="drawer-row__body">' +
        '<a class="drawer-row__name" href="listing.php?slug=' + encodeURIComponent(slug) + '">' + l.name + "</a>" +
        '<span class="drawer-row__meta">' + l.area + " · " + l.room + "</span>" +
        '<p class="drawer-row__price">' + formatZar(l.rent) + " <small>/ month</small></p>" +
        '<p class="drawer-row__meta">' + l.walk + " min walk to campus</p>" +
        "</div>" +
        '<button type="button" class="btn btn--ghost drawer-row__remove" data-remove="' + slug + '" aria-label="Remove ' + l.name + ' from shortlist">' +
        '<svg viewBox="0 0 24 24" class="icon" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>' +
        "</button>" +
        "</div>"
      );
    }).join("");

    var compare = "";
    if (shortlist.length > 1) {
      var saved = shortlist.map(function (s) { return LISTINGS[s]; }).filter(Boolean);
      var cheapest = saved.reduce(function (a, b) { return b.rent < a.rent ? b : a; });
      var closest = saved.reduce(function (a, b) { return b.walk < a.walk ? b : a; });
      var most = saved.reduce(function (a, b) { return b.amenities > a.amenities ? b : a; });
      compare =
        '<div class="drawer-compare">' +
        '<p class="rf-eyebrow" style="color:var(--rf-ink-muted)">At a glance</p><dl>' +
        "<div><dt>Lowest rent</dt><dd>" + cheapest.name + "</dd></div>" +
        "<div><dt>Closest to campus</dt><dd>" + closest.name + "</dd></div>" +
        "<div><dt>Most included</dt><dd>" + most.name + "</dd></div>" +
        "</dl></div>";
    }

    body.innerHTML = rows + compare;

    if (foot) {
      foot.hidden = false;
      var bookAll = $("#drawerBookAll");
      if (bookAll) {
        bookAll.href = "apply.php?listings=" + encodeURIComponent(shortlist.join(","));
        bookAll.textContent = "Book viewings for all " + shortlist.length;
      }
    }
  }

  /* Delegated clicks: hearts anywhere, remove buttons in the drawer */
  document.addEventListener("click", function (ev) {
    var heart = ev.target.closest ? ev.target.closest("[data-heart]") : null;
    if (heart) {
      ev.preventDefault();
      ev.stopPropagation();
      toggleShortlist(heart.getAttribute("data-heart"));
      return;
    }
    var remove = ev.target.closest ? ev.target.closest("[data-remove]") : null;
    if (remove) {
      toggleShortlist(remove.getAttribute("data-remove"));
    }
  });

  /* Drawer open/close */
  var drawer = $("#shortlistDrawer");
  var backdrop = $("#drawerBackdrop");

  function openDrawer() {
    if (!drawer) return;
    drawer.hidden = false;
    drawer.setAttribute("aria-hidden", "false");
    if (backdrop) backdrop.hidden = false;
    document.body.style.overflow = "hidden";
    var close = $("#shortlistClose");
    if (close) close.focus();
  }

  function closeDrawer() {
    if (!drawer) return;
    drawer.hidden = true;
    drawer.setAttribute("aria-hidden", "true");
    if (backdrop) backdrop.hidden = true;
    document.body.style.overflow = "";
    var trigger = $("#shortlistOpen");
    if (trigger) trigger.focus();
  }

  var openBtn = $("#shortlistOpen");
  if (openBtn) openBtn.addEventListener("click", openDrawer);
  var closeBtn = $("#shortlistClose");
  if (closeBtn) closeBtn.addEventListener("click", closeDrawer);
  if (backdrop) backdrop.addEventListener("click", closeDrawer);
  document.addEventListener("keydown", function (ev) {
    if (ev.key === "Escape" && drawer && !drawer.hidden) closeDrawer();
  });

  var clearBtn = $("#drawerClear");
  if (clearBtn) clearBtn.addEventListener("click", function () { writeShortlist([]); });

  /* ======================================================================
     Sticky header — solid once scrolled past the hero
     ====================================================================== */

  var header = $("#siteHeader");
  if (header && !document.body.classList.contains("page--inner")) {
    var onScroll = function () {
      header.classList.toggle("is-solid", window.scrollY > 80);
    };
    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
  }

  /* Mobile nav */
  var menuToggle = $("#menuToggle");
  var mobileNav = $("#mobileNav");
  if (menuToggle && mobileNav) {
    menuToggle.addEventListener("click", function () {
      var open = mobileNav.hidden;
      mobileNav.hidden = !open;
      menuToggle.setAttribute("aria-expanded", open ? "true" : "false");
      menuToggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
    });
  }

  /* ======================================================================
     Scroll reveals — the CSS handles reduced-motion
     ====================================================================== */

  var reveals = $$(".reveal");
  if (reveals.length) {
    if (!("IntersectionObserver" in window)) {
      reveals.forEach(function (el) { el.classList.add("is-shown"); });
    } else {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-shown");
            io.unobserve(entry.target);
          }
        });
      }, { rootMargin: "0px 0px -12% 0px", threshold: 0.05 });
      reveals.forEach(function (el) { io.observe(el); });
    }
  }

  /* ======================================================================
     Budget slider labels (hero + filters + calculator)
     ====================================================================== */

  $$("[data-money-out]").forEach(function (input) {
    var out = $("#" + input.getAttribute("data-money-out"));
    if (!out) return;
    var sync = function () { out.textContent = formatZar(Number(input.value)); };
    sync();
    input.addEventListener("input", sync);
  });

  /* ======================================================================
     Payment calculator
     ====================================================================== */

  var calc = $("#calc");
  if (calc) {
    var UPFRONT = 0.05, TERMLY = 0.02, ADMIN = 950;
    var plan = "monthly";

    var rentIn = $("#calcRent");
    var leaseIn = $("#calcLease");
    var depIn = $("#calcDeposit");

    function compute() {
      var rent = Number(rentIn.value);
      var months = Number(leaseIn.value);
      var depMonths = Number(depIn.value);

      var gross = rent * months;
      var rate = plan === "upfront" ? UPFRONT : plan === "termly" ? TERMLY : 0;
      var discount = Math.round(gross * rate);
      var rentTotal = Math.round(gross - discount);
      var deposit = Math.round(rent * depMonths);
      var total = rentTotal + deposit + ADMIN;

      var instalments = months === 0 ? 0
        : plan === "upfront" ? 1
        : plan === "termly" ? Math.ceil(months / 3)
        : months;
      var instalment = instalments === 0 ? 0 : Math.round(rentTotal / instalments);
      var dueNow = plan === "upfront" ? total : deposit + ADMIN + instalment;

      $("#calcDueNow").textContent = formatZar(dueNow);
      $("#calcInstalment").textContent = formatZar(instalment);
      $("#calcDeposit_out").textContent = formatZar(deposit);
      $("#calcAdmin").textContent = formatZar(ADMIN);
      $("#calcTotal").textContent = formatZar(total);

      var label = instalments === 1
        ? "Single payment"
        : instalments + " × " + (plan === "termly" ? "per term" : "monthly") + " instalment";
      $("#calcInstalmentLabel").textContent = label;

      var discRow = $("#calcDiscountRow");
      if (discount > 0) {
        discRow.hidden = false;
        $("#calcDiscountLabel").textContent = (plan === "upfront" ? "Upfront" : "Termly") + " discount on rent";
        $("#calcDiscount").textContent = "−" + formatZar(discount);
      } else {
        discRow.hidden = true;
      }
    }

    $$(".tab", calc).forEach(function (tab) {
      tab.addEventListener("click", function () {
        plan = tab.getAttribute("data-plan");
        $$(".tab", calc).forEach(function (t) {
          t.setAttribute("aria-selected", t === tab ? "true" : "false");
        });
        compute();
      });
    });

    [rentIn, leaseIn, depIn].forEach(function (el) {
      el.addEventListener("input", compute);
      el.addEventListener("change", compute);
    });

    compute();
  }

  /* ======================================================================
     Leaflet map
     ====================================================================== */

  function campusIcon() {
    return L.divIcon({
      className: "",
      html: '<span class="map-pin-campus">RF</span>',
      iconSize: [34, 34],
      iconAnchor: [17, 17]
    });
  }

  function listingIcon() {
    return L.divIcon({
      className: "",
      html: '<span class="map-pin-listing"></span>',
      iconSize: [18, 18],
      iconAnchor: [9, 9]
    });
  }

  var mapEl = $("#map");
  var map = null;
  var markerLayer = null;

  function drawMap(campusId) {
    var campus = CAMPUSES[campusId];
    if (!campus || !mapEl || typeof L === "undefined") return;

    if (!map) {
      map = L.map(mapEl, {
        center: [campus.lat, campus.lng],
        zoom: 14,
        // Page scroll must keep working when the cursor crosses the map.
        scrollWheelZoom: false
      });
      // OpenStreetMap attribution is a licence condition. Do not remove it.
      L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
      }).addTo(map);
      markerLayer = L.layerGroup().addTo(map);
    } else {
      map.setView([campus.lat, campus.lng], map.getZoom(), { animate: true });
      markerLayer.clearLayers();
    }

    L.marker([campus.lat, campus.lng], { icon: campusIcon() })
      .bindPopup(
        "<strong>Richfield " + campus.name + "</strong><br>" +
        campus.city + ", " + campus.province +
        (campus.confirmed ? "" : '<br><em style="font-size:.75rem">Location to confirm</em>')
      )
      .addTo(markerLayer);

    Object.keys(LISTINGS).forEach(function (slug) {
      var l = LISTINGS[slug];
      if (l.campus !== campusId) return;
      L.marker([l.lat, l.lng], { icon: listingIcon(), title: l.name })
        .bindPopup(
          "<strong>" + l.name + "</strong><br>" +
          l.area + " · " + l.room + "<br>" +
          "<strong>" + formatZar(l.rent) + "</strong> per month<br>" +
          l.walk + " min walk · " + l.drive + " min drive<br>" +
          '<a href="listing.php?slug=' + encodeURIComponent(slug) + '" style="color:var(--rf-blue);font-weight:700">View room</a>'
        )
        .addTo(markerLayer);
    });
  }

  if (mapEl) {
    drawMap(mapEl.getAttribute("data-campus"));

    /* Campus switcher beside the map on the home page */
    var mapSelect = $("#mapCampus");
    if (mapSelect) {
      mapSelect.addEventListener("change", function () {
        var id = mapSelect.value;
        drawMap(id);
        // Swap the accessible list beside the map
        $$("[data-campus-list]").forEach(function (ul) {
          ul.hidden = ul.getAttribute("data-campus-list") !== id;
        });
      });
    }
  }

  /* Initial paint */
  renderShortlist();
})();

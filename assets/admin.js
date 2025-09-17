/* File: assets/admin.js */
/* global jQuery, ajaxurl, wp */
(function ($, d, w) {
  "use strict";

  // -------------------------------
  // Toast (let, inline)
  // -------------------------------
  function toast(msg) {
    try {
      var el = d.getElementById("nowonline-elt-toast");
      if (!el) {
        el = d.createElement("div");
        el.id = "nowonline-elt-toast";
        el.setAttribute(
          "style",
          "position:fixed;bottom:16px;left:50%;transform:translateX(-50%);padding:10px 14px;border-radius:8px;background:#1e1e1e;color:#fff;font:500 13px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Arial;box-shadow:0 8px 30px rgba(0,0,0,.25);z-index:99999;opacity:0;transition:opacity .18s ease"
        );
        d.body.appendChild(el);
      }
      el.textContent = msg;
      el.style.opacity = "1";
      clearTimeout(el._t);
      el._t = setTimeout(function () {
        el.style.opacity = "0";
      }, 1600);
    } catch (e) {}
  }

  // -------------------------------
  // Media picker (robust, single frame)
  // -------------------------------
  var frame = null;

  function openMedia(cb) {
    if (!w.wp || !wp.media) {
      alert("Media library not loaded");
      return;
    }
    if (!frame) {
      frame = wp.media({
        title: "Select image",
        multiple: false,
        library: { type: "image" },
      });
    }
    if (frame.off) frame.off("select");
    frame.on("select", function () {
      var sel = frame.state().get("selection");
      if (!sel || !sel.first) return;
      var a = sel.first();
      var json = a && a.toJSON ? a.toJSON() : a;
      if (cb) cb(json || null);
    });
    frame.open();
  }

  // -------------------------------
  // Helpers
  // -------------------------------
  function readId(card) {
    var idAttr = card.getAttribute("data-id");
    var idData = card.dataset ? card.dataset.id : null;
    var id = parseInt(idAttr || idData || 0, 10);
    return isNaN(id) ? 0 : id;
  }

  function pickUrlFromMedia(att) {
    if (!att) return "";
    if (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url)
      return att.sizes.thumbnail.url;
    if (att.sizes && att.sizes.medium && att.sizes.medium.url)
      return att.sizes.medium.url;
    return att.url || "";
  }

  function applyThumbToCard(card, url) {
    if (!url) return;
    var bust = (url.indexOf("?") === -1 ? "?" : "&") + "t=" + Date.now();
    var finalUrl = url + bust;

    var $thumb = $(card).find(".thumb").first();
    if (!$thumb.length) {
      $(card).prepend($('<img class="thumb" alt="">').attr("src", finalUrl));
      card.setAttribute("data-hasimg", "1");
      return;
    }
    if ($thumb.is("img")) {
      $thumb.attr("src", finalUrl).attr("alt", "").removeClass("noimg");
    } else {
      $thumb.replaceWith($('<img class="thumb" alt="">').attr("src", finalUrl));
    }
    card.setAttribute("data-hasimg", "1");
  }

  function setThumb(card, att) {
    var id = readId(card);
    var aid = parseInt(att && att.id ? att.id : 0, 10);
    var nonce = $("#nowonline_elt_media_nonce").val();

    if (!id || !aid) {
      console.warn("[NowOnline] Missing ids for setThumb", {
        post_id: id,
        attachment_id: aid,
      });
      return;
    }

    card.classList.add("is-loading");

    $.post(
      ajaxurl,
      {
        action: "nowonline_elt_set_thumb",
        _wpnonce: nonce,
        post_id: id,
        attachment_id: aid,
      },
      function (r) {
        var o = r;
        if (typeof r === "string") {
          try {
            o = JSON.parse(r);
          } catch (e) {
            o = {};
          }
        }
        var url = (o && (o.url_thumb || o.url)) || pickUrlFromMedia(att);

        if (o && o.success && url) {
          applyThumbToCard(card, url);
          toast("Billede opdateret");
        } else {
          console.warn("[NowOnline] Set image failed", o || r);
          toast("Kunne ikke opdatere billede");
        }
      }
    ).always(function () {
      card.classList.remove("is-loading");
    });
  }

  function removeThumb(card) {
    var id = readId(card);
    var nonce = $("#nowonline_elt_media_nonce").val();
    if (!id) {
      console.warn("[NowOnline] Missing post_id for removeThumb");
      return;
    }
    card.classList.add("is-loading");

    $.post(
      ajaxurl,
      { action: "nowonline_elt_remove_thumb", _wpnonce: nonce, post_id: id },
      function () {
        var $thumb = $(card).find(".thumb").first();
        if ($thumb.length) {
          $thumb.replaceWith($('<div class="noimg thumb">No image</div>'));
        }
        card.setAttribute("data-hasimg", "0");
        toast("Billede fjernet");
      }
    ).always(function () {
      card.classList.remove("is-loading");
    });
  }

  function isInteractive(el) {
    var tag = (el.tagName || "").toLowerCase();
    if (/^(input|button|select|textarea|label|a)$/i.test(tag)) return true;
    if (el.closest && el.closest(".setimg, .removeimg")) return true;
    return false;
  }

  // -------------------------------
  // DOM refs
  // -------------------------------
  var grid = d.getElementById("nowonline-elt-grid") || d;
  var searchInput = d.getElementById("nowonline-elt-search");
  var clearSearchBtn = d.getElementById("nowonline-elt-clear-search");
  var selectAllBtn = d.getElementById("nowonline-elt-select-all");
  var deselectAllBtn = d.getElementById("nowonline-elt-deselect-all");
  var countEl = d.getElementById("nowonline-elt-count");
  var sortSel = d.getElementById("nowonline-elt-sort");

  function getCards() {
    return Array.prototype.slice.call(
      d.querySelectorAll(".nowonline-elt-card")
    );
  }

  var cards = getCards();
  var totalCount = cards.length;

  function refreshCards() {
    cards = getCards();
    totalCount = cards.length;
    for (var i = 0; i < cards.length; i++) {
      if (!cards[i].hasAttribute("tabindex"))
        cards[i].setAttribute("tabindex", "0");
      cards[i].setAttribute("role", "group");
      var box = cards[i].querySelector('input[type="checkbox"]');
      var sel = !!(box && box.checked);
      cards[i].setAttribute("aria-selected", sel ? "true" : "false");
    }
    updateCount();
  }

  // -------------------------------
  // Filter, tags, sorting (m/ persistence)
  // -------------------------------
  var STORAGE_KEYS = {
    q: "nowelt_q",
    chip: "nowelt_chip",
    sort: "nowelt_sort",
  };

  var state = { q: "", chip: "all", sort: "title-asc" };

  function loadState() {
    try {
      var q = w.localStorage.getItem(STORAGE_KEYS.q);
      var chip = w.localStorage.getItem(STORAGE_KEYS.chip);
      var sort = w.localStorage.getItem(STORAGE_KEYS.sort);
      if (q) state.q = q;
      if (chip) state.chip = chip;
      if (sort) state.sort = sort;

      if (searchInput) searchInput.value = state.q;

      var chips = d.querySelectorAll("[data-filter-chip]");
      var activeFound = false;
      for (var i = 0; i < chips.length; i++) {
        var val = chips[i].getAttribute("data-filter-chip") || "all";
        var isActive = val === state.chip;
        chips[i].classList.toggle("is-active", isActive);
        if (isActive) activeFound = true;
      }
      if (!activeFound && chips.length) {
        chips[0].classList.add("is-active");
        state.chip = chips[0].getAttribute("data-filter-chip") || "all";
      }

      if (sortSel) sortSel.value = state.sort;
    } catch (e) {}
  }

  function saveState() {
    try {
      w.localStorage.setItem(STORAGE_KEYS.q, state.q);
      w.localStorage.setItem(STORAGE_KEYS.chip, state.chip);
      w.localStorage.setItem(STORAGE_KEYS.sort, state.sort);
    } catch (e) {}
  }

  function normalize(s) {
    return (s || "").toString().toLowerCase();
  }

  function cardMatches(card) {
    var title = normalize(card.getAttribute("data-title"));
    var tags = normalize(card.getAttribute("data-tags") || "");
    var okSearch =
      state.q === "" ||
      title.indexOf(state.q) > -1 ||
      tags.indexOf(state.q) > -1;

    var chip = state.chip;
    var okChip = true;
    if (chip === "hasimg") okChip = card.getAttribute("data-hasimg") === "1";
    else if (chip === "noimg")
      okChip = card.getAttribute("data-hasimg") !== "1";
    else if (chip !== "all" && chip)
      okChip = ("," + tags + ",").indexOf("," + chip + ",") > -1;

    return okSearch && okChip;
  }

  function applyFilter() {
    var vis = 0;
    for (var i = 0; i < cards.length; i++) {
      var el = cards[i];
      var show = cardMatches(el);
      el.style.display = show ? "" : "none";
      if (show) vis++;
    }
    updateCount(vis);
  }

  function updateCount(visibleCount) {
    if (!countEl) return;
    var v = typeof visibleCount === "number" ? visibleCount : null;
    if (v === null) {
      v = 0;
      for (var i = 0; i < cards.length; i++) {
        if (cards[i].style.display !== "none") v++;
      }
    }
    countEl.textContent = v + " / " + totalCount;
  }

  var debounceTimer = null;
  function debounced(fn, wait) {
    return function () {
      var ctx = this,
        args = arguments;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () {
        fn.apply(ctx, args);
      }, wait);
    };
  }

  if (searchInput) {
    searchInput.addEventListener(
      "input",
      debounced(function () {
        state.q = normalize(searchInput.value);
        saveState();
        applyFilter();
      }, 120)
    );
    searchInput.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        searchInput.value = "";
        state.q = "";
        saveState();
        applyFilter();
        e.stopPropagation();
        e.preventDefault();
      }
    });
  }

  if (clearSearchBtn) {
    clearSearchBtn.addEventListener("click", function () {
      if (!searchInput) return;
      searchInput.value = "";
      state.q = "";
      saveState();
      applyFilter();
      searchInput.focus();
    });
  }

  d.addEventListener("click", function (e) {
    var chip = e.target.closest ? e.target.closest("[data-filter-chip]") : null;
    if (!chip) return;
    var val = chip.getAttribute("data-filter-chip") || "all";
    state.chip = val;
    saveState();
    var chips = d.querySelectorAll("[data-filter-chip]");
    for (var i = 0; i < chips.length; i++) {
      chips[i].classList.toggle("is-active", chips[i] === chip);
    }
    applyFilter();
  });

  function sortCards() {
    if (!sortSel) return;
    var val = sortSel.value || "title-asc";
    state.sort = val;
    saveState();

    var parts = val.split("-");
    var by = parts[0] || "title";
    var dir = (parts[1] || "asc").toLowerCase() === "desc" ? -1 : 1;

    var parent = cards.length ? cards[0].parentNode : null;
    if (!parent) return;

    function norm(s) {
      return (s || "").toString().toLowerCase();
    }

    cards.sort(function (a, b) {
      var result = 0;
      if (by === "id") {
        result = readId(a) - readId(b);
      } else if (by === "title") {
        var ta = norm(a.getAttribute("data-title"));
        var tb = norm(b.getAttribute("data-title"));
        result = ta < tb ? -1 : ta > tb ? 1 : 0;
      } else {
        var da = a.getAttribute("data-" + by) || "";
        var db = b.getAttribute("data-" + by) || "";
        var na = parseFloat(da),
          nb = parseFloat(db);
        if (!isNaN(na) && !isNaN(nb)) result = na - nb;
        else result = norm(da) < norm(db) ? -1 : norm(da) > norm(db) ? 1 : 0;
      }
      return result * dir;
    });

    var frag = d.createDocumentFragment();
    for (var i = 0; i < cards.length; i++) frag.appendChild(cards[i]);
    parent.appendChild(frag);
  }

  if (sortSel) sortSel.addEventListener("change", sortCards);

  // -------------------------------
  // Selection UX + a11y
  // -------------------------------
  function setCardSelected(card, checked) {
    var box = card.querySelector('input[type="checkbox"]');
    if (box) box.checked = !!checked;
    card.classList.toggle("is-selected", !!checked);
    card.setAttribute("aria-selected", checked ? "true" : "false");
  }

  d.addEventListener("click", function (e) {
    var card = e.target.closest
      ? e.target.closest(".nowonline-elt-card")
      : null;
    if (!card) return;
    if (isInteractive(e.target)) return;

    var box = card.querySelector('input[type="checkbox"]');
    if (!box) return;
    var to = !box.checked;
    setCardSelected(card, to);
  });

  d.addEventListener("keydown", function (e) {
    var card = e.target.closest
      ? e.target.closest(".nowonline-elt-card")
      : null;
    if (!card) return;
    if (isInteractive(e.target)) return;
    if (e.key === " " || e.key === "Enter") {
      var box = card.querySelector('input[type="checkbox"]');
      if (!box) return;
      e.preventDefault();
      setCardSelected(card, !box.checked);
    }
  });

  d.addEventListener("change", function (e) {
    var box = e.target;
    if (
      !box.matches ||
      !box.matches(".nowonline-elt-card input[type=checkbox]")
    )
      return;
    var card = box.closest(".nowonline-elt-card");
    if (!card) return;
    setCardSelected(card, box.checked);
  });

  function forEachVisibleCard(fn) {
    for (var i = 0; i < cards.length; i++) {
      if (cards[i].style.display === "none") continue;
      fn(cards[i], i);
    }
  }

  if (selectAllBtn) {
    selectAllBtn.addEventListener("click", function () {
      forEachVisibleCard(function (c) {
        setCardSelected(c, true);
      });
    });
  }
  if (deselectAllBtn) {
    deselectAllBtn.addEventListener("click", function () {
      forEachVisibleCard(function (c) {
        setCardSelected(c, false);
      });
    });
  }

  d.addEventListener("keydown", function (e) {
    if (!(e.metaKey || e.ctrlKey)) return;
    if (e.key !== "a" && e.key !== "A") return;
    var active = d.activeElement;
    var inside =
      (grid && grid.contains(active)) ||
      (active && active.closest && active.closest("#nowonline-elt-grid"));
    if (!inside) return;
    e.preventDefault();
    forEachVisibleCard(function (c) {
      setCardSelected(c, true);
    });
  });

  // -------------------------------
  // Image set/remove (delegated)
  // -------------------------------
  $(d).on("click", ".nowonline-elt-card .setimg", function (e) {
    e.preventDefault();
    var card = e.currentTarget.closest(".nowonline-elt-card");
    openMedia(function (att) {
      if (att) setThumb(card, att);
    });
  });

  $(d).on("click", ".nowonline-elt-card .removeimg", function (e) {
    e.preventDefault();
    var card = e.currentTarget.closest(".nowonline-elt-card");
    removeThumb(card);
  });

  // -------------------------------
  // Observe DOM changes (cards added/removed)
  // -------------------------------
  try {
    if (grid && grid !== d && w.MutationObserver) {
      var mo = new MutationObserver(function (muts) {
        var changed = false;
        for (var i = 0; i < muts.length; i++) {
          if (
            (muts[i].addedNodes && muts[i].addedNodes.length) ||
            (muts[i].removedNodes && muts[i].removedNodes.length)
          ) {
            changed = true;
            break;
          }
        }
        if (changed) {
          refreshCards();
          applyFilter();
          sortCards();
        }
      });
      mo.observe(grid, { childList: true, subtree: true });
    }
  } catch (e) {}

  // -------------------------------
  // TinyMCE: ROBUST SYNC -> textarea
  // -------------------------------
  function syncEditors() {
    // 1) Standard WP helper – skriver visual indhold ned i textareas
    try {
      if (w.tinyMCE && tinyMCE.triggerSave) tinyMCE.triggerSave();
    } catch (e) {}

    // 2) Fallback for enheder/temaer hvor triggerSave ikke er nok
    try {
      $(".wp-editor-area").each(function () {
        var id = this.id;
        if (!id || !w.tinyMCE) return;
        var ed = tinyMCE.get(id);
        if (ed && ed.getContent && (!ed.isHidden || !ed.isHidden())) {
          this.value = ed.getContent({ format: "raw" });
        }
      });
    } catch (e) {}
  }

  // Bind til alle relevante TinyMCE events – både setup og init
  $(document).on("tinymce-editor-setup tinymce-editor-init", function (_e, ed) {
    try {
      ed.on("change keyup input undo redo paste SetContent blur", function () {
        if (ed.save) ed.save();
      });
    } catch (e) {}
  });

  // Sync ved submit
  $(document).on("submit", "#post", function () {
    syncEditors();
  });

  // Sync LIGE FØR klik på publish/update/save (mousedown) + click
  $(document).on(
    "mousedown click",
    "#publish, #save-post, #post-preview, #submitpost input[type=submit]",
    function () {
      syncEditors();
    }
  );

  // Sync ved Ctrl/Cmd+S (Save Draft genvej)
  d.addEventListener("keydown", function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === "s" || e.key === "S")) {
      syncEditors();
      // lad WP håndtere genvejen
    }
  });

  // Sync når siden skjules / man forlader den (edge case)
  d.addEventListener("visibilitychange", function () {
    if (d.visibilityState === "hidden") syncEditors();
  });
  w.addEventListener("beforeunload", function () {
    syncEditors();
  });

  // -------------------------------
  // Initial apply
  // -------------------------------
  loadState();
  refreshCards();
  applyFilter();
  sortCards();
})(jQuery, document, window);

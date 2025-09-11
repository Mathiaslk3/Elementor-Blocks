/* File: assets/admin.js */
/* global jQuery, ajaxurl, wp */
(function ($) {
  "use strict";

  var frame = null;

  function openMedia(cb) {
    if (frame) {
      frame.open();
      return;
    }
    if (!window.wp || !wp.media) {
      alert("Media library not loaded");
      return;
    }
    frame = wp.media({
      title: "Select image",
      multiple: false,
      library: { type: "image" },
    });
    frame.on("select", function () {
      var a = frame.state().get("selection").first().toJSON();
      if (cb) {
        cb(a);
      }
    });
    frame.open();
  }

  function readId(card) {
    var idAttr = $(card).attr("data-id");
    var idData = $(card).data("id");
    var id = parseInt(idAttr || idData || 0, 10);
    return isNaN(id) ? 0 : id;
  }

  function pickUrlFromMedia(att) {
    if (!att) return "";
    // Fallbacks in priority order
    if (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url)
      return att.sizes.thumbnail.url;
    if (att.sizes && att.sizes.medium && att.sizes.medium.url)
      return att.sizes.medium.url;
    return att.url || "";
  }

  function applyThumbToCard(card, url) {
    if (!url) return;
    // cache-bust så vi ser opdateringen med det samme
    var bust = (url.indexOf("?") === -1 ? "?" : "&") + "t=" + Date.now();
    var finalUrl = url + bust;

    var $thumb = $(card).find(".thumb").first();
    if (!$thumb.length) {
      $(card).prepend($('<img class="thumb">').attr("src", finalUrl));
      return;
    }
    if ($thumb.is("img")) {
      $thumb.attr("src", finalUrl).removeClass("noimg");
    } else {
      $thumb.replaceWith($('<img class="thumb">').attr("src", finalUrl));
    }
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

    $.post(
      ajaxurl,
      {
        action: "nowonline_elt_set_thumb",
        _wpnonce: nonce,
        post_id: id,
        attachment_id: aid,
      },
      function (r) {
        var o =
          typeof r === "string"
            ? (function () {
                try {
                  return JSON.parse(r);
                } catch (e) {
                  return {};
                }
              })()
            : r;

        // Brug serverens URL hvis muligt, ellers fallback til mediets URLer
        var url = (o && (o.url_thumb || o.url)) || pickUrlFromMedia(att);

        if (o && o.success && url) {
          applyThumbToCard(card, url);
        } else {
          console.warn("[NowOnline] Set image failed", o || r);
        }
      }
    );
  }

  function removeThumb(card) {
    var id = readId(card);
    var nonce = $("#nowonline_elt_media_nonce").val();
    if (!id) {
      console.warn("[NowOnline] Missing post_id for removeThumb");
      return;
    }

    $.post(
      ajaxurl,
      { action: "nowonline_elt_remove_thumb", _wpnonce: nonce, post_id: id },
      function () {
        var $thumb = $(card).find(".thumb").first();
        if ($thumb.length) {
          $thumb.replaceWith($('<div class="noimg thumb">No image</div>'));
        }
      }
    );
  }

  // Handlers
  $(document).on("click", ".nowonline-elt-card .setimg", function (e) {
    e.preventDefault();
    var card = $(e.currentTarget).closest(".nowonline-elt-card");
    openMedia(function (att) {
      setThumb(card, att);
    });
  });

  $(document).on("click", ".nowonline-elt-card .removeimg", function (e) {
    e.preventDefault();
    removeThumb($(e.currentTarget).closest(".nowonline-elt-card"));
  });

  // Search + select/deselect all
  var q = document.getElementById("nowonline-elt-search");
  var cards = [].slice.call(document.querySelectorAll(".nowonline-elt-card"));

  function filter() {
    var s = (q && q.value ? q.value : "").toLowerCase();
    for (var i = 0; i < cards.length; i++) {
      var el = cards[i];
      var t = el.getAttribute("data-title") || "";
      el.style.display = s === "" || t.indexOf(s) > -1 ? "grid" : "none";
    }
  }

  if (q) {
    q.addEventListener("input", filter);
  }

  var btnA = document.getElementById("nowonline-elt-select-all");
  if (btnA)
    btnA.addEventListener("click", function () {
      var boxes = document.querySelectorAll(
        ".nowonline-elt-card input[type=checkbox]"
      );
      for (var i = 0; i < boxes.length; i++) boxes[i].checked = true;
    });

  var btnB = document.getElementById("nowonline-elt-deselect-all");
  if (btnB)
    btnB.addEventListener("click", function () {
      var boxes = document.querySelectorAll(
        ".nowonline-elt-card input[type=checkbox]"
      );
      for (var i = 0; i < boxes.length; i++) boxes[i].checked = false;
    });
})(jQuery);

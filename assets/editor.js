// File: assets/editor.js
// @ts-nocheck
(function () {
  "use strict";

  // --- WP shims --------------------------------------------------------------
  var WP = window.wp || {};
  var __ =
    (WP.i18n && WP.i18n.__) ||
    function (s) {
      return s;
    };
  var el = WP.element && WP.element.createElement;
  if (!el) {
    console.error(
      "[NowOnline] wp.element mangler – kan ikke initialisere blok."
    );
    return;
  }

  var domReady =
    WP.domReady ||
    function (cb) {
      document.readyState === "loading"
        ? document.addEventListener("DOMContentLoaded", cb)
        : cb();
    };

  var C = WP.components || {};
  var B = WP.blockEditor || WP.editor || {};
  var Blocks = WP.blocks || {};

  function scrubNextProps(p) {
    if (!p) return p;
    var c = Object.assign({}, p);
    delete c.__next40pxDefaultSize;
    delete c.__nextHasNoMarginBottom;
    return c;
  }

  // Fallback components (kun når nødvendigt)
  var PanelBody =
    C.PanelBody ||
    function (p) {
      p = scrubNextProps(p);
      return el("div", Object.assign({}, p), p && p.children);
    };
  var TextControl =
    C.TextControl ||
    function (p) {
      p = scrubNextProps(p);
      var i = Object.assign({ type: "text" }, p);
      delete i.children;
      delete i.label;
      delete i.help;
      return el("input", i);
    };
  var TextareaControl =
    C.TextareaControl ||
    function (p) {
      p = scrubNextProps(p);
      var t = Object.assign({ rows: 4 }, p);
      delete t.children;
      delete t.label;
      delete t.help;
      return el("textarea", t);
    };
  var CheckboxControl =
    C.CheckboxControl ||
    function (p) {
      p = scrubNextProps(p);
      return el(
        "label",
        {},
        el("input", {
          type: "checkbox",
          checked: !!p.checked,
          onChange: function (e) {
            p.onChange && p.onChange(!!e.target.checked);
          },
        }),
        " ",
        p.label || ""
      );
    };
  var ColorPalette =
    C.ColorPalette ||
    function (p) {
      p = scrubNextProps(p);
      return el("input", {
        type: "color",
        value: p.value || "#000000",
        onChange: function (e) {
          p.onChange && p.onChange(e.target.value);
        },
      });
    };
  var Button =
    C.Button ||
    function (p) {
      p = scrubNextProps(p);
      var b = Object.assign({ type: "button", className: "button" }, p);
      return el("button", b, p && p.children);
    };

  var InspectorControls = B && B.InspectorControls ? B.InspectorControls : null;
  var MediaUpload = B && B.MediaUpload ? B.MediaUpload : null;
  var RichText = B && B.RichText ? B.RichText : null;

  var useBlockProps =
    (B && B.useBlockProps) ||
    function () {
      return {};
    };

  // --- hooks -----------------------------------------------------------------
  var useEffect = (WP.element && WP.element.useEffect) || function () {};
  var useRef =
    (WP.element && WP.element.useRef) ||
    function (v) {
      return { current: v };
    };
  var useState =
    (WP.element && WP.element.useState) ||
    function (v) {
      return [v, function () {}];
    };

  // Classic editor / TinyMCE API
  var OldEditor = (WP && (WP.editor || WP.oldEditor)) || null;

  var LinkControl =
    (B && B.LinkControl) ||
    (B && B.__experimentalLinkControl) ||
    (C && C.__experimentalLinkControl) ||
    null;

  // --------------------------------------------------------------------------
  function Icon() {
    return el(
      "svg",
      { viewBox: "0 0 24 24", width: 24, height: 24, role: "img" },
      el("rect", { x: 3, y: 3, width: 8, height: 8, fill: "currentColor" }),
      el("rect", { x: 13, y: 3, width: 8, height: 5, fill: "currentColor" }),
      el("rect", { x: 13, y: 10, width: 8, height: 11, fill: "currentColor" }),
      el("rect", { x: 3, y: 13, width: 8, height: 8, fill: "currentColor" })
    );
  }

  // --- helpers ---------------------------------------------------------------
  function decodeEntities(input) {
    if (typeof input !== "string" || !input) return input;
    var doc = new DOMParser().parseFromString(
      "<!doctype html><body>" + input,
      "text/html"
    );
    return (doc && doc.body && doc.body.textContent) || "";
  }

  function getPreviewSrc(t) {
    function take(v) {
      if (!v) return "";
      if (Array.isArray(v)) {
        for (var i = 0; i < v.length; i++) {
          var hit = take(v[i]);
          if (hit) return hit;
        }
        return "";
      }
      if (typeof v === "string") return v;
      if (v.url) return v.url;
      if (v.src) return v.src;
      if (v.preview_url) return v.preview_url;
      if (v.previewUrl) return v.previewUrl;
      if (v.sizes) {
        if (v.sizes.large) return v.sizes.large.url || v.sizes.large;
        if (v.sizes.full) return v.sizes.full.url || v.sizes.full;
        if (v.sizes.medium) return v.sizes.medium.url || v.sizes.medium;
      }
      if (v.settings) {
        var s =
          take(v.settings.preview) ||
          take(v.settings.image) ||
          take(v.settings.screenshot) ||
          take(v.settings.inserter);
        if (s) return s;
      }
      var keys = [
        "block_preview",
        "blockPreview",
        "preview",
        "preview_image",
        "previewImage",
        "preview_large",
        "previewLarge",
        "inserter",
        "thumb",
        "image",
        "screenshot",
        "canvas",
      ];
      for (var i2 = 0; i2 < keys.length; i2++)
        if (v[keys[i2]]) {
          var s2 = take(v[keys[i2]]);
          if (s2) return s2;
        }
      return "";
    }
    return take(t) || (t && t.meta && take(t.meta)) || "";
  }

  function getFieldDefs(id) {
    var M = window.NOWONLINE_FIELDS || {};
    var arr = (M && M[id]) || [];
    if (!Array.isArray(arr)) return [];
    return arr.map(function (d) {
      var copy = Object.assign({}, d);
      if (copy.label) copy.label = decodeEntities(copy.label);
      return copy;
    });
  }

  function cleanLabel(s) {
    return String(s || "").replace(
      /\s*\((?:rich|wysiwyg|text|textarea)\)\s*$/i,
      ""
    );
  }
  function labelFor(def) {
    return cleanLabel(def.label || def.key);
  }

  function Row(label, content, key) {
    return el(
      "div",
      { key: key, className: "now-elt-sec-item" },
      el("div", { className: "now-elt-label" }, label),
      el("div", { className: "now-elt-field" }, content)
    );
  }

  function tplById(id) {
    var list = Array.isArray(window.NOWONLINE_TEMPLATES_DECODED)
      ? window.NOWONLINE_TEMPLATES_DECODED
      : Array.isArray(window.NOWONLINE_TEMPLATES)
      ? window.NOWONLINE_TEMPLATES
      : [];
    id = parseInt(id || 0, 10);
    for (var i = 0; i < list.length; i++)
      if (parseInt(list[i].id, 10) === id) return list[i];
    return null;
  }

  function fixUrl(u) {
    u = (u || "").trim();
    if (!u) return u;
    if (u.indexOf("//") === 0) u = "https:" + u;
    u = u
      .replace(/^http\/:\/\//i, "http://")
      .replace(/^https\/:\/\//i, "https://")
      .replace(/^(https?:\/\/)(https?:\/\/)/i, "$1")
      .replace(/^(https?:\/\/)+/i, "$1");
    if (/^www\./i.test(u)) u = "https://" + u;
    if (!/^[a-z][a-z0-9+.\-]*:\/\//i.test(u) && /^[^\/\s]+\.[^\s]+/.test(u))
      u = "https://" + u;
    return u;
  }

  // Titel/heading → ren tekst, så template-styling arves
  function sanitizeRichHtml(input, inlineOnly) {
    var html = String(input || "");
    if (!html) return "";

    // Headline/inline: returnér ren tekst
    if (inlineOnly) {
      var doc = new DOMParser().parseFromString(
        "<!doctype html><body>" + html,
        "text/html"
      );
      var text = (doc && doc.body && doc.body.textContent) || "";
      return text.replace(/\s+/g, " ").trim();
    }

    // Rich (ikke-inline): fjern ALLE class-attributter (matcher serverens sanitizer),
    // tillad kun sikre attributter
    var wrap = document.createElement("div");
    wrap.innerHTML = html;

    var allowed = {
      a: ["href", "target", "rel"],
      img: ["src", "alt"],
      span: ["style"],
      p: ["style"],
      div: ["style"],
      strong: [],
      em: [],
      b: [],
      i: [],
      u: [],
      br: [],
      ul: [],
      ol: [],
      li: [],
      code: [],
      h1: [],
      h2: [],
      h3: [],
      h4: [],
      h5: [],
      h6: [],
    };

    var stack = [];
    for (var i = 0; i < wrap.childNodes.length; i++)
      stack.push(wrap.childNodes[i]);

    var processed = 0,
      LIMIT = 10000;
    while (stack.length && processed < LIMIT) {
      var node = stack.pop();
      processed++;
      if (!node || node.nodeType !== 1) continue;

      var tag = node.tagName.toLowerCase();

      // Fjern altid class-attributter (uanset indhold)
      if (node.hasAttribute("class")) node.removeAttribute("class");

      var keep =
        allowed[tag] ||
        (tag === "span" || tag === "p" || tag === "div" ? ["style"] : []);

      // Fjern alle ikke-tilladte attributter
      for (var ai = node.attributes.length - 1; ai >= 0; ai--) {
        var name = node.attributes[ai].name.toLowerCase();
        if (keep.indexOf(name) === -1) node.removeAttribute(name);
      }

      // DFS
      for (var ci = node.childNodes.length - 1; ci >= 0; ci--)
        stack.push(node.childNodes[ci]);
    }

    return wrap.innerHTML;
  }

  // --- type helpers ----------------------------------------------------------
  function t(def) {
    return (def && def.type ? String(def.type) : "").toLowerCase().trim();
  }
  function k(def) {
    return (def && def.key ? String(def.key) : "").toLowerCase().trim();
  }

  function norm(def) {
    var _t = t(def),
      _k = k(def);
    if (/^(rich|wysiwyg|richtext|rte|editor|html)$/.test(_t)) return "rich";
    if (/^(textarea|longtext|multiline|text_area)$/.test(_t)) return "textarea";
    if (/^(url|link|href)$/.test(_t)) return "url";
    if (/^(img|image|picture|photo)$/.test(_t)) return "img";
    if (/^(bg|background|background_image)$/.test(_t)) return "bg";
    if (/^gallery$/.test(_t)) return "gallery";
    if (/^video$/.test(_t)) return "video";
    if (["titel", "undertitel", "beskrivelse"].indexOf(_k) >= 0) return "rich";
    if (_k === "billede") return "img";
    if (_k === "galleri") return "gallery";
    if (/^video(url)?$/.test(_k)) return "video";
    if (!_t || _t === "text") {
      if (/(rich|wysiwyg|rte|editor|html)/.test(_k)) return "rich";
      if (/textarea|longtext|multiline/.test(_k)) return "textarea";
      if (/url|link|href/.test(_k)) return "url";
      if (/^img|image|photo/.test(_k)) return "img";
      if (/bg|background/.test(_k)) return "bg";
      if (/galleri|gallery/.test(_k)) return "gallery";
      if (/video/.test(_k)) return "video";
    }
    return _t || "text";
  }

  function isRich(d) {
    return norm(d) === "rich";
  }
  function isTextarea(d) {
    return norm(d) === "textarea";
  }
  function isUrl(d) {
    return norm(d) === "url";
  }
  function isImage(d) {
    var n = norm(d);
    return n === "img" || n === "bg";
  }
  function isGallery(d) {
    return norm(d) === "gallery";
  }
  function isVideo(d) {
    return norm(d) === "video";
  }
  function isHeadingOrText(d) {
    var n = norm(d);
    return n === "text" || n === "p" || /^h[1-6]$/.test(n) || !n;
  }

  function isButtonTextDef(def) {
    var key = String(def.key || "").toLowerCase();
    var label = String(def.label || "").toLowerCase();
    return (
      (/(cta|knap|button|btn)([_\s-]?text)?$/.test(key) ||
        /(cta|knap|button|btn)/.test(label)) &&
      !isTextarea(def) &&
      !isRich(def)
    );
  }

  // --- Feltkomponenter -------------------------------------------------------
  function ImageField(block, def) {
    var url = (block.attributes.fields || {})[def.key] || "";
    function onSelect(media) {
      var u = (media && media.url) || "";
      var next = Object.assign({}, block.attributes.fields || {});
      next[def.key] = u;
      block.setAttributes({ fields: next });
    }
    function clear() {
      var next = Object.assign({}, block.attributes.fields || {});
      delete next[def.key];
      block.setAttributes({ fields: next });
    }
    var preview = url
      ? el("img", { src: url, className: "now-elt-imgprev", alt: "" })
      : el(
          "div",
          { className: "now-elt-imgprev now-elt-noimg" },
          __("No image", "nowonline")
        );
    return Row(
      labelFor(def),
      el(
        "div",
        {},
        preview,
        MediaUpload
          ? el(MediaUpload, {
              onSelect: onSelect,
              allowedTypes: ["image"],
              value: 0,
              render: function (o) {
                return el(
                  "div",
                  { className: "now-elt-btnrow" },
                  el(
                    "button",
                    { className: "button", onClick: o.open },
                    __("Vælg billede", "nowonline")
                  ),
                  el(
                    "button",
                    { className: "button is-secondary", onClick: clear },
                    __("Fjern", "nowonline")
                  )
                );
              },
            })
          : el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline"))
      ),
      def.key
    );
  }

  function VideoField(block, def) {
    var key = def.key;
    var fields = block.attributes.fields || {};
    var url = fields[key] || "";
    var posterKey = key + "_poster";
    var poster = fields[posterKey] || "";

    function setField(k, v) {
      var next = Object.assign({}, block.attributes.fields || {});
      if (v === null) delete next[k];
      else next[k] = v;
      block.setAttributes({ fields: next });
    }
    function onSelectVideo(media) {
      setField(key, (media && media.url) || "");
    }
    function onSelectPoster(media) {
      setField(posterKey, (media && media.url) || "");
    }

    var vidPreview = url
      ? el("video", {
          src: url,
          poster: poster || undefined,
          controls: true,
          className: "now-elt-video-prev",
        })
      : el(
          "div",
          { className: "now-elt-imgprev now-elt-noimg" },
          __("Ingen video valgt", "nowonline")
        );
    var posterPreview = poster
      ? el("img", {
          src: poster,
          alt: "",
          className: "now-elt-imgprev now-elt-poster-prev",
        })
      : null;

    return Row(
      labelFor(def),
      el(
        "div",
        {},
        vidPreview,
        MediaUpload
          ? el(MediaUpload, {
              onSelect: onSelectVideo,
              allowedTypes: ["video"],
              value: 0,
              render: function (o) {
                return el(
                  "div",
                  { className: "now-elt-mt-6 now-elt-btnrow" },
                  el(
                    "button",
                    { className: "button", onClick: o.open },
                    __("Vælg video", "nowonline")
                  ),
                  el(
                    "button",
                    {
                      className: "button is-secondary",
                      onClick: function () {
                        setField(key, null);
                      },
                    },
                    __("Fjern", "nowonline")
                  ),
                  el(TextControl, {
                    type: "url",
                    value: url || "",
                    onChange: function (v) {
                      setField(key, fixUrl(v || ""));
                    },
                    placeholder: __("eller indsæt video-URL…", "nowonline"),
                    className: "now-elt-mt-8",
                  })
                );
              },
            })
          : el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline")),
        el(
          "div",
          { className: "now-elt-mt-10" },
          el(
            "div",
            { className: "now-elt-label" },
            __("Poster (valgfri)", "nowonline")
          ),
          posterPreview || null,
          MediaUpload
            ? el(MediaUpload, {
                onSelect: onSelectPoster,
                allowedTypes: ["image"],
                value: 0,
                render: function (o) {
                  return el(
                    "div",
                    { className: "now-elt-btnrow" },
                    el(
                      "button",
                      { className: "button", onClick: o.open },
                      __("Vælg poster", "nowonline")
                    ),
                    el(
                      "button",
                      {
                        className: "button is-secondary",
                        onClick: function () {
                          setField(posterKey, null);
                        },
                      },
                      __("Fjern", "nowonline")
                    )
                  );
                },
              })
            : null
        )
      ),
      key
    );
  }

  function GalleryField(block, def) {
    var value = (block.attributes.fields || {})[def.key] || [];
    if (!Array.isArray(value)) value = [];
    function onSelect(items) {
      var urls = [];
      if (Array.isArray(items))
        urls = items
          .map(function (m) {
            return (m && (m.url || m.source_url)) || "";
          })
          .filter(Boolean);
      else if (items && items.url) urls = [items.url];
      var next = Object.assign({}, block.attributes.fields || {});
      next[def.key] = urls;
      block.setAttributes({ fields: next });
    }
    function clear() {
      var next = Object.assign({}, block.attributes.fields || {});
      delete next[def.key];
      block.setAttributes({ fields: next });
    }

    var thumbs = value.length
      ? el(
          "div",
          { className: "now-elt-gallery-thumbs" },
          value.map(function (u, i) {
            return el("img", {
              key: i,
              src: u,
              alt: "",
              className: "now-elt-imgprev",
            });
          })
        )
      : el(
          "div",
          { className: "now-elt-imgprev now-elt-noimg" },
          __("Ingen billeder i galleriet", "nowonline")
        );

    return Row(
      labelFor(def),
      el(
        "div",
        {},
        thumbs,
        MediaUpload
          ? el(MediaUpload, {
              onSelect: onSelect,
              allowedTypes: ["image"],
              multiple: true,
              gallery: true,
              value: 0,
              render: function (o) {
                return el(
                  "div",
                  { className: "now-elt-btnrow" },
                  el(
                    "button",
                    { className: "button", onClick: o.open },
                    __("Vælg billeder", "nowonline")
                  ),
                  el(
                    "button",
                    { className: "button is-secondary", onClick: clear },
                    __("Ryd galleri", "nowonline")
                  )
                );
              },
            })
          : el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline"))
      ),
      def.key
    );
  }

  // ============ TinyMCE som komponent =======================================
  function TinyMCEField(props) {
    var block = props.block;
    var def = props.def;
    var activeTab = props.activeTab;
    var showEditor = props.showEditor;

    var fieldKey = def.key;
    var initial =
      (block.attributes.fields && block.attributes.fields[fieldKey]) || "";

    var inlineOnly = /^(titel|title|heading|overskrift|headline)$/i.test(
      String(fieldKey || "")
    );
    var cleanedInitial = sanitizeRichHtml(initial, inlineOnly);

    function safe(s) {
      return String(s || "").replace(/[^a-z0-9_-]/gi, "");
    }
    var instId = safe(block.clientId || Math.random().toString(36).slice(2, 8));
    var idRef = useRef("nowelt-" + instId + "-" + safe(fieldKey));
    var taRef = useRef(null);

    var fieldsRef = useRef(block.attributes.fields || {});
    useEffect(
      function () {
        fieldsRef.current = block.attributes.fields || {};
      },
      [block.attributes.fields]
    );

    useEffect(
      function () {
        if (!showEditor) return;
        if (!OldEditor || !OldEditor.initialize) return;
        if (!(window.tinymce && window.tinymce.Editor)) return;

        var disposed = false,
          ed = null,
          wait = null,
          guard = null;

        function hasEditor() {
          return !!(
            window.tinymce &&
            window.tinymce.get &&
            window.tinymce.get(idRef.current)
          );
        }
        function readRaw() {
          return ed && typeof ed.getContent === "function"
            ? ed.getContent()
            : taRef.current
            ? taRef.current.value
            : "";
        }

        function textMeaningful(s) {
          var doc = new DOMParser().parseFromString(
            "<!doctype html><body>" + (s || ""),
            "text/html"
          );
          var t = (doc && doc.body && doc.body.textContent) || "";
          return t.replace(/\s+/g, " ").trim().length > 0;
        }

        function sync() {
          var raw = readRaw();
          var next = Object.assign({}, fieldsRef.current);

          if (!textMeaningful(raw)) {
            if (Object.prototype.hasOwnProperty.call(next, fieldKey)) {
              delete next[fieldKey];
              block.setAttributes({ fields: next });
            }
            return;
          }

          var sanitized = sanitizeRichHtml(raw, inlineOnly);
          if (next[fieldKey] !== sanitized) {
            next[fieldKey] = sanitized;
            block.setAttributes({ fields: next });
          }
        }

        function bindWhenReady() {
          wait = setInterval(function () {
            if (disposed) return;
            ed =
              window.tinymce &&
              window.tinymce.get &&
              window.tinymce.get(idRef.current);
            if (ed) {
              clearInterval(wait);
              if (cleanedInitial) ed.setContent(cleanedInitial);
              ed.on("change input keyup setcontent undo redo blur", sync);
            }
          }, 50);
        }

        function initIfNeeded() {
          if (activeTab !== "content") return;
          if (!showEditor) return;
          if (hasEditor()) return;

          try {
            OldEditor.remove(idRef.current);
          } catch (e) {}
          if (
            window.QTags &&
            window.QTags.instances &&
            window.QTags.instances[idRef.current]
          ) {
            try {
              delete window.QTags.instances[idRef.current];
            } catch (e) {}
          }

          OldEditor.initialize(idRef.current, {
            tinymce: {
              wpautop: !inlineOnly,
              forced_root_block: inlineOnly ? "" : "p",
              menubar: false,
              paste_as_text: !!inlineOnly, // titel: indsæt som ren tekst
              toolbar1: inlineOnly
                ? "bold,italic,underline,undo,redo"
                : "formatselect,bold,italic,link,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo",
            },
            quicktags: false,
            mediaButtons: false,
          });
          bindWhenReady();
        }

        initIfNeeded();
        guard = setInterval(function () {
          if (
            !disposed &&
            activeTab === "content" &&
            showEditor &&
            !hasEditor()
          )
            initIfNeeded();
        }, 200);
        if (taRef.current) taRef.current.addEventListener("input", sync);

        return function () {
          try {
            sync();
          } catch (e) {}
          disposed = true;
          try {
            OldEditor.remove(idRef.current);
          } catch (e) {}
          if (taRef.current) taRef.current.removeEventListener("input", sync);
          clearInterval(wait);
          clearInterval(guard);
        };
      },
      [
        block.clientId,
        block.attributes.templateId,
        fieldKey,
        activeTab,
        showEditor,
      ]
    );

    return Row(
      labelFor(def),
      el("textarea", {
        id: idRef.current,
        ref: taRef,
        defaultValue: cleanedInitial,
      }),
      fieldKey
    );
  }

  // --------------------------------------------------------------------------
  function OnlyPreviewEl(tplId) {
    var tpl = tplById(tplId) || {};
    var prevSrc = tpl._previewSrc || getPreviewSrc(tpl) || "";
    return el(
      "div",
      { className: "now-elt-inserter-preview" },
      prevSrc
        ? el("img", {
            src: prevSrc,
            alt: "",
            draggable: false,
            onDragStart: function (e) {
              e.preventDefault();
            },
            className: "now-elt-canvas-preview now-elt-canvas-preview--large",
          })
        : el(
            "div",
            { className: "now-elt-inserter-preview__empty" },
            __("No preview available.", "nowonline")
          )
    );
  }

  // --- Blok-registrering -----------------------------------------------------
  domReady(function () {
    try {
      var RAW_MAP = Array.isArray(window.NOWONLINE_TEMPLATES)
        ? window.NOWONLINE_TEMPLATES
        : [];
      var MAP = RAW_MAP.map(function (t) {
        var copy = Object.assign({}, t);
        copy.title = decodeEntities(t.title || "");
        copy._previewSrc = getPreviewSrc(t) || "";
        return copy;
      });
      window.NOWONLINE_TEMPLATES_DECODED = MAP;

      if (!Blocks || !Blocks.registerBlockType) return;

      // Vælg kategori, fallback til 'widgets' hvis custom kategori ikke findes
      var desiredCat = "nowonline-elementor";
      var useCat = desiredCat;
      try {
        var cats = Blocks.getCategories ? Blocks.getCategories() : [];
        if (
          !cats ||
          !cats.some(function (c) {
            return c && c.slug === desiredCat;
          })
        ) {
          useCat = "widgets";
        }
      } catch (e) {
        useCat = "widgets";
      }

      var already =
        Blocks.getBlockType && Blocks.getBlockType("nowonline/elt-template");
      if (!already) {
        Blocks.registerBlockType("nowonline/elt-template", {
          apiVersion: 2,
          title: __("Elementor Template", "nowonline"),
          icon: Icon(),
          category: useCat,
          supports: { inserter: true, align: false },
          example: { attributes: { templateId: 0 } },
          attributes: {
            templateId: { type: "number", default: 0 },
            gap: { type: "number", default: 24 },
            fields: { type: "object", default: {} },
            design: { type: "object", default: {} },
            background: { type: "object", default: {} },
            responsive: { type: "object", default: {} },
            spacing: { type: "object", default: {} },

            containerBg: { type: "string", default: "" },

            btnTextColor: { type: "string", default: "" },
            btnBorderColor: { type: "string", default: "" },
            btnBorderWidth: { type: "string", default: "" },
            btnBorderRadius: { type: "string", default: "" },

            // Desktop typografi (matcher Renderer.php)
            fsH1: { type: "string", default: "" },
            fsH2: { type: "string", default: "" },
            fsH3: { type: "string", default: "" },
            fsH4: { type: "string", default: "" },
            fsH5: { type: "string", default: "" },
            fsH6: { type: "string", default: "" },
            fsBody: { type: "string", default: "" },
            fsBtn: { type: "string", default: "" },

            bgVideo: { type: "string", default: "" },
            bgImg: { type: "string", default: "" },
            bgImgTablet: { type: "string", default: "" },
            bgImgMobile: { type: "string", default: "" },
            bgPos: { type: "string", default: "center center" },
            bgSize: { type: "string", default: "cover" },
            bgFixed: { type: "boolean", default: false },

            hideDesktop: { type: "boolean", default: false },
            hideTablet: { type: "boolean", default: false },
            hideMobile: { type: "boolean", default: false },

            padTopDesktop: { type: "string", default: "" },
            padBottomDesktop: { type: "string", default: "" },
            padTopLaptop: { type: "string", default: "" },
            padBottomLaptop: { type: "string", default: "" },
            padTopTablet: { type: "string", default: "" },
            padBottomTablet: { type: "string", default: "" },
            padTopMobile: { type: "string", default: "" },
            padBottomMobile: { type: "string", default: "" },

            containerTargetMode: { type: "string", default: "auto" },
            containerTarget: { type: "string", default: "" },
          },

          edit: function (props) {
            var rootRef = useRef(null);
            var _show = useState(false),
              showEditor = _show[0],
              setShowEditor = _show[1];
            var _tab = useState("content"),
              activeTab = _tab[0],
              setActiveTab = _tab[1];

            if (props.__unstableIsPreview) {
              var a = props.attributes || {};
              return el("div", { ref: rootRef }, OnlyPreviewEl(a.templateId));
            }

            var attrs = props.attributes || {};
            var templateId = attrs.templateId || 0;
            var fields = attrs.fields || {};

            useEffect(
              function () {
                setShowEditor(false);
              },
              [templateId]
            );

            var containerBg = attrs.containerBg || "";
            var btnTextColor = attrs.btnTextColor || "";
            var btnBorderColor = attrs.btnBorderColor || "";
            var btnBorderWidth = attrs.btnBorderWidth || "";
            var btnBorderRadius = attrs.btnBorderRadius || "";

            // Desktop typografi
            var fsH1 = attrs.fsH1 || "";
            var fsH2 = attrs.fsH2 || "";
            var fsH3 = attrs.fsH3 || "";
            var fsH4 = attrs.fsH4 || "";
            var fsH5 = attrs.fsH5 || "";
            var fsH6 = attrs.fsH6 || "";
            var fsBody = attrs.fsBody || "";
            var fsBtn = attrs.fsBtn || "";

            var bgVideo = attrs.bgVideo || "";
            var bgImg = attrs.bgImg || "";
            var bgImgTablet = attrs.bgImgTablet || "";
            var bgImgMobile = attrs.bgImgMobile || "";
            var bgPos = attrs.bgPos || "center center";
            var bgSize = attrs.bgSize || "cover";
            var bgFixed = !!attrs.bgFixed;

            var hideDesktop = !!attrs.hideDesktop;
            var hideTablet = !!attrs.hideTablet;
            var hideMobile = !!attrs.hideMobile;

            var padTopDesktop = attrs.padTopDesktop || "";
            var padBottomDesktop = attrs.padBottomDesktop || "";
            var padTopLaptop = attrs.padTopLaptop || "";
            var padBottomLaptop = attrs.padBottomLaptop || "";
            var padTopTablet = attrs.padTopTablet || "";
            var padBottomTablet = attrs.padBottomTablet || "";
            var padTopMobile = attrs.padTopMobile || "";
            var padBottomMobile = attrs.padBottomMobile || "";

            function setField(k, v) {
              var next = Object.assign({}, fields);
              if (v === null || (typeof v === "string" && v.trim() === ""))
                delete next[k];
              else next[k] = v;
              props.setAttributes({ fields: next });
            }
            function setAttr(next) {
              props.setAttributes(next);
            }

            var defs = getFieldDefs(templateId) || [];
            var richDefs = defs.filter(isRich);
            var textDefs = defs.filter(function (d) {
              return isHeadingOrText(d) && !isRich(d);
            });
            var areaDefs = defs.filter(isTextarea);
            var urlDefs = defs.filter(isUrl);
            var imageDefs = defs.filter(isImage);
            var galDefs = defs.filter(isGallery);
            var videoDefs = defs.filter(isVideo);

            var btnTextDef = null;
            for (var i = 0; i < textDefs.length; i++) {
              if (isButtonTextDef(textDefs[i])) {
                btnTextDef = textDefs[i];
                break;
              }
            }
            var btnUrlDef = urlDefs.length ? urlDefs[0] : null;
            if (btnTextDef)
              textDefs = textDefs.filter(function (d) {
                return d !== btnTextDef;
              });
            if (btnUrlDef)
              urlDefs = urlDefs.filter(function (d) {
                return d !== btnUrlDef;
              });

            // --- URL felt
            function UrlInput(def) {
              var val = fields[def.key];
              var curr =
                val && typeof val === "object"
                  ? val
                  : { url: val || "", newTab: false, type: "external" };

              var control = LinkControl
                ? el(LinkControl, {
                    value: { url: curr.url || "" },
                    onChange: function (next) {
                      var url =
                        typeof next === "string"
                          ? next
                          : (next && next.url) || "";
                      var newTab = !!(
                        next &&
                        (next.opensInNewTab ||
                          next.newTab ||
                          next.target === "_blank")
                      );
                      setField(def.key, { url: fixUrl(url), newTab: newTab });
                    },
                    showInitialSuggestions: true,
                    withCreateSuggestion: false,
                  })
                : el(TextControl, {
                    type: "url",
                    label: undefined,
                    value: curr.url || "",
                    onChange: function (v) {
                      setField(def.key, {
                        url: fixUrl(v || ""),
                        newTab: !!curr.newTab,
                      });
                    },
                  });

              return Row(
                labelFor(def),
                el(
                  "div",
                  {},
                  control,
                  el(
                    "div",
                    { className: "now-elt-mt-6" },
                    el(CheckboxControl, {
                      label: __("Åbn i ny fane", "nowonline"),
                      checked: !!curr.newTab,
                      onChange: function (v) {
                        setField(
                          def.key,
                          Object.assign({}, curr, { newTab: !!v })
                        );
                      },
                    })
                  )
                ),
                def.key
              );
            }

            var linkInputs = urlDefs.map(UrlInput);
            var imageInputs = imageDefs.map(function (def) {
              return ImageField(props, def);
            });
            var videoInputs = videoDefs.map(function (def) {
              return VideoField(props, def);
            });
            var galleryInputs = galDefs.map(function (def) {
              return GalleryField(props, def);
            });

            var hasTiny = !!(
              OldEditor &&
              OldEditor.initialize &&
              window.tinymce
            );

            // Rich
            var richInputs = hasTiny
              ? richDefs.map(function (def) {
                  return el(TinyMCEField, {
                    key: def.key,
                    block: props,
                    def: def,
                    activeTab: activeTab,
                    showEditor: showEditor,
                  });
                })
              : RichText
              ? richDefs.map(function (def) {
                  var inlineOnly =
                    /^(titel|title|heading|overskrift|headline)$/i.test(
                      String(def.key || "")
                    );
                  var value = sanitizeRichHtml(
                    fields[def.key] || "",
                    inlineOnly
                  );
                  return Row(
                    labelFor(def),
                    el(RichText, {
                      tagName: "div",
                      value: value,
                      onChange: function (v) {
                        var next = Object.assign(
                          {},
                          props.attributes.fields || {}
                        );
                        var cleaned = sanitizeRichHtml(v || "", inlineOnly);
                        if (!cleaned) delete next[def.key];
                        else next[def.key] = cleaned;
                        props.setAttributes({ fields: next });
                      },
                      placeholder: __("Skriv formateret tekst…", "nowonline"),
                      allowedFormats: [
                        "core/bold",
                        "core/italic",
                        "core/link",
                        "core/strikethrough",
                        "core/underline",
                        "core/code",
                      ],
                    }),
                    def.key
                  );
                })
              : [
                  el(
                    "div",
                    { key: "norich", className: "now-elt-muted" },
                    __("RichText/TinyMCE ikke tilgængelig.", "nowonline")
                  ),
                ];

            // TinyMCE som default for tekstfelter
            var textInputs = hasTiny
              ? textDefs.map(function (def) {
                  return el(TinyMCEField, {
                    key: def.key,
                    block: props,
                    def: def,
                    activeTab: activeTab,
                    showEditor: showEditor,
                  });
                })
              : textDefs.map(function (def) {
                  var value = fields[def.key] || "";
                  return Row(
                    labelFor(def),
                    el(TextareaControl, {
                      label: undefined,
                      value: value,
                      rows: 3,
                      onChange: function (v) {
                        setField(def.key, v);
                      },
                    }),
                    def.key
                  );
                });

            // Textarea felter
            var areaInputs = areaDefs.map(function (def) {
              var value = fields[def.key] || "";
              return Row(
                labelFor(def),
                el(TextareaControl, {
                  label: undefined,
                  value: value,
                  rows: 6,
                  onChange: function (v) {
                    setField(def.key, v);
                  },
                }),
                def.key
              );
            });

            function ButtonSection() {
              var children = [];
              if (btnTextDef) {
                children.push(
                  Row(
                    __("Tekst", "nowonline"),
                    el(TextControl, {
                      value: fields[btnTextDef.key] || "",
                      onChange: function (v) {
                        setField(btnTextDef.key, v);
                      },
                      placeholder: __("Skriv knaptekst…", "nowonline"),
                      __next40pxDefaultSize: true,
                      __nextHasNoMarginBottom: true,
                      className: "now-elt-input-narrow",
                    }),
                    "btn-text"
                  )
                );
              }
              if (btnUrlDef) children.push(UrlInput(btnUrlDef));
              if (!children.length) return null;
              return el(
                PanelBody,
                { title: __("Knap", "nowonline"), initialOpen: true },
                el("div", {}, children)
              );
            }

            function TabBtn(name, title) {
              var active = activeTab === name;
              return el(
                "button",
                {
                  type: "button",
                  className:
                    "button " + (active ? "is-primary" : "is-secondary"),
                  onClick: function () {
                    setActiveTab(name);
                  },
                  "aria-selected": active,
                },
                title
              );
            }

            function PreviewFirstLayer() {
              var tpl = tplById(templateId) || {};
              var prevSrc = tpl._previewSrc || getPreviewSrc(tpl) || "";
              function openEditor() {
                setShowEditor(true);
              }
              return el(
                "div",
                { className: "now-elt-flat", ref: rootRef },
                prevSrc
                  ? el(
                      "div",
                      {
                        role: "button",
                        tabIndex: 0,
                        onClick: openEditor,
                        onKeyDown: function (e) {
                          if (e.key === "Enter" || e.key === " ") openEditor();
                        },
                        className: "now-elt-preview-toggle",
                        draggable: false,
                        onDragStart: function (e) {
                          e.preventDefault();
                        },
                        style: {
                          display: "block",
                          background: "transparent",
                          border: 0,
                          padding: 0,
                          cursor: "pointer",
                          textAlign: "left",
                          userSelect: "none",
                        },
                        "aria-label": __("Åbn editor", "nowonline"),
                      },
                      el("img", {
                        key: "canvas",
                        className:
                          "now-elt-canvas-preview now-elt-canvas-preview--large",
                        src: prevSrc,
                        alt: "",
                        draggable: false,
                        onDragStart: function (e) {
                          e.preventDefault();
                        },
                        style: { userSelect: "none" },
                      }),
                      el(
                        "div",
                        {
                          className: "now-elt-overlay-hint",
                          style: {
                            marginTop: 8,
                            opacity: 0.8,
                            userSelect: "none",
                            pointerEvents: "none",
                          },
                        },
                        "Klik for at redigere"
                      )
                    )
                  : el(
                      "div",
                      { className: "now-elt-inserter-preview__empty" },
                      __("No preview available.", "nowonline"),
                      el(
                        "div",
                        { style: { marginTop: 8 } },
                        el(
                          Button,
                          {
                            className: "button is-primary",
                            onClick: openEditor,
                            draggable: false,
                            onDragStart: function (e) {
                              e.preventDefault();
                            },
                          },
                          __("Åbn editor", "nowonline")
                        )
                      )
                    )
              );
            }

            function EditorShell(childrenEl) {
              var tpl = tplById(templateId) || {};
              var prevSrc = tpl._previewSrc || getPreviewSrc(tpl) || "";
              var title = tpl.title || "#" + (tpl.id || templateId);

              return el(
                "div",
                { className: "now-elt-flat", ref: rootRef },
                prevSrc
                  ? el("img", {
                      className:
                        "now-elt-canvas-preview now-elt-canvas-preview--large",
                      src: prevSrc,
                      alt: "",
                      draggable: false,
                      onDragStart: function (e) {
                        e.preventDefault();
                      },
                      style: { userSelect: "none" },
                    })
                  : null,
                el(
                  "div",
                  { className: "now-elt-tabbar", style: { marginTop: 12 } },
                  el(
                    "div",
                    { role: "tablist" },
                    TabBtn("content", __("Indhold", "nowonline")),
                    TabBtn("design", __("Design", "nowonline")),
                    TabBtn("background", __("Baggrund", "nowonline")),
                    TabBtn("advanced", __("Advanced", "nowonline"))
                  )
                ),
                el(
                  "div",
                  {
                    className: "nowelt-flat-titlebar",
                    style: {
                      display: "flex",
                      alignItems: "center",
                      gap: 8,
                      marginTop: 8,
                    },
                  },
                  el(
                    "h2",
                    { className: "nowelt-flat-title", style: { margin: 0 } },
                    title
                  ),
                  el(
                    Button,
                    {
                      className: "button is-secondary",
                      onClick: function () {
                        setShowEditor(false);
                      },
                      style: { marginLeft: "auto" },
                    },
                    __("Vis preview", "nowonline")
                  )
                ),
                el("div", { style: { marginTop: 10 } }, childrenEl)
              );
            }

            function ContentTab() {
              var btnSection = ButtonSection();
              var flatItems = []
                .concat(btnSection ? [btnSection] : [])
                .concat(
                  richInputs,
                  textInputs,
                  areaInputs,
                  linkInputs,
                  videoInputs,
                  imageInputs,
                  galleryInputs
                );
              return el("div", {}, flatItems);
            }

            function DesignTab() {
              function ResetTypography() {
                setAttr({
                  fsH1: "",
                  fsH2: "",
                  fsH3: "",
                  fsH4: "",
                  fsH5: "",
                  fsH6: "",
                  fsBody: "",
                  fsBtn: "",
                });
              }
              function sizeHelp() {
                return __(
                  "Angiv CSS-størrelser for desktop (≥1025px). Eksempler: 48px, 3rem, 120%",
                  "nowonline"
                );
              }

              return el(
                "div",
                {},
                el(
                  PanelBody,
                  { title: __("Background", "nowonline"), initialOpen: true },
                  el(
                    "div",
                    { className: "now-elt-row-flex" },
                    el(
                      "div",
                      { className: "now-elt-row-label" },
                      __("Baggrundsfarve", "nowonline")
                    ),
                    el(ColorPalette, {
                      value: containerBg || "",
                      onChange: function (v) {
                        setAttr({ containerBg: v || "" });
                      },
                    }),
                    el(TextControl, {
                      value: containerBg || "",
                      onChange: function (v) {
                        setAttr({ containerBg: (v || "").trim() });
                      },
                      placeholder:
                        "fx #cf4747, rgb(), rgba(), hsl(), var(--token), red",
                      __next40pxDefaultSize: true,
                      __nextHasNoMarginBottom: true,
                      className: "now-elt-input-wide",
                    }),
                    el(
                      Button,
                      {
                        className: "button is-secondary",
                        onClick: function () {
                          setAttr({ containerBg: "" });
                        },
                      },
                      __("Nulstil baggrund", "nowonline")
                    )
                  )
                ),

                el(
                  PanelBody,
                  { title: __("Knap", "nowonline"), initialOpen: false },
                  el(
                    "div",
                    { className: "now-elt-row-flex" },
                    el(
                      "div",
                      { className: "now-elt-row-label" },
                      __("Tekstfarve", "nowonline")
                    ),
                    el(ColorPalette, {
                      value: btnTextColor || "",
                      onChange: function (v) {
                        setAttr({ btnTextColor: v || "" });
                      },
                    }),
                    el(TextControl, {
                      value: btnTextColor || "",
                      onChange: function (v) {
                        setAttr({ btnTextColor: (v || "").trim() });
                      },
                      placeholder: __("fx #ffffff eller rgba()", "nowonline"),
                      __next40pxDefaultSize: true,
                      __nextHasNoMarginBottom: true,
                      className: "now-elt-input-wide",
                    })
                  ),
                  el(
                    "div",
                    { className: "now-elt-row-flex" },
                    el(
                      "div",
                      { className: "now-elt-row-label" },
                      __("Borderfarve", "nowonline")
                    ),
                    el(ColorPalette, {
                      value: btnBorderColor || "",
                      onChange: function (v) {
                        setAttr({ btnBorderColor: v || "" });
                      },
                    }),
                    el(TextControl, {
                      value: btnBorderColor || "",
                      onChange: function (v) {
                        setAttr({ btnBorderColor: (v || "").trim() });
                      },
                      placeholder: __("fx #000000 eller rgba()", "nowonline"),
                      __next40pxDefaultSize: true,
                      __nextHasNoMarginBottom: true,
                      className: "now-elt-input-wide",
                    })
                  ),
                  el(TextControl, {
                    label: __("Border bredde", "nowonline"),
                    value: btnBorderWidth || "",
                    onChange: function (v) {
                      setAttr({ btnBorderWidth: (v || "").trim() });
                    },
                    help: __(
                      "Fx 2px, 0.125rem eller 0 for ingen.",
                      "nowonline"
                    ),
                  }),
                  el(TextControl, {
                    label: __("Border radius", "nowonline"),
                    value: btnBorderRadius || "",
                    onChange: function (v) {
                      setAttr({ btnBorderRadius: (v || "").trim() });
                    },
                    help: __("Fx 8px, 0.5rem eller 50%.", "nowonline"),
                  }),
                  el(
                    "div",
                    { className: "now-elt-mt-8" },
                    el(
                      Button,
                      {
                        className: "button is-secondary",
                        onClick: function () {
                          setAttr({
                            btnTextColor: "",
                            btnBorderColor: "",
                            btnBorderWidth: "",
                            btnBorderRadius: "",
                          });
                        },
                      },
                      __("Nulstil knap", "nowonline")
                    )
                  )
                ),

                el(
                  PanelBody,
                  {
                    title: __("Typografi (desktop)", "nowonline"),
                    initialOpen: false,
                  },
                  el(
                    "div",
                    { className: "now-elt-grid-2" },
                    el(TextControl, {
                      label: "H1",
                      value: fsH1,
                      onChange: function (v) {
                        setAttr({ fsH1: (v || "").trim() });
                      },
                      placeholder: "fx 64px",
                      help: sizeHelp(),
                    }),
                    el(TextControl, {
                      label: "H2",
                      value: fsH2,
                      onChange: function (v) {
                        setAttr({ fsH2: (v || "").trim() });
                      },
                      placeholder: "fx 48px",
                      help: sizeHelp(),
                    }),
                    el(TextControl, {
                      label: "H3",
                      value: fsH3,
                      onChange: function (v) {
                        setAttr({ fsH3: (v || "").trim() });
                      },
                      placeholder: "fx 40px",
                      help: sizeHelp(),
                    }),
                    el(TextControl, {
                      label: "H4",
                      value: fsH4,
                      onChange: function (v) {
                        setAttr({ fsH4: (v || "").trim() });
                      },
                      placeholder: "fx 32px",
                      help: sizeHelp(),
                    }),
                    el(TextControl, {
                      label: "H5",
                      value: fsH5,
                      onChange: function (v) {
                        setAttr({ fsH5: (v || "").trim() });
                      },
                      placeholder: "fx 24px",
                      help: sizeHelp(),
                    }),
                    el(TextControl, {
                      label: "H6",
                      value: fsH6,
                      onChange: function (v) {
                        setAttr({ fsH6: (v || "").trim() });
                      },
                      placeholder: "fx 20px",
                      help: sizeHelp(),
                    }),
                    el(TextControl, {
                      label: __("Brødtekst", "nowonline"),
                      value: fsBody,
                      onChange: function (v) {
                        setAttr({ fsBody: (v || "").trim() });
                      },
                      placeholder: "fx 18px",
                      help: sizeHelp(),
                    }),
                    el(TextControl, {
                      label: __("Knap", "nowonline"),
                      value: fsBtn,
                      onChange: function (v) {
                        setAttr({ fsBtn: (v || "").trim() });
                      },
                      placeholder: "fx 16px",
                      help: sizeHelp(),
                    })
                  ),
                  el(
                    Button,
                    {
                      className: "button is-secondary",
                      onClick: ResetTypography,
                      style: { marginTop: 6 },
                    },
                    __("Nulstil typografi", "nowonline")
                  )
                )
              );
            }

            function BackgroundTab() {
              function ImgPicker(label, key) {
                var url = props.attributes[key] || "";
                function onSelect(media) {
                  var obj = {};
                  obj[key] = (media && media.url) || "";
                  setAttr(obj);
                }
                function clear() {
                  var obj = {};
                  obj[key] = "";
                  setAttr(obj);
                }
                return el(
                  "div",
                  { className: "now-elt-sec-item" },
                  el("div", { className: "now-elt-label" }, label),
                  el(
                    "div",
                    { className: "now-elt-field" },
                    url
                      ? el("img", {
                          src: url,
                          alt: "",
                          className: "now-elt-imgprev now-elt-bg-prev",
                          draggable: false,
                          onDragStart: function (e) {
                            e.preventDefault();
                          },
                        })
                      : el(
                          "div",
                          { className: "now-elt-imgprev now-elt-noimg" },
                          __("No image selected", "nowonline")
                        ),
                    MediaUpload
                      ? el(MediaUpload, {
                          onSelect: onSelect,
                          value: 0,
                          allowedTypes: ["image"],
                          render: function (o) {
                            return el(
                              "div",
                              { className: "now-elt-btnrow" },
                              el(
                                "button",
                                { className: "button", onClick: o.open },
                                __("Add Image", "nowonline")
                              ),
                              el(
                                "button",
                                {
                                  className: "button is-secondary",
                                  onClick: clear,
                                },
                                __("Clear", "nowonline")
                              )
                            );
                          },
                        })
                      : null
                  )
                );
              }

              function VideoPicker() {
                var url = props.attributes.bgVideo || "";
                function onSelect(media) {
                  setAttr({ bgVideo: (media && media.url) || "" });
                }
                return el(
                  "div",
                  { className: "now-elt-sec-item" },
                  el(
                    "div",
                    { className: "now-elt-label" },
                    __("Baggrundsvideo", "nowonline")
                  ),
                  el(
                    "div",
                    { className: "now-elt-field" },
                    url
                      ? el("video", {
                          src: url,
                          controls: true,
                          className: "now-elt-video-prev now-elt-bg-video-prev",
                        })
                      : el(
                          "div",
                          { className: "now-elt-imgprev now-elt-noimg" },
                          __("No video selected", "nowonline")
                        ),
                    MediaUpload
                      ? el(MediaUpload, {
                          onSelect: onSelect,
                          value: 0,
                          allowedTypes: ["video"],
                          render: function (o) {
                            return el(
                              "div",
                              {},
                              el(
                                "div",
                                { className: "now-elt-btnrow" },
                                el(
                                  "button",
                                  { className: "button", onClick: o.open },
                                  __("Choose video", "nowonline")
                                ),
                                el(
                                  "button",
                                  {
                                    className: "button is-secondary",
                                    onClick: function () {
                                      setAttr({ bgVideo: "" });
                                    },
                                  },
                                  __("Remove", "nowonline")
                                )
                              ),
                              el(TextControl, {
                                value: url || "",
                                onChange: function (v) {
                                  setAttr({ bgVideo: (v || "").trim() });
                                },
                                placeholder: __(
                                  "or paste video URL…",
                                  "nowonline"
                                ),
                                className: "now-elt-mt-8 now-elt-input-wide",
                              })
                            );
                          },
                        })
                      : null
                  )
                );
              }

              return el(
                "div",
                {},
                el(
                  PanelBody,
                  {
                    title: __("Baggrundsvideo", "nowonline"),
                    initialOpen: true,
                  },
                  VideoPicker()
                ),
                el(
                  PanelBody,
                  {
                    title: __("Baggrundsbillede", "nowonline"),
                    initialOpen: true,
                  },
                  ImgPicker(__("Baggrundsbillede", "nowonline"), "bgImg"),
                  el(
                    "div",
                    { className: "now-elt-grid-3" },
                    el(
                      "div",
                      {},
                      el(
                        "div",
                        { className: "now-elt-label" },
                        __("Background position", "nowonline")
                      ),
                      el(
                        "select",
                        {
                          value: bgPos,
                          onChange: function (e) {
                            setAttr({ bgPos: e.target.value });
                          },
                        },
                        [
                          "center center",
                          "top center",
                          "bottom center",
                          "center left",
                          "center right",
                          "top left",
                          "top right",
                          "bottom left",
                          "bottom right",
                          // ekstra short-hands matcher backend-sanitizeren
                          "center",
                          "top",
                          "bottom",
                          "left",
                          "right",
                        ].map(function (p) {
                          return el("option", { key: p, value: p }, p);
                        })
                      )
                    ),
                    el(
                      "div",
                      {},
                      el(
                        "div",
                        { className: "now-elt-label" },
                        __("Background size", "nowonline")
                      ),
                      el(
                        "select",
                        {
                          value: bgSize,
                          onChange: function (e) {
                            setAttr({ bgSize: e.target.value });
                          },
                        },
                        ["cover", "contain", "auto"].map(function (s) {
                          return el("option", { key: s, value: s }, s);
                        })
                      )
                    ),
                    el(
                      "div",
                      {},
                      el(
                        "div",
                        { className: "now-elt-label" },
                        __("Background Fixed", "nowonline")
                      ),
                      el(CheckboxControl, {
                        checked: !!bgFixed,
                        onChange: function (v) {
                          setAttr({ bgFixed: !!v });
                        },
                        label: bgFixed
                          ? __("Yes", "nowonline")
                          : __("No", "nowonline"),
                      })
                    )
                  )
                ),
                el(
                  PanelBody,
                  {
                    title: __("Baggrundsbillede (tablet)", "nowonline"),
                    initialOpen: false,
                  },
                  ImgPicker(__("Tablet background", "nowonline"), "bgImgTablet")
                ),
                el(
                  PanelBody,
                  {
                    title: __("Baggrundsbillede (telefon)", "nowonline"),
                    initialOpen: false,
                  },
                  ImgPicker(__("Mobile background", "nowonline"), "bgImgMobile")
                )
              );
            }

            function AdvancedTab() {
              function unitHelp() {
                return __(
                  "Eksempler: 80px, 6rem, 10vh, 8% — tom = ingen ændring",
                  "nowonline"
                );
              }
              function RowTwo(aLabel, aKey, aVal, bLabel, bKey, bVal) {
                return el(
                  "div",
                  { className: "now-elt-grid-2" },
                  el(TextControl, {
                    label: aLabel,
                    value: aVal || "",
                    onChange: function (v) {
                      var n = {};
                      n[aKey] = (v || "").trim();
                      setAttr(n);
                    },
                    placeholder: "fx 80px",
                    help: unitHelp(),
                  }),
                  el(TextControl, {
                    label: bLabel,
                    value: bVal || "",
                    onChange: function (v) {
                      var n = {};
                      n[bKey] = (v || "").trim();
                      setAttr(n);
                    },
                    placeholder: "fx 80px",
                    help: unitHelp(),
                  })
                );
              }
              function ResetBtn(keys) {
                return el(
                  Button,
                  {
                    className: "button is-secondary",
                    onClick: function () {
                      var n = {};
                      keys.forEach(function (k) {
                        n[k] = "";
                      });
                      setAttr(n);
                    },
                    style: { marginTop: 4 },
                  },
                  __("Nulstil", "nowonline")
                );
              }

              var hideDesktop = !!attrs.hideDesktop;
              var hideTablet = !!attrs.hideTablet;
              var hideMobile = !!attrs.hideMobile;

              return el(
                "div",
                {},
                el(
                  PanelBody,
                  { title: __("Visibility", "nowonline"), initialOpen: true },
                  el(CheckboxControl, {
                    label: __("Skjul på computer", "nowonline"),
                    checked: hideDesktop,
                    onChange: function (v) {
                      setAttr({ hideDesktop: !!v });
                    },
                  }),
                  el(CheckboxControl, {
                    label: __("Skjul på tablet", "nowonline"),
                    checked: hideTablet,
                    onChange: function (v) {
                      setAttr({ hideTablet: !!v });
                    },
                  }),
                  el(CheckboxControl, {
                    label: __("Skjul på telefon", "nowonline"),
                    checked: hideMobile,
                    onChange: function (v) {
                      setAttr({ hideMobile: !!v });
                    },
                  }),
                  el(
                    "div",
                    { className: "now-elt-muted", style: { marginTop: 8 } },
                    __(
                      "Skjuler hele blokken pr. device. Renderes via server (frontend).",
                      "nowonline"
                    )
                  )
                ),

                el(
                  PanelBody,
                  { title: __("Computer", "nowonline"), initialOpen: true },
                  RowTwo(
                    __("Padding top (desktop)", "nowonline"),
                    "padTopDesktop",
                    padTopDesktop,
                    __("Padding bottom (desktop)", "nowonline"),
                    "padBottomDesktop",
                    padBottomDesktop
                  ),
                  ResetBtn(["padTopDesktop", "padBottomDesktop"])
                ),
                el(
                  PanelBody,
                  { title: __("Bærbar", "nowonline"), initialOpen: false },
                  RowTwo(
                    __("Padding top (laptop)", "nowonline"),
                    "padTopLaptop",
                    padTopLaptop,
                    __("Padding bottom (laptop)", "nowonline"),
                    "padBottomLaptop",
                    padBottomLaptop
                  ),
                  ResetBtn(["padTopLaptop", "padBottomLaptop"])
                ),
                el(
                  PanelBody,
                  { title: __("Tablet", "nowonline"), initialOpen: false },
                  RowTwo(
                    __("Padding top (tablet)", "nowonline"),
                    "padTopTablet",
                    padTopTablet,
                    __("Padding bottom (tablet)", "nowonline"),
                    "padBottomTablet",
                    padBottomTablet
                  ),
                  ResetBtn(["padTopTablet", "padBottomTablet"])
                ),
                el(
                  PanelBody,
                  { title: __("Telefon", "nowonline"), initialOpen: false },
                  RowTwo(
                    __("Padding top (mobile)", "nowonline"),
                    "padTopMobile",
                    padTopMobile,
                    __("Padding bottom (mobile)", "nowonline"),
                    "padBottomMobile",
                    padBottomMobile
                  ),
                  ResetBtn(["padTopMobile", "padBottomMobile"])
                )
              );
            }

            var blockProps = useBlockProps ? useBlockProps() : {};
            var rootStyle = Object.assign(
              {},
              (blockProps && blockProps.style) || {},
              {
                width: "100%",
                flexBasis: "100%",
                alignSelf: "stretch",
                flexGrow: 1,
                flexShrink: 0,
              }
            );
            var rootProps = Object.assign({}, blockProps, {
              ref: rootRef,
              style: rootStyle,
              className: (
                (blockProps.className || "") + " now-elt-edit-root"
              ).trim(),
            });

            if (!showEditor) return el("div", rootProps, PreviewFirstLayer());

            var tabContent =
              activeTab === "design"
                ? DesignTab()
                : activeTab === "background"
                ? BackgroundTab()
                : activeTab === "advanced"
                ? AdvancedTab()
                : ContentTab();

            return el("div", rootProps, EditorShell(tabContent));
          },

          save: function () {
            return null;
          },
        });
      }

      if (
        Blocks &&
        typeof Blocks.registerBlockVariation === "function" &&
        Array.isArray(window.NOWONLINE_TEMPLATES_DECODED)
      ) {
        window.NOWONLINE_TEMPLATES_DECODED.forEach(function (t) {
          var icon = t.thumb
            ? el("span", {
                className: "now-elt-var-thumb",
                style: { backgroundImage: "url(" + t.thumb + ")" },
                draggable: false,
                "aria-hidden": true,
                onDragStart: function (e) {
                  e.preventDefault && e.preventDefault();
                },
                onMouseDown: function (e) {
                  e.preventDefault && e.preventDefault();
                },
              })
            : Icon();

          Blocks.registerBlockVariation("nowonline/elt-template", {
            name: "nowonline-elt-" + t.id,
            title: t.title || "#" + t.id,
            description: __("Elementor template", "nowonline"),
            icon: icon,
            attributes: { templateId: t.id },
            example: { attributes: { templateId: t.id } },
            scope: ["inserter"],
            keywords: ["elementor", "template", "nowonline"],
          });
        });
      }

      if (window.console) console.info("[NowOnline] ELT registered.");
    } catch (e) {
      if (window && window.console)
        console.warn("[NowOnline Elementor Blocks] init error", e);
    }
  });

  if (typeof module !== "undefined" && module.exports) {
    module.exports = { decodeEntities, getPreviewSrc };
  }
})();

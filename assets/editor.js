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
  var el = (WP.element && WP.element.createElement) || function () {};
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
  var data = WP.data || {};
  var HAS_USESELECT = !!(data && typeof data.useSelect === "function");

  var PanelBody =
    C.PanelBody ||
    function (p) {
      return el("div", p, p.children);
    };
  var TextControl =
    C.TextControl ||
    function (p) {
      return el("input", Object.assign({ type: "text" }, p));
    };
  var TextareaControl =
    C.TextareaControl ||
    function (p) {
      return el("textarea", Object.assign({ rows: 4 }, p));
    };
  var CheckboxControl =
    C.CheckboxControl ||
    function (p) {
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
  var SelectControl =
    C.SelectControl ||
    function (p) {
      return el(
        "label",
        {},
        p.label || "",
        el(
          "select",
          {
            value: p.value,
            onChange: function (e) {
              p.onChange && p.onChange(e.target.value);
            },
            style: { display: "block", marginTop: 4 },
          },
          (p.options || []).map(function (o) {
            return el("option", { value: o.value, key: o.value }, o.label);
          })
        )
      );
    };
  var ColorPalette =
    C.ColorPalette ||
    function (p) {
      return el("input", {
        type: "color",
        value: p.value || "#000000",
        onChange: function (e) {
          p.onChange && p.onChange(e.target.value);
        },
      });
    };

  var InspectorControls = B.InspectorControls || "div";
  var MediaUpload = B.MediaUpload;
  var RichText = B.RichText;
  var TabPanel = C.TabPanel;
  var useBlockProps =
    B && B.useBlockProps
      ? B.useBlockProps
      : function () {
          return {};
        };

  // --- React hooks -----------------------------------------------------------
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
  var useSelect = HAS_USESELECT ? data.useSelect : null;

  // Classic editor / TinyMCE API
  var OldEditor = (WP && (WP.editor || WP.oldEditor)) || null;

  // LinkControl (fallback)
  var LinkControl =
    (B && (B.__experimentalLinkControl || B.LinkControl)) ||
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

  function getFieldDefs(id) {
    var M = window.NOWONLINE_FIELDS || {};
    return (M && M[id]) || [];
  }
  function labelFor(def) {
    return def.label || def.key;
  }
  function tplById(id) {
    var list = Array.isArray(window.NOWONLINE_TEMPLATES)
      ? window.NOWONLINE_TEMPLATES
      : [];
    id = parseInt(id || 0, 10);
    for (var i = 0; i < list.length; i++)
      if (parseInt(list[i].id, 10) === id) return list[i];
    return null;
  }

  // ---- URL normalisering (klient) ------------------------------------------
  function fixUrl(u) {
    u = (u || "").trim();
    if (!u) return u;
    if (u.indexOf("//") === 0) u = "https:" + u;
    u = u
      .replace(/^http\/:\/\//i, "http://")
      .replace(/^https\/:\/\//i, "https://");
    u = u.replace(/^(https?:\/\/)(https?:\/\/)/i, "$1");
    u = u.replace(/^(https?:\/\/)+/i, "$1");
    if (/^www\./i.test(u)) u = "https://" + u;
    if (!/^[a-z][a-z0-9+.\-]*:\/\//i.test(u) && /^[^\/\s]+\.[^\s]+/.test(u)) {
      u = "https://" + u;
    }
    return u;
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

  // --- Image field -----------------------------------------------------------
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
    return el(
      "div",
      { key: def.key, className: "now-elt-sec-item" },
      el("label", { className: "now-elt-label" }, labelFor(def)),
      preview,
      MediaUpload
        ? el(MediaUpload, {
            onSelect: onSelect,
            allowedTypes: ["image"],
            value: 0,
            render: function (o) {
              return el(
                "div",
                {},
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
    );
  }

  // --- Video field -----------------------------------------------------------
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
      var u = (media && media.url) || "";
      setField(key, u);
    }
    function onSelectPoster(media) {
      var u = (media && media.url) || "";
      setField(posterKey, u);
    }
    var vidPreview = url
      ? el(
          "video",
          {
            src: url,
            poster: poster || undefined,
            controls: true,
            style: { width: "100%", maxWidth: "420px", display: "block" },
          },
          null
        )
      : el(
          "div",
          { className: "now-elt-imgprev now-elt-noimg" },
          __("Ingen video valgt", "nowonline")
        );
    var posterPreview = poster
      ? el("img", {
          src: poster,
          alt: "",
          className: "now-elt-imgprev",
          style: { maxWidth: "210px", marginTop: "6px" },
        })
      : null;
    return el(
      "div",
      { key: key, className: "now-elt-sec-item" },
      el("label", { className: "now-elt-label" }, labelFor(def)),
      vidPreview,
      MediaUpload
        ? el(MediaUpload, {
            onSelect: onSelectVideo,
            allowedTypes: ["video"],
            value: 0,
            render: function (o) {
              return el(
                "div",
                { style: { marginTop: "6px" } },
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
                    style: { marginLeft: "6px" },
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
                  style: { display: "block", marginTop: "8px" },
                })
              );
            },
          })
        : el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline")),
      el(
        "div",
        { style: { marginTop: "10px" } },
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
                  {},
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
                      style: { marginLeft: "6px" },
                    },
                    __("Fjern", "nowonline")
                  )
                );
              },
            })
          : null
      )
    );
  }

  // --- Gallery field ---------------------------------------------------------
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
              style: { maxWidth: "120px", marginRight: "6px" },
            });
          })
        )
      : el(
          "div",
          { className: "now-elt-imgprev now-elt-noimg" },
          __("Ingen billeder i galleriet", "nowonline")
        );
    return el(
      "div",
      { key: def.key, className: "now-elt-sec-item" },
      el("label", { className: "now-elt-label" }, labelFor(def)),
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
                {},
                el(
                  "button",
                  { className: "button", onClick: o.open },
                  __("Vælg billeder", "nowonline")
                ),
                el(
                  "button",
                  {
                    className: "button is-secondary",
                    onClick: clear,
                    style: { marginLeft: "6px" },
                  },
                  __("Ryd galleri", "nowonline")
                )
              );
            },
          })
        : el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline"))
    );
  }

  // --- PagePicker for URL fields (kun når hook findes) ----------------------
  function PagePicker(p) {
    var block = p.block,
      def = p.def,
      fieldKey = def.key;
    var current = (block.attributes.fields || {})[fieldKey];
    var currObj =
      current && typeof current === "object"
        ? current
        : { url: current || "", newTab: false, id: 0, type: "external" };

    var sel = useSelect(function (select) {
      if (!select || !select("core")) return { pages: [], isResolving: false };
      var s = select("core");
      var query = {
        per_page: 100,
        orderby: "title",
        order: "asc",
        status: ["publish", "private"],
      };
      return {
        pages: s.getEntityRecords
          ? s.getEntityRecords("postType", "page", query) || []
          : [],
        isResolving: s.isResolving
          ? s.isResolving("getEntityRecords", ["postType", "page", query])
          : false,
      };
    }, []);

    var _s = useState(""),
      search = _s[0],
      setSearch = _s[1];
    var pages = sel && sel.pages ? sel.pages : [];
    if (search && pages.length) {
      var needle = search.toLowerCase();
      pages = pages.filter(function (pg) {
        var t = pg.title && pg.title.rendered ? pg.title.rendered : "";
        return String(t).toLowerCase().indexOf(needle) !== -1;
      });
    }

    function pickPage(pg) {
      var next = Object.assign({}, block.attributes.fields || {});
      next[fieldKey] = {
        url: fixUrl(pg.link || (pg.guid && pg.guid.rendered) || ""),
        id: pg.id,
        type: "page",
        newTab: !!currObj.newTab,
        title: (pg.title && pg.title.rendered) || "",
      };
      block.setAttributes({ fields: next });
    }
    function setNewTab(v) {
      var next = Object.assign({}, block.attributes.fields || {});
      next[fieldKey] = Object.assign({}, currObj, { newTab: !!v });
      block.setAttributes({ fields: next });
    }
    function setManual(v) {
      var next = Object.assign({}, block.attributes.fields || {});
      next[fieldKey] = {
        url: fixUrl(v || ""),
        type: "external",
        newTab: !!currObj.newTab,
      };
      block.setAttributes({ fields: next });
    }

    var list = el(
      "div",
      {
        className: "now-elt-pages-list",
        style: {
          maxHeight: "220px",
          overflow: "auto",
          border: "1px solid #e2e4e7",
          borderRadius: "6px",
          padding: "6px",
        },
      },
      pages.map(function (pg) {
        var t = pg.title && pg.title.rendered ? pg.title.rendered : "#" + pg.id;
        var active = currObj && currObj.id === pg.id;
        return el(
          "button",
          {
            key: pg.id,
            type: "button",
            onClick: function () {
              pickPage(pg);
            },
            className: "button " + (active ? "is-primary" : "is-secondary"),
            style: {
              display: "block",
              width: "100%",
              textAlign: "left",
              marginBottom: "6px",
            },
          },
          String(t).replace(/<[^>]*>/g, "")
        );
      })
    );

    return el(
      "div",
      { className: "now-elt-sec-item" },
      el("div", { className: "now-elt-label" }, labelFor(def)),
      el(
        "div",
        { className: "now-elt-urlpicker" },
        el(
          "div",
          { style: { display: "flex", gap: "8px", marginBottom: "6px" } },
          el("input", {
            type: "search",
            placeholder: __("Søg side…", "nowonline"),
            value: search,
            onChange: function (e) {
              setSearch(e.target.value);
            },
            style: { flex: "1 1 auto" },
          }),
          el("input", {
            type: "url",
            placeholder: __("eller indsæt manuel URL…", "nowonline"),
            value: currObj.url || "",
            onChange: function (e) {
              setManual(e.target.value);
            },
            style: { flex: "2 1 auto" },
          })
        ),
        list,
        el(
          "div",
          { style: { marginTop: "8px" } },
          el(CheckboxControl, {
            label: __("Åbn i ny fane", "nowonline"),
            checked: !!currObj.newTab,
            onChange: setNewTab,
          })
        )
      )
    );
  }

  // --- Blok-registrering -----------------------------------------------------
  domReady(function () {
    try {
      var MAP = Array.isArray(window.NOWONLINE_TEMPLATES)
        ? window.NOWONLINE_TEMPLATES
        : [];
      if (!Blocks || !Blocks.registerBlockType) return;

      var already =
        Blocks.getBlockType && Blocks.getBlockType("nowonline/elt-template");
      if (!already) {
        Blocks.registerBlockType("nowonline/elt-template", {
          title: __("Elementor Template", "nowonline"),
          icon: Icon(),
          category: "nowonline-elementor",
          attributes: {
            templateId: { type: "number", default: 0 },
            gap: { type: "number", default: 24 },
            fields: { type: "object", default: {} },
            design: { type: "object", default: {} },
            background: { type: "object", default: {} },
            responsive: { type: "object", default: {} },
            spacing: { type: "object", default: {} },
            // Design tab attrs
            containerBg: { type: "string", default: "" },
            containerTargetMode: { type: "string", default: "auto" }, // auto | data-id | css_id | css_class | selector
            containerTarget: { type: "string", default: "" },
          },

          edit: function (props) {
            var attrs = props.attributes || {};
            var templateId = attrs.templateId || 0;
            var fields = attrs.fields || {};
            var containerBg = attrs.containerBg || "";
            var containerTargetMode = attrs.containerTargetMode || "auto";
            var containerTarget = attrs.containerTarget || "";

            function onPick(e) {
              var id = parseInt(e.target.value || "0", 10) || 0;
              props.setAttributes({ templateId: id, fields: {} });
            }
            function setField(k, v) {
              var next = Object.assign({}, fields);
              next[k] = v;
              props.setAttributes({ fields: next });
            }

            function setAttr(next) {
              props.setAttributes(next);
            }

            var picker = el(
              "select",
              {
                onChange: onPick,
                value: templateId || 0,
                className: "now-elt-select",
              },
              [
                el("option", { value: 0 }, __("Vælg template…", "nowonline")),
              ].concat(
                (MAP || []).map(function (x) {
                  return el(
                    "option",
                    { value: x.id, key: x.id },
                    x.title || "#" + x.id
                  );
                })
              )
            );

            // Feltdefinitioner for valgt template
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

            function Section(title, children) {
              if (!children || !children.length) return null;
              return el(
                "div",
                { className: "now-elt-sec" },
                el("div", { className: "now-elt-sec-title" }, title),
                children
              );
            }

            // Tekst (korte)
            var textInputs = textDefs
              .map(function (def) {
                var value = fields[def.key] || "";
                return el(TextareaControl, {
                  key: def.key,
                  label: labelFor(def),
                  value: value,
                  rows: 3,
                  onChange: function (v) {
                    setField(def.key, v);
                  },
                });
              })
              .concat(
                areaDefs.map(function (def) {
                  var value = fields[def.key] || "";
                  return el(TextareaControl, {
                    key: def.key,
                    label: labelFor(def),
                    value: value,
                    rows: 6,
                    onChange: function (v) {
                      setField(def.key, v);
                    },
                  });
                })
              );

            // URL inputs – PagePicker hvis hook findes, ellers fallback
            function UrlInput(def) {
              if (HAS_USESELECT)
                return el(PagePicker, { key: def.key, block: props, def: def });
              var val = fields[def.key];
              var curr =
                val && typeof val === "object"
                  ? val
                  : { url: val || "", newTab: false, type: "external" };
              return el(
                "div",
                { key: def.key, className: "now-elt-sec-item" },
                el("div", { className: "now-elt-label" }, labelFor(def)),
                LinkControl
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
                      value: curr.url || "",
                      onChange: function (v) {
                        setField(def.key, {
                          url: fixUrl(v || ""),
                          newTab: !!curr.newTab,
                        });
                      },
                    }),
                el(
                  "div",
                  { style: { marginTop: "6px" } },
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

            // --- Rich (TinyMCE hvis muligt, ellers RichText) -----------------
            function TinyMCEField(def) {
              var fieldKey = def.key;
              var initial =
                (props.attributes.fields &&
                  props.attributes.fields[fieldKey]) ||
                "";
              function safe(s) {
                return String(s || "").replace(/[^a-z0-9_-]/gi, "");
              }
              var instId = safe(
                props.clientId || Math.random().toString(36).slice(2, 8)
              );
              var uniqueId = "nowelt-" + instId + "-" + safe(fieldKey);
              var idRef = useRef(uniqueId);
              var taRef = useRef(null);
              useEffect(
                function () {
                  if (!OldEditor || !OldEditor.initialize) return;
                  try {
                    OldEditor.remove(idRef.current);
                  } catch (e) {}
                  OldEditor.initialize(idRef.current, {
                    tinymce: {
                      wpautop: true,
                      menubar: false,
                      toolbar1:
                        "formatselect,bold,italic,link,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo",
                    },
                    quicktags: true,
                    mediaButtons: false,
                  });
                  var ed, poll;
                  function sync() {
                    var val =
                      ed && typeof ed.getContent === "function"
                        ? ed.getContent()
                        : taRef.current
                        ? taRef.current.value
                        : "";
                    var next = Object.assign({}, props.attributes.fields || {});
                    next[fieldKey] = val || "";
                    props.setAttributes({ fields: next });
                  }
                  poll = setInterval(function () {
                    ed = window.tinymce && window.tinymce.get(idRef.current);
                    if (ed) {
                      clearInterval(poll);
                      if (initial) ed.setContent(initial);
                      ed.on("change keyup input setcontent", sync);
                    }
                  }, 50);
                  if (taRef.current)
                    taRef.current.addEventListener("input", sync);
                  return function () {
                    try {
                      OldEditor.remove(idRef.current);
                    } catch (e) {}
                    if (taRef.current)
                      taRef.current.removeEventListener("input", sync);
                    clearInterval(poll);
                  };
                },
                [props.clientId, props.attributes.templateId, fieldKey]
              );
              return el(
                "div",
                { key: fieldKey, className: "now-elt-sec-item" },
                el("div", { className: "now-elt-label" }, labelFor(def)),
                el("textarea", {
                  id: idRef.current,
                  ref: taRef,
                  defaultValue: initial,
                })
              );
            }

            var richInputs =
              OldEditor && OldEditor.initialize
                ? richDefs.map(TinyMCEField)
                : RichText
                ? richDefs.map(function (def) {
                    var value = fields[def.key] || "";
                    return el(
                      "div",
                      { key: def.key, className: "now-elt-sec-item" },
                      el("div", { className: "now-elt-label" }, labelFor(def)),
                      el(RichText, {
                        tagName: "div",
                        value: value,
                        onChange: function (v) {
                          setField(def.key, v || "");
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
                      })
                    );
                  })
                : [
                    el(
                      "div",
                      { key: "norich", className: "now-elt-muted" },
                      __("RichText/TinyMCE ikke tilgængelig.", "nowonline")
                    ),
                  ];

            // --- Tabs content -------------------------------------------------
            function ContentTab() {
              var pickerRow = el(
                "div",
                { className: "now-elt-picker" },
                picker
              );
              return el(
                "div",
                {},
                pickerRow,
                Section(__("Formateret tekst", "nowonline"), richInputs),
                Section(__("Tekster", "nowonline"), textInputs),
                Section(__("Links", "nowonline"), linkInputs),
                Section(__("Videoer", "nowonline"), videoInputs),
                Section(
                  __("Billeder", "nowonline"),
                  imageInputs.concat(galleryInputs)
                )
              );
            }

            function DesignTab() {
              return el(
                "div",
                {},
                el(
                  PanelBody,
                  { title: __("Background", "nowonline"), initialOpen: true },
                  el(
                    "div",
                    {
                      style: { display: "flex", alignItems: "center", gap: 8 },
                    },
                    el(
                      "div",
                      { style: { minWidth: 120 } },
                      __("Container bg", "nowonline")
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
                        setAttr({ containerBg: v || "" });
                      },
                      placeholder: __(
                        "fx #000, rgb(), var(--token)",
                        "nowonline"
                      ),
                      style: { maxWidth: 220 },
                    })
                  )
                ),
                el(
                  PanelBody,
                  {
                    title: __("Target container", "nowonline"),
                    initialOpen: false,
                  },
                  el(SelectControl, {
                    label: __("Mode", "nowonline"),
                    value: containerTargetMode,
                    onChange: function (v) {
                      setAttr({ containerTargetMode: v });
                    },
                    options: [
                      {
                        label: __("Auto (outer wrapper)", "nowonline"),
                        value: "auto",
                      },
                      {
                        label: __("Elementor data-id", "nowonline"),
                        value: "data-id",
                      },
                      {
                        label: __("CSS id (#id)", "nowonline"),
                        value: "css_id",
                      },
                      {
                        label: __("CSS class (.class)", "nowonline"),
                        value: "css_class",
                      },
                      {
                        label: __("Custom selector", "nowonline"),
                        value: "selector",
                      },
                    ],
                  }),
                  containerTargetMode !== "auto"
                    ? el(TextControl, {
                        label: __("Value", "nowonline"),
                        value: containerTarget || "",
                        onChange: function (v) {
                          setAttr({ containerTarget: v || "" });
                        },
                        placeholder:
                          containerTargetMode === "data-id"
                            ? __("e.g. e05f2aa", "nowonline")
                            : containerTargetMode === "css_id"
                            ? __("e.g. hero (without #)", "nowonline")
                            : containerTargetMode === "css_class"
                            ? __("e.g. section-hero (without .)", "nowonline")
                            : __("e.g. .elementor-section .inner", "nowonline"),
                      })
                    : null
                )
              );
            }

            function BackgroundTab() {
              return el(
                "div",
                {},
                el(
                  "div",
                  { className: "now-elt-muted" },
                  __("(Reserved)", "nowonline")
                )
              );
            }
            function AdvancedTab() {
              return el(
                "div",
                {},
                el(
                  "div",
                  { className: "now-elt-muted" },
                  __("(Reserved)", "nowonline")
                )
              );
            }

            var tabsUI = TabPanel
              ? el(
                  TabPanel,
                  {
                    tabs: [
                      {
                        name: "content",
                        title: __("Indhold", "nowonline"),
                        className: "nowonline-tab",
                      },
                      {
                        name: "design",
                        title: __("Design", "nowonline"),
                        className: "nowonline-tab",
                      },
                      {
                        name: "background",
                        title: __("Baggrund", "nowonline"),
                        className: "nowonline-tab",
                      },
                      {
                        name: "advanced",
                        title: __("Advanced", "nowonline"),
                        className: "nowonline-tab",
                      },
                    ],
                    initialTabName: "content",
                  },
                  function (tab) {
                    if (!tab || !tab.name) return ContentTab();
                    if (tab.name === "design") return DesignTab();
                    if (tab.name === "background") return BackgroundTab();
                    if (tab.name === "advanced") return AdvancedTab();
                    return ContentTab();
                  }
                )
              : el("div", {}, ContentTab());

            var blockProps = useBlockProps ? useBlockProps() : {};
            var rootProps = Object.assign({}, blockProps, {
              className: (
                (blockProps.className || "") + " now-elt-edit-root"
              ).trim(),
            });
            // Editor preview of background color on wrapper
            if (containerBg) {
              rootProps.style = Object.assign({}, rootProps.style || {}, {
                backgroundColor: containerBg,
              });
            }

            return el(
              "div",
              rootProps,
              el(function CanvasPreview() {
                var tpl = tplById(templateId) || {};
                var src = tpl.preview || tpl.thumb || "";
                if (!src) return null;
                return el("img", {
                  src: src,
                  alt: "",
                  draggable: false,
                  decoding: "async",
                  style: {
                    display: "block",
                    maxWidth: "100%",
                    height: "auto",
                    margin: "0 auto",
                  },
                });
              }, {}),
              tabsUI,
              el(
                InspectorControls,
                {},
                el(
                  PanelBody,
                  { title: __("(Info)", "nowonline"), initialOpen: false },
                  el(
                    "div",
                    {},
                    __("Denne blok rendres på frontend.", "nowonline")
                  )
                )
              )
            );
          },

          save: function () {
            return null;
          },
          supports: { inserter: true },
        });
      }

      // Variationer (en pr. Elementor-template)
      if (
        Blocks &&
        typeof Blocks.registerBlockVariation === "function" &&
        Array.isArray(MAP)
      ) {
        MAP.forEach(function (t) {
          var icon = t.thumb
            ? function () {
                return el("img", {
                  src: t.thumb,
                  alt: "",
                  className: "now-elt-var-thumb",
                });
              }
            : Icon();
          Blocks.registerBlockVariation("nowonline/elt-template", {
            name: "nowonline-elt-" + t.id,
            title: t.title || "#" + t.id,
            description: __("Elementor template", "nowonline"),
            icon: icon,
            attributes: { templateId: t.id },
            scope: ["inserter"],
            keywords: ["elementor", "template", "nowonline"],
          });
        });
      }

      if (window.console)
        console.info(
          "[NowOnline] ELT variations:",
          Array.isArray(MAP) ? MAP.length : 0
        );
    } catch (e) {
      if (window && window.console)
        console.warn("[NowOnline Elementor Blocks] init error", e);
    }
  });
})();

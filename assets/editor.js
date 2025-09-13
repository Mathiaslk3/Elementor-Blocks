// File: assets/editor.js
// @ts-nocheck
(function () {
  "use strict";

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
  var ToggleControl =
    C.ToggleControl ||
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
      var opts = (p.options || []).map(function (o) {
        return el("option", { value: o.value }, o.label);
      });
      return el(
        "label",
        {},
        el("span", { className: "now-elt-label" }, p.label || ""),
        el(
          "select",
          Object.assign({}, p, {
            onChange: function (e) {
              p.onChange && p.onChange(e.target.value);
            },
          }),
          opts
        )
      );
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
  var RangeControl = C.RangeControl;
  function RangeOrNumber(props) {
    return RangeControl
      ? el(RangeControl, props)
      : el(TextControl, Object.assign({}, props, { type: "number" }));
  }

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

  // React hooks
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
  var useSelect =
    (data && data.useSelect) ||
    function () {
      return null;
    };

  // Classic editor / TinyMCE API (til [[rich]] fallback)
  var OldEditor = (WP && (WP.editor || WP.oldEditor)) || null;

  // LinkControl (fallback)
  var LinkControl =
    (B && (B.__experimentalLinkControl || B.LinkControl)) ||
    (C && C.__experimentalLinkControl) ||
    null;

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
    for (var i = 0; i < list.length; i++) {
      if (parseInt(list[i].id, 10) === id) return list[i];
    }
    return null;
  }

  // --- typer ---
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
    if (["titel", "undertitel", "beskrivelse"].indexOf(_k) >= 0) return "rich";
    if (_k === "billede") return "img";
    if (_k === "galleri") return "gallery";
    if (!_t || _t === "text") {
      if (/(rich|wysiwyg|rte|editor|html)/.test(_k)) return "rich";
      if (/textarea|longtext|multiline/.test(_k)) return "textarea";
      if (/url|link|href/.test(_k)) return "url";
      if (/^img|image|photo/.test(_k)) return "img";
      if (/bg|background/.test(_k)) return "bg";
      if (/galleri|gallery/.test(_k)) return "gallery";
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
  function isHeadingOrText(d) {
    var n = norm(d);
    return n === "text" || n === "p" || /^h[1-6]$/.test(n) || !n;
  }

  // --- enkelt billede ---
  function ImageField(props, def) {
    var url = (props.attributes.fields || {})[def.key] || "";
    function onSelect(media) {
      var u = (media && media.url) || "";
      var next = Object.assign({}, props.attributes.fields || {});
      next[def.key] = u;
      props.setAttributes({ fields: next });
    }
    function clear() {
      var next = Object.assign({}, props.attributes.fields || {});
      delete next[def.key];
      props.setAttributes({ fields: next });
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

  // --- galleri ---
  function GalleryField(props, def) {
    var value = (props.attributes.fields || {})[def.key] || [];
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
      var next = Object.assign({}, props.attributes.fields || {});
      next[def.key] = urls;
      props.setAttributes({ fields: next });
    }
    function clear() {
      var next = Object.assign({}, props.attributes.fields || {});
      delete next[def.key];
      props.setAttributes({ fields: next });
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

  // --- NY: PagePicker til URL felter ---
  function PagePicker(props, def) {
    var fieldKey = def.key;
    var current = (props.attributes.fields || {})[fieldKey];
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

    var _useState = useState(""),
      search = _useState[0],
      setSearch = _useState[1];

    var pages = sel && sel.pages ? sel.pages : [];
    if (search && pages.length) {
      var s = search.toLowerCase();
      pages = pages.filter(function (p) {
        var t = p.title && p.title.rendered ? p.title.rendered : "";
        return String(t).toLowerCase().indexOf(s) !== -1;
      });
    }

    function pickPage(p) {
      var next = Object.assign({}, props.attributes.fields || {});
      next[fieldKey] = {
        url: p.link || p.guid?.rendered || "",
        id: p.id,
        type: "page",
        newTab: !!currObj.newTab,
        title: (p.title && p.title.rendered) || "",
      };
      props.setAttributes({ fields: next });
    }
    function setNewTab(v) {
      var next = Object.assign({}, props.attributes.fields || {});
      next[fieldKey] = Object.assign({}, currObj, { newTab: !!v });
      props.setAttributes({ fields: next });
    }
    function setManual(v) {
      var next = Object.assign({}, props.attributes.fields || {});
      next[fieldKey] = {
        url: v || "",
        type: "external",
        newTab: !!currObj.newTab,
      };
      props.setAttributes({ fields: next });
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
      pages.map(function (p) {
        var t = p.title && p.title.rendered ? p.title.rendered : "#" + p.id;
        var active = currObj && currObj.id === p.id;
        return el(
          "button",
          {
            key: p.id,
            type: "button",
            onClick: function () {
              pickPage(p);
            },
            className: "button " + (active ? "is-primary" : "is-secondary"),
            style: {
              display: "block",
              width: "100%",
              textAlign: "left",
              marginBottom: "6px",
            },
          },
          t
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
          },

          edit: function (props) {
            var attrs = props.attributes || {};
            var templateId = attrs.templateId || 0;
            var fields = attrs.fields || {};

            function onPick(e) {
              var id = parseInt(e.target.value || "0", 10) || 0;
              props.setAttributes({ templateId: id, fields: {} });
            }
            function setField(k, v) {
              var next = Object.assign({}, fields);
              next[k] = v;
              props.setAttributes({ fields: next });
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

            var defs = getFieldDefs(templateId) || [];
            var richDefs = defs.filter(isRich);
            var textDefs = defs.filter(function (d) {
              return isHeadingOrText(d) && !isRich(d);
            });
            var areaDefs = defs.filter(isTextarea);
            var urlDefs = defs.filter(isUrl);
            var imageDefs = defs.filter(isImage);
            var galDefs = defs.filter(isGallery);

            function Section(title, children) {
              if (!children || !children.length) return null;
              return el(
                "div",
                { className: "now-elt-sec" },
                el("div", { className: "now-elt-sec-title" }, title),
                children
              );
            }

            var textInputs = textDefs
              .map(function (def) {
                var value = fields[def.key] || "";
                return el(TextControl, {
                  key: def.key,
                  label: labelFor(def),
                  value: value,
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

            // Sider/URL – brug PagePicker hvor muligt, ellers LinkControl fallback
            function UrlInput(def) {
              if (useSelect && data && data.select) {
                return el(
                  PagePicker,
                  Object.assign(
                    { key: def.key },
                    { props: props, def: def, attributes: props.attributes }
                  )
                );
              }
              // Fallback
              var value =
                (fields[def.key] && fields[def.key].url) ||
                fields[def.key] ||
                "";
              return el(
                "div",
                { key: def.key, className: "now-elt-sec-item" },
                el("div", { className: "now-elt-label" }, labelFor(def)),
                LinkControl
                  ? el(LinkControl, {
                      value: { url: value },
                      onChange: function (next) {
                        var url =
                          typeof next === "string"
                            ? next
                            : (next && next.url) || "";
                        setField(def.key, { url: url, newTab: false });
                      },
                      showInitialSuggestions: true,
                      withCreateSuggestion: false,
                    })
                  : el(TextControl, {
                      type: "url",
                      value: value,
                      onChange: function (v) {
                        setField(def.key, { url: v, newTab: false });
                      },
                    })
              );
            }
            var linkInputs = urlDefs.map(UrlInput);

            var imageInputs = imageDefs.map(function (def) {
              return ImageField(props, def);
            });
            var galleryInputs = galDefs.map(function (def) {
              return GalleryField(props, def);
            });

            // Rich (TinyMCE hvis tilgængelig, ellers RichText)
            function TinyMCEField(def) {
              var fieldKey = def.key;
              var initial = (fields && fields[fieldKey]) || "";
              var idRef = useRef(
                ("nowelt-" + fieldKey).replace(/[^a-z0-9_\-]/gi, "_")
              );
              var taRef = useRef(null);
              useEffect(function () {
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
                function sync() {
                  var ed = window.tinymce && window.tinymce.get(idRef.current);
                  var val = ed
                    ? ed.getContent()
                    : taRef.current
                    ? taRef.current.value
                    : "";
                  var next = Object.assign({}, fields);
                  next[fieldKey] = val || "";
                  props.setAttributes({ fields: next });
                }
                var wait = setInterval(function () {
                  var ed = window.tinymce && window.tinymce.get(idRef.current);
                  if (ed) {
                    clearInterval(wait);
                    ed.on("change keyup input", sync);
                  }
                }, 50);
                return function () {
                  try {
                    OldEditor.remove(idRef.current);
                  } catch (e) {}
                  clearInterval(wait);
                };
              }, []);
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
                Section(
                  __("Billeder", "nowonline"),
                  imageInputs.concat(galleryInputs)
                )
              );
            }

            function CanvasPreview() {
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
            }

            var tabs = [
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
            ];
            var tabsUI = TabPanel
              ? el(
                  TabPanel,
                  { tabs: tabs, initialTabName: "content" },
                  function (tab) {
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

            return el(
              "div",
              rootProps,
              el(CanvasPreview, {}),
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

// File: assets/editor.js
// @ts-nocheck
(function () {
  "use strict";

  var WP = window.wp || {};
  var __ = (WP.i18n && WP.i18n.__) || function (s) { return s; };
  var el = (WP.element && WP.element.createElement) || function () {};
  var domReady = WP.domReady || function (cb) {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", cb);
    else cb();
  };

  var C = WP.components || {};
  var B = WP.blockEditor || WP.editor || {};
  var Blocks = WP.blocks || {};

  // Fallbacks
  var PanelBody = C.PanelBody || function (p) { return el("div", p, p.children); };
  var TextControl = C.TextControl || function (p) { return el("input", Object.assign({ type: "text" }, p)); };
  var TextareaControl = C.TextareaControl || function (p) { return el("textarea", Object.assign({ rows: 4 }, p)); };
  var ToggleControl = C.ToggleControl || function (p) {
    return el("label", {},
      el("input", { type: "checkbox", checked: !!p.checked, onChange: function (e) { p.onChange && p.onChange(!!e.target.checked); } }),
      " ", p.label || ""
    );
  };
  var SelectControl = C.SelectControl || function (p) {
    var opts = (p.options || []).map(function (o) { return el("option", { value: o.value }, o.label); });
    return el("label", {},
      el("span", { className: "now-elt-label" }, p.label || ""),
      el("select", Object.assign({}, p, { onChange: function (e) { p.onChange && p.onChange(e.target.value); } }), opts)
    );
  };
  var CheckboxControl = C.CheckboxControl || function (p) {
    return el("label", {},
      el("input", { type: "checkbox", checked: !!p.checked, onChange: function (e) { p.onChange && p.onChange(!!e.target.checked); } }),
      " ", p.label || ""
    );
  };
  var RangeControl = C.RangeControl;
  function RangeOrNumber(props) {
    return RangeControl ? el(RangeControl, props) : el(TextControl, Object.assign({}, props, { type: "number" }));
  }

  var InspectorControls = B.InspectorControls || "div";
  var MediaUpload = B.MediaUpload;
  var RichText = B.RichText;
  var TabPanel = C.TabPanel;

  function Icon() {
    return el("svg", { viewBox: "0 0 24 24", width: 24, height: 24, role: "img" },
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
  function labelFor(def) { return def.label || def.key; }
  function tplById(id) {
    var list = Array.isArray(window.NOWONLINE_TEMPLATES) ? window.NOWONLINE_TEMPLATES : [];
    id = parseInt(id || 0, 10);
    for (var i = 0; i < list.length; i++) { if (parseInt(list[i].id, 10) === id) return list[i]; }
    return null;
  }

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
      : el("div", { className: "now-elt-imgprev now-elt-noimg" }, __("No image", "nowonline"));
    return el("div", { key: def.key, className: "now-elt-sec-item" },
      el("label", { className: "now-elt-label" }, labelFor(def)),
      preview,
      MediaUpload
        ? el(MediaUpload, {
            onSelect: onSelect, allowedTypes: ["image"], value: 0,
            render: function (o) {
              return el("div", {},
                el("button", { className: "button", onClick: o.open }, __("Vælg billede", "nowonline")),
                el("button", { className: "button is-secondary", onClick: clear }, __("Fjern", "nowonline"))
              );
            }
          })
        : el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline"))
    );
  }

  domReady(function () {
    try {
      var MAP = Array.isArray(window.NOWONLINE_TEMPLATES) ? window.NOWONLINE_TEMPLATES : [];
      if (!Blocks || !Blocks.registerBlockType) return;

      var already = Blocks.getBlockType && Blocks.getBlockType("nowonline/elt-template");
      if (!already) {
        Blocks.registerBlockType("nowonline/elt-template", {
          title: __("Elementor Template", "nowonline"),
          icon: Icon(),
          category: "nowonline-elementor",
          attributes: {
            templateId: { type: "number", default: 0 },
            gap:        { type: "number", default: 24 },
            fields:     { type: "object", default: {} },
            design:     { type: "object", default: {} },
            background: { type: "object", default: {} },
            responsive: { type: "object", default: {} },
            spacing:    { type: "object", default: {} }
          },

          edit: function (props) {
            var attrs      = props.attributes || {};
            var templateId = attrs.templateId || 0;
            var fields     = attrs.fields || {};
            var design     = attrs.design || {};
            var background = attrs.background || {};
            var responsive = attrs.responsive || {};
            var spacing    = attrs.spacing || {};

            function onPick(e) {
              var id = parseInt(e.target.value || "0", 10) || 0;
              props.setAttributes({ templateId: id, fields: {} });
            }
            function setField(k, v) {
              var next = Object.assign({}, fields); next[k] = v;
              props.setAttributes({ fields: next });
            }
            function setDesign(p)     { props.setAttributes({ design:     Object.assign({}, design, p) }); }
            function setBackground(p) { props.setAttributes({ background: Object.assign({}, background, p) }); }
            function setResponsive(p) { props.setAttributes({ responsive: Object.assign({}, responsive, p) }); }
            function setSpacing(p)    { props.setAttributes({ spacing:    Object.assign({}, spacing, p) }); }

            var picker = el("select",
              { onChange: onPick, value: templateId || 0, className: "now-elt-select" },
              [ el("option", { value: 0 }, __("Vælg template…", "nowonline")) ]
                .concat(MAP.map(function (x) { return el("option", { value: x.id, key: x.id }, x.title || "#" + x.id); }))
            );

            var defs      = getFieldDefs(templateId);
            var richDefs  = defs.filter(function (d) { return d.type === "rich" || d.type === "wysiwyg"; });
            var otherDefs = defs.filter(function (d) { return !(d.type === "rich" || d.type === "wysiwyg"); });
            var textDefs  = otherDefs.filter(function (d) { return d.type === "text" || d.type === "p" || /^h[1-6]$/.test(d.type); });
            var areaDefs  = otherDefs.filter(function (d) { return d.type === "textarea"; });
            var urlDefs   = otherDefs.filter(function (d) { return d.type === "url"; });
            var imageDefs = otherDefs.filter(function (d) { return d.type === "img" || d.type === "bg"; });

            function Section(title, children) {
              if (!children || !children.length) return null;
              return el("div", { className: "now-elt-sec" },
                el("div", { className: "now-elt-sec-title" }, title),
                children
              );
            }

            var textInputs = textDefs.map(function (def) {
              var value = fields[def.key] || "";
              return el(TextControl, {
                key: def.key, label: labelFor(def), value: value,
                onChange: function (v) { setField(def.key, v); }
              });
            }).concat(areaDefs.map(function (def) {
              var value = fields[def.key] || "";
              return el(TextareaControl, {
                key: def.key, label: labelFor(def), value: value,
                onChange: function (v) { setField(def.key, v); }
              });
            }));

            var linkInputs  = urlDefs.map(function (def) {
              var value = fields[def.key] || "";
              return el(TextControl, {
                key: def.key, label: labelFor(def), type: "url", value: value,
                onChange: function (v) { setField(def.key, v); }
              });
            });
            var imageInputs = imageDefs.map(function (def) { return ImageField(props, def); });

            var richInputs = RichText ? richDefs.map(function (def) {
              var value = fields[def.key] || "";
              return el("div", { key: def.key, className: "now-elt-sec-item" },
                el("div", { className: "now-elt-label" }, labelFor(def)),
                el(RichText, {
                  tagName: "div", value: value,
                  allowedFormats: ["core/bold","core/italic","core/link","core/strikethrough","core/underline","core/text-color","core/code"],
                  onChange: function (v) { setField(def.key, v || ""); },
                  placeholder: __("Skriv formateret tekst…", "nowonline")
                })
              );
            }) : [ el("div", { key: "norich", className: "now-elt-muted" }, __("RichText-komponent ikke tilgængelig i denne editor.", "nowonline")) ];

            // Content-tab: KUN template-vælger + felter
            function ContentTab() {
              var pickerRow = el("div", { className: "now-elt-picker" }, picker);
              return el("div", {},
                pickerRow,
                Section(__("Tekster", "nowonline"),           textInputs),
                Section(__("Formateret tekst", "nowonline"),  richInputs),
                Section(__("Billeder", "nowonline"),          imageInputs),
                Section(__("Links", "nowonline"),             linkInputs)
              );
            }

            // Enkel thumbnail-preview (ingen titel/tekst – og ikke-draggable)
// Viser kun billedet – ingen wrappers, ingen tvungen bredde/højde
function CanvasPreview() {
  var tpl = tplById(templateId) || {};
  var src = tpl.preview || tpl.thumb || '';

  // intet valgt = intet preview (eller returnér en lille tekst hvis du vil)
  if (!src) return null;

  return el('img', {
    src: src,
    alt: '',
    draggable: false,
    decoding: 'async',
    // naturlig størrelse; maxWidth holder den inde i editorens kolonne
    style: { display: 'block', width: 'auto', height: 'auto', maxWidth: '100%' }
  });
}



            var tabs = [
              { name: "content",   title: __("Indhold",   "nowonline"), className: "nowonline-tab" },
              { name: "design",    title: __("Design",    "nowonline"), className: "nowonline-tab" },
              { name: "background",title: __("Baggrund",  "nowonline"), className: "nowonline-tab" },
              { name: "advanced",  title: __("Advanced",  "nowonline"), className: "nowonline-tab" }
            ];

            var tabsUI = TabPanel ? el(TabPanel, { tabs: tabs, initialTabName: "content" }, function (tab) {
              if (tab.name === "design")     return DesignTab();
              if (tab.name === "background") return BackgroundTab();
              if (tab.name === "advanced")   return AdvancedTab();
              return ContentTab();
            }) : el("div", {}, ContentTab(), DesignTab(), BackgroundTab(), AdvancedTab());

            // STABIL WRAPPER (ikke Fragment) => bedre måling/drag i editoren
            return el('div', { className: 'now-elt-edit-root' },
              el(CanvasPreview, {}),
              tabsUI,
              el(InspectorControls, {},
                el(PanelBody, { title: __("(Info)", "nowonline"), initialOpen: false },
                  el("div", {}, __("Denne blok rendres på frontend.", "nowonline"))
                )
              )
            );
          },

          save: function () { return null; },
          supports: { inserter: true }
        });
      }

      // Variationer (thumbnail som ikon)
      if (Blocks && typeof Blocks.registerBlockVariation === "function" && Array.isArray(MAP)) {
        MAP.forEach(function (t) {
          var icon = t.thumb ? function () {
            return el("img", { src: t.thumb, alt: "", className: "now-elt-var-thumb" });
          } : Icon();
          Blocks.registerBlockVariation("nowonline/elt-template", {
            name: "nowonline-elt-" + t.id,
            title: t.title || ("#" + t.id),
            description: __("Elementor template", "nowonline"),
            icon: icon,
            attributes: { templateId: t.id },
            scope: ["inserter"],
            keywords: ["elementor","template","nowonline"]
          });
        });
      }

      if (window.console) console.info("[NowOnline] ELT variations:", Array.isArray(MAP) ? MAP.length : 0);
    } catch (e) {
      if (window && window.console) console.warn("[NowOnline Elementor Blocks] init error", e);
    }
  });
})();

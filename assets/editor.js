// File: assets/editor.js
// @ts-nocheck
(function() {
    "use strict";

    // --- WP shims --------------------------------------------------------------
    var WP = window.wp || {};
    var __ =
        (WP.i18n && WP.i18n.__) ||
        function(s) {
            return s;
        };
    var el = (WP.element && WP.element.createElement) || function() {};
    var domReady =
        WP.domReady ||
        function(cb) {
            document.readyState === "loading" ?
                document.addEventListener("DOMContentLoaded", cb) :
                cb();
        };

    var C = WP.components || {};
    var B = WP.blockEditor || WP.editor || {};
    var Blocks = WP.blocks || {};
    var data = WP.data || {};
    var HAS_USESELECT = !!(data && typeof data.useSelect === "function");

    var PanelBody =
        C.PanelBody ||
        function(p) {
            return el("div", p, p.children);
        };
    var TextControl =
        C.TextControl ||
        function(p) {
            return el("input", Object.assign({ type: "text" }, p));
        };
    var TextareaControl =
        C.TextareaControl ||
        function(p) {
            return el("textarea", Object.assign({ rows: 4 }, p));
        };
    var CheckboxControl =
        C.CheckboxControl ||
        function(p) {
            return el(
                "label", {},
                el("input", {
                    type: "checkbox",
                    checked: !!p.checked,
                    onChange: function(e) {
                        p.onChange && p.onChange(!!e.target.checked);
                    },
                }),
                " ",
                p.label || ""
            );
        };
    var ColorPalette =
        C.ColorPalette ||
        function(p) {
            return el("input", {
                type: "color",
                value: p.value || "#000000",
                onChange: function(e) {
                    p.onChange && p.onChange(e.target.value);
                },
            });
        };
    var Button =
        C.Button ||
        function(p) {
            return el(
                "button",
                Object.assign({ type: "button", className: "button" }, p),
                p.children
            );
        };

    var InspectorControls = B.InspectorControls || "div";
    var MediaUpload = B.MediaUpload;
    var RichText = B.RichText;
    var useBlockProps =
        B && B.useBlockProps ?
        B.useBlockProps :
        function() {
            return {};
        };

    // --- React hooks -----------------------------------------------------------
    var useEffect = (WP.element && WP.element.useEffect) || function() {};
    var useRef =
        (WP.element && WP.element.useRef) ||
        function(v) {
            return { current: v };
        };
    var useState =
        (WP.element && WP.element.useState) ||
        function(v) {
            return [v, function() {}];
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
            "svg", { viewBox: "0 0 24 24", width: 24, height: 24, role: "img" },
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
        var list = Array.isArray(window.NOWONLINE_TEMPLATES) ?
            window.NOWONLINE_TEMPLATES : [];
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
        u = u
            .replace(/^(https?:\/\/)(https?:\/\/)/i, "$1")
            .replace(/^(https?:\/\/)+/i, "$1");
        if (/^www\./i.test(u)) u = "https://" + u;
        if (!/^[a-z][a-z0-9+.\-]*:\/\//i.test(u) && /^[^\/\s]+\.[^\s]+/.test(u)) {
            u = "https://" + u;
        }
        return u;
    }

    // --- Rich sanitizer -------------------------------------------------------
    /**
     * Sanitize rich HTML coming from inputs.
     * inlineOnly=true: unwrap/block-drop <p>, <div>, <h1-6> to avoid invalid markup when injected inside headings
     */
    function sanitizeRichHtml(input, inlineOnly) {
        var html = String(input || "");
        if (!html) return "";
        var wrap = document.createElement("div");
        wrap.innerHTML = html;

        function unwrap(node) {
            var parent = node.parentNode;
            if (!parent) return;
            while (node.firstChild) parent.insertBefore(node.firstChild, node);
            parent.removeChild(node);
        }

        function walk(node) {
            if (!node || node.nodeType !== 1) return;
            var tag = node.tagName.toLowerCase();

            // strip Elementor classes to avoid style collisions
            var cls = node.getAttribute("class") || "";
            if (/elementor-/.test(cls)) node.removeAttribute("class");

            // If inlineOnly: remove block wrappers that would break when inserted in a <h1>
            if (
                inlineOnly &&
                (/^h[1-6]$/.test(tag) || tag === "p" || tag === "div")
            ) {
                var next = node.nextSibling;
                unwrap(node);
                if (next && next.nodeType === 1) walk(next);
                return;
            }

            // Allowed attributes per tag (we never drop the tag itself)
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
                // allow headings with no attributes if not inlineOnly
                h1: [],
                h2: [],
                h3: [],
                h4: [],
                h5: [],
                h6: [],
            };
            var keep =
                allowed[tag] ||
                (tag === "span" || tag === "p" || tag === "div" ? ["style"] : []);

            // remove all non-allowed attributes
            var toRemove = [];
            for (var i = 0; i < node.attributes.length; i++) {
                var name = node.attributes[i].name;
                if (keep.indexOf(name) === -1) toRemove.push(name);
            }
            toRemove.forEach(function(n) {
                node.removeAttribute(n);
            });

            Array.prototype.slice.call(node.children || []).forEach(walk);
        }

        Array.prototype.slice.call(wrap.children || []).forEach(walk);
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
        var preview = url ?
            el("img", { src: url, className: "now-elt-imgprev", alt: "" }) :
            el(
                "div", { className: "now-elt-imgprev now-elt-noimg" },
                __("No image", "nowonline")
            );
        return el(
            "div", { key: def.key, className: "now-elt-sec-item" },
            el("label", { className: "now-elt-label" }, labelFor(def)),
            preview,
            MediaUpload ?
            el(MediaUpload, {
                onSelect: onSelect,
                allowedTypes: ["image"],
                value: 0,
                render: function(o) {
                    return el(
                        "div", {},
                        el(
                            "button", { className: "button", onClick: o.open },
                            __("Vælg billede", "nowonline")
                        ),
                        el(
                            "button", { className: "button is-secondary", onClick: clear },
                            __("Fjern", "nowonline")
                        )
                    );
                },
            }) :
            el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline"))
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
            setField(key, (media && media.url) || "");
        }

        function onSelectPoster(media) {
            setField(posterKey, (media && media.url) || "");
        }
        var vidPreview = url ?
            el(
                "video", {
                    src: url,
                    poster: poster || undefined,
                    controls: true,
                    style: { width: "100%", maxWidth: "420px", display: "block" },
                },
                null
            ) :
            el(
                "div", { className: "now-elt-imgprev now-elt-noimg" },
                __("Ingen video valgt", "nowonline")
            );
        var posterPreview = poster ?
            el("img", {
                src: poster,
                alt: "",
                className: "now-elt-imgprev",
                style: { maxWidth: "210px", marginTop: "6px" },
            }) :
            null;
        return el(
            "div", { key: key, className: "now-elt-sec-item" },
            el("label", { className: "now-elt-label" }, labelFor(def)),
            vidPreview,
            MediaUpload ?
            el(MediaUpload, {
                onSelect: onSelectVideo,
                allowedTypes: ["video"],
                value: 0,
                render: function(o) {
                    return el(
                        "div", { style: { marginTop: "6px" } },
                        el(
                            "button", { className: "button", onClick: o.open },
                            __("Vælg video", "nowonline")
                        ),
                        el(
                            "button", {
                                className: "button is-secondary",
                                onClick: function() {
                                    setField(key, null);
                                },
                                style: { marginLeft: "6px" },
                            },
                            __("Fjern", "nowonline")
                        ),
                        el(TextControl, {
                            type: "url",
                            value: url || "",
                            onChange: function(v) {
                                setField(key, fixUrl(v || ""));
                            },
                            placeholder: __("eller indsæt video-URL…", "nowonline"),
                            style: { display: "block", marginTop: "8px" },
                        })
                    );
                },
            }) :
            el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline")),
            el(
                "div", { style: { marginTop: "10px" } },
                el(
                    "div", { className: "now-elt-label" },
                    __("Poster (valgfri)", "nowonline")
                ),
                posterPreview || null,
                MediaUpload ?
                el(MediaUpload, {
                    onSelect: onSelectPoster,
                    allowedTypes: ["image"],
                    value: 0,
                    render: function(o) {
                        return el(
                            "div", {},
                            el(
                                "button", { className: "button", onClick: o.open },
                                __("Vælg poster", "nowonline")
                            ),
                            el(
                                "button", {
                                    className: "button is-secondary",
                                    onClick: function() {
                                        setField(posterKey, null);
                                    },
                                    style: { marginLeft: "6px" },
                                },
                                __("Fjern", "nowonline")
                            )
                        );
                    },
                }) :
                null
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
                .map(function(m) {
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
        var thumbs = value.length ?
            el(
                "div", { className: "now-elt-gallery-thumbs" },
                value.map(function(u, i) {
                    return el("img", {
                        key: i,
                        src: u,
                        alt: "",
                        className: "now-elt-imgprev",
                        style: { maxWidth: "120px", marginRight: "6px" },
                    });
                })
            ) :
            el(
                "div", { className: "now-elt-imgprev now-elt-noimg" },
                __("Ingen billeder i galleriet", "nowonline")
            );
        return el(
            "div", { key: def.key, className: "now-elt-sec-item" },
            el("label", { className: "now-elt-label" }, labelFor(def)),
            thumbs,
            MediaUpload ?
            el(MediaUpload, {
                onSelect: onSelect,
                allowedTypes: ["image"],
                multiple: true,
                gallery: true,
                value: 0,
                render: function(o) {
                    return el(
                        "div", {},
                        el(
                            "button", { className: "button", onClick: o.open },
                            __("Vælg billeder", "nowonline")
                        ),
                        el(
                            "button", {
                                className: "button is-secondary",
                                onClick: clear,
                                style: { marginLeft: "6px" },
                            },
                            __("Ryd galleri", "nowonline")
                        )
                    );
                },
            }) :
            el("div", {}, __("MediaUpload ikke tilgængelig", "nowonline"))
        );
    }

    // --- PagePicker for URL fields (kun når hook findes) ----------------------
    function PagePicker(p) {
        var block = p.block,
            def = p.def,
            fieldKey = def.key;
        var current = (block.attributes.fields || {})[fieldKey];
        var currObj =
            current && typeof current === "object" ?
            current : { url: current || "", newTab: false, id: 0, type: "external" };

        var sel = useSelect(function(select) {
            if (!select || !select("core")) return { pages: [], isResolving: false };
            var s = select("core");
            var query = {
                per_page: 100,
                orderby: "title",
                order: "asc",
                status: ["publish", "private"],
            };
            return {
                pages: s.getEntityRecords ?
                    s.getEntityRecords("postType", "page", query) || [] : [],
                isResolving: s.isResolving ?
                    s.isResolving("getEntityRecords", ["postType", "page", query]) : false,
            };
        }, []);

        var _s = useState(""),
            search = _s[0],
            setSearch = _s[1];
        var pages = sel && sel.pages ? sel.pages : [];
        if (search && pages.length) {
            var needle = search.toLowerCase();
            pages = pages.filter(function(pg) {
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
            "div", {
                className: "now-elt-pages-list",
                style: {
                    maxHeight: "220px",
                    overflow: "auto",
                    border: "1px solid #e2e4e7",
                    borderRadius: "6px",
                    padding: "6px",
                },
            },
            pages.map(function(pg) {
                var t = pg.title && pg.title.rendered ? pg.title.rendered : "#" + pg.id;
                var active = currObj && currObj.id === pg.id;
                return el(
                    "button", {
                        key: pg.id,
                        type: "button",
                        onClick: function() {
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
            "div", { className: "now-elt-sec-item" },
            el("div", { className: "now-elt-label" }, labelFor(def)),
            el(
                "div", { className: "now-elt-urlpicker" },
                el(
                    "div", { style: { display: "flex", gap: "8px", marginBottom: "6px" } },
                    el("input", {
                        type: "search",
                        placeholder: __("Søg side…", "nowonline"),
                        value: search,
                        onChange: function(e) {
                            setSearch(e.target.value);
                        },
                        style: { flex: "1 1 auto" },
                    }),
                    el("input", {
                        type: "url",
                        placeholder: __("eller indsæt manuel URL…", "nowonline"),
                        value: currObj.url || "",
                        onChange: function(e) {
                            setManual(e.target.value);
                        },
                        style: { flex: "2 1 auto" },
                    })
                ),
                list,
                el(
                    "div", { style: { marginTop: "8px" } },
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
    domReady(function() {
        try {
            var MAP = Array.isArray(window.NOWONLINE_TEMPLATES) ?
                window.NOWONLINE_TEMPLATES : [];
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

                        // Design
                        containerBg: { type: "string", default: "" },

                        // Knap-design
                        btnTextColor: { type: "string", default: "" },
                        btnBorderColor: { type: "string", default: "" },
                        btnBorderWidth: { type: "string", default: "" },
                        btnBorderRadius: { type: "string", default: "" },

                        // Background media / options
                        bgVideo: { type: "string", default: "" },
                        bgImg: { type: "string", default: "" },
                        bgImgTablet: { type: "string", default: "" },
                        bgImgMobile: { type: "string", default: "" },
                        bgPos: { type: "string", default: "center center" },
                        bgSize: { type: "string", default: "cover" },
                        bgFixed: { type: "boolean", default: false },

                        // --- NEW: Advanced / visibility ---
                        hideDesktop: { type: "boolean", default: false },
                        hideTablet: { type: "boolean", default: false },
                        hideMobile: { type: "boolean", default: false },

                        // --- NEW: Responsive paddings ---
                        padTopDesktop: { type: "string", default: "" },
                        padBottomDesktop: { type: "string", default: "" },
                        padTopLaptop: { type: "string", default: "" },
                        padBottomLaptop: { type: "string", default: "" },
                        padTopTablet: { type: "string", default: "" },
                        padBottomTablet: { type: "string", default: "" },
                        padTopMobile: { type: "string", default: "" },
                        padBottomMobile: { type: "string", default: "" },

                        // legacy
                        containerTargetMode: { type: "string", default: "auto" },
                        containerTarget: { type: "string", default: "" },
                    },

                    edit: function(props) {
                        var attrs = props.attributes || {};
                        var templateId = attrs.templateId || 0;
                        var fields = attrs.fields || {};
                        var containerBg = attrs.containerBg || "";

                        // knap-attributter
                        var btnTextColor = attrs.btnTextColor || "";
                        var btnBorderColor = attrs.btnBorderColor || "";
                        var btnBorderWidth = attrs.btnBorderWidth || "";
                        var btnBorderRadius = attrs.btnBorderRadius || "";

                        // baggrunds-attributter
                        var bgVideo = attrs.bgVideo || "";
                        var bgImg = attrs.bgImg || "";
                        var bgImgTablet = attrs.bgImgTablet || "";
                        var bgImgMobile = attrs.bgImgMobile || "";
                        var bgPos = attrs.bgPos || "center center";
                        var bgSize = attrs.bgSize || "cover";
                        var bgFixed = !!attrs.bgFixed;

                        // Advanced values
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

                        // Persistente faner
                        var _tab = useState("content"),
                            activeTab = _tab[0],
                            setActiveTab = _tab[1];
                        var contentVisible = activeTab === "content";

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
                            "select", {
                                onChange: onPick,
                                value: templateId || 0,
                                className: "now-elt-select",
                            }, [
                                el("option", { value: 0 }, __("Vælg template…", "nowonline")),
                            ].concat(
                                (MAP || []).map(function(x) {
                                    return el(
                                        "option", { value: x.id, key: x.id },
                                        x.title || "#" + x.id
                                    );
                                })
                            )
                        );

                        // Feltdefinitioner
                        var defs = getFieldDefs(templateId) || [];
                        var richDefs = defs.filter(isRich);
                        var textDefs = defs.filter(function(d) {
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
                                "div", { className: "now-elt-sec" },
                                el("div", { className: "now-elt-sec-title" }, title),
                                children
                            );
                        }

                        // Tekster
                        var textInputs = textDefs
                            .map(function(def) {
                                var value = fields[def.key] || "";
                                return el(TextareaControl, {
                                    key: def.key,
                                    label: labelFor(def),
                                    value: value,
                                    rows: 3,
                                    onChange: function(v) {
                                        setField(def.key, v);
                                    },
                                });
                            })
                            .concat(
                                areaDefs.map(function(def) {
                                    var value = fields[def.key] || "";
                                    return el(TextareaControl, {
                                        key: def.key,
                                        label: labelFor(def),
                                        value: value,
                                        rows: 6,
                                        onChange: function(v) {
                                            setField(def.key, v);
                                        },
                                    });
                                })
                            );

                        // URL inputs
                        function UrlInput(def) {
                            if (HAS_USESELECT)
                                return el(PagePicker, { key: def.key, block: props, def: def });
                            var val = fields[def.key];
                            var curr =
                                val && typeof val === "object" ?
                                val : { url: val || "", newTab: false, type: "external" };
                            return el(
                                "div", { key: def.key, className: "now-elt-sec-item" },
                                el("div", { className: "now-elt-label" }, labelFor(def)),
                                LinkControl ?
                                el(LinkControl, {
                                    value: { url: curr.url || "" },
                                    onChange: function(next) {
                                        var url =
                                            typeof next === "string" ?
                                            next :
                                            (next && next.url) || "";
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
                                }) :
                                el(TextControl, {
                                    type: "url",
                                    value: curr.url || "",
                                    onChange: function(v) {
                                        setField(def.key, {
                                            url: fixUrl(v || ""),
                                            newTab: !!curr.newTab,
                                        });
                                    },
                                }),
                                el(
                                    "div", { style: { marginTop: "6px" } },
                                    el(CheckboxControl, {
                                        label: __("Åbn i ny fane", "nowonline"),
                                        checked: !!curr.newTab,
                                        onChange: function(v) {
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

                        var imageInputs = imageDefs.map(function(def) {
                            return ImageField(props, def);
                        });
                        var videoInputs = videoDefs.map(function(def) {
                            return VideoField(props, def);
                        });
                        var galleryInputs = galDefs.map(function(def) {
                            return GalleryField(props, def);
                        });

                        // --- Rich (TinyMCE hvis muligt, ellers RichText) -----------------
                        function TinyMCEField(def) {
                            var fieldKey = def.key;
                            var initial =
                                (props.attributes.fields &&
                                    props.attributes.fields[fieldKey]) ||
                                "";

                            // inlineOnly for headings/titles so Elementor heading inherits styles
                            var inlineOnly =
                                /^(titel|title|heading|overskrift|headline)$/i.test(
                                    String(fieldKey || "")
                                );
                            var cleanedInitial = sanitizeRichHtml(initial, inlineOnly);

                            function safe(s) {
                                return String(s || "").replace(/[^a-z0-9_-]/gi, "");
                            }
                            var instId = safe(
                                props.clientId || Math.random().toString(36).slice(2, 8)
                            );
                            var idRef = useRef("nowelt-" + instId + "-" + safe(fieldKey));
                            var taRef = useRef(null);

                            useEffect(
                                function() {
                                    if (!OldEditor || !OldEditor.initialize) return;
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

                                    function sync() {
                                        var raw =
                                            ed && typeof ed.getContent === "function" ?
                                            ed.getContent() :
                                            taRef.current ?
                                            taRef.current.value :
                                            "";
                                        var next = Object.assign({}, props.attributes.fields || {});
                                        next[fieldKey] = sanitizeRichHtml(raw, inlineOnly) || "";
                                        props.setAttributes({ fields: next });
                                    }

                                    function bindWhenReady() {
                                        wait = setInterval(function() {
                                            if (disposed) return;
                                            ed =
                                                window.tinymce &&
                                                window.tinymce.get &&
                                                window.tinymce.get(idRef.current);
                                            if (ed) {
                                                clearInterval(wait);
                                                if (cleanedInitial) ed.setContent(cleanedInitial);
                                                ed.on("change keyup input setcontent", sync);
                                            }
                                        }, 50);
                                    }

                                    function initIfNeeded() {
                                        if (!contentVisible) return;
                                        if (hasEditor()) return;
                                        try {
                                            OldEditor.remove(idRef.current);
                                        } catch (e) {}
                                        OldEditor.initialize(idRef.current, {
                                            tinymce: {
                                                wpautop: true,
                                                menubar: false,
                                                toolbar1: "formatselect,bold,italic,link,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo",
                                            },
                                            quicktags: true,
                                            mediaButtons: false,
                                        });
                                        bindWhenReady();
                                    }

                                    initIfNeeded();
                                    guard = setInterval(function() {
                                        if (!disposed && contentVisible && !hasEditor())
                                            initIfNeeded();
                                    }, 200);
                                    if (taRef.current)
                                        taRef.current.addEventListener("input", sync);

                                    return function() {
                                        disposed = true;
                                        try {
                                            OldEditor.remove(idRef.current);
                                        } catch (e) {}
                                        if (taRef.current)
                                            taRef.current.removeEventListener("input", sync);
                                        clearInterval(wait);
                                        clearInterval(guard);
                                    };
                                }, [
                                    props.clientId,
                                    props.attributes.templateId,
                                    fieldKey,
                                    contentVisible,
                                ]
                            );

                            return el(
                                "div", { key: fieldKey, className: "now-elt-sec-item" },
                                el("div", { className: "now-elt-label" }, labelFor(def)),
                                el("textarea", {
                                    id: idRef.current,
                                    ref: taRef,
                                    defaultValue: cleanedInitial,
                                })
                            );
                        }

                        var richInputs =
                            OldEditor && OldEditor.initialize ?
                            richDefs.map(TinyMCEField) :
                            RichText ?
                            richDefs.map(function(def) {
                                var inlineOnly =
                                    /^(titel|title|heading|overskrift|headline)$/i.test(
                                        String(def.key || "")
                                    );
                                var value = sanitizeRichHtml(
                                    fields[def.key] || "",
                                    inlineOnly
                                );
                                return el(
                                    "div", { key: def.key, className: "now-elt-sec-item" },
                                    el("div", { className: "now-elt-label" }, labelFor(def)),
                                    el(RichText, {
                                        tagName: "div",
                                        value: value,
                                        onChange: function(v) {
                                            var next = Object.assign({},
                                                props.attributes.fields || {}
                                            );
                                            next[def.key] = sanitizeRichHtml(v || "", inlineOnly);
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
                                    })
                                );
                            }) : [
                                el(
                                    "div", { key: "norich", className: "now-elt-muted" },
                                    __("RichText/TinyMCE ikke tilgængelig.", "nowonline")
                                ),
                            ];

                        // --- Tabs ---------------------------------------------------------
                        function TabBtn(name, title) {
                            var active = activeTab === name;
                            return el(
                                "button", {
                                    type: "button",
                                    className: "button " + (active ? "is-primary" : "is-secondary"),
                                    onClick: function() {
                                        setActiveTab(name);
                                    },
                                    "aria-selected": active,
                                    style: { marginRight: 6 },
                                },
                                title
                            );
                        }

                        function ContentTab() {
                            var pickerRow = el(
                                "div", { className: "now-elt-picker" },
                                picker
                            );
                            return el(
                                "div", {},
                                pickerRow,
                                Section(__("Lille overskrift", "nowonline"), richInputs),
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
                                "div", {},

                                // ====== Background (farve) ======
                                el(
                                    PanelBody, { title: __("Background", "nowonline"), initialOpen: true },
                                    el(
                                        "div", {
                                            style: {
                                                display: "flex",
                                                alignItems: "center",
                                                gap: 8,
                                                flexWrap: "wrap",
                                                marginBottom: 8,
                                            },
                                        },
                                        el(
                                            "div", { style: { minWidth: 120 } },
                                            __("Container bg", "nowonline")
                                        ),
                                        el(ColorPalette, {
                                            value: containerBg || "",
                                            onChange: function(v) {
                                                setAttr({ containerBg: v || "" });
                                            },
                                        }),
                                        el(TextControl, {
                                            value: containerBg || "",
                                            onChange: function(v) {
                                                setAttr({ containerBg: (v || "").trim() });
                                            },
                                            placeholder: __(
                                                "fx #cf4747, rgb(), rgba(), hsl(), var(--token), red",
                                                "nowonline"
                                            ),
                                            __next40pxDefaultSize: true,
                                            __nextHasNoMarginBottom: true,
                                            style: { minWidth: 260 },
                                        }),
                                        el(
                                            Button, {
                                                className: "button is-secondary",
                                                onClick: function() {
                                                    setAttr({ containerBg: "" });
                                                },
                                            },
                                            __("Nulstil baggrund", "nowonline")
                                        )
                                    ),
                                    el(
                                        "div", { className: "now-elt-muted" },
                                        __("Mål vælges automatisk på frontend:", "nowonline"),
                                        " ",
                                        el(
                                            "code", {},
                                            "[data-now-bg], .now-bg, [data-nowonline-bg], .nowonline-bg"
                                        ),
                                        ". ",
                                        __(
                                            "Marker ønsket container i Elementor med en af disse.",
                                            "nowonline"
                                        )
                                    )
                                ),

                                // ====== Button ======
                                el(
                                    PanelBody, { title: __("Knap", "nowonline"), initialOpen: false },
                                    // Tekstfarve
                                    el(
                                        "div", {
                                            style: {
                                                display: "flex",
                                                alignItems: "center",
                                                gap: 8,
                                                flexWrap: "wrap",
                                                marginBottom: 8,
                                            },
                                        },
                                        el(
                                            "div", { style: { minWidth: 120 } },
                                            __("Tekstfarve", "nowonline")
                                        ),
                                        el(ColorPalette, {
                                            value: btnTextColor || "",
                                            onChange: function(v) {
                                                setAttr({ btnTextColor: v || "" });
                                            },
                                        }),
                                        el(TextControl, {
                                            value: btnTextColor || "",
                                            onChange: function(v) {
                                                setAttr({ btnTextColor: (v || "").trim() });
                                            },
                                            placeholder: __("fx #ffffff eller rgba()", "nowonline"),
                                            __next40pxDefaultSize: true,
                                            __nextHasNoMarginBottom: true,
                                            style: { minWidth: 260 },
                                        })
                                    ),
                                    // Borderfarve
                                    el(
                                        "div", {
                                            style: {
                                                display: "flex",
                                                alignItems: "center",
                                                gap: 8,
                                                flexWrap: "wrap",
                                                marginBottom: 8,
                                            },
                                        },
                                        el(
                                            "div", { style: { minWidth: 120 } },
                                            __("Borderfarve", "nowonline")
                                        ),
                                        el(ColorPalette, {
                                            value: btnBorderColor || "",
                                            onChange: function(v) {
                                                setAttr({ btnBorderColor: v || "" });
                                            },
                                        }),
                                        el(TextControl, {
                                            value: btnBorderColor || "",
                                            onChange: function(v) {
                                                setAttr({ btnBorderColor: (v || "").trim() });
                                            },
                                            placeholder: __("fx #000000 eller rgba()", "nowonline"),
                                            __next40pxDefaultSize: true,
                                            __nextHasNoMarginBottom: true,
                                            style: { minWidth: 260 },
                                        })
                                    ),
                                    // Border bredde
                                    el(TextControl, {
                                        label: __("Border bredde", "nowonline"),
                                        value: btnBorderWidth || "",
                                        onChange: function(v) {
                                            setAttr({ btnBorderWidth: (v || "").trim() });
                                        },
                                        help: __(
                                            "Fx 2px, 0.125rem eller 0 for ingen.",
                                            "nowonline"
                                        ),
                                        __next40pxDefaultSize: true,
                                        __nextHasNoMarginBottom: true,
                                    }),
                                    // Border radius
                                    el(TextControl, {
                                        label: __("Border radius", "nowonline"),
                                        value: btnBorderRadius || "",
                                        onChange: function(v) {
                                            setAttr({ btnBorderRadius: (v || "").trim() });
                                        },
                                        help: __("Fx 8px, 0.5rem eller 50%.", "nowonline"),
                                        __next40pxDefaultSize: true,
                                        __nextHasNoMarginBottom: true,
                                    }),
                                    // Reset
                                    el(
                                        "div", { style: { marginTop: 8 } },
                                        el(
                                            Button, {
                                                className: "button is-secondary",
                                                onClick: function() {
                                                    setAttr({
                                                        btnTextColor: "",
                                                        btnBorderColor: "",
                                                        btnBorderWidth: "",
                                                        btnBorderRadius: "",
                                                    });
                                                },
                                            },
                                            __("Nulstil knap-stil", "nowonline")
                                        ),
                                        el(
                                            "div", { className: "now-elt-muted", style: { marginTop: 8 } },
                                            __("Gælder knappen med ", "nowonline"),
                                            el("code", {}, 'id="now-link-link"'),
                                            "."
                                        )
                                    )
                                )
                            );
                        }

                        // ====== Baggrunds-fanen ======
                        function BackgroundTab() {
                            var posOpts = [
                                "center center",
                                "top center",
                                "bottom center",
                                "center left",
                                "center right",
                                "top left",
                                "top right",
                                "bottom left",
                                "bottom right",
                            ];
                            var sizeOpts = ["cover", "contain", "auto"];

                            function ImgPicker(label, key) {
                                var url = props.attributes[key] || "";

                                function onSelect(media) {
                                    setAttr({
                                        [key]: (media && media.url) || ""
                                    });
                                }

                                function clear() {
                                    setAttr({
                                        [key]: ""
                                    });
                                }
                                return el(
                                    "div", { className: "now-elt-sec-item" },
                                    el("div", { className: "now-elt-label" }, label),
                                    url ?
                                    el("img", {
                                        src: url,
                                        alt: "",
                                        className: "now-elt-imgprev",
                                        style: {
                                            maxWidth: 260,
                                            display: "block",
                                            marginBottom: 6,
                                        },
                                    }) :
                                    el(
                                        "div", { className: "now-elt-imgprev now-elt-noimg" },
                                        __("No image selected", "nowonline")
                                    ),
                                    MediaUpload ?
                                    el(MediaUpload, {
                                        onSelect: onSelect,
                                        value: 0,
                                        allowedTypes: ["image"],
                                        render: function(o) {
                                            return el(
                                                "div", {},
                                                el(
                                                    "button", { className: "button", onClick: o.open },
                                                    __("Add Image", "nowonline")
                                                ),
                                                el(
                                                    "button", {
                                                        className: "button is-secondary",
                                                        onClick: clear,
                                                        style: { marginLeft: 6 },
                                                    },
                                                    __("Clear", "nowonline")
                                                )
                                            );
                                        },
                                    }) :
                                    null
                                );
                            }

                            function VideoPicker() {
                                var url = props.attributes.bgVideo || "";

                                function onSelect(media) {
                                    setAttr({ bgVideo: (media && media.url) || "" });
                                }
                                return el(
                                    "div", { className: "now-elt-sec-item" },
                                    el(
                                        "div", { className: "now-elt-label" },
                                        __("Background video", "nowonline")
                                    ),
                                    url ?
                                    el("video", {
                                        src: url,
                                        controls: true,
                                        style: {
                                            width: "100%",
                                            maxWidth: 520,
                                            display: "block",
                                            marginBottom: 6,
                                        },
                                    }) :
                                    el(
                                        "div", { className: "now-elt-imgprev now-elt-noimg" },
                                        __("No video selected", "nowonline")
                                    ),
                                    MediaUpload ?
                                    el(MediaUpload, {
                                        onSelect: onSelect,
                                        value: 0,
                                        allowedTypes: ["video"],
                                        render: function(o) {
                                            return el(
                                                "div", {},
                                                el(
                                                    "button", { className: "button", onClick: o.open },
                                                    __("Choose video", "nowonline")
                                                ),
                                                el(
                                                    "button", {
                                                        className: "button is-secondary",
                                                        onClick: function() {
                                                            setAttr({ bgVideo: "" });
                                                        },
                                                        style: { marginLeft: 6 },
                                                    },
                                                    __("Remove", "nowonline")
                                                ),
                                                el(TextControl, {
                                                    value: url || "",
                                                    onChange: function(v) {
                                                        setAttr({ bgVideo: (v || "").trim() });
                                                    },
                                                    placeholder: __(
                                                        "or paste video URL…",
                                                        "nowonline"
                                                    ),
                                                    style: { marginTop: 8, minWidth: 280 },
                                                })
                                            );
                                        },
                                    }) :
                                    null
                                );
                            }

                            return el(
                                "div", {},
                                // Video
                                el(
                                    PanelBody, {
                                        title: __("Background video", "nowonline"),
                                        initialOpen: true,
                                    },
                                    VideoPicker()
                                ),

                                // Desktop image + options
                                el(
                                    PanelBody, {
                                        title: __("Background image", "nowonline"),
                                        initialOpen: true,
                                    },
                                    ImgPicker(__("Background image", "nowonline"), "bgImg"),
                                    el(
                                        "div", {
                                            style: {
                                                display: "grid",
                                                gridTemplateColumns: "1fr 1fr 1fr",
                                                gap: 8,
                                                alignItems: "center",
                                                marginTop: 8,
                                            },
                                        },
                                        // Position
                                        el(
                                            "div", {},
                                            el(
                                                "div", { className: "now-elt-label" },
                                                __("Background position", "nowonline")
                                            ),
                                            el(
                                                "select", {
                                                    value: bgPos,
                                                    onChange: function(e) {
                                                        setAttr({ bgPos: e.target.value });
                                                    },
                                                },
                                                posOpts.map(function(p) {
                                                    return el("option", { key: p, value: p }, p);
                                                })
                                            )
                                        ),
                                        // Size
                                        el(
                                            "div", {},
                                            el(
                                                "div", { className: "now-elt-label" },
                                                __("Background size", "nowonline")
                                            ),
                                            el(
                                                "select", {
                                                    value: bgSize,
                                                    onChange: function(e) {
                                                        setAttr({ bgSize: e.target.value });
                                                    },
                                                },
                                                sizeOpts.map(function(s) {
                                                    return el("option", { key: s, value: s }, s);
                                                })
                                            )
                                        ),
                                        // Fixed
                                        el(
                                            "div", {},
                                            el(
                                                "div", { className: "now-elt-label" },
                                                __("Background Fixed", "nowonline")
                                            ),
                                            el(CheckboxControl, {
                                                checked: !!bgFixed,
                                                onChange: function(v) {
                                                    setAttr({ bgFixed: !!v });
                                                },
                                                label: bgFixed ?
                                                    __("Yes", "nowonline") : __("No", "nowonline"),
                                            })
                                        )
                                    )
                                ),

                                // Tablet
                                el(
                                    PanelBody, {
                                        title: __("Background image tablet", "nowonline"),
                                        initialOpen: false,
                                    },
                                    ImgPicker(__("Tablet background", "nowonline"), "bgImgTablet")
                                ),

                                // Mobile
                                el(
                                    PanelBody, {
                                        title: __("Background image mobile", "nowonline"),
                                        initialOpen: false,
                                    },
                                    ImgPicker(__("Mobile background", "nowonline"), "bgImgMobile")
                                ),

                                el(
                                    "div", { className: "now-elt-muted", style: { marginTop: 8 } },
                                    __(
                                        "Anvendes på container markeret i Elementor som ",
                                        "nowonline"
                                    ),
                                    el(
                                        "code", {},
                                        "[data-nowonline-bg], .nowonline-bg, [data-now-bg], .now-bg"
                                    ),
                                    "."
                                )
                            );
                        }

                        // ====== NEW: Advanced fanen (visibility + responsive spacing) ====
                        function AdvancedTab() {
                            function unitHelp() {
                                return __(
                                    "Eksempler: 80px, 6rem, 10vh, 8% — tom = ingen ændring",
                                    "nowonline"
                                );
                            }

                            function RowTwo(aLabel, aKey, aVal, bLabel, bKey, bVal) {
                                return el(
                                    "div", {
                                        style: {
                                            display: "grid",
                                            gridTemplateColumns: "1fr 1fr",
                                            gap: 8,
                                            marginBottom: 8,
                                        },
                                    },
                                    el(TextControl, {
                                        label: aLabel,
                                        value: aVal || "",
                                        onChange: function(v) {
                                            var next = {};
                                            next[aKey] = (v || "").trim();
                                            setAttr(next);
                                        },
                                        placeholder: "fx 80px",
                                        help: unitHelp(),
                                    }),
                                    el(TextControl, {
                                        label: bLabel,
                                        value: bVal || "",
                                        onChange: function(v) {
                                            var next = {};
                                            next[bKey] = (v || "").trim();
                                            setAttr(next);
                                        },
                                        placeholder: "fx 80px",
                                        help: unitHelp(),
                                    })
                                );
                            }

                            function ResetBtn(keys) {
                                return el(
                                    Button, {
                                        className: "button is-secondary",
                                        onClick: function() {
                                            var next = {};
                                            keys.forEach(function(k) {
                                                next[k] = "";
                                            });
                                            setAttr(next);
                                        },
                                        style: { marginTop: 4 },
                                    },
                                    __("Nulstil", "nowonline")
                                );
                            }

                            return el(
                                "div", {},
                                // Visibility
                                el(
                                    PanelBody, { title: __("Visibility", "nowonline"), initialOpen: true },
                                    el(CheckboxControl, {
                                        label: __("Hide on Desktop", "nowonline"),
                                        checked: !!hideDesktop,
                                        onChange: function(v) {
                                            setAttr({ hideDesktop: !!v });
                                        },
                                    }),
                                    el(CheckboxControl, {
                                        label: __("Hide on Tablet", "nowonline"),
                                        checked: !!hideTablet,
                                        onChange: function(v) {
                                            setAttr({ hideTablet: !!v });
                                        },
                                    }),
                                    el(CheckboxControl, {
                                        label: __("Hide on Mobile", "nowonline"),
                                        checked: !!hideMobile,
                                        onChange: function(v) {
                                            setAttr({ hideMobile: !!v });
                                        },
                                    }),
                                    el(
                                        "div", { className: "now-elt-muted", style: { marginTop: 8 } },
                                        __(
                                            "Skjuler hele blokken pr. device. Renderes via server (frontend).",
                                            "nowonline"
                                        )
                                    )
                                ),

                                // Spacing – Desktop
                                el(
                                    PanelBody, {
                                        title: __("Spacing – Desktop", "nowonline"),
                                        initialOpen: true,
                                    },
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

                                // Spacing – Laptop
                                el(
                                    PanelBody, {
                                        title: __("Spacing – Laptop (≤1440px)", "nowonline"),
                                        initialOpen: false,
                                    },
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

                                // Spacing – Tablet
                                el(
                                    PanelBody, {
                                        title: __("Spacing – Tablet (≤1024px)", "nowonline"),
                                        initialOpen: false,
                                    },
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

                                // Spacing – Mobile
                                el(
                                    PanelBody, {
                                        title: __("Spacing – Mobile (≤767px)", "nowonline"),
                                        initialOpen: false,
                                    },
                                    RowTwo(
                                        __("Padding top (mobile)", "nowonline"),
                                        "padTopMobile",
                                        padTopMobile,
                                        __("Padding bottom (mobile)", "nowonline"),
                                        "padBottomMobile",
                                        padBottomMobile
                                    ),
                                    ResetBtn(["padTopMobile", "padBottomMobile"])
                                ),

                                el(
                                    "div", { className: "now-elt-muted", style: { marginTop: 8 } },
                                    __(
                                        "Tip: Brug enheder som px, rem, vh, % osv. Tomt felt = ingen override.",
                                        "nowonline"
                                    )
                                )
                            );
                        }

                        var blockProps = useBlockProps ? useBlockProps() : {};
                        var rootProps = Object.assign({}, blockProps, {
                            className: (
                                (blockProps.className || "") + " now-elt-edit-root"
                            ).trim(),
                        });

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
                            el(
                                "div", { className: "now-elt-tabbar", style: { margin: "10px 0" } },
                                TabBtn("content", __("Indhold", "nowonline")),
                                TabBtn("design", __("Design", "nowonline")),
                                TabBtn("background", __("Baggrund", "nowonline")),
                                TabBtn("advanced", __("Advanced", "nowonline"))
                            ),
                            el(
                                "div", {
                                    hidden: activeTab !== "content",
                                    style: {
                                        display: activeTab === "content" ? "block" : "none",
                                    },
                                },
                                ContentTab()
                            ),
                            el(
                                "div", {
                                    hidden: activeTab !== "design",
                                    style: { display: activeTab === "design" ? "block" : "none" },
                                },
                                DesignTab()
                            ),
                            el(
                                "div", {
                                    hidden: activeTab !== "background",
                                    style: {
                                        display: activeTab === "background" ? "block" : "none",
                                    },
                                },
                                BackgroundTab()
                            ),
                            el(
                                "div", {
                                    hidden: activeTab !== "advanced",
                                    style: {
                                        display: activeTab === "advanced" ? "block" : "none",
                                    },
                                },
                                AdvancedTab()
                            ),
                            el(
                                InspectorControls, {},
                                el(
                                    PanelBody, { title: __("(Info)", "nowonline"), initialOpen: false },
                                    el(
                                        "div", {},
                                        __("Denne blok rendres på frontend.", "nowonline")
                                    )
                                )
                            )
                        );
                    },

                    save: function() {
                        return null;
                    },
                    supports: { inserter: true },
                });
            }

            // Variationer
            if (
                Blocks &&
                typeof Blocks.registerBlockVariation === "function" &&
                Array.isArray(MAP)
            ) {
                MAP.forEach(function(t) {
                    var icon = t.thumb ?

                        function() {
                            return el("img", {
                                src: t.thumb,
                                alt: "",
                                className: "now-elt-var-thumb",
                            });
                        } :
                        Icon();
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

            if (window.console) console.info("[NowOnline] ELT registered.");
        } catch (e) {
            if (window && window.console)
                console.warn("[NowOnline Elementor Blocks] init error", e);
        }
    });
})();

// File: assets/frontend.js
(function() {
    "use strict";

    function pickTarget(root) {
        if (!root) return null;
        var sel = [
            "[data-now-bg]",
            ".now-bg",
            "[data-nowonline-bg]",
            ".nowonline-bg",
        ];
        for (var i = 0; i < sel.length; i++) {
            var el = root.querySelector(sel[i]);
            if (el) return el;
        }
        return null;
    }

    function applyBgColor(root, color) {
        var target = pickTarget(root) || root;
        if (!color) {
            target.style.removeProperty("background-color");
            return;
        }
        target.style.setProperty("background-color", color, "important");
    }

    function boot() {
        var roots = document.querySelectorAll(
            ".now-elt-frontend-root[data-now-bg-value]"
        );
        for (var i = 0; i < roots.length; i++) {
            var r = roots[i];
            var color = r.getAttribute("data-now-bg-value") || "";
            if (color) applyBgColor(r, color);
        }
    }

    if (document.readyState === "loading")
        document.addEventListener("DOMContentLoaded", boot);
    else boot();
})();
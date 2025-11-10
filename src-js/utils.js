// Fil: src-js/utils.js
import { __ } from "@wordpress/i18n";

/**
 * Afkoder HTML-entiteter.
 */
export function decodeEntities(input) {
  if (typeof input !== "string" || !input) return input;
  try {
    const doc = new DOMParser().parseFromString(
      "<!doctype html><body>" + input,
      "text/html"
    );
    return (doc && doc.body && doc.body.textContent) || "";
  } catch (e) {
    return input;
  }
}

/**
 * Finder den bedste preview-URL fra et skabelon-objekt.
 */
export function getPreviewSrc(t) {
  // ... (Indsæt hele getPreviewSrc-funktionen fra assets/editor.js her) ...
  function take(v) {
    if (!v) return "";
    if (Array.isArray(v)) {
      for (let i = 0; i < v.length; i++) {
        const hit = take(v[i]);
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
      const s =
        take(v.settings.preview) ||
        take(v.settings.image) ||
        take(v.settings.screenshot) ||
        take(v.settings.inserter);
      if (s) return s;
    }
    const keys = [
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
    for (let i2 = 0; i2 < keys.length; i2++)
      if (v[keys[i2]]) {
        const s2 = take(v[keys[i2]]);
        if (s2) return s2;
      }
    return "";
  }
  return take(t) || (t && t.meta && take(t.meta)) || "";
}

/**
 * Henter felt-definitioner for et template ID.
 */
export function getFieldDefs(id) {
  const M = window.NOWONLINE_FIELDS || {};
  const arr = (M && M[id]) || [];
  if (!Array.isArray(arr)) return [];
  return arr.map((d) => {
    const copy = { ...d };
    if (copy.label) copy.label = decodeEntities(copy.label);
    return copy;
  });
}

/**
 * Finder en skabelon ud fra ID.
 */
export function tplById(id) {
  const list = Array.isArray(window.NOWONLINE_TEMPLATES_DECODED)
    ? window.NOWONLINE_TEMPLATES_DECODED
    : Array.isArray(window.NOWONLINE_TEMPLATES)
    ? window.NOWONLINE_TEMPLATES
    : [];
  id = parseInt(id || 0, 10);
  for (let i = 0; i < list.length; i++)
    if (parseInt(list[i].id, 10) === id) return list[i];
  return null;
}

/**
 * Normaliserer felt-typer.
 */
export function norm(def) {
  const _t = (def && def.type ? String(def.type) : "").toLowerCase().trim();
  const _k = (def && def.key ? String(def.key) : "").toLowerCase().trim();

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

// Type-tjekkere
export const isRich = (d) => norm(d) === "rich";
export const isTextarea = (d) => norm(d) === "textarea";
export const isUrl = (d) => norm(d) === "url";
export const isImage = (d) => norm(d) === "img" || norm(d) === "bg";
export const isGallery = (d) => norm(d) === "gallery";
export const isVideo = (d) => norm(d) === "video";
export const isHeadingOrText = (d) => {
  const n = norm(d);
  return n === "text" || n === "p" || /^h[1-6]$/.test(n) || !n;
};
export const isButtonTextDef = (def) => {
  const key = String(def.key || "").toLowerCase();
  const label = String(def.label || "").toLowerCase();
  return (
    (/(cta|knap|button|btn)([_\s-]?text)?$/.test(key) ||
      /(cta|knap|button|btn)/.test(label)) &&
    !isTextarea(def) &&
    !isRich(def)
  );
};

/**
 * Simpel række-komponent
 */
export const Row = ({ label, children, key }) => (
  <div key={key} className="now-elt-sec-item">
    <div className="now-elt-label">{label}</div>
    <div className="now-elt-field">{children}</div>
  </div>
);

/**
 * Renset label
 */
export const labelFor = (def) => {
  const s = String(def.label || def.key || "");
  return s.replace(/\s*\((?:rich|wysiwyg|text|textarea)\)\s*$/i, "");
};

/**
 * Renser Rich HTML (client-side version)
 */
export function sanitizeRichHtml(input, inlineOnly) {
  // ... (Indsæt hele sanitizeRichHtml-funktionen fra assets/editor.js her) ...
  let html = String(input || "");
  if (!html) return "";

  if (inlineOnly) {
    try {
      const doc = new DOMParser().parseFromString(
        "<!doctype html><body>" + html,
        "text/html"
      );
      const text = (doc && doc.body && doc.body.textContent) || "";
      return text.replace(/\s+/g, " ").trim();
    } catch (e) {
      return "";
    }
  }

  try {
    const wrap = document.createElement("div");
    wrap.innerHTML = html;

    const allowed = {
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

    const stack = [];
    for (let i = 0; i < wrap.childNodes.length; i++)
      stack.push(wrap.childNodes[i]);

    let processed = 0,
      LIMIT = 10000;
    while (stack.length && processed < LIMIT) {
      const node = stack.pop();
      processed++;
      if (!node || node.nodeType !== 1) continue;

      const tag = node.tagName.toLowerCase();
      if (node.hasAttribute("class")) node.removeAttribute("class");
      const keep =
        allowed[tag] ||
        (tag === "span" || tag === "p" || tag === "div" ? ["style"] : []);

      for (let ai = node.attributes.length - 1; ai >= 0; ai--) {
        const name = node.attributes[ai].name.toLowerCase();
        if (keep.indexOf(name) === -1) node.removeAttribute(name);
      }

      for (let ci = node.childNodes.length - 1; ci >= 0; ci--)
        stack.push(node.childNodes[ci]);
    }
    return wrap.innerHTML;
  } catch (e) {
    return "";
  }
}

/**
 * Renser URL
 */
export function fixUrl(u) {
  // ... (Indsæt hele fixUrl-funktionen fra assets/editor.js her) ...
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

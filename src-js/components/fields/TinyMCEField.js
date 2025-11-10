// Fil: src-js/components/fields/TinyMCEField.js
import { __ } from "@wordpress/i18n";
import { useEffect, useRef } from "@wordpress/element";
import { Row, labelFor, sanitizeRichHtml } from "../../utils";

const OldEditor =
  (window.wp && (window.wp.editor || window.wp.oldEditor)) || null;

export const TinyMCEField = ({ block, def, activeTab, showEditor }) => {
  const { clientId, attributes, setAttributes } = block;
  const { fields } = attributes;
  const fieldKey = def.key;

  const inlineOnly = /^(titel|title|heading|overskrift|headline)$/i.test(
    String(fieldKey || "")
  );
  const initial = (fields && fields[fieldKey]) || "";
  const cleanedInitial = sanitizeRichHtml(initial, inlineOnly);

  const instId = `nowelt-${clientId.slice(0, 8)}-${fieldKey}`;
  const taRef = useRef(null);
  const fieldsRef = useRef(fields || {});

  useEffect(() => {
    fieldsRef.current = fields || {};
  }, [fields]);

  useEffect(() => {
    if (!showEditor || activeTab !== "content") return;
    if (!OldEditor || !OldEditor.initialize || !window.tinymce) return;

    let disposed = false,
      ed = null,
      wait = null,
      guard = null;

    const hasEditor = () =>
      !!(window.tinymce?.get && window.tinymce.get(instId));
    const readRaw = () =>
      ed?.getContent ? ed.getContent() : taRef.current?.value || "";
    const textMeaningful = (s) =>
      (s || "")
        .replace(/<[^>]+>/g, "")
        .replace(/\s+/g, " ")
        .trim().length > 0;

    const sync = () => {
      if (disposed) return;
      const raw = readRaw();
      const next = { ...fieldsRef.current };

      if (!textMeaningful(raw)) {
        if (Object.prototype.hasOwnProperty.call(next, fieldKey)) {
          delete next[fieldKey];
          setAttributes({ fields: next });
        }
        return;
      }

      const sanitized = sanitizeRichHtml(raw, inlineOnly);
      if (next[fieldKey] !== sanitized) {
        next[fieldKey] = sanitized;
        setAttributes({ fields: next });
      }
    };

    const bindWhenReady = () => {
      wait = setInterval(() => {
        if (disposed) return;
        ed = window.tinymce?.get && window.tinymce.get(instId);
        if (ed) {
          clearInterval(wait);
          if (cleanedInitial) ed.setContent(cleanedInitial);
          ed.on("change input keyup setcontent undo redo blur", sync);
        }
      }, 50);
    };

    const initIfNeeded = () => {
      if (hasEditor()) return;

      try {
        OldEditor.remove(instId);
      } catch (e) {}
      if (window.QTags?.instances[instId]) {
        try {
          delete window.QTags.instances[instId];
        } catch (e) {}
      }

      OldEditor.initialize(instId, {
        tinymce: {
          wpautop: !inlineOnly,
          forced_root_block: inlineOnly ? "" : "p",
          menubar: false,
          paste_as_text: !!inlineOnly,
          toolbar1: inlineOnly
            ? "bold,italic,underline,undo,redo"
            : "formatselect,bold,italic,link,bullist,numlist,blockquote,alignleft,aligncenter,alignright,undo,redo",
        },
        quicktags: false,
        mediaButtons: false,
      });
      bindWhenReady();
    };

    initIfNeeded();
    guard = setInterval(() => {
      if (disposed) return;
      if (activeTab === "content" && showEditor && !hasEditor()) {
        initIfNeeded();
      }
    }, 200);

    if (taRef.current) taRef.current.addEventListener("input", sync);

    return () => {
      disposed = true;
      try {
        sync();
      } catch (e) {}
      try {
        OldEditor.remove(instId);
      } catch (e) {}
      if (taRef.current) taRef.current.removeEventListener("input", sync);
      clearInterval(wait);
      clearInterval(guard);
    };
  }, [clientId, attributes.templateId, fieldKey, activeTab, showEditor]);

  return (
    <Row label={labelFor(def)} key={fieldKey}>
      <textarea id={instId} ref={taRef} defaultValue={cleanedInitial} />
    </Row>
  );
};

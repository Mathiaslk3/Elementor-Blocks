// Fil: src-js/components/Preview.js
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import { getPreviewSrc, tplById } from "../utils";

// Preview til Inserter/Preview-panelet
export const OnlyPreviewEl = ({ templateId }) => {
  const tpl = tplById(templateId) || {};
  const prevSrc = tpl._previewSrc || getPreviewSrc(tpl) || "";

  return (
    <div className="now-elt-inserter-preview">
      {prevSrc ? (
        <img
          src={prevSrc}
          alt=""
          draggable={false}
          onDragStart={(e) => e.preventDefault()}
          className="now-elt-canvas-preview now-elt-canvas-preview--large"
        />
      ) : (
        <div className="now-elt-inserter-preview__empty">
          {__("No preview available.", "nowonline")}
        </div>
      )}
    </div>
  );
};

// Det første "klikbare" preview-lag i editoren
export const PreviewFirstLayer = ({ templateId, openEditor }) => {
  const tpl = tplById(templateId) || {};
  const prevSrc = tpl._previewSrc || getPreviewSrc(tpl) || "";

  return (
    <div className="now-elt-flat">
      {prevSrc ? (
        <div
          role="button"
          tabIndex={0}
          onClick={openEditor}
          onKeyDown={(e) => {
            if (e.key === "Enter" || e.key === " ") openEditor();
          }}
          className="now-elt-preview-toggle"
          aria-label={__("Åbn editor", "nowonline")}
        >
          <img
            src={prevSrc}
            alt=""
            draggable={false}
            onDragStart={(e) => e.preventDefault()}
            className="now-elt-canvas-preview now-elt-canvas-preview--large"
          />
          <div className="now-elt-overlay-hint" style={{ marginTop: 8 }}>
            Klik for at redigere
          </div>
        </div>
      ) : (
        <div className="now-elt-inserter-preview__empty">
          {__("No preview available.", "nowonline")}
          <div style={{ marginTop: 8 }}>
            <Button variant="primary" onClick={openEditor}>
              {__("Åbn editor", "nowonline")}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
};

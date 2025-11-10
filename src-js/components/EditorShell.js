// Fil: src-js/components/EditorShell.js
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import { tplById, getPreviewSrc } from "../utils";

// Fanebladsknap
const TabBtn = ({ name, title, activeTab, setActiveTab }) => {
  const active = activeTab === name;
  return (
    <Button
      variant={active ? "primary" : "secondary"}
      onClick={() => setActiveTab(name)}
      aria-selected={active}
    >
      {title}
    </Button>
  );
};

// Hele skallen rundt om fanebladene
export const EditorShell = ({
  templateId,
  activeTab,
  setActiveTab,
  setShowEditor,
  children,
}) => {
  const tpl = tplById(templateId) || {};
  const prevSrc = tpl._previewSrc || getPreviewSrc(tpl) || "";
  const title = tpl.title || `#${tpl.id || templateId}`;

  return (
    <div className="now-elt-flat">
      {prevSrc && (
        <img
          className="now-elt-canvas-preview now-elt-canvas-preview--large"
          src={prevSrc}
          alt=""
          draggable={false}
        />
      )}
      <div className="now-elt-tabbar" style={{ marginTop: 12 }}>
        <div role="tablist">
          <TabBtn
            name="content"
            title={__("Indhold", "nowonline")}
            activeTab={activeTab}
            setActiveTab={setActiveTab}
          />
          <TabBtn
            name="design"
            title={__("Design", "nowonline")}
            activeTab={activeTab}
            setActiveTab={setActiveTab}
          />
          <TabBtn
            name="background"
            title={__("Baggrund", "nowonline")}
            activeTab={activeTab}
            setActiveTab={setActiveTab}
          />
          <TabBtn
            name="advanced"
            title={__("Advanced", "nowonline")}
            activeTab={activeTab}
            setActiveTab={setActiveTab}
          />
        </div>
      </div>
      <div
        className="nowelt-flat-titlebar"
        style={{
          display: "flex",
          alignItems: "center",
          gap: 8,
          marginTop: 8,
        }}
      >
        <h2 className="nowelt-flat-title" style={{ margin: 0 }}>
          {title}
        </h2>
        <Button
          variant="secondary"
          onClick={() => setShowEditor(false)}
          style={{ marginLeft: "auto" }}
        >
          {__("Vis preview", "nowonline")}
        </Button>
      </div>
      <div style={{ marginTop: 10 }}>{children}</div>
    </div>
  );
};

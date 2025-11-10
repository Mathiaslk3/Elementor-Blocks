// Fil: src-js/components/DesignTab.js
import { __ } from "@wordpress/i18n";
import {
  PanelBody,
  ColorPalette,
  TextControl,
  Button,
} from "@wordpress/components";

export const DesignTab = ({ attributes, setAttributes }) => {
  const {
    containerBg = "",
    btnTextColor = "",
    btnBorderColor = "",
    btnBorderWidth = "",
    btnBorderRadius = "",
    fsH1 = "",
    fsH2 = "",
    fsH3 = "",
    fsH4 = "",
    fsH5 = "",
    fsH6 = "",
    fsBody = "",
    fsBtn = "",
  } = attributes;

  const resetTypography = () => {
    setAttributes({
      fsH1: "",
      fsH2: "",
      fsH3: "",
      fsH4: "",
      fsH5: "",
      fsH6: "",
      fsBody: "",
      fsBtn: "",
    });
  };

  const sizeHelp = __(
    "Angiv CSS-størrelser for desktop (≥1025px). Eksempler: 48px, 3rem, 120%",
    "nowonline"
  );

  return (
    <div>
      <PanelBody title={__("Background", "nowonline")} initialOpen={true}>
        <div className="now-elt-row-flex">
          <div className="now-elt-row-label">
            {__("Baggrundsfarve", "nowonline")}
          </div>
          <ColorPalette
            value={containerBg}
            onChange={(v) => setAttributes({ containerBg: v || "" })}
          />
          <TextControl
            value={containerBg}
            onChange={(v) => setAttributes({ containerBg: (v || "").trim() })}
            placeholder="fx #cf4747, rgb(), var(--token), red"
            className="now-elt-input-wide"
          />
          <Button
            variant="secondary"
            onClick={() => setAttributes({ containerBg: "" })}
          >
            {__("Nulstil baggrund", "nowonline")}
          </Button>
        </div>
      </PanelBody>

      <PanelBody title={__("Knap", "nowonline")} initialOpen={false}>
        <div className="now-elt-row-flex">
          <div className="now-elt-row-label">
            {__("Tekstfarve", "nowonline")}
          </div>
          <ColorPalette
            value={btnTextColor}
            onChange={(v) => setAttributes({ btnTextColor: v || "" })}
          />
          <TextControl
            value={btnTextColor}
            onChange={(v) => setAttributes({ btnTextColor: (v || "").trim() })}
            placeholder={__("fx #ffffff eller rgba()", "nowonline")}
            className="now-elt-input-wide"
          />
        </div>
        <div className="now-elt-row-flex">
          <div className="now-elt-row-label">
            {__("Borderfarve", "nowonline")}
          </div>
          <ColorPalette
            value={btnBorderColor}
            onChange={(v) => setAttributes({ btnBorderColor: v || "" })}
          />
          <TextControl
            value={btnBorderColor}
            onChange={(v) =>
              setAttributes({ btnBorderColor: (v || "").trim() })
            }
            placeholder={__("fx #000000 eller rgba()", "nowonline")}
            className="now-elt-input-wide"
          />
        </div>
        <TextControl
          label={__("Border bredde", "nowonline")}
          value={btnBorderWidth}
          onChange={(v) => setAttributes({ btnBorderWidth: (v || "").trim() })}
          help={__("Fx 2px, 0.125rem eller 0 for ingen.", "nowonline")}
        />
        <TextControl
          label={__("Border radius", "nowonline")}
          value={btnBorderRadius}
          onChange={(v) => setAttributes({ btnBorderRadius: (v || "").trim() })}
          help={__("Fx 8px, 0.5rem eller 50%.", "nowonline")}
        />
        <div className="now-elt-mt-8">
          <Button
            variant="secondary"
            onClick={() =>
              setAttributes({
                btnTextColor: "",
                btnBorderColor: "",
                btnBorderWidth: "",
                btnBorderRadius: "",
              })
            }
          >
            {__("Nulstil knap", "nowonline")}
          </Button>
        </div>
      </PanelBody>

      <PanelBody
        title={__("Typografi (desktop)", "nowonline")}
        initialOpen={false}
      >
        <div className="now-elt-grid-2">
          <TextControl
            label="H1"
            value={fsH1}
            onChange={(v) => setAttributes({ fsH1: (v || "").trim() })}
            help={sizeHelp}
          />
          <TextControl
            label="H2"
            value={fsH2}
            onChange={(v) => setAttributes({ fsH2: (v || "").trim() })}
            help={sizeHelp}
          />
          <TextControl
            label="H3"
            value={fsH3}
            onChange={(v) => setAttributes({ fsH3: (v || "").trim() })}
            help={sizeHelp}
          />
          <TextControl
            label="H4"
            value={fsH4}
            onChange={(v) => setAttributes({ fsH4: (v || "").trim() })}
            help={sizeHelp}
          />
          <TextControl
            label="H5"
            value={fsH5}
            onChange={(v) => setAttributes({ fsH5: (v || "").trim() })}
            help={sizeHelp}
          />
          <TextControl
            label="H6"
            value={fsH6}
            onChange={(v) => setAttributes({ fsH6: (v || "").trim() })}
            help={sizeHelp}
          />
          <TextControl
            label={__("Brødtekst", "nowonline")}
            value={fsBody}
            onChange={(v) => setAttributes({ fsBody: (v || "").trim() })}
            help={sizeHelp}
          />
          <TextControl
            label={__("Knap", "nowonline")}
            value={fsBtn}
            onChange={(v) => setAttributes({ fsBtn: (v || "").trim() })}
            help={sizeHelp}
          />
        </div>
        <Button
          variant="secondary"
          onClick={resetTypography}
          style={{ marginTop: 6 }}
        >
          {__("Nulstil typografi", "nowonline")}
        </Button>
      </PanelBody>
    </div>
  );
};

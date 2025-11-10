// Fil: src-js/components/AdvancedTab.js
import { __ } from "@wordpress/i18n";
import {
  PanelBody,
  TextControl,
  CheckboxControl,
  Button,
} from "@wordpress/components";

const unitHelp = __(
  "Eksempler: 80px, 6rem, 10vh, 8% — tom = ingen ændring",
  "nowonline"
);

const RowTwo = ({ aLabel, aKey, aVal, bLabel, bKey, bVal, setAttributes }) => (
  <div className="now-elt-grid-2">
    <TextControl
      label={aLabel}
      value={aVal || ""}
      onChange={(v) => setAttributes({ [aKey]: (v || "").trim() })}
      placeholder="fx 80px"
      help={unitHelp}
    />
    <TextControl
      label={bLabel}
      value={bVal || ""}
      onChange={(v) => setAttributes({ [bKey]: (v || "").trim() })}
      placeholder="fx 80px"
      help={unitHelp}
    />
  </div>
);

const ResetBtn = ({ keys, setAttributes }) => (
  <Button
    variant="secondary"
    onClick={() => {
      const n = {};
      keys.forEach((k) => (n[k] = ""));
      setAttributes(n);
    }}
    style={{ marginTop: 4 }}
  >
    {__("Nulstil", "nowonline")}
  </Button>
);

export const AdvancedTab = ({ attributes, setAttributes }) => {
  const {
    hideDesktop = false,
    hideTablet = false,
    hideMobile = false,
  } = attributes;

  return (
    <div>
      <PanelBody title={__("Visibility", "nowonline")} initialOpen={true}>
        <CheckboxControl
          label={__("Skjul på computer", "nowonline")}
          checked={hideDesktop}
          onChange={(v) => setAttributes({ hideDesktop: !!v })}
        />
        <CheckboxControl
          label={__("Skjul på tablet", "nowonline")}
          checked={hideTablet}
          onChange={(v) => setAttributes({ hideTablet: !!v })}
        />
        <CheckboxControl
          label={__("Skjul på telefon", "nowonline")}
          checked={hideMobile}
          onChange={(v) => setAttributes({ hideMobile: !!v })}
        />
      </PanelBody>

      <PanelBody title={__("Computer", "nowonline")} initialOpen={true}>
        <RowTwo
          aLabel={__("Padding top (desktop)", "nowonline")}
          aKey="padTopDesktop"
          aVal={attributes.padTopDesktop}
          bLabel={__("Padding bottom (desktop)", "nowonline")}
          bKey="padBottomDesktop"
          bVal={attributes.padBottomDesktop}
          setAttributes={setAttributes}
        />
        <ResetBtn
          keys={["padTopDesktop", "padBottomDesktop"]}
          setAttributes={setAttributes}
        />
      </PanelBody>

      <PanelBody title={__("Bærbar", "nowonline")} initialOpen={false}>
        <RowTwo
          aLabel={__("Padding top (laptop)", "nowonline")}
          aKey="padTopLaptop"
          aVal={attributes.padTopLaptop}
          bLabel={__("Padding bottom (laptop)", "nowonline")}
          bKey="padBottomLaptop"
          bVal={attributes.padBottomLaptop}
          setAttributes={setAttributes}
        />
        <ResetBtn
          keys={["padTopLaptop", "padBottomLaptop"]}
          setAttributes={setAttributes}
        />
      </PanelBody>

      <PanelBody title={__("Tablet", "nowonline")} initialOpen={false}>
        <RowTwo
          aLabel={__("Padding top (tablet)", "nowonline")}
          aKey="padTopTablet"
          aVal={attributes.padTopTablet}
          bLabel={__("Padding bottom (tablet)", "nowonline")}
          bKey="padBottomTablet"
          bVal={attributes.padBottomTablet}
          setAttributes={setAttributes}
        />
        <ResetBtn
          keys={["padTopTablet", "padBottomTablet"]}
          setAttributes={setAttributes}
        />
      </PanelBody>

      <PanelBody title={__("Telefon", "nowonline")} initialOpen={false}>
        <RowTwo
          aLabel={__("Padding top (mobile)", "nowonline")}
          aKey="padTopMobile"
          aVal={attributes.padTopMobile}
          bLabel={__("Padding bottom (mobile)", "nowonline")}
          bKey="padBottomMobile"
          bVal={attributes.padBottomMobile}
          setAttributes={setAttributes}
        />
        <ResetBtn
          keys={["padTopMobile", "padBottomMobile"]}
          setAttributes={setAttributes}
        />
      </PanelBody>
    </div>
  );
};

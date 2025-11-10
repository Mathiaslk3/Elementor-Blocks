// Fil: src-js/components/BackgroundTab.js
import { __ } from "@wordpress/i18n";
import {
  PanelBody,
  Button,
  TextControl,
  CheckboxControl,
  SelectControl,
} from "@wordpress/components";
import { MediaUpload } from "@wordpress/block-editor";
import { Row } from "../utils";

// Fælles billedvælger-komponent (uændret)
const ImgPicker = ({ label, attrKey, attributes, setAttributes }) => {
  const url = attributes[attrKey] || "";
  const onSelect = (media) =>
    setAttributes({ [attrKey]: (media && media.url) || "" });
  const clear = () => setAttributes({ [attrKey]: "" });

  return (
    <Row label={label}>
      {url ? (
        <img src={url} alt="" className="now-elt-imgprev now-elt-bg-prev" />
      ) : (
        <div className="now-elt-imgprev now-elt-noimg">
          {__("No image selected", "nowonline")}
        </div>
      )}
      <MediaUpload
        onSelect={onSelect}
        value={0}
        allowedTypes={["image"]}
        render={({ open }) => (
          <div className="now-elt-btnrow">
            <Button variant="primary" onClick={open}>
              {__("Add Image", "nowonline")}
            </Button>
            <Button variant="secondary" onClick={clear}>
              {__("Clear", "nowonline")}
            </Button>
          </div>
        )}
      />
    </Row>
  );
};

// Videovælger-komponent (uændret)
const VideoPicker = ({ attributes, setAttributes }) => {
  const url = attributes.bgVideo || "";
  const onSelect = (media) =>
    setAttributes({ bgVideo: (media && media.url) || "" });

  return (
    <Row label={__("Baggrundsvideo", "nowonline")}>
      {url ? (
        <video
          src={url}
          controls
          className="now-elt-video-prev now-elt-bg-video-prev"
        />
      ) : (
        <div className="now-elt-imgprev now-elt-noimg">
          {__("No video selected", "nowonline")}
        </div>
      )}
      <MediaUpload
        onSelect={onSelect}
        value={0}
        allowedTypes={["video"]}
        render={({ open }) => (
          <>
            <div className="now-elt-btnrow">
              <Button variant="primary" onClick={open}>
                {__("Choose video", "nowonline")}
              </Button>
              <Button
                variant="secondary"
                onClick={() => setAttributes({ bgVideo: "" })}
              >
                {__("Remove", "nowonline")}
              </Button>
            </div>
            <TextControl
              value={url}
              onChange={(v) => setAttributes({ bgVideo: (v || "").trim() })}
              placeholder={__("or paste video URL…", "nowonline")}
              className="now-elt-mt-8 now-elt-input-wide"
            />
          </>
        )}
      />
    </Row>
  );
};

export const BackgroundTab = ({ attributes, setAttributes }) => {
  // --- OPDATERET: Tilføj "Standard" (tom streng) som den første valgmulighed ---
  const posOptions = [
    { value: "", label: __("Standard (fra skabelon)", "nowonline") },
    { value: "center center", label: "center center" },
    { value: "top center", label: "top center" },
    { value: "bottom center", label: "bottom center" },
    { value: "center left", label: "center left" },
    { value: "center right", label: "center right" },
    { value: "top left", label: "top left" },
    { value: "top right", label: "top right" },
    { value: "bottom left", label: "bottom left" },
    { value: "bottom right", label: "bottom right" },
  ];

  const sizeOptions = [
    { value: "", label: __("Standard (fra skabelon)", "nowonline") },
    { value: "cover", label: "cover" },
    { value: "contain", label: "contain" },
    { value: "auto", label: "auto" },
  ];

  const repeatOptions = [
    { value: "", label: __("Standard (fra skabelon)", "nowonline") },
    { value: "no-repeat", label: "No Repeat" },
    { value: "repeat", label: "Repeat" },
    { value: "repeat-x", label: "Repeat X" },
    { value: "repeat-y", label: "Repeat Y" },
  ];
  // --- SLUT OPDATERING ---

  return (
    <div>
      <PanelBody title={__("Baggrundsvideo", "nowonline")} initialOpen={true}>
        <VideoPicker attributes={attributes} setAttributes={setAttributes} />
      </PanelBody>
      <PanelBody title={__("Baggrundsbillede", "nowonline")} initialOpen={true}>
        <ImgPicker
          label={__("Baggrundsbillede", "nowonline")}
          attrKey="bgImg"
          attributes={attributes}
          setAttributes={setAttributes}
        />
        <div className="now-elt-grid-2" style={{ alignItems: "flex-end" }}>
          <SelectControl
            label={__("Background position", "nowonline")}
            value={attributes.bgPos || ""} // <-- RETTET
            onChange={(v) => setAttributes({ bgPos: v })}
            options={posOptions} // <-- RETTET
          />
          <SelectControl
            label={__("Background size", "nowonline")}
            value={attributes.bgSize || ""} // <-- RETTET
            onChange={(v) => setAttributes({ bgSize: v })}
            options={sizeOptions} // <-- RETTET
          />
          <SelectControl
            label={__("Background repeat", "nowonline")}
            value={attributes.bgRepeat || ""} // <-- RETTET
            onChange={(v) => setAttributes({ bgRepeat: v })}
            options={repeatOptions} // <-- RETTET
          />
          <CheckboxControl
            label={__("Background Fixed", "nowonline")}
            checked={!!attributes.bgFixed}
            onChange={(v) => setAttributes({ bgFixed: !!v })}
          />
        </div>
      </PanelBody>
      <PanelBody
        title={__("Baggrundsbillede (tablet)", "nowonline")}
        initialOpen={false}
      >
        <ImgPicker
          label={__("Tablet background", "nowonline")}
          attrKey="bgImgTablet"
          attributes={attributes}
          setAttributes={setAttributes}
        />
      </PanelBody>
      <PanelBody
        title={__("Baggrundsbillede (telefon)", "nowonline")}
        initialOpen={false}
      >
        <ImgPicker
          label={__("Mobile background", "nowonline")}
          attrKey="bgImgMobile"
          attributes={attributes}
          setAttributes={setAttributes}
        />
      </PanelBody>
    </div>
  );
};

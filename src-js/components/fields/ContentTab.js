// Fil: src-js/components/ContentTab.js
import { __ } from "@wordpress/i18n";
import { PanelBody, TextControl, TextareaControl } from "@wordpress/components";
import { RichText } from "@wordpress/block-editor";
import {
  isRich,
  isTextarea,
  isUrl,
  isImage,
  isGallery,
  isVideo,
  isHeadingOrText,
  isButtonTextDef,
  labelFor,
  Row,
  sanitizeRichHtml,
} from "../../utils";

// Importer felt-komponenter
import { ImageField } from "./ImageField";
import { VideoField } from "./VideoField";
import { GalleryField } from "./GalleryField";
import { UrlField } from "./UrlField";
import { TinyMCEField } from "./TinyMCEField";

// Tjek om vi har TinyMCE
const hasTiny = !!(
  window.wp &&
  (window.wp.editor || window.wp.oldEditor) &&
  window.wp.editor.initialize &&
  window.tinymce
);

export const ContentTab = ({ block, defs, activeTab, showEditor }) => {
  const { attributes, setAttributes } = block;
  const { fields } = attributes;

  const setField = (k, v) => {
    const next = { ...(fields || {}) };
    if (v === null || (typeof v === "string" && v.trim() === ""))
      delete next[k];
    else next[k] = v;
    setAttributes({ fields: next });
  };

  // Opdel definitioner
  const richDefs = defs.filter(isRich);
  let textDefs = defs.filter((d) => isHeadingOrText(d) && !isRich(d));
  const areaDefs = defs.filter(isTextarea);
  let urlDefs = defs.filter(isUrl);
  const imageDefs = defs.filter(isImage);
  const galDefs = defs.filter(isGallery);
  const videoDefs = defs.filter(isVideo);

  const btnTextDef = textDefs.find(isButtonTextDef);
  const btnUrlDef = urlDefs.length ? urlDefs[0] : null;

  if (btnTextDef) textDefs = textDefs.filter((d) => d !== btnTextDef);
  if (btnUrlDef) urlDefs = urlDefs.filter((d) => d !== btnUrlDef);

  // Byg felt-elementer
  const linkInputs = urlDefs.map((def) => (
    <UrlField key={def.key} block={block} def={def} />
  ));
  const imageInputs = imageDefs.map((def) => (
    <ImageField key={def.key} block={block} def={def} />
  ));
  const videoInputs = videoDefs.map((def) => (
    <VideoField key={def.key} block={block} def={def} />
  ));
  const galleryInputs = galDefs.map((def) => (
    <GalleryField key={def.key} block={block} def={def} />
  ));

  const richInputs = richDefs.map((def) => (
    <TinyMCEField
      key={def.key}
      block={block}
      def={def}
      activeTab={activeTab}
      showEditor={showEditor}
    />
  ));

  const textInputs = textDefs.map((def) => (
    <TinyMCEField
      key={def.key}
      block={block}
      def={def}
      activeTab={activeTab}
      showEditor={showEditor}
    />
  ));

  const areaInputs = areaDefs.map((def) => (
    <Row label={labelFor(def)} key={def.key}>
      <TextareaControl
        label={undefined}
        value={fields[def.key] || ""}
        rows={6}
        onChange={(v) => setField(def.key, v)}
      />
    </Row>
  ));

  const ButtonSection = () => {
    if (!btnTextDef && !btnUrlDef) return null;
    return (
      <PanelBody title={__("Knap", "nowonline")} initialOpen={true}>
        {btnTextDef && (
          <Row label={__("Tekst", "nowonline")} key="btn-text">
            <TextControl
              value={fields[btnTextDef.key] || ""}
              onChange={(v) => setField(btnTextDef.key, v)}
              placeholder={__("Skriv knaptekst…", "nowonline")}
              className="now-elt-input-narrow"
            />
          </Row>
        )}
        {btnUrlDef && <UrlField block={block} def={btnUrlDef} />}
      </PanelBody>
    );
  };

  return (
    <div>
      <ButtonSection />
      {richInputs}
      {textInputs}
      {areaInputs}
      {linkInputs}
      {videoInputs}
      {imageInputs}
      {galleryInputs}
    </div>
  );
};

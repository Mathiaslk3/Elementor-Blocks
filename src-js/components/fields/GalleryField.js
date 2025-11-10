// Fil: src-js/components/fields/GalleryField.js
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import { MediaUpload } from "@wordpress/block-editor";
import { Row, labelFor } from "../../utils";

export const GalleryField = ({ block, def }) => {
  let value = (block.attributes.fields || {})[def.key] || [];
  if (!Array.isArray(value)) value = [];

  const onSelect = (items) => {
    let urls = [];
    if (Array.isArray(items))
      urls = items
        .map((m) => (m && (m.url || m.source_url)) || "")
        .filter(Boolean);
    else if (items && items.url) urls = [items.url];
    const next = { ...(block.attributes.fields || {}) };
    next[def.key] = urls;
    block.setAttributes({ fields: next });
  };

  const clear = () => {
    const next = { ...(block.attributes.fields || {}) };
    delete next[def.key];
    block.setAttributes({ fields: next });
  };

  const thumbs = value.length ? (
    <div className="now-elt-gallery-thumbs">
      {value.map((u, i) => (
        <img key={i} src={u} alt="" className="now-elt-imgprev" />
      ))}
    </div>
  ) : (
    <div className="now-elt-imgprev now-elt-noimg">
      {__("Ingen billeder i galleriet", "nowonline")}
    </div>
  );

  return (
    <Row label={labelFor(def)} key={def.key}>
      <div>
        {thumbs}
        <MediaUpload
          onSelect={onSelect}
          allowedTypes={["image"]}
          multiple={true}
          gallery={true}
          value={value.map((v, i) => i)} // Behøver bare et array af korr. længde
          render={({ open }) => (
            <div className="now-elt-btnrow">
              <Button variant="primary" onClick={open}>
                {__("Vælg billeder", "nowonline")}
              </Button>
              <Button variant="secondary" onClick={clear}>
                {__("Ryd galleri", "nowonline")}
              </Button>
            </div>
          )}
        />
      </div>
    </Row>
  );
};

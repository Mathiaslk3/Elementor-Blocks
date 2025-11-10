// Fil: src-js/components/fields/ImageField.js
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import { MediaUpload } from "@wordpress/block-editor";
import { Row, labelFor } from "../../utils";

export const ImageField = ({ block, def }) => {
  const url = (block.attributes.fields || {})[def.key] || "";

  const onSelect = (media) => {
    const u = (media && media.url) || "";
    const next = { ...(block.attributes.fields || {}) };
    next[def.key] = u;
    block.setAttributes({ fields: next });
  };

  const clear = () => {
    const next = { ...(block.attributes.fields || {}) };
    delete next[def.key];
    block.setAttributes({ fields: next });
  };

  const preview = url ? (
    <img src={url} className="now-elt-imgprev" alt="" />
  ) : (
    <div className="now-elt-imgprev now-elt-noimg">
      {__("No image", "nowonline")}
    </div>
  );

  return (
    <Row label={labelFor(def)} key={def.key}>
      <div>
        {preview}
        <MediaUpload
          onSelect={onSelect}
          allowedTypes={["image"]}
          value={0}
          render={({ open }) => (
            <div className="now-elt-btnrow">
              <Button variant="primary" onClick={open}>
                {__("Vælg billede", "nowonline")}
              </Button>
              <Button variant="secondary" onClick={clear}>
                {__("Fjern", "nowonline")}
              </Button>
            </div>
          )}
        />
      </div>
    </Row>
  );
};

// Fil: src-js/components/fields/VideoField.js
import { __ } from "@wordpress/i18n";
import { Button, TextControl } from "@wordpress/components";
import { MediaUpload } from "@wordpress/block-editor";
import { Row, labelFor, fixUrl } from "../../utils";

export const VideoField = ({ block, def }) => {
  const key = def.key;
  const fields = block.attributes.fields || {};
  const url = fields[key] || "";
  const posterKey = key + "_poster";
  const poster = fields[posterKey] || "";

  const setField = (k, v) => {
    const next = { ...fields };
    if (v === null) delete next[k];
    else next[k] = v;
    block.setAttributes({ fields: next });
  };

  const vidPreview = url ? (
    <video
      src={url}
      poster={poster || undefined}
      controls
      className="now-elt-video-prev"
    />
  ) : (
    <div className="now-elt-imgprev now-elt-noimg">
      {__("Ingen video valgt", "nowonline")}
    </div>
  );

  const posterPreview = poster ? (
    <img src={poster} alt="" className="now-elt-imgprev now-elt-poster-prev" />
  ) : null;

  return (
    <Row label={labelFor(def)} key={key}>
      <div>
        {vidPreview}
        <MediaUpload
          onSelect={(media) => setField(key, (media && media.url) || "")}
          allowedTypes={["video"]}
          value={0}
          render={({ open }) => (
            <>
              <div className="now-elt-mt-6 now-elt-btnrow">
                <Button variant="primary" onClick={open}>
                  {__("Vælg video", "nowonline")}
                </Button>
                <Button variant="secondary" onClick={() => setField(key, null)}>
                  {__("Fjern", "nowonline")}
                </Button>
              </div>
              <TextControl
                type="url"
                value={url || ""}
                onChange={(v) => setField(key, fixUrl(v || ""))}
                placeholder={__("eller indsæt video-URL…", "nowonline")}
                className="now-elt-mt-8"
              />
            </>
          )}
        />
        <div className="now-elt-mt-10">
          <div className="now-elt-label">
            {__("Poster (valgfri)", "nowonline")}
          </div>
          {posterPreview}
          <MediaUpload
            onSelect={(media) =>
              setField(posterKey, (media && media.url) || "")
            }
            allowedTypes={["image"]}
            value={0}
            render={({ open }) => (
              <div className="now-elt-btnrow">
                <Button variant="primary" onClick={open}>
                  {__("Vælg poster", "nowonline")}
                </Button>
                <Button
                  variant="secondary"
                  onClick={() => setField(posterKey, null)}
                >
                  {__("Fjern", "nowonline")}
                </Button>
              </div>
            )}
          />
        </div>
      </div>
    </Row>
  );
};

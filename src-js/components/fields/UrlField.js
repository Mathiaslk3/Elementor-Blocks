// Fil: src-js/components/fields/UrlField.js
import { __ } from "@wordpress/i18n";
import { CheckboxControl, TextControl } from "@wordpress/components";
import { LinkControl } from "@wordpress/block-editor";
import { Row, labelFor, fixUrl } from "../../utils";

export const UrlField = ({ block, def }) => {
  const fields = block.attributes.fields || {};
  const val = fields[def.key];
  const curr =
    val && typeof val === "object"
      ? val
      : { url: val || "", newTab: false, type: "external" };

  const setField = (k, v) => {
    const next = { ...fields };
    if (v === null || (typeof v === "string" && v.trim() === ""))
      delete next[k];
    else next[k] = v;
    block.setAttributes({ fields: next });
  };

  return (
    <Row label={labelFor(def)} key={def.key}>
      <LinkControl
        value={{ url: curr.url || "" }}
        onChange={(next) => {
          const url =
            typeof next === "string" ? next : (next && next.url) || "";
          const newTab = !!(
            next &&
            (next.opensInNewTab || next.newTab || next.target === "_blank")
          );
          setField(def.key, { url: fixUrl(url), newTab });
        }}
        showInitialSuggestions={true}
        withCreateSuggestion={false}
      />
      <div className="now-elt-mt-6">
        <CheckboxControl
          label={__("Åbn i ny fane", "nowonline")}
          checked={!!curr.newTab}
          onChange={(v) => {
            setField(def.key, { ...curr, url: fixUrl(curr.url), newTab: !!v });
          }}
        />
      </div>
    </Row>
  );
};

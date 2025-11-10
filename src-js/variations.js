// Fil: src-js/variations.js
import { registerBlockVariation } from "@wordpress/blocks";
import { __ } from "@wordpress/i18n";
import { createElement } from "@wordpress/element";
import { Icon } from "./icon";
import { decodeEntities, getPreviewSrc } from "./utils";

export const registerVariations = () => {
  const RAW_MAP = Array.isArray(window.NOWONLINE_TEMPLATES)
    ? window.NOWONLINE_TEMPLATES
    : [];

  const MAP = RAW_MAP.map((t) => {
    const copy = { ...t };
    copy.title = decodeEntities(t.title || "");
    copy._previewSrc = getPreviewSrc(t) || "";
    return copy;
  });
  window.NOWONLINE_TEMPLATES_DECODED = MAP;

  MAP.forEach((t) => {
    const variationIcon = t.thumb ? (
      <span
        className="now-elt-var-thumb"
        style={{ backgroundImage: `url(${t.thumb})` }}
        aria-hidden="true"
      />
    ) : (
      <Icon />
    );

    registerBlockVariation("nowonline/elt-template", {
      name: `nowonline-elt-${t.id}`,
      title: t.title || `#${t.id}`,
      description: __("Elementor template", "nowonline"),
      icon: variationIcon,
      attributes: { templateId: t.id },
      example: { attributes: { templateId: t.id } },
      scope: ["inserter"],
      keywords: ["elementor", "template", "nowonline"],
    });
  });
};

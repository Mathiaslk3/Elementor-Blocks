// Fil: src-js/index.js
import { registerBlockType } from "@wordpress/blocks";
import { __ } from "@wordpress/i18n";
import domReady from "@wordpress/dom-ready";

import { Icon } from "./icon";
import { Edit } from "./components/Edit";
import { registerVariations } from "./variations";

// CSS til editoren importeres, så build-værktøjet håndterer det
// Du skal flytte assets/editor.css til src-js/editor.scss
// import './editor.scss';

domReady(() => {
  // 1. Registrer hoved-blokken
  registerBlockType("nowonline/elt-template", {
    apiVersion: 2,
    title: __("Elementor Template", "nowonline"),
    icon: Icon,
    category: "nowonline-elementor", // Antager du har registreret denne kategori i PHP
    supports: { inserter: true, align: ["full"] },
    example: { attributes: { templateId: 0 } },
    attributes: {
      templateId: { type: "number", default: 0 },
      gap: { type: "number", default: 24 },
      fields: { type: "object", default: {} },
      design: { type: "object", default: {} },
      background: { type: "object", default: {} },
      responsive: { type: "object", default: {} },
      spacing: { type: "object", default: {} },
      containerBg: { type: "string", default: "" },
      btnTextColor: { type: "string", default: "" },
      btnBorderColor: { type: "string", default: "" },
      btnBorderWidth: { type: "string", default: "" },
      btnBorderRadius: { type: "string", default: "" },
      fsH1: { type: "string", default: "" },
      fsH2: { type: "string", default: "" },
      fsH3: { type: "string", default: "" },
      fsH4: { type: "string", default: "" },
      fsH5: { type: "string", default: "" },
      fsH6: { type: "string", default: "" },
      fsBody: { type: "string", default: "" },
      fsBtn: { type: "string", default: "" },
      bgVideo: { type: "string", default: "" },
      bgImg: { type: "string", default: "" },
      bgImgTablet: { type: "string", default: "" },
      bgImgMobile: { type: "string", default: "" },
      bgPos: { type: "string", default: "center center" },
      bgSize: { type: "string", default: "cover" },
      bgRepeat: { type: "string", default: "no-repeat" },
      bgFixed: { type: "boolean", default: false },
      hideDesktop: { type: "boolean", default: false },
      hideTablet: { type: "boolean", default: false },
      hideMobile: { type: "boolean", default: false },
      padTopDesktop: { type: "string", default: "" },
      padBottomDesktop: { type: "string", default: "" },
      padTopLaptop: { type: "string", default: "" },
      padBottomLaptop: { type: "string", default: "" },
      padTopTablet: { type: "string", default: "" },
      padBottomTablet: { type: "string", default: "" },
      padTopMobile: { type: "string", default: "" },
      padBottomMobile: { type: "string", default: "" },
    },
    edit: Edit,
    save: () => null, // Dynamisk blok, gemmer intet
  });

  // 2. Registrer alle varianter
  registerVariations();

  console.info("[NowOnline] ELT registered via new build system.");
});

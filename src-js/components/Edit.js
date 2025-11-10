// Fil: src-js/components/Edit.js
import { __ } from "@wordpress/i18n";
import { useBlockProps } from "@wordpress/block-editor";
import { useState, useEffect, useRef } from "@wordpress/element";
import { useSelect, useDispatch } from "@wordpress/data";

import { getFieldDefs } from "../utils";
import { OnlyPreviewEl, PreviewFirstLayer } from "./Preview";
import { EditorShell } from "./EditorShell";

// --- RETTET IMPORT-STIER ---
// Disse filer ligger på samme niveau som Edit.js
import { ContentTab } from "./ContentTab";
import { DesignTab } from "./DesignTab";
import { BackgroundTab } from "./BackgroundTab";
import { AdvancedTab } from "./AdvancedTab";
// --- SLUT PÅ RETTELSE ---

export const Edit = (props) => {
  const { attributes, setAttributes, clientId, __unstableIsPreview } = props;
  const { templateId } = attributes;

  const [showEditor, setShowEditor] = useState(false);
  const [activeTab, setActiveTab] = useState("content");

  // Hent feltdefinitioner
  const defs = getFieldDefs(templateId) || [];

  // Nulstil editor-visning, når templateId ændres
  useEffect(() => {
    setShowEditor(false);
  }, [templateId]);

  // Håndter blokkens placering (flyt ud af kolonner)
  const movedRef = useRef(false);
  const { parentBlockType } = useSelect(
    (select) => {
      const { getBlockParents, getBlockName } = select("core/block-editor");
      const parents = getBlockParents(clientId);
      const firstParentId = parents[parents.length - 1];
      return {
        parentBlockType: firstParentId ? getBlockName(firstParentId) : null,
      };
    },
    [clientId]
  );

  const { removeBlocks, insertBlocks } = useDispatch("core/block-editor");
  const { createNotice } = useDispatch("core/notices");

  useEffect(() => {
    const PROBLEM_PARENTS = {
      "core/columns": 1,
      "core/column": 1,
      "core/row": 1,
      "core/group": 1, // Antager, at group også kan være et problem
    };

    if (PROBLEM_PARENTS[parentBlockType] && !movedRef.current) {
      const block = props.block; // Få fat i det korrekte blok-objekt

      // Tjek om block er valid før vi forsøger at genindsætte
      if (block && block.clientId) {
        removeBlocks([clientId], false);
        // Brug insertBlocks med det fulde blok-objekt
        insertBlocks(block);

        movedRef.current = true;
        createNotice(
          "warning",
          __(
            "NowOnline-blokken kan ikke placeres side om side. Den er flyttet til fuld bredde.",
            "nowonline"
          ),
          { type: "snackbar" }
        );
      }
    }
  }, [
    parentBlockType,
    clientId,
    removeBlocks,
    insertBlocks,
    createNotice,
    props.block,
  ]); // Tilføjet props.block som dependency

  // Håndter preview-tilstand (fx i Inserter)
  if (__unstableIsPreview) {
    return <OnlyPreviewEl templateId={templateId} />;
  }

  // Standard editor-visning
  const blockProps = useBlockProps({
    className: "now-elt-edit-root",
  });

  if (!showEditor) {
    return (
      <div {...blockProps}>
        <PreviewFirstLayer
          templateId={templateId}
          openEditor={() => setShowEditor(true)}
        />
      </div>
    );
  }

  // Vis fuld editor
  const tabContent =
    activeTab === "design" ? (
      <DesignTab attributes={attributes} setAttributes={setAttributes} />
    ) : activeTab === "background" ? (
      <BackgroundTab attributes={attributes} setAttributes={setAttributes} />
    ) : activeTab === "advanced" ? (
      <AdvancedTab attributes={attributes} setAttributes={setAttributes} />
    ) : (
      <ContentTab
        block={props}
        defs={defs}
        activeTab={activeTab}
        showEditor={showEditor}
      />
    );

  return (
    <div {...blockProps}>
      <EditorShell
        templateId={templateId}
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        setShowEditor={setShowEditor}
      >
        {tabContent}
      </EditorShell>
    </div>
  );
};

// Fil: src-js/components/Edit.js
import { __ } from "@wordpress/i18n";
import { useBlockProps } from "@wordpress/block-editor";
import { useState, useEffect, useRef } from "@wordpress/element";
import { useSelect, useDispatch } from "@wordpress/data";

import { getFieldDefs } from "../utils";
import { OnlyPreviewEl, PreviewFirstLayer } from "./Preview";
import { EditorShell } from "./EditorShell";

// Importer fane-komponenterne
import { ContentTab } from "./ContentTab";
import { DesignTab } from "./DesignTab";
import { BackgroundTab } from "./BackgroundTab";
import { AdvancedTab } from "./AdvancedTab";

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

  // --- START PÅ RETTELSE ---

  // Hent block-data og dispatchers
  const { block, parentBlockType } = useSelect(
    (select) => {
      const { getBlock, getBlockParents, getBlockName } =
        select("core/block-editor");
      const parents = getBlockParents(clientId, true); // true = inkluderer root
      const firstParentId = parents.length > 0 ? parents[0] : null;

      return {
        block: getBlock(clientId),
        parentBlockType: firstParentId ? getBlockName(firstParentId) : null,
      };
    },
    [clientId]
  );

  const { removeBlocks, insertBlocks } = useDispatch("core/block-editor");
  const { createNotice } = useDispatch("core/notices");
  const movedRef = useRef(false);

  useEffect(() => {
    const PROBLEM_PARENTS = {
      "core/columns": 1,
      "core/column": 1,
      "core/row": 1,
      "core/group": 1,
    };

    // 'parentBlockType' er nu korrekt defineret via useSelect
    if (PROBLEM_PARENTS[parentBlockType] && !movedRef.current) {
      if (block) {
        removeBlocks([clientId], false);
        insertBlocks(block); // Genindsæt selve blokken på rod-niveau

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
    block,
  ]); // Dependency array er nu korrekt

  // --- SLUT PÅ RETTELSE ---

  // Slette-funktion (fra forrige trin, er korrekt)
  const onDeleteBlock = () => {
    removeBlocks(clientId);
  };

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
        onDeleteBlock={onDeleteBlock}
      >
        {tabContent}
      </EditorShell>
    </div>
  );
};

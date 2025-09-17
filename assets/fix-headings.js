/* ============================================================================
 * File: assets/fix-headings.js  (v3.1 – verbose logging, adopt user heading)
 * ============================================================================ */
(function () {
  var DEBUG = (function () {
    try {
      if (typeof window.NOWONLINE_DEBUG !== "undefined")
        return !!window.NOWONLINE_DEBUG;
      var q = new URLSearchParams(window.location.search);
      if (q.get("nowonline_debug") === "1") return true;
      return localStorage.getItem("NOWONLINE_DEBUG") === "1";
    } catch (e) {
      return false;
    }
  })();
  function log() {
    if (DEBUG && console && console.debug)
      console.debug.apply(
        console,
        ["[nowonline-fix-h]"].concat([].slice.call(arguments))
      );
  }
  if (DEBUG) log("debug enabled");
  window.NOWONLINE_DEBUG_ON = function (on) {
    try {
      localStorage.setItem("NOWONLINE_DEBUG", on ? "1" : "");
    } catch (e) {}
    window.location.reload();
  };

  function getDirectHeadings(container) {
    var out = [];
    for (var n = container.firstElementChild; n; n = n.nextElementSibling) {
      if (n.nodeType === 1 && /^H[1-6]$/.test(n.tagName)) out.push(n);
    }
    return out;
  }
  function describe(el) {
    if (!el) return "null";
    var attrs = {};
    try {
      for (var i = 0; i < el.attributes.length; i++) {
        var a = el.attributes[i];
        attrs[a.name] = a.value;
      }
    } catch (e) {}
    return {
      tag: el.tagName,
      classes: el.className,
      attrs: attrs,
      text: (el.textContent || "").slice(0, 80),
    };
  }

  function mergeContainers(container) {
    if (!container || container.__nowonlineMerged) return;
    var title = container.querySelector(".elementor-heading-title");
    if (!title) return;

    var headings = getDirectHeadings(container);
    if (!headings.length) return;
    var extras = [];
    for (var i = 0; i < headings.length; i++) {
      if (headings[i] !== title) extras.push(headings[i]);
    }
    if (!extras.length) return;

    var winner = extras[0];
    log("found", { title: describe(title), extras: extras.map(describe) });

    if (extras.length > 1) {
      var html = winner.innerHTML;
      for (var k = 1; k < extras.length; k++) html += extras[k].innerHTML;
      winner.innerHTML = html;
      log("merged content from extras into winner");
    }

    try {
      var tClasses = (title.getAttribute("class") || "")
          .split(/\s+/)
          .filter(Boolean),
        added = [];
      for (var c = 0; c < tClasses.length; c++) {
        if (!winner.classList.contains(tClasses[c])) {
          winner.classList.add(tClasses[c]);
          added.push(tClasses[c]);
        }
      }
      if (added.length) log("added classes →", added.join(" "));
      for (var ai = 0; ai < title.attributes.length; ai++) {
        var a = title.attributes[ai];
        if (a.name === "class") continue;
        if (a.name === "id") {
          if (winner.hasAttribute("id"))
            winner.setAttribute("data-original-id", winner.getAttribute("id"));
          winner.setAttribute("id", a.value);
          log("set id →", a.value);
          continue;
        }
        if (!winner.hasAttribute(a.name) || /^data-|^aria-/.test(a.name)) {
          winner.setAttribute(a.name, a.value);
          log("copied attr →", a.name, a.value);
        }
      }
      var baseStyle = (title.getAttribute("style") || "").trim(),
        userStyle = (winner.getAttribute("style") || "").trim(),
        merged = (baseStyle ? baseStyle + "; " : "") + userStyle;
      if (merged) {
        winner.setAttribute("style", merged);
        log("merged style");
      }
    } catch (e) {
      log("attr merge fail", e);
    }

    try {
      if (title.parentNode) {
        title.parentNode.removeChild(title);
        log("removed template title");
      }
    } catch (e) {
      log("remove title fail", e);
    }
    for (var x = 1; x < extras.length; x++) {
      try {
        if (extras[x].parentNode) {
          extras[x].parentNode.removeChild(extras[x]);
          log("removed extra", describe(extras[x]));
        }
      } catch (e) {
        log("remove extra fail", e);
      }
    }

    container.__nowonlineMerged = true;
    log("final winner", describe(winner));
  }

  function process(root) {
    var scope = root || document;
    var containers = scope.querySelectorAll(
      ".elementor-widget-heading .elementor-widget-container"
    );
    if (!containers || !containers.length) return;
    for (var i = 0; i < containers.length; i++) mergeContainers(containers[i]);
  }
  window.nowonlineFixHeadingsRun = function (root) {
    try {
      process(root || document);
    } catch (e) {
      log("manual run error", e);
    }
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function () {
      process(document);
    });
  } else {
    process(document);
  }
  if (window.elementorFrontend && elementorFrontend.hooks) {
    var cb = function ($scope) {
      var n = $scope && $scope[0];
      if (n) process(n);
    };
    elementorFrontend.hooks.addAction(
      "frontend/element_ready/heading.default",
      cb
    );
    elementorFrontend.hooks.addAction("frontend/element_ready/widget", cb);
    elementorFrontend.hooks.addAction("frontend/element_ready/global", cb);
  }
  try {
    var mo = new MutationObserver(function (list) {
      for (var i = 0; i < list.length; i++) {
        var m = list[i];
        if (!m.addedNodes) continue;
        for (var j = 0; j < m.addedNodes.length; j++) {
          var node = m.addedNodes[j];
          if (!node || node.nodeType !== 1) continue;
          if (
            (node.matches &&
              node.matches(
                ".elementor-widget-heading, .elementor-widget-heading *"
              )) ||
            (node.querySelector &&
              node.querySelector(".elementor-widget-heading"))
          ) {
            process(node);
          }
        }
      }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) {
    log("MO fail", e);
  }
})();

/* Woodex OS ↔ the ported v2.1 admin — the adapter (docs/audit/16.7, 16.8).
 *
 * Loaded BEFORE admin.js so that everything their shell does at boot already talks to Woodex OS:
 *   · the screen comes from the OS URL, not from their sidebar;
 *   · their two API endpoints are rewritten to the OS routes (same `{action}` protocol);
 *   · every request carries the OS CSRF token, and the session cookie is the only authentication;
 *   · their sign-in screen is never reached — the OS session is the session.
 *
 * Nothing here patches a module: the modules are copied unchanged, and every action they call resolves
 * (or honestly refuses) on the server.
 */
(function () {
  "use strict";

  var C = window.WX_STUDIO || {};
  var screen = C.screen || "dashboard";

  /* Their router reads location.hash on boot. Put the screen there without adding a history entry. */
  try {
    if (!/^#\//.test(location.hash)) {
      history.replaceState(null, "", location.pathname + "#/" + screen);
    }
  } catch (e) { /* file:// or a blocked history API: the default screen still renders */ }

  /* Their shell restores a token from sessionStorage and boots with it. There is no token any more — the
     cookie decides — so we hand it a marker and let the server answer `status` and `me` from the session. */
  try { sessionStorage.setItem("wxaTok", "os-session"); } catch (e) {}

  var API = C.api || "/admin/website/studio/api";
  var BAPI = C.builderApi || "/admin/website/studio/builder-api";

  var realFetch = window.fetch.bind(window);

  window.fetch = function (input, init) {
    if (typeof input === "string") {
      if (input === "/api/admin.php") input = API;
      else if (input === "/api/builder.php") input = BAPI;
    }

    init = init || {};
    var headers = {};
    var source = init.headers || {};

    if (typeof source.forEach === "function" && typeof source.get === "function") {
      source.forEach(function (value, key) { headers[key] = value; });
    } else {
      Object.keys(source).forEach(function (key) { headers[key] = source[key]; });
    }

    // Laravel's CSRF check, on the session's own token — not theirs.
    headers["X-CSRF-TOKEN"] = C.csrf || "";
    headers["X-Requested-With"] = "XMLHttpRequest";
    init.headers = headers;
    init.credentials = init.credentials || "same-origin";

    return realFetch(input, init);
  };

  /* The OS strip is the only navigation these screens carry; keep the hash when leaving so a reload of the
     OS page does not try to open a screen that no longer exists. */
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !document.querySelector("#modal:not([hidden])")) {
      var exit = document.querySelector(".wx21-exit");
      if (exit) exit.focus();
    }
  });
})();

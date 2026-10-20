/* Woodex OS — the City pages screen, ported from the v2.1 admin (docs/audit/16.8).
 *
 * v2.1 kept city drafts as content rows and published them by writing HTML. The OS has one kind of page,
 * so a city draft is simply a page of type `city` with draft status: it appears in this list, opens in the
 * section editor, and goes live from Pages when it is ready.
 *
 * “Bulk create with AI” is not carried over: the AI rewrite needs `ai_run`, which is not ported. The bulk
 * path still exists — one city per line, copied from a template — and the screen says the words are the
 * template's until someone edits them, rather than pretending a key is doing it.
 */
(function () {
  "use strict";

  var W = window.WXA, api = W.api, esc = W.esc, ic = W.ic, $ = W.$, toast = W.toast,
      modal = W.modal, closeModal = W.closeModal, head = W.head;

  var EDIT = "/admin/website/pages/";
  var MENUS = "/admin/website/menus";

  function slugify(s) {
    return String(s || "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 60);
  }

  function cityName(p) {
    var first = String(p.url || "").replace(/^\/+|\/+$/g, "").split("/")[0];
    return first ? first.replace(/-/g, " ").replace(/\b\w/g, function (c) { return c.toUpperCase(); }) : p.title;
  }

  W.VIEWS.cities = function (el) {
    el.innerHTML = head("City pages", "Cities",
        '<button class="btn" id="ct-bulk">' + ic("plus") + 'Bulk create</button>' +
        '<button class="btn pri" id="ct-new">' + ic("plus") + 'New city</button>') +
      '<div class="banner">' + ic("info") + '<span>New cities are copied from a template city and saved as <b>drafts</b>. ' +
        'Review each draft, then publish it from Pages. Add a new city to the footer menu in ' +
        '<a href="' + MENUS + '">Header &amp; footer</a>.</span></div>' +
      '<div class="card"><div class="card-h"><h3>Cities</h3>' +
        '<input id="ct-q" class="sm-in" placeholder="Search…"></div>' +
        '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>City</th><th>Status</th><th>Address</th><th></th></tr></thead>' +
        '<tbody id="ct-rows"><tr><td colspan="4" class="muted">Loading…</td></tr></tbody></table></div></div>';

    var cities = [];

    function draw() {
      var q = ($("#ct-q").value || "").toLowerCase();
      var shown = cities.filter(function (p) {
        return !q || (cityName(p) + p.url).toLowerCase().indexOf(q) > -1;
      });

      // Drafts first: they are the ones that still need someone's attention (v2.1 did the same).
      shown.sort(function (a, b) {
        return a.status === b.status ? cityName(a).localeCompare(cityName(b)) : (a.status === "draft" ? -1 : 1);
      });

      $("#ct-rows").innerHTML = shown.length ? shown.map(function (p) {
        var live = p.status === "published";
        return '<tr class="click" data-i="' + p.id + '">' +
          '<td><b>' + esc(cityName(p)) + '</b></td>' +
          '<td><span class="badge ' + (live ? 'ok' : 'warn') + '">' + (live ? 'Live' : 'Draft') + '</span></td>' +
          '<td class="muted">' + esc(p.url) + '</td>' +
          '<td class="r"><span class="btn sm">' + ic("square-pen") + (live ? 'Edit content' : 'Review') + '</span></td></tr>';
      }).join("") : '<tr><td colspan="4" class="muted">No city page matches.</td></tr>';

      W.fillIcons($("#ct-rows"));
    }

    api("pages").then(function (r) {
      if (!r.ok) {
        $("#ct-rows").innerHTML = '<tr><td colspan="4"><b style="color:#d92d20">Could not load city pages:</b> ' +
          esc(r.error || "no answer") + ' <button class="btn sm" id="ct-retry">Retry</button></td></tr>';
        $("#ct-retry").onclick = function () { W.VIEWS.cities(el); };
        return;
      }

      cities = (r.pages || []).filter(function (p) { return p.kind === "city"; });
      draw();
      $("#ct-q").oninput = draw;
    }).catch(function (e) {
      $("#ct-rows").innerHTML = '<tr><td colspan="4"><b style="color:#d92d20">Could not load city pages:</b> ' + esc(e.message) + '</td></tr>';
    });

    $("#ct-rows").onclick = function (e) {
      var tr = e.target.closest("tr[data-i]");
      if (tr) location.href = EDIT + tr.dataset.i;
    };

    function templateOptions() {
      var live = cities.filter(function (p) { return p.status === "published"; });
      return live.map(function (p, i) {
        return '<option value="' + p.id + '"' + (i === 1 || (live.length === 1 && i === 0) ? " selected" : "") + '>' +
          esc(cityName(p)) + '</option>';
      }).join("");
    }

    $("#ct-new").onclick = function () {
      modal('<h3>New city</h3><p class="muted">A draft copied from a template city. Nothing appears on the website until you publish it.</p>' +
        '<form id="nc-f"><label>City name<input id="nc-n" required placeholder="Gujrat"></label>' +
        '<label>Copy from<select id="nc-s">' + templateOptions() + '</select></label>' +
        '<p class="muted">Address: <span id="nc-u">/…/</span></p>' +
        '<p class="banner" style="margin:0 0 12px">The copy keeps the template’s words with the city name swapped. Edit the sections before publishing.</p>' +
        '<p class="err" id="nc-err"></p><div class="modal-actions">' +
        '<button type="button" class="btn" id="nc-x">Cancel</button>' +
        '<button class="btn pri" id="nc-go">Create draft</button></div></form>');

      $("#nc-x").onclick = closeModal;
      $("#nc-n").oninput = function () { $("#nc-u").textContent = "/" + (slugify(this.value) || "…") + "/"; };
      $("#nc-f").onsubmit = function (e) {
        e.preventDefault();
        var b = $("#nc-go");
        b.disabled = true;
        b.textContent = "Creating…";

        api("cms_city_new", { name: $("#nc-n").value, from: +$("#nc-s").value })
          .then(function (r) {
            if (!r.ok) throw new Error(r.error || "Could not create the city page");
            closeModal();
            toast("Draft created: " + r.item.url);
            location.href = EDIT + r.item.id;
          })
          .catch(function (x) {
            b.disabled = false;
            b.textContent = "Create draft";
            $("#nc-err").textContent = x.message;
          });
      };
    };

    $("#ct-bulk").onclick = function () {
      modal('<h3>Bulk create cities</h3><p class="muted">One city per line, up to 25. Each becomes a <b>draft</b> copied from the template.</p>' +
        '<label>Cities<textarea id="bk-l" rows="6" placeholder="Gujrat\nSargodha\nSahiwal"></textarea></label>' +
        '<label>Copy from<select id="bk-s">' + templateOptions() + '</select></label>' +
        '<p class="banner" style="margin:0 0 12px">The text is the template’s, with the city name swapped — no AI rewrite (that key is not connected here). Edit each draft before publishing.</p>' +
        '<div id="bk-p" class="bk-prog"></div><div class="modal-actions">' +
        '<button type="button" class="btn" id="bk-x">Close</button>' +
        '<button class="btn pri" id="bk-go">Create drafts</button></div>');

      $("#bk-x").onclick = function () { closeModal(); W.VIEWS.cities(el); };
      $("#bk-go").onclick = function () {
        var names = $("#bk-l").value.split(/\n|,/).map(function (s) { return s.trim(); }).filter(Boolean).slice(0, 25);
        if (!names.length) return;

        var b = this, P = $("#bk-p");
        b.disabled = true;
        P.innerHTML = names.map(function (n, i) {
          return '<div data-i="' + i + '"><span class="dot"></span>' + esc(n) + ' <small class="muted">waiting</small></div>';
        }).join("");

        api("cms_city_bulk", { names: names, from: +$("#bk-s").value })
          .then(function (r) {
            if (!r.ok) throw new Error(r.error || "Bulk create failed");
            var failed = r.failed || [];
            names.forEach(function (n, i) {
              var row = $('[data-i="' + i + '"]', P);
              if (!row) return;
              var bad = failed.find(function (f) { return f.name === n; });
              row.className = bad ? "bad" : "ok";
              $("small", row).textContent = bad ? bad.error : "draft ready";
            });
            b.disabled = false;
            b.textContent = "Create drafts";
            toast(r.created.length + " draft(s) created" + (failed.length ? ", " + failed.length + " skipped" : ""));
          })
          .catch(function (x) {
            b.disabled = false;
            toast(x.message, true);
          });
      };
    };
  };
})();

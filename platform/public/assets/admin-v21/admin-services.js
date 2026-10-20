/* Woodex OS — the Service pages screen, ported from the v2.1 admin (docs/audit/16.8).
 *
 * v2.1 listed the built HTML files and edited a page by rewriting the file in place. The OS renders every
 * page from blocks, so this screen lists the OS page table instead and sends “Edit content” to the section
 * editor. The two things a person actually does here keep their v2.1 meaning, against the same pages:
 * duplicate a row, or start a new page from an existing layout.
 *
 * It replaces VIEWS.services from admin-fields.js, which is loaded before this file. Nothing else from that
 * module is touched — the field editor it also defines is used by no screen here.
 */
(function () {
  "use strict";

  var W = window.WXA, api = W.api, esc = W.esc, ic = W.ic, $ = W.$, toast = W.toast,
      modal = W.modal, closeModal = W.closeModal, head = W.head;

  var EDIT = "/admin/website/pages/";      // the OS section editor, full document
  var TEMPLATES = "/admin/website/templates";

  function slugify(s) {
    return String(s || "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "").slice(0, 60);
  }

  /* The pages this screen owns: everything but the city drafts (their own screen) and the two error pages. */
  function manages(p) {
    return p.kind !== "city" && !/^(404|500|503)\.html$/.test(p.path);
  }

  function address(p) {
    return p.url || "/";
  }

  W.VIEWS.services = function (el) {
    el.innerHTML = head("Service & site pages", "Service pages",
        '<a class="btn" href="' + TEMPLATES + '">' + ic("blocks") + 'From a template</a>' +
        '<button class="btn pri" id="sv-new">' + ic("plus") + 'New service page</button>') +
      '<div class="p19-how"><b>Add a service page</b><ol>' +
        '<li><b>New service page</b>: copy the sections of an existing service page (or start blank), then give it a title and address.</li>' +
        '<li><b>Duplicate</b> on any row: the same page as a fresh draft.</li>' +
        '<li>Both open in the <b>section editor</b> as a draft. Publish from Pages when it is ready.</li>' +
      '</ol></div>' +
      '<div class="card"><div class="card-h"><h3>Pages</h3>' +
        '<input id="sv-q" class="sm-in" placeholder="Search…"></div>' +
        '<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Page</th><th>Address</th><th>Type</th><th></th></tr></thead>' +
        '<tbody id="sv-rows"><tr><td colspan="4" class="muted">Loading…</td></tr></tbody></table></div></div>';

    var pages = [];

    function draw() {
      var q = ($("#sv-q").value || "").toLowerCase();
      var shown = pages.filter(manages).filter(function (p) {
        return !q || (p.title + p.url).toLowerCase().indexOf(q) > -1;
      });

      $("#sv-rows").innerHTML = shown.length ? shown.map(function (p) {
        return '<tr class="click" data-i="' + p.id + '">' +
          '<td><b>' + esc(p.title.replace(/\s*\|\s*Woodex.*$/, "")) + '</b></td>' +
          '<td class="muted">' + esc(address(p)) + '</td>' +
          '<td><span class="badge">' + esc(p.kind) + '</span></td>' +
          '<td class="r"><span class="btn sm ghost" data-dup>' + ic("copy") + 'Duplicate</span> ' +
          '<span class="btn sm">' + ic("square-pen") + 'Edit content</span></td></tr>';
      }).join("") : '<tr><td colspan="4" class="muted">No page matches.</td></tr>';

      W.fillIcons($("#sv-rows"));
    }

    api("pages").then(function (r) {
      if (!r.ok) {
        $("#sv-rows").innerHTML = '<tr><td colspan="4"><b style="color:#d92d20">Could not load pages:</b> ' +
          esc(r.error || "no answer") + ' <button class="btn sm" id="sv-retry">Retry</button></td></tr>';
        $("#sv-retry").onclick = function () { W.VIEWS.services(el); };
        return;
      }

      pages = r.pages || [];
      draw();
      $("#sv-q").oninput = draw;
    }).catch(function (e) {
      $("#sv-rows").innerHTML = '<tr><td colspan="4"><b style="color:#d92d20">Could not load pages:</b> ' + esc(e.message) + '</td></tr>';
    });

    $("#sv-rows").onclick = function (e) {
      var tr = e.target.closest("tr[data-i]");
      if (!tr) return;
      var page = pages.find(function (p) { return String(p.id) === tr.dataset.i; });

      if (e.target.closest("[data-dup]")) {
        var b = e.target.closest("[data-dup]");
        b.style.pointerEvents = "none";
        api("cms_page_duplicate", { id: page.id }).then(function (r) {
          b.style.pointerEvents = "";
          if (!r.ok) return toast(r.error, true);
          toast("Draft created: " + r.item.url);
          W.VIEWS.services(el);
        }).catch(function (x) { b.style.pointerEvents = ""; toast(x.message, true); });
        return;
      }

      location.href = EDIT + page.id;
    };

    $("#sv-new").onclick = function () {
      var options = pages.filter(function (p) { return p.kind === "service"; }).map(function (p) {
        return '<option value="' + p.id + '">' + esc(p.title) + '</option>';
      }).join("");

      modal('<h3>New service page</h3><p class="muted">The new page is a <b>draft</b>. Nothing appears on the website until you publish it.</p>' +
        '<form id="ns-f"><label>Title<input id="ns-t" required placeholder="Modular kitchen in Lahore"></label>' +
        '<label>Address<span class="muted" id="ns-u">Address: /…/</span><input id="ns-s" required placeholder="modular-kitchen-lahore"></label>' +
        '<label>Copy sections from<select id="ns-from"><option value="">Start blank</option>' + options + '</select></label>' +
        (options ? '' : '<p class="banner" style="margin:0 0 12px">No service page exists yet, so this one starts blank.</p>') +
        '<p class="err" id="ns-err"></p><div class="modal-actions">' +
        '<button type="button" class="btn" id="ns-x">Cancel</button>' +
        '<button class="btn pri" id="ns-go">Create draft</button></div></form>');

      $("#ns-x").onclick = closeModal;
      $("#ns-t").oninput = function () {
        if (!$("#ns-s").dataset.touched) $("#ns-s").value = slugify(this.value);
        $("#ns-u").textContent = "Address: /" + (slugify($("#ns-s").value) || "…") + "/";
      };
      $("#ns-s").oninput = function () {
        this.dataset.touched = "1";
        $("#ns-u").textContent = "Address: /" + (slugify(this.value) || "…") + "/";
      };
      $("#ns-f").onsubmit = function (e) {
        e.preventDefault();
        var b = $("#ns-go");
        b.disabled = true;
        b.textContent = "Creating…";

        api("cms_page_new", {
          title: $("#ns-t").value,
          slug: "/" + slugify($("#ns-s").value) + "/",
          from: $("#ns-from").value ? +$("#ns-from").value : 0
        }).then(function (r) {
          if (!r.ok) throw new Error(r.error || "Could not create the page");
          closeModal();
          toast("Draft created: " + r.item.url);
          location.href = EDIT + r.item.id;
        }).catch(function (x) {
          b.disabled = false;
          b.textContent = "Create draft";
          $("#ns-err").textContent = x.message;
        });
      };
    };
  };
})();

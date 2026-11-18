/* Private Studio resources. Deliberately independent of legacy template/FAQ adapters and renderers. */
(function () {
  "use strict";
  var W = window.WXA, C = window.WX_STUDIO;
  if (!W || !C) return;
  var active = null;
  window.addEventListener("beforeunload", function (event) {
    if (active && active.el.isConnected && (active.dirty || active.busy)) {
      event.preventDefault(); event.returnValue = "";
    }
  });
  C.beforeStudioNavigate = function () {
    if (!active || !active.el.isConnected) return true;
    if (active.busy) return false;
    if (active.dirty && !window.confirm("Leave without saving your local resource changes?")) return false;
    active.dirty = false;
    return true;
  };
  W.VIEWS.resources = function (el) {
    var s = {el: el, kind: "template", scope: "post", item: null, dirty: false, busy: false, uncertain: false, page: 1, listSeq: 0, baseline: ""};
    active = s;
    var $ = function (q) { return el.querySelector(q); };
    var esc = W.esc;
    el.innerHTML = '<div class="ph"><div><h1>Templates &amp; FAQ groups</h1><p class="muted">Private drafts and explicitly published resource snapshots</p></div></div>' +
      '<p class="banner">Save keeps changes private. Publish promotes only the saved resource, not any page. Choose published resources in Blog/Portfolio, then Preview and explicitly Publish each page. No automatic defaults or updates to existing pages.</p>' +
      '<div class="toolbar" aria-label="Resource context"><label for="sr-context">Manage</label><select id="sr-context"><option value="post">Blog templates</option><option value="study">Portfolio templates</option><option value="shared">Shared FAQ groups</option></select><button type="button" class="btn pri" id="sr-new">New resource</button></div>' +
      '<p id="sr-message" role="status" aria-live="polite"></p><div class="sr-layout"><section class="card" aria-label="Saved resources"><div class="card-b"><h2>Saved resources</h2><div id="sr-list" aria-live="polite"></div><div class="toolbar"><button class="btn sm" id="sr-prev" type="button">Previous</button><span id="sr-page"></span><button class="btn sm" id="sr-next" type="button">Next</button><button class="btn sm" id="sr-refresh" type="button">Refresh list</button></div></div></section><section class="card" aria-label="Resource editor"><div class="card-b" id="sr-editor"><p>Select a resource or create a new one.</p></div></section></div>';
    function message(text, bad) { $("#sr-message").textContent = text; $("#sr-message").className = bad ? "err" : "muted"; }
    function alive() { return el.isConnected && active === s; }
    function permit() { return !s.busy && (!s.dirty || window.confirm("Replace your unsaved local changes?")); }
    function defaults() {
      var data = {name: "", description: ""};
      if (s.kind === "faq_group") data.items = [];
      else Object.assign(data, {hero: "image", toc: false, meta: true, facts: true, sections: ["body", "summary", "faqs", "quote", "related"].map(function (k) { return {k: k, on: true}; })});
      return data;
    }
    function read() {
      var data = {name: $("#sr-name").value, description: $("#sr-description").value};
      if (s.kind === "faq_group") data.items = Array.from(el.querySelectorAll(".sr-faq")).map(function (row) { return {q: row.querySelector("input").value, a: row.querySelector("textarea").value}; });
      else {
        data.hero = $("#sr-hero").value;
        ["toc", "meta", "facts"].forEach(function (key) { data[key] = $("#sr-" + key).checked; });
        data.sections = Array.from(el.querySelectorAll(".sr-section")).map(function (row) { return {k: row.dataset.key, on: row.querySelector("input").checked}; });
      }
      return data;
    }
    function state() {
      if (!$("#sr-form")) return;
      s.dirty = JSON.stringify(read()) !== s.baseline;
      $("#sr-local").textContent = s.dirty ? "Unsaved local changes — Save before publishing." : "No unsaved local changes.";
      $("#sr-save").disabled = s.busy || s.uncertain;
      var pub = $("#sr-publish");
      if (pub) pub.disabled = s.busy || s.uncertain || s.dirty || !s.item || !s.item.has_draft;
      $("#sr-discard").disabled = s.busy || s.uncertain || !s.item || !s.item.has_draft;
      $("#sr-reopen").disabled = s.busy || !s.item;
    }
    function busy(value) {
      s.busy = value;
      el.setAttribute("aria-busy", String(value));
      el.querySelectorAll("button, select").forEach(function (b) { b.disabled = value; });
      var fields = $("#sr-fields"); if (fields) fields.disabled = value;
      state();
      if (!value) {
        pagination();
        var rows = el.querySelectorAll(".sr-row");
        rows.forEach(function (row, i) {
          row.querySelector('[data-move="-1"]').disabled = i === 0;
          row.querySelector('[data-move="1"]').disabled = i === rows.length - 1;
        });
        if ($("#sr-add")) $("#sr-add").disabled = rows.length >= 100;
      }
    }
    var total = 0;
    function pagination() {
      $("#sr-prev").disabled = s.busy || s.page <= 1;
      $("#sr-next").disabled = s.busy || s.page * 25 >= total;
    }
    async function request(action, data) {
      var r = await W.api("cms_resource_" + action, Object.assign({kind: s.kind, scope: s.scope}, data || {}));
      if (!r || !r.ok) {
        var error = new Error((r && (r.error || r.message)) || "Request failed. Reopen to check server state before retrying.");
        error.uncertain = /network error|server error|could not complete|request failed|expired|CSRF/i.test(error.message);
        throw error;
      }
      return r;
    }
    async function list() {
      var seq = ++s.listSeq;
      $("#sr-list").textContent = "Loading resources…";
      try {
        var r = await request("list", {page: s.page});
        if (!alive() || seq !== s.listSeq) return;
        total = r.total;
        $("#sr-list").innerHTML = r.items.length ? r.items.map(function (it) {
          return '<p><button type="button" class="btn sr-open" data-id="' + it.id + '">' + esc(it.name || "Discarded resource #" + it.id) + '</button><small class="muted sr-state">' + (it.is_published ? "Published snapshot" : "Not published") + (it.has_draft ? " · Private draft" : "") + '</small></p>';
        }).join("") : '<p class="empty">No saved resources in this context.</p>';
        $("#sr-page").textContent = "Page " + r.page + " · " + r.total + " resources";
        pagination();
        $("#sr-list").querySelectorAll("button").forEach(function (b) { b.disabled = s.busy; b.onclick = function () { open(Number(b.dataset.id)); }; });
      } catch (error) { if (alive() && seq === s.listSeq) { $("#sr-list").textContent = "Could not load resources. Use Refresh list to retry."; message(error.message, true); } }
    }
    function moves(i, count) {
      return '<button type="button" class="btn sm" data-move="-1" aria-label="Move item ' + (i + 1) + ' up"' + (i === 0 ? ' disabled' : '') + '>Up</button><button type="button" class="btn sm" data-move="1" aria-label="Move item ' + (i + 1) + ' down"' + (i === count - 1 ? ' disabled' : '') + '>Down</button>';
    }
    function rows(data) {
      if (s.kind === "faq_group") return '<h3>Ordered questions</h3><p class="muted">Plain text only. An empty group is valid. Maximum 100 questions.</p><div id="sr-rows">' + data.items.map(function (f, i) {
        return '<div class="sr-row sr-faq"><label>Question ' + (i + 1) + '<input required maxlength="500" value="' + esc(f.q) + '"></label><label>Answer ' + (i + 1) + '<textarea required maxlength="5000" rows="3">' + esc(f.a) + '</textarea></label><div class="toolbar">' + moves(i, data.items.length) + '<button type="button" class="btn sm" data-remove>Remove question ' + (i + 1) + '</button></div></div>';
      }).join("") + '</div><button type="button" class="btn" id="sr-add"' + (data.items.length >= 100 ? ' disabled' : '') + '>Add question</button>';
      return '<label for="sr-hero">Hero style</label><select id="sr-hero">' + [["image", "Full photo"], ["navy", "Navy, no photo"], ["media", "Navy + photo below"]].map(function (x) { return '<option value="' + x[0] + '"' + (data.hero === x[0] ? ' selected' : '') + '>' + x[1] + '</option>'; }).join("") + '</select><div class="toolbar">' + [["toc", "Contents list"], ["meta", "Metadata"], ["facts", "Facts"]].map(function (x) { return '<label><input type="checkbox" id="sr-' + x[0] + '"' + (data[x[0]] ? ' checked' : '') + '> ' + x[1] + '</label>'; }).join("") + '</div><h3>Section order and visibility</h3><div id="sr-rows">' + data.sections.map(function (section, i) { return '<div class="sr-row sr-section" data-key="' + esc(section.k) + '"><label><input type="checkbox"' + (section.on ? ' checked' : '') + '> ' + esc(section.k) + '</label><div class="toolbar">' + moves(i, data.sections.length) + '</div></div>'; }).join("") + '</div>';
    }
    function draw(data, reset) {
      data = data || defaults();
      if (reset) s.baseline = JSON.stringify(data);
      var it = s.item;
      $("#sr-editor").innerHTML = '<h2>' + (it ? 'Resource #' + it.id : 'New resource') + '</h2><p id="sr-version" class="muted">' + (it ? 'Revision ' + it.revision + ' · ' + (it.published ? 'Published revision ' + it.published_revision : 'Not published') + (it.has_draft ? ' · Private draft saved' : '') : 'Not saved') + '</p><p id="sr-local" role="status"></p><form id="sr-form"><fieldset id="sr-fields"><legend class="sr-legend">Resource details</legend><label for="sr-name">Name</label><input id="sr-name" maxlength="120" required value="' + esc(data.name) + '"><label for="sr-description">Description</label><textarea id="sr-description" maxlength="1000" rows="2">' + esc(data.description) + '</textarea>' + rows(data) + '</fieldset><div class="toolbar sr-actions"><button type="submit" class="btn pri" id="sr-save">Save private draft</button>' + (C.canPublish ? '<button type="button" class="btn" id="sr-publish">Publish saved resource</button>' : '<span class="muted">Publishing requires website.publish permission.</span>') + '<button type="button" class="btn" id="sr-discard">Discard saved draft</button><button type="button" class="btn" id="sr-reopen">Reopen server version</button></div></form>';
      state(); bind(); busy(s.busy);
    }
    function bind() {
      $("#sr-form").oninput = state;
      $("#sr-form").onchange = state;
      $("#sr-form").onsubmit = function (e) { e.preventDefault(); mutate("save"); };
      if ($("#sr-publish")) $("#sr-publish").onclick = function () { mutate("publish"); };
      $("#sr-discard").onclick = function () { mutate("discard"); };
      $("#sr-reopen").onclick = function () { open(s.item.id); };
      if ($("#sr-add")) $("#sr-add").onclick = function () {
        var data = read(); if (data.items.length >= 100) return;
        data.items.push({q: "", a: ""}); draw(data, false);
        var inputs = el.querySelectorAll(".sr-faq input"); inputs[inputs.length - 1].focus();
      };
      $("#sr-rows").onclick = function (event) {
        var button = event.target.closest("button"); if (!button || s.busy) return;
        var row = button.closest(".sr-row"), all = Array.from($("#sr-rows").children), i = all.indexOf(row);
        var data = read(), items = s.kind === "template" ? data.sections : data.items;
        if (button.hasAttribute("data-remove")) {
          if (!window.confirm("Remove this question from the local draft? Save is still required.")) return;
          items.splice(i, 1);
        } else {
          var j = i + Number(button.dataset.move); if (j < 0 || j >= items.length) return;
          var temp = items[i]; items[i] = items[j]; items[j] = temp;
        }
        draw(data, false);
      };
    }
    async function open(id) {
      if (!permit()) return;
      busy(true); message("Opening server version…");
      try {
        var r = await request("get", {id: id}); if (!alive()) return;
        s.uncertain = false; s.item = r.item; draw(r.item.data, true); message("Opened the latest server revision.");
      } catch (error) { if (alive()) message(error.message, true); }
      finally { if (alive()) busy(false); }
    }
    async function mutate(action) {
      if (s.busy || s.uncertain) return;
      state();
      if (action === "publish" && (s.dirty || !s.item || !s.item.has_draft || !C.canPublish)) return;
      if (action === "discard" && (!s.item || !s.item.has_draft)) return;
      if (action !== "save" && !window.confirm(action === "publish" ? "Publish this saved resource snapshot? No pages will be republished." : "Discard the saved private draft and any local changes? The published snapshot stays unchanged.")) return;
      var body = s.item ? {id: s.item.id, revision: s.item.revision, token: s.item.token} : {};
      if (action === "save") body.data = read();
      if (action === "publish") body.draft_hash = s.item.draft_hash;
      busy(true); message("Working…");
      try {
        var r = await request(action, body); if (!alive()) return;
        s.item = r.item; draw(r.item.data, true);
        message(action === "save" ? "Private draft saved. Nothing was published." : action === "publish" ? "Saved resource published. No pages were changed." : "Private draft discarded. Published snapshot unchanged.");
        await list();
      } catch (error) {
        if (alive()) {
          s.uncertain = !!error.uncertain;
          message(error.message + " Local fields were kept. " + (s.uncertain ? "Outcome uncertain: writes are blocked. Reopen the server version, or check the refreshed list before explicitly starting a new resource." : "Reopen server version before retrying a conflict."), true);
          if (s.uncertain) await list();
        }
      }
      finally { if (alive()) busy(false); }
    }
    $("#sr-context").onchange = function () {
      if (!permit()) { this.value = s.scope; return; }
      s.scope = this.value; s.kind = s.scope === "shared" ? "faq_group" : "template";
      s.page = 1; s.item = null; s.dirty = false; s.uncertain = false;
      $("#sr-editor").innerHTML = '<p>Select a resource or create a new one.</p>'; message(""); list();
    };
    $("#sr-new").onclick = function () { if (!permit()) return; if (s.uncertain && !window.confirm("The previous write may have succeeded. Check the resource list first. Start another new resource anyway?")) return; s.uncertain = false; s.item = null; draw(defaults(), true); message("New local resource. Save to create a private draft."); $("#sr-name").focus(); };
    $("#sr-refresh").onclick = list;
    $("#sr-prev").onclick = function () { if (s.page > 1 && !s.busy) { s.page--; list(); } };
    $("#sr-next").onclick = function () { if (s.page * 25 < total && !s.busy) { s.page++; list(); } };
    list();
  };
})();

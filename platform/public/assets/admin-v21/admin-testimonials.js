/* Woodex OS — the Testimonials screen, ported from the v2.1 admin (docs/audit/16.17).
 *
 * v2.1 kept testimonials in its content store, placed a reviews section on chosen pages by rewriting their
 * HTML, and had a "Live" switch per card. Woodex OS keeps one shared review library (content_entries, kind =
 * testimonial) and a page's Reviews section picks from it — so the switch that mattered ("is this on the
 * site?") is the page's own section, and this screen shows where each review is actually quoted.
 *
 * The site's Reviews section renders four things: the words, the client's name, when, and the source. It shows
 * five stars for every review. So this screen edits exactly those four fields and does not offer a per-review
 * rating or photo — fields that would never appear on a page (docs/audit/16.17).
 */
(function () {
  "use strict";

  var W = window.WXA, api = W.api, esc = W.esc, ic = W.ic, $ = W.$, head = W.head, toast = W.toast,
      modal = W.modal, closeModal = W.closeModal;

  var PAGES = "/admin/website/pages";

  W.VIEWS.testimonials = function (el) {
    el.innerHTML = head("Testimonials", "Content",
        '<button class="btn pri" id="tm-new">' + ic("plus") + 'Review</button>') +
      '<div class="banner">' + ic("info") + '<span>Reviews are <b>quoted verbatim</b> — never rewritten, never invented. ' +
        'A page shows the ones its Reviews section picks, so “Used on” is the switch: add the review to a page’s ' +
        'section in <a href="' + PAGES + '">Pages</a>, or remove it there to stop showing it. The site’s section ' +
        'shows five stars and no photo, so those are not fields here.</span></div>' +
      '<div class="card"><div class="card-h"><h3>Reviews</h3><small class="muted" id="tm-count"></small></div>' +
        '<div class="tbl-wrap"><table class="tbl"><thead><tr>' +
        '<th style="width:44%">What the client said</th><th>Client</th><th style="width:18%">Used on</th><th></th>' +
        '</tr></thead><tbody id="tm-rows"><tr><td colspan="4" class="muted">Reading the reviews…</td></tr></tbody></table></div></div>';

    var items = [];

    function draw() {
      $("#tm-count").textContent = items.length + (items.length === 1 ? " review" : " reviews");

      $("#tm-rows").innerHTML = items.length ? items.map(function (t, i) {
        var used = t.used_on || [];
        var where = used.length
          ? used.slice(0, 3).map(function (p) {
            return '<a href="' + esc(PAGES + "/" + p.id) + '">' + esc(p.title) + "</a>";
          }).join(", ") + (used.length > 3 ? ' <span class="muted">+' + (used.length - 3) + "</span>" : "")
          : '<span class="muted">not on a page yet</span>';

        var meta = [t.when, t.source].filter(Boolean).join(" · ");

        return '<tr data-id="' + t.id + '">' +
          '<td>“' + esc(t.text.length > 180 ? t.text.slice(0, 180) + "…" : t.text) + '”</td>' +
          '<td><b>' + esc(t.name) + '</b>' + (meta ? '<div class="muted" style="font-size:12px">' + esc(meta) + "</div>" : "") + '</td>' +
          '<td>' + where + '</td>' +
          '<td class="r"><span class="fq-btns">' +
            '<button class="btn sm" data-up="' + i + '" title="Move up"' + (i === 0 ? " disabled" : "") + '>' + ic("arrow-up") + '</button>' +
            '<button class="btn sm" data-down="' + i + '" title="Move down"' + (i === items.length - 1 ? " disabled" : "") + '>' + ic("arrow-down") + '</button>' +
            '<button class="btn sm" data-ed="' + t.id + '">Edit</button>' +
            '<button class="btn sm ghost" data-del="' + t.id + '" title="Delete">' + ic("trash") + '</button>' +
          '</span></td></tr>';
      }).join("") : '<tr><td colspan="4" class="muted">No reviews yet. Add the first one.</td></tr>';

      W.fillIcons($("#tm-rows"));
    }

    function load() {
      api("cms_testimonials").then(function (r) {
        if (!r.ok) {
          $("#tm-rows").innerHTML = '<tr><td colspan="4"><b style="color:#d92d20">Could not read the reviews:</b> ' + esc(r.error || "no answer") + '</td></tr>';
          return;
        }

        items = r.items || [];
        draw();
      });
    }

    function edit(item) {
      var isNew = !item;
      modal("<h3>" + (isNew ? "Add a review" : "Edit review") + '</h3>' +
        '<label>What the client said<textarea id="tm-t" rows="4" placeholder="Paste the review exactly as the client wrote or said it.">' + esc(item ? item.text : "") + '</textarea></label>' +
        '<div class="g2">' +
          '<label>Client name<input id="tm-n" value="' + esc(item ? item.name : "") + '" placeholder="Ayesha Khan" maxlength="120"></label>' +
          '<label>Source <small>optional</small><input id="tm-s" value="' + esc(item ? item.source : "") + '" placeholder="Google" maxlength="60"></label>' +
        '</div>' +
        '<label>When <small>optional</small><input id="tm-w" value="' + esc(item ? item.when : "") + '" placeholder="March 2026" maxlength="40"></label>' +
        '<p class="muted" style="margin:0 0 10px">Type the words as they were given. The screen does not tidy a review — a quote that reads better than the client said it is no longer a quote.</p>' +
        '<p class="err" id="tm-err"></p><div class="modal-actions">' +
        (isNew ? "" : '<button class="btn ghost danger" id="tm-del" style="margin-right:auto">Delete</button>') +
        '<button type="button" class="btn" id="tm-x">Cancel</button>' +
        '<button class="btn pri" id="tm-go">' + (isNew ? "Add" : "Save") + '</button></div>');
      $("#modal-card").classList.add("wide");

      $("#tm-x").onclick = function () { $("#modal-card").classList.remove("wide"); closeModal(); };

      if (!isNew) {
        $("#tm-del").onclick = function () {
          if (!confirm("Delete this review?")) return;
          api("cms_testimonial_delete", { id: item.id }).then(function (r) {
            if (!r.ok) return toast(r.error || "Not deleted", true);
            $("#modal-card").classList.remove("wide");
            closeModal();
            toast(r.note || "Deleted");
            load();
          });
        };
      }

      $("#tm-go").onclick = function () {
        var b = this;
        b.disabled = true;

        api("cms_testimonial_save", {
          id: isNew ? 0 : item.id,
          text: $("#tm-t").value,
          name: $("#tm-n").value,
          when: $("#tm-w").value,
          source: $("#tm-s").value
        })
          .then(function (r) {
            if (!r.ok) throw new Error(r.error || "Not saved");
            $("#modal-card").classList.remove("wide");
            closeModal();
            toast(isNew ? "Review added" : "Saved");
            load();
          })
          .catch(function (x) {
            b.disabled = false;
            $("#tm-err").textContent = x.message;
          });
      };

      setTimeout(function () { $("#tm-t").focus(); }, 30);
    }

    function move(from, to) {
      if (to < 0 || to >= items.length) return;
      var moved = items.splice(from, 1)[0];
      items.splice(to, 0, moved);
      draw();
      api("cms_testimonial_order", { ids: items.map(function (t) { return t.id; }) }).then(function (r) {
        if (!r.ok) return toast(r.error || "The new order was not saved", true);
        toast(r.note || "Order saved");
      });
    }

    el.addEventListener("click", function (e) {
      var b = e.target.closest("button");
      if (!b) return;

      if (b.id === "tm-new") return edit(null);
      if (b.dataset.ed) return edit(items.filter(function (t) { return t.id === +b.dataset.ed; })[0]);
      if (b.dataset.up !== undefined) return move(+b.dataset.up, +b.dataset.up - 1);
      if (b.dataset.down !== undefined) return move(+b.dataset.down, +b.dataset.down + 1);
      if (b.dataset.del) {
        var item = items.filter(function (t) { return t.id === +b.dataset.del; })[0];
        if (item) edit(item);
      }
    });

    load();
  };
})();

/* Woodex OS — the FAQs screen, ported from the v2.1 admin (docs/audit/16.16).
 *
 * v2.1 called these "FAQ groups": a named set of questions you wrote once and reused on pages, in the chat
 * bot's answers, and in Google's FAQ results. Woodex OS keeps one shared FAQ library: each answer is a row
 * that any page's FAQ section picks, so "write once, reuse" holds without a second store. The screen says
 * where each answer is used, because that is the question an owner actually asks.
 *
 * The chat bot does not read this list — it answers from the studio's knowledge (Settings → Bot), which
 * mirrors page text and hand-written answers. That is stated on the banner instead of implied.
 */
(function () {
  "use strict";

  var W = window.WXA, api = W.api, esc = W.esc, ic = W.ic, $ = W.$, head = W.head, toast = W.toast,
      modal = W.modal, closeModal = W.closeModal;

  var BUILDER = "/admin/website/pages";
  var SETTINGS = "/admin/settings";

  W.VIEWS.faqs = function (el) {
    el.innerHTML = head("FAQs", "Content",
        '<button class="btn pri" id="fq-new">' + ic("plus") + 'Question</button>') +
      '<div class="banner">' + ic("info") + '<span>Answers written here are <b>reusable</b>: a page’s FAQ section ' +
        'picks them from this list, and every page that shows them gets Google’s FAQ markup. Questions that only ' +
        'one page needs can be typed inside its FAQ section in <a href="' + BUILDER + '">Pages</a>. The chat bot ' +
        'answers from the studio’s knowledge in <a href="' + SETTINGS + '">Settings</a>, not from this list.</span></div>' +
      '<div class="card"><div class="card-h"><h3>Shared answers</h3>' +
        '<small class="muted" id="fq-count"></small></div>' +
        '<div class="tbl-wrap"><table class="tbl"><thead><tr>' +
        '<th style="width:38%">Question</th><th>Answer</th><th style="width:18%">Used on</th><th></th>' +
        '</tr></thead><tbody id="fq-rows"><tr><td colspan="4" class="muted">Reading the answers…</td></tr></tbody></table></div></div>';

    var items = [];

    function draw() {
      $("#fq-count").textContent = items.length + (items.length === 1 ? " answer" : " answers");

      $("#fq-rows").innerHTML = items.length ? items.map(function (f, i) {
        var used = f.used_on || [];
        var where = used.length
          ? used.slice(0, 3).map(function (p) {
            return '<a href="' + esc(BUILDER + "/" + p.id) + '">' + esc(p.title) + "</a>";
          }).join(", ") + (used.length > 3 ? ' <span class="muted">+' + (used.length - 3) + "</span>" : "")
          : '<span class="muted">not on a page yet</span>';

        return '<tr data-id="' + f.id + '">' +
          '<td><b>' + esc(f.question) + '</b></td>' +
          '<td class="muted">' + esc(f.answer.length > 160 ? f.answer.slice(0, 160) + "…" : f.answer) + '</td>' +
          '<td>' + where + '</td>' +
          '<td class="r"><span class="fq-btns">' +
            '<button class="btn sm" data-up="' + i + '" title="Move up"' + (i === 0 ? " disabled" : "") + '>' + ic("arrow-up") + '</button>' +
            '<button class="btn sm" data-down="' + i + '" title="Move down"' + (i === items.length - 1 ? " disabled" : "") + '>' + ic("arrow-down") + '</button>' +
            '<button class="btn sm" data-ed="' + f.id + '">Edit</button>' +
            '<button class="btn sm ghost" data-del="' + f.id + '" title="Delete">' + ic("trash") + '</button>' +
          '</span></td></tr>';
      }).join("") : '<tr><td colspan="4" class="muted">No answers yet. Add the first question.</td></tr>';

      W.fillIcons($("#fq-rows"));
    }

    function load() {
      api("cms_faqs").then(function (r) {
        if (!r.ok) {
          $("#fq-rows").innerHTML = '<tr><td colspan="4"><b style="color:#d92d20">Could not read the answers:</b> ' + esc(r.error || "no answer") + '</td></tr>';
          return;
        }

        items = r.items || [];
        draw();
      });
    }

    function edit(item) {
      var isNew = !item;
      modal("<h3>" + (isNew ? "New question" : "Edit question") + '</h3>' +
        '<label>Question<input id="fq-q" value="' + esc(item ? item.question : "") + '" placeholder="How long does a full home interior take?" maxlength="300"></label>' +
        '<label>Answer<textarea id="fq-a" rows="6" placeholder="Two to four sentences, in the studio’s voice.">' + esc(item ? item.answer : "") + '</textarea></label>' +
        '<p class="muted" style="margin:0 0 10px">Keep the answer to what the studio can stand behind — it appears on every page that picks it, in Google’s FAQ results, and in answers to visitors.</p>' +
        '<p class="err" id="fq-err"></p><div class="modal-actions">' +
        (isNew ? "" : '<button class="btn ghost danger" id="fq-del" style="margin-right:auto">Delete</button>') +
        '<button type="button" class="btn" id="fq-x">Cancel</button>' +
        '<button class="btn pri" id="fq-go">' + (isNew ? "Add" : "Save") + '</button></div>');
      $("#modal-card").classList.add("wide");

      $("#fq-x").onclick = function () { $("#modal-card").classList.remove("wide"); closeModal(); };

      if (!isNew) {
        $("#fq-del").onclick = function () {
          if (!confirm("Delete this answer?")) return;
          api("cms_faq_delete", { id: item.id }).then(function (r) {
            if (!r.ok) return toast(r.error || "Not deleted", true);
            $("#modal-card").classList.remove("wide");
            closeModal();
            toast(r.note || "Deleted");
            load();
          });
        };
      }

      $("#fq-go").onclick = function () {
        var b = this;
        b.disabled = true;

        api("cms_faq_save", { id: isNew ? 0 : item.id, question: $("#fq-q").value, answer: $("#fq-a").value })
          .then(function (r) {
            if (!r.ok) throw new Error(r.error || "Not saved");
            $("#modal-card").classList.remove("wide");
            closeModal();
            toast(isNew ? "Question added" : "Saved");
            load();
          })
          .catch(function (x) {
            b.disabled = false;
            $("#fq-err").textContent = x.message;
          });
      };

      setTimeout(function () { $("#fq-q").focus(); }, 30);
    }

    function move(from, to) {
      if (to < 0 || to >= items.length) return;
      var moved = items.splice(from, 1)[0];
      items.splice(to, 0, moved);
      draw();
      api("cms_faq_order", { ids: items.map(function (f) { return f.id; }) }).then(function (r) {
        if (!r.ok) return toast(r.error || "The new order was not saved", true);
        toast(r.note || "Order saved");
      });
    }

    el.addEventListener("click", function (e) {
      var b = e.target.closest("button");
      if (!b) return;

      if (b.id === "fq-new") return edit(null);
      if (b.dataset.ed) return edit(items.filter(function (f) { return f.id === +b.dataset.ed; })[0]);
      if (b.dataset.up !== undefined) return move(+b.dataset.up, +b.dataset.up - 1);
      if (b.dataset.down !== undefined) return move(+b.dataset.down, +b.dataset.down + 1);
      if (b.dataset.del) {
        var item = items.filter(function (f) { return f.id === +b.dataset.del; })[0];
        if (item) edit(item);
      }
    });

    load();
  };
})();

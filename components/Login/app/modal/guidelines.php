<!-- Guidelines -->
<section id="guidelines" class="container container-max my-5">
  <div class="d-flex align-items-center justify-content-between gap-3 mb-3 sticky-top bg-body pt-3" style="top:72px; z-index: 5;">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-info-circle-fill text-primary fs-4"></i>
      <h4 class="fw-bold mb-0">Resident Service Guidelines</h4>
      <?php if ($guide_result && $guide_result->num_rows > 0): ?>
        <span id="guideCount" class="badge text-bg-light border ms-1"><?=
          number_format($guide_result->num_rows)
        ?> total</span>
      <?php endif; ?>
    </div>

    <!-- Search -->
    <div class="ms-auto" style="min-width: 260px;">
      <div class="input-group input-group-sm shadow-sm">
        <span class="input-group-text bg-body"><i class="bi bi-search"></i></span>
        <input id="guideSearch" type="search" class="form-control" placeholder="Search guidelines…">
        <button id="clearSearch" class="btn btn-outline-secondary" type="button" aria-label="Clear" title="Clear"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
  </div>

  <?php if ($guide_result && $guide_result->num_rows > 0): ?>
    <div id="guideList" class="row g-3">
      <?php while ($guide = $guide_result->fetch_assoc()): ?>
        <?php
          $text = trim($guide['guide_description'] ?? '');
          // Create a short preview (first 180 chars, word-safe)
          $preview = mb_strimwidth($text, 0, 180, '…', 'UTF-8');
          $id = 'g'.substr(sha1($text), 0, 8);
        ?>
        <div class="col-12">
          <article class="card border-0 shadow-sm hover-lift h-100 rounded-4">
            <div class="card-body p-4">
              <div class="d-flex align-items-start gap-3">
                <div class="flex-shrink-0">
                  <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success-subtle text-success"
                        style="width:36px;height:36px">
                    <i class="bi bi-check2 fw-bold"></i>
                  </span>
                </div>
                <div class="flex-grow-1">
                  <!-- Optional: derive a title by taking text up to first period/colon -->
                  <?php
                    $title = $text;
                    if (preg_match('/^(.{10,120}?)([.:•\-–]|$)/u', $text, $m)) { $title = $m[1]; }
                  ?>
                  <h6 class="card-title fw-semibold mb-1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h6>
                  <p class="card-text text-secondary mb-0" data-full="#full-<?= $id ?>">
                    <span class="guide-preview"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if (mb_strlen($text,'UTF-8') > 180): ?>
                      <button class="btn btn-link btn-sm px-1 ms-1 align-baseline guide-toggle"
                              data-target="#full-<?= $id ?>" aria-expanded="false">Show more</button>
                    <?php endif; ?>
                  </p>
                  <div id="full-<?= $id ?>" class="collapse mt-2">
                    <div class="bg-body-tertiary rounded-3 p-3 small">
                      <?= nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                    <button class="btn btn-link btn-sm px-0 mt-1 guide-toggle" data-target="#full-<?= $id ?>">Show less</button>
                  </div>
                </div>
                <!-- Quick actions -->
                <div class="ms-auto d-none d-md-flex gap-2">
                  <button class="btn btn-outline-secondary btn-sm" onclick="copyText(`<?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>`)" title="Copy">
                    <i class="bi bi-clipboard"></i>
                  </button>
                  <!--<button class="btn btn-outline-secondary btn-sm" onclick="printGuideline('full-<?= $id ?>')" title="Print">-->
                  <!--  <i class="bi bi-printer"></i>-->
                  <!--</button>-->
                </div>
              </div>
            </div>
          </article>
        </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="text-center py-5">
      <div class="display-6 mb-2">🙋‍♀️</div>
      <h5 class="fw-semibold mb-1">No guidelines available</h5>
      <p class="text-secondary mb-3">Please check back later or contact the barangay office for assistance.</p>
      <a href="/contact" class="btn btn-primary">
        <i class="bi bi-chat-left-text me-1"></i> Contact us
      </a>
    </div>
  <?php endif; ?>
</section>

<!-- Minimal styles -->
<style>
  .hover-lift { transition: transform .15s ease, box-shadow .15s ease; }
  .hover-lift:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important; }
  .sticky-top.bg-body { box-shadow: 0 6px 12px -12px rgba(0,0,0,.25); }
  #guideList .card-title { line-height: 1.25; }
  #guideSearch::-webkit-search-cancel-button { display:none; }
  .guide-hit { background: var(--bs-warning-bg-subtle); border-radius: .3rem; padding: 0 .15rem; }
</style>

<!-- Tiny, dependency-free helpers -->
<script>
  // Live search/filter with highlight
  (function () {
    const q = document.getElementById('guideSearch');
    const clearBtn = document.getElementById('clearSearch');
    const list = document.getElementById('guideList');
    if (!q || !list) return;

    const items = [...list.querySelectorAll('.col-12')];
    const countBadge = document.getElementById('guideCount');

    function normalize(s){ return s.toLowerCase().normalize("NFKD"); }

    function highlight(textEl, query){
      // remove old marks
      textEl.innerHTML = textEl.textContent;
      if (!query) return;
      const t = textEl.textContent;
      const idx = t.toLowerCase().indexOf(query.toLowerCase());
      if (idx >= 0) {
        const before = t.slice(0, idx);
        const match = t.slice(idx, idx + query.length);
        const after = t.slice(idx + query.length);
        textEl.innerHTML = `${before}<mark class="guide-hit">${match}</mark>${after}`;
      }
    }

    function applyFilter() {
      const term = q.value.trim();
      const needle = normalize(term);
      let shown = 0;

      items.forEach(card => {
        const hay = normalize(card.textContent);
        const ok = !needle || hay.includes(needle);
        card.classList.toggle('d-none', !ok);
        if (ok) {
          shown++;
          const prev = card.querySelector('.guide-preview');
          if (prev) highlight(prev, term);
        }
      });

      if (countBadge) countBadge.textContent = `${shown} ${shown === 1 ? 'result' : 'results'}`;
    }

    q.addEventListener('input', applyFilter);
    clearBtn?.addEventListener('click', () => { q.value=''; q.focus(); applyFilter(); });

    // Show more/less toggles
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.guide-toggle');
      if (!btn) return;
      const target = document.querySelector(btn.dataset.target);
      if (!target) return;
      const isOpen = target.classList.contains('show');
      const collapse = new bootstrap.Collapse(target, { toggle: true });
      btn.setAttribute('aria-expanded', String(!isOpen));
      btn.textContent = isOpen ? 'Show more' : 'Show less';
    });

    // Init count on load
    applyFilter();
  })();

  // Copy full text
  async function copyText(txt){
    try {
      await navigator.clipboard.writeText(txt);
      toast('Copied guideline to clipboard');
    } catch { alert('Copy failed'); }
  }

  // Print a single guideline
  function printGuideline(id){
    const node = document.getElementById(id);
    const html = node ? node.innerText : '';
    const w = window.open('', '_blank', 'width=720,height=900');
    w.document.write(`<pre style="font: 14px/1.4 system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial">${html}</pre>`);
    w.document.close(); w.focus(); w.print();
  }

  // Lightweight toast (uses Bootstrap if available)
  function toast(msg){
    if (!window.bootstrap) { alert(msg); return; }
    const holder = document.getElementById('toasts') || (() => {
      const d = document.createElement('div'); d.id='toasts';
      d.className='position-fixed bottom-0 end-0 p-3'; d.style.zIndex='1080';
      document.body.appendChild(d); return d;
    })();
    const el = document.createElement('div');
    el.className='toast align-items-center text-bg-dark border-0';
    el.role='status'; el.ariaLive='polite';
    el.innerHTML=`<div class="d-flex"><div class="toast-body">${msg}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>`;
    holder.appendChild(el);
    const t = new bootstrap.Toast(el,{delay:1800}); t.show();
    el.addEventListener('hidden.bs.toast',()=>el.remove());
  }
</script>

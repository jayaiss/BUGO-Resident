<!-- FAQ -->
<section id="faq" class="container container-max my-5">
  <h4 class="fw-bold mb-3" data-reveal>
    <i class="bi bi-question-circle-fill me-2"></i> Frequently Asked Questions
  </h4>

  <div class="accordion" id="faqAccordion" data-reveal>
    <?php if ($faqresult && $faqresult->num_rows): ?>
      <?php $i = 0; while ($row = $faqresult->fetch_assoc()): $i++; ?>
        <?php
          $qid        = (int)$row['faq_id'];
          $headerId   = 'faqHeader' . $qid;
          $collapseId = 'faqCollapse' . $qid;
          $isFirst    = ($i === 1);
        ?>
        <div class="accordion-item rounded-lg overflow-hidden mb-3 border-0 shadow-soft">
          <h2 class="accordion-header" id="<?= $headerId ?>">
            <button
              class="accordion-button <?= $isFirst ? '' : 'collapsed' ?>"
              type="button"
              data-bs-toggle="collapse"
              data-bs-target="#<?= $collapseId ?>"
              aria-expanded="<?= $isFirst ? 'true' : 'false' ?>"
              aria-controls="<?= $collapseId ?>">
              <?= htmlspecialchars($row['faq_question'], ENT_QUOTES, 'UTF-8') ?>
            </button>
          </h2>

          <div
            id="<?= $collapseId ?>"
            class="accordion-collapse collapse <?= $isFirst ? 'show' : '' ?>"
            data-bs-parent="#faqAccordion"
            aria-labelledby="<?= $headerId ?>">
            <div class="accordion-body">
              <?= nl2br(htmlspecialchars($row['faq_answer'], ENT_QUOTES, 'UTF-8')) ?>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <div class="text-muted">No FAQs published yet.</div>
    <?php endif; ?>
  </div>
</section>
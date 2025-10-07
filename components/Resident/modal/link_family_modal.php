    <!-- Link Family Modal -->
    <div class="modal fade" id="linkFamilyModal" tabindex="-1" aria-labelledby="linkFamilyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST"
            action="class/save_relationship.php"
            enctype="multipart/form-data"
            class="modal-content needs-validation"
            novalidate>
        <input type="hidden" name="parent_id" value="<?= (int)$loggedInResidentId; ?>">
        <!-- Optional: CSRF -->
        <!-- <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>"> -->

        <div class="modal-header border-0 pb-0">
            <div>
            <h5 class="modal-title fw-bold" id="linkFamilyModalLabel">
                <i class="bi bi-people me-2"></i>Link a Family Member
            </h5>
            <div class="text-muted small">Choose a resident, set the relationship, and attach proof.</div>
            </div>
            <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body pt-3">
            <!-- Resident picker -->
            <div class="mb-4">
            <label class="form-label fw-medium">Select Resident</label>
            <div class="search-dropdown position-relative">
                <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input
                    type="text"
                    class="form-control"
                    id="residentSearch"
                    placeholder="Type full name exactly (e.g., Juan Dela Cruz)"
                    autocomplete="off"
                    aria-describedby="residentHelp"
                    required>
                </div>
                <div class="search-results" id="searchResults" role="listbox" aria-label="Residents"></div>
                <input type="hidden" name="child_id" id="selectedResidentId" required>
            </div>
            <div id="residentHelp" class="form-text">Security: exact full-name match required to reduce mislinks.</div>
            <div class="invalid-feedback">Please select a valid resident from the list.</div>
            </div>

            <!-- Relationship -->
            <div class="mb-4">
            <label class="form-label fw-medium">Relationship Type</label>
            <div class="btn-group w-100 gap-2 flex-wrap" role="group" aria-label="Relationship">
                <!-- Use radios styled as pills so the value still posts -->
                <?php
                $rels = ['Child'];
                foreach ($rels as $rel):
                    $id = 'rel_'.strtolower($rel);
                ?>
                <input type="radio" class="btn-check" name="relationship_type" id="<?= $id ?>" value="<?= $rel ?>" required>
                <label class="btn btn-outline-primary rounded-pill px-3" for="<?= $id ?>">
                    <i class="bi bi-link-45deg me-1"></i><?= $rel ?>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="invalid-feedback d-block mt-1" style="display:none;"></div>
            </div>

            <!-- Proof uploader -->
            <div class="mb-2">
            <label class="form-label fw-medium">Upload Birth Certificate</label>

            <div id="bcDrop" class="dropzone-tile" tabindex="0" role="button" aria-label="Upload birth certificate">
                <div class="dz-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                <div class="dz-copy">
                <div class="fw-medium">Drag & drop file here, or click to browse</div>
                <div class="text-muted small">Accepted: JPG, PNG, WEBP, PDF • Max 2MB</div>
                </div>
                <input class="d-none" type="file" name="birth_certificate" id="birthCertificate"
                    accept=".jpg,.jpeg,.png,.webp,.pdf" required>
            </div>

            <div id="bcPreview" class="mt-3 d-none">
                <div class="card shadow-sm border-0">
                <div class="card-body d-flex align-items-center gap-3 py-2">
                    <div class="preview-thumb" id="bcThumb"></div>
                    <div class="flex-grow-1">
                    <div class="fw-medium" id="bcName">filename</div>
                    <div class="text-muted small" id="bcMeta">type • size</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="bcReplace">
                    <i class="bi bi-arrow-repeat me-1"></i>Replace
                    </button>
                </div>
                </div>
            </div>

            <div class="invalid-feedback">Please attach a valid file (JPG/PNG/WEBP/PDF, ≤ 2MB).</div>
            </div>
        </div>

        <div class="modal-footer border-0 pt-0">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">
            <i class="bi bi-link-45deg me-1"></i>Link
            </button>
        </div>
        </form>
    </div>
    </div>

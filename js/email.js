const apiKey = "f1193b27dc2e4a2881e21b686404b621";
const emailDupURL = "ajax/check_email_exists.php"; // Adjust path if needed

/* ---------------- Debounce helper ---------------- */
function debounce(fn, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

/* ---------------- Check duplicate in DB ---------------- */
async function emailAlreadyUsed(email, excludeId = null) {
    const url = `${emailDupURL}?email=${encodeURIComponent(email)}` +
        (excludeId ? `&exclude=${excludeId}` : "");
    const res = await fetch(url);
    const data = await res.json();
    return data.exists;
}

/* ---------------- UI Feedback ---------------- */
function markInvalid(input, feedback, message) {
    input.classList.remove("is-valid");
    input.classList.add("is-invalid");
    feedback.classList.remove("valid-feedback");
    feedback.classList.add("invalid-feedback");
    feedback.textContent = message;
}

function markValid(input, feedback) {
    input.classList.remove("is-invalid");
    input.classList.add("is-valid");
    feedback.classList.remove("invalid-feedback");
    feedback.classList.add("valid-feedback");
    feedback.textContent = "Email is valid.";
}

/* ---------------- Core validation function ---------------- */
async function validateEmail(input, feedback, currentId = null) {
    const email = input.value.trim();
    if (!email) return;

    const isPrimary = input.id === "primary_email";
    const primaryInput = document.getElementById("primary_email");
    const primaryEmail = primaryInput?.value.trim().toLowerCase();

    // ❌ Block if child email matches primary
    if (!isPrimary && email.toLowerCase() === primaryEmail) {
        markInvalid(input, feedback, "This email is already used as the primary email.");
        return;
    }

    // ❌ Block if this email is already used in another field (e.g. another child)
    const allEmails = Array.from(document.querySelectorAll("input[type='email']"))
        .filter(el => el !== input)
        .map(el => el.value.trim().toLowerCase());

    if (allEmails.includes(email.toLowerCase())) {
        markInvalid(input, feedback, "Duplicate email within the form.");
        return;
    }

    // 📨 Validate email format/deliverability via API
    const url = `https://emailvalidation.abstractapi.com/v1/?api_key=${apiKey}&email=${encodeURIComponent(email)}`;

    try {
        const res = await fetch(url);

        if (res.status === 429) {
            markInvalid(input, feedback, "Too many requests — try again later.");
            return;
        }

        const data = await res.json();

        if (data.deliverability !== "DELIVERABLE") {
            markInvalid(input, feedback, "Invalid Email");
            return;
        }

        // 📛 Check if email already exists in the DB
        if (await emailAlreadyUsed(email, currentId)) {
            markInvalid(input, feedback, "Email already used.");
            return;
        }

        // ✅ If all checks pass, it's valid
        markValid(input, feedback);
    } catch (err) {
        console.error("Email validation error:", err);
        markInvalid(input, feedback, "Could not validate email right now.");
    }
}

/* ---------------- Debounced version to reduce API hammering ---------------- */
const debouncedValidate = debounce(validateEmail, 500);

/* ================= Primary Form Email ================= */
const primaryInput = document.getElementById("primary_email");
const primaryFeedback = document.getElementById("emailFeedback");
if (primaryInput) {
    primaryInput.addEventListener("blur", () =>
        debouncedValidate(primaryInput, primaryFeedback)
    );
}

/* ============== Family Members (Add Modal) ============= */
document.addEventListener("blur", (e) => {
    if (e.target && e.target.classList.contains("family-email")) {
        const container = e.target.closest(".col-md-4") || e.target.parentElement;
        let feedbackEl = container.querySelector(".email-feedback");
        if (!feedbackEl) {
            feedbackEl = document.createElement("small");
            feedbackEl.className = "form-text email-feedback";
            container.appendChild(feedbackEl);
        }
        debouncedValidate(e.target, feedbackEl);
    }
}, true);

/* ===== Prevent form submit if any email is invalid ===== */
document.querySelector("form")?.addEventListener("submit", (e) => {
    if (document.querySelectorAll("input.is-invalid").length) {
        e.preventDefault();
        alert("Please fix invalid or duplicate email addresses before submitting.");
    }
});

/* ============== Edit Modal Email =================== */
// For Edit Email field
const editEmail = document.getElementById("editEmail");
const editEmailFeedback = document.getElementById("editEmailFeedback");  // Feedback element for Edit Email
if (editEmail) {
    editEmail.addEventListener("blur", () => {
        const currentId = document.getElementById("editId")?.value;
        debouncedValidate(editEmail, editEmailFeedback, currentId);  // Apply validation to the email field
    });
}

// For Emergency Contact Email field
const emergencyContactEmail = document.getElementById("emergencyContactEmail");
const emergencyContactEmailFeedback = document.getElementById("emergencyContactEmailFeedback");  // Feedback element for Emergency Contact Email
if (emergencyContactEmail) {
    emergencyContactEmail.addEventListener("blur", () => {
        debouncedValidate(emergencyContactEmail, emergencyContactEmailFeedback);  // Apply validation to the contact email field
    });
}

/* === Block submit in Edit Modal === */
const editForm = document.getElementById("editForm");
if (editForm) {
    editForm.addEventListener("submit", (e) => {
        if (editForm.querySelectorAll("input.is-invalid").length) {
            e.preventDefault();
            alert("Please correct invalid or duplicate email addresses before saving.");
        }
    });
}

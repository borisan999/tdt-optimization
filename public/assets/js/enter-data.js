/* =========================================
   Validation Rules
========================================= */
const validationRules = {
    piso: { min: 0, max: 200 },
    apartamento: { min: 0 },
    tus_requeridos: { min: 0, max: 20 },
    largo_cable_derivador: { min: 0 },
    largo_cable_repartidor: { min: 0, max: 200 },
    largo_cable_tu: { min: 0, max: 200 },
    tu_index: { min: 1 },
};

function validateField(input) {
    const field = input.dataset.field;
    if (!field) return true; // Not a field to validate

    const raw = input.value;
    if (raw === "") {
        input.classList.remove("is-invalid");
        return true;
    }

    const value = Number(raw);
    if (Number.isNaN(value)) {
        input.classList.add("is-invalid");
        return false;
    }

    const rules = validationRules[field];
    if (!rules) {
        input.classList.remove("is-invalid");
        return true;
    }

    if (rules.min !== undefined && value < rules.min) {
        input.classList.add("is-invalid");
        return false;
    }

    if (rules.max !== undefined && value > rules.max) {
        input.classList.add("is-invalid");
        return false;
    }

    input.classList.remove("is-invalid");
    return true;
}

function checkFormValidity() {
    const inputs = document.querySelectorAll("#manualInputForm .validate-field");
    let allFieldsValid = true;
    inputs.forEach(inp => {
        if (!validateField(inp)) {
            allFieldsValid = false;
        }
    });

    const saveBtn = document.getElementById("saveBtn");
    if (saveBtn) saveBtn.disabled = !allFieldsValid;
    
    const runBtn = document.querySelector('button[formaction*="run_python"]');
    if (runBtn) runBtn.disabled = !allFieldsValid;
}

document.addEventListener("input", e => {
    if (e.target.classList.contains("validate-field")) {
        validateField(e.target);
        checkFormValidity();
    }
});

/* =========================================
   Tabs
========================================= */
function showTab(tabId) {
    ["upload_tab", "manual_tab", "history_tab"].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add("hidden");
    });
    const showEl = document.getElementById(tabId);
    if (showEl) showEl.classList.remove("hidden");
}

/* =========================================
   Editable Row Builders
========================================= */
function addApartmentRow(data = {}) {
    const apartmentsBody = document.getElementById("apartmentsBody");
    if (!apartmentsBody) return;
    const newRowHtml = `
        <tr>
            <td><input type="number" name="piso[]" class="form-control validate-field" data-field="piso" value="${data.piso ?? ''}" required></td>
            <td><input type="number" name="apartamento[]" class="form-control validate-field" data-field="apartamento" value="${data.apartamento ?? ''}" required></td>
            <td><input type="number" name="tus_requeridos[]" class="form-control validate-field" data-field="tus_requeridos" value="${data.tus_requeridos ?? ''}" required></td>
            <td><input type="number" name="cable_derivador[]" class="form-control validate-field" data-field="largo_cable_derivador" step="any" value="${data.largo_cable_derivador ?? ''}" required></td>
            <td><input type="number" name="cable_repartidor[]" class="form-control validate-field" data-field="largo_cable_repartidor" step="any" value="${data.largo_cable_repartidor ?? ''}" required></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); checkFormValidity();">X</button>
            </td>
        </tr>
    `;
    apartmentsBody.insertAdjacentHTML("beforeend", newRowHtml);
}

function addTuRow(data = {}) {
    const tuBody = document.getElementById("tuBody");
    if (!tuBody) return;
    const newRowHtml = `
        <tr>
            <td><input type="number" name="tu_piso[]" class="form-control validate-field" data-field="piso" value="${data.piso ?? ''}" required></td>
            <td><input type="number" name="tu_apartamento[]" class="form-control validate-field" data-field="apartamento" value="${data.apartamento ?? ''}" required></td>
            <td><input type="number" name="tu_index[]" class="form-control validate-field" data-field="tu_index" value="${data.tu_index ?? ''}" required></td>
            <td><input type="number" name="largo_tu[]" class="form-control validate-field" data-field="largo_cable_tu" step="any" value="${data.largo_cable_tu ?? ''}" required></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); checkFormValidity();">X</button>
            </td>
        </tr>
    `;
    tuBody.insertAdjacentHTML("beforeend", newRowHtml);
}

/* =========================================
   Canonical Rebuild from Data Model
========================================= */
function rebuildFromCanonical() {
    if (!window.CANONICAL_DATA || typeof window.CANONICAL_DATA !== 'object') {
        console.warn('No canonical data found to rebuild tables.');
        return;
    }

    rebuildApartmentsFromCanonical(window.CANONICAL_DATA.apartments || []);
    rebuildTUsFromCanonical(window.CANONICAL_DATA.tus || []);
    rebuildParamsFromCanonical(window.CANONICAL_DATA.inputs || {});
}

function rebuildApartmentsFromCanonical(apartments) {
    const apartmentsBody = document.getElementById("apartmentsBody");
    if (!apartmentsBody) return;
    apartmentsBody.innerHTML = '';
    apartments.forEach(ap => addApartmentRow(ap));
}

function rebuildTUsFromCanonical(tus) {
    const tuBody = document.getElementById("tuBody");
    if (!tuBody) return;
    tuBody.innerHTML = '';
    tus.forEach(tu => addTuRow(tu));
}

function rebuildParamsFromCanonical(params) {
    for (const name in params) {
        const input = document.querySelector(`input[name="param_${name}"]`);
        if (input) {
            input.value = params[name];
        }
    }
}

/* =========================================
   DOM Extraction
========================================= */
function extractApartmentsFromDOM() {
    const apartments = [];
    document.querySelectorAll('#apartmentsBody tr').forEach(row => {
        const inputs = row.querySelectorAll('input');
        if (inputs.length < 5) return;
        apartments.push({
            piso: Number(inputs[0].value),
            apartamento: Number(inputs[1].value),
            tus_requeridos: Number(inputs[2].value),
            largo_cable_derivador: Number(inputs[3].value),
            largo_cable_repartidor: Number(inputs[4].value),
        });
    });
    return apartments;
}

function extractTUsFromDOM() {
    const tus = [];
    document.querySelectorAll('#tuBody tr').forEach(row => {
        const inputs = row.querySelectorAll('input');
        if (inputs.length < 4) return;
        tus.push({
            piso: Number(inputs[0].value),
            apartamento: Number(inputs[1].value),
            tu_index: Number(inputs[2].value),
            largo_cable_tu: Number(inputs[3].value),
        });
    });
    return tus;
}

function extractParamsFromDOM() {
    const params = {};
    const inputs = document.querySelectorAll('input[name^="param_"]');
    inputs.forEach(input => {
        const name = input.name.replace('param_', '');
        params[name] = input.value;
    });
    return params;
}

/* =========================================
   Bootstrap & Form Submission
========================================= */
document.addEventListener('DOMContentLoaded', function () {
    if (!window.CANONICAL_DATA || typeof window.CANONICAL_DATA !== 'object') {
        window.CANONICAL_DATA = { inputs: {}, apartments: [], tus: [] };
    }

    rebuildFromCanonical();
    checkFormValidity();
    
    // On first load, default to the manual tab if data is present, otherwise upload tab.
    const hasData = window.CANONICAL_DATA && 
                    (window.CANONICAL_DATA.apartments?.length > 0 || 
                     window.CANONICAL_DATA.tus?.length > 0 || 
                     Object.keys(window.CANONICAL_DATA.inputs || {}).length > 0);

    if (hasData) {
        showTab('manual_tab');
    } else {
        showTab('upload_tab');
    }

    const form = document.getElementById('manualInputForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            checkFormValidity();
            const saveBtn = document.getElementById("saveBtn");
            if (saveBtn && saveBtn.disabled) {
                 e.preventDefault();
                 alert("Please fix the validation errors before submitting.");
                 return;
            }

            const canonical = {
                inputs: extractParamsFromDOM(),
                apartments: extractApartmentsFromDOM(),
                tus: extractTUsFromDOM()
            };

            const payloadInput = document.getElementById('canonical_payload');
            if (payloadInput) {
                payloadInput.value = JSON.stringify(canonical);
            } else {
                console.error('CRITICAL: Hidden input #canonical_payload is missing!');
                e.preventDefault(); 
            }
        });
    }
});

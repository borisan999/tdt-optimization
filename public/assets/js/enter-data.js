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
    if (!field) return true;

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
    
    const runBtn = document.getElementById("runOptimizationBtn");
    if (runBtn) runBtn.disabled = !allFieldsValid;
    
    return allFieldsValid;
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
        if (el) {
            el.classList.add("hidden");
            el.classList.remove("show", "active");
        }
        
        // Fix: derive button ID correctly (e.g. upload_tab -> upload-tab-btn)
        const btnId = id.replace('_', '-') + '-btn';
        const btn = document.getElementById(btnId) || document.querySelector(`.tab-btn[onclick*="${id}"]`);
        
        if (btn) {
            btn.classList.remove("active", "btn-primary");
            if (btn.classList.contains("tab-btn")) btn.classList.add("btn-secondary");
        }
    });
    
    const showEl = document.getElementById(tabId);
    if (showEl) {
        showEl.classList.remove("hidden");
        setTimeout(() => showEl.classList.add("show", "active"), 10);
    }
    
    const activeBtnId = tabId.replace('_', '-') + '-btn';
    const activeBtn = document.getElementById(activeBtnId) || document.querySelector(`.tab-btn[onclick*="${tabId}"]`);
    
    if (activeBtn) {
        activeBtn.classList.add("active");
        if (activeBtn.classList.contains("tab-btn")) {
            activeBtn.classList.remove("btn-secondary");
            activeBtn.classList.add("btn-primary");
        }
    }
}

/* =========================================
   Editable Row Builders
========================================= */
const paramLabels = {
    "Nivel_maximo": __("param_Nivel_maximo"),
    "Nivel_minimo": __("param_Nivel_minimo"),
    "Piso_Maximo": __("param_Piso_Maximo"),
    "Potencia_Objetivo_TU": __("param_Potencia_Objetivo_TU"),
    "apartamentos_por_piso": __("param_apartamentos_por_piso"),
    "atenuacion_cable_470mhz": __("param_atenuacion_cable_470mhz"),
    "atenuacion_cable_698mhz": __("param_atenuacion_cable_698mhz"),
    "atenuacion_cable_por_metro": __("param_atenuacion_cable_por_metro"),
    "atenuacion_conector": __("param_atenuacion_conector"),
    "atenuacion_conexion_tu": __("param_atenuacion_conexion_tu"),
    "conectores_por_union": __("param_conectores_por_union"),
    "largo_cable_amplificador_ultimo_piso": __("param_largo_cable_amplificador_ultimo_piso"),
    "largo_cable_entre_pisos": __("param_largo_cable_entre_pisos"),
    "largo_cable_feeder_bloque": __("param_largo_cable_feeder_bloque"),
    "p_troncal": __("param_p_troncal"),
    "potencia_entrada": __("param_potencia_entrada"),
};

const paramDescriptions = {
    "Nivel_maximo": __("tooltip_Nivel_maximo"),
    "Nivel_minimo": __("tooltip_Nivel_minimo"),
    // ... add more if needed
};

const tableFieldHints = {
    "tus": __("hint_tus"),
    "deriv_rep": __("hint_deriv_rep"),
    "rep_tu": __("hint_rep_tu"),
};

function addApartmentRow(piso = '', apto = '', tus = '', deriv = '') {
    const apartmentsBody = document.getElementById("apartmentsBody");
    if (!apartmentsBody) return;
    const newRowHtml = `
        <tr>
            <td><input type="number" name="piso[]" class="form-control form-control-sm validate-field" data-field="piso" value="${piso}" required title="${__('col_piso_tooltip')}"></td>
            <td><input type="number" name="apartamento[]" class="form-control form-control-sm validate-field" data-field="apartamento" value="${apto}" required title="${__('col_apto_tooltip')}"></td>
            <td><input type="number" name="tus_requeridos[]" class="form-control form-control-sm validate-field" data-field="tus_requeridos" value="${tus}" title="${__('col_tus_tooltip')}" required></td>
            <td><input type="number" name="largo_cable_derivador[]" class="form-control form-control-sm validate-field" data-field="largo_cable_derivador" step="any" value="${deriv}" title="${__('col_deriv_len_tooltip')}" required></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger" onclick="this.closest('tr').remove(); checkFormValidity();">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `;
    apartmentsBody.insertAdjacentHTML("beforeend", newRowHtml);
}

function addTuRow(piso = '', apto = '', idx = '', len = '') {
    const tuBody = document.getElementById("tuBody");
    if (!tuBody) return;
    const newRowHtml = `
        <tr>
            <td><input type="number" name="tu_piso[]" class="form-control form-control-sm validate-field" data-field="piso" value="${piso}" required title="${__('col_piso_tooltip')}"></td>
            <td><input type="number" name="tu_apartamento[]" class="form-control form-control-sm validate-field" data-field="apartamento" value="${apto}" required title="${__('col_apto_tooltip')}"></td>
            <td><input type="number" name="tu_index[]" class="form-control form-control-sm validate-field" data-field="tu_index" value="${idx}" required title="${__('col_tu_idx_tooltip')}"></td>
            <td><input type="number" name="largo_tu[]" class="form-control form-control-sm validate-field" data-field="largo_cable_tu" step="any" value="${len}" title="${__('col_length_tooltip')}" required></td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-link text-danger" onclick="this.closest('tr').remove(); checkFormValidity();">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </td>
        </tr>
    `;
    tuBody.insertAdjacentHTML("beforeend", newRowHtml);
}

function renderGeneralParamsForm(params) {
    const container = document.getElementById('generalParamsContainer');
    if (!container) return;
    container.innerHTML = '';

    const categories = [
        {
            title: __('cat_geometry'),
            icon: 'fa-building',
            fields: ['Piso_Maximo', 'apartamentos_por_piso', 'largo_cable_entre_pisos', 'largo_cable_amplificador_ultimo_piso', 'largo_cable_feeder_bloque']
        },
        {
            title: __('cat_constraints'),
            icon: 'fa-signal',
            fields: ['potencia_entrada', 'Nivel_minimo', 'Nivel_maximo', 'Potencia_Objetivo_TU', 'p_troncal']
        },
        {
            title: __('cat_attenuation'),
            icon: 'fa-chart-line',
            fields: ['atenuacion_cable_por_metro', 'atenuacion_cable_470mhz', 'atenuacion_cable_698mhz', 'atenuacion_conector', 'atenuacion_conexion_tu', 'conectores_por_union']
        }
    ];

    let rowHtml = '<div class="row">';
    categories.forEach(cat => {
        let cardBody = '<div class="row g-2">';
        cat.fields.forEach(name => {
            const label = paramLabels[name] || name;
            const desc = paramDescriptions[name] || '';
            const value = params[name] ?? '';
            cardBody += `
                <div class="col-md-6 col-lg-12 mb-2">
                    <label class="form-label small fw-bold mb-0">${label}</label>
                    <input type="number" step="any" class="form-control form-control-sm validate-field" 
                           name="param_${name}" data-field="${name}" value="${value}" 
                           title="${desc}" required>
                </div>`;
        });
        cardBody += '</div>';

        rowHtml += `
            <div class="col-lg-4 mb-4">
                <div class="card h-100 shadow-sm border-light">
                    <div class="card-header bg-white py-2 border-bottom-0">
                        <h6 class="mb-0 text-primary fw-bold"><i class="fas ${cat.icon} me-2"></i>${cat.title}</h6>
                    </div>
                    <div class="card-body pt-0">
                        ${cardBody}
                    </div>
                </div>
            </div>`;
    });
    rowHtml += '</div>';
    container.innerHTML = rowHtml;
    
    // Complex Data (Read-only Tables)
    const complexContainer = document.getElementById('complexParamsForm');
    if (complexContainer) {
        complexContainer.innerHTML = '';
        
        // Helper to build a pretty table from catalog data
        const buildTable = (title, headers, data) => {
            let html = `<div class="mb-4">
                <label class="form-label small fw-bold text-uppercase text-primary">${title}</label>
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered mb-0 small">
                        <thead class="table-light"><tr>`;
            headers.forEach(h => html += `<th>${h}</th>`);
            html += `</tr></thead><tbody>`;
            
            Object.entries(data || {}).forEach(([model, specs]) => {
                html += `<tr><td class="fw-bold">${model}</td>`;
                if (title.includes('Derivadores')) {
                    html += `<td>${specs.derivacion} dB</td><td>${specs.paso} dB</td><td>${specs.salidas}</td>`;
                } else {
                    html += `<td>${specs.perdida_insercion} dB</td><td>${specs.salidas}</td>`;
                }
                html += `</tr>`;
            });
            
            if (Object.keys(data || {}).length === 0) {
                html += `<tr><td colspan="${headers.length}" class="text-center text-muted italic">No data available</td></tr>`;
            }
            
            html += `</tbody></table></div></div>`;
            return html;
        };

        const derivTable = buildTable('Derivadores Catalog', ['Modelo', 'Derivación', 'Paso', 'Salidas'], params.derivadores_data);
        const repTable = buildTable('Repartidores Catalog', ['Modelo', 'Pérdida Inserción', 'Salidas'], params.repartidores_data);

        complexContainer.innerHTML = `
            ${derivTable}
            ${repTable}
            <input type="hidden" id="derivadores_data_json" value='${JSON.stringify(params.derivadores_data || {})}'>
            <input type="hidden" id="repartidores_data_json" value='${JSON.stringify(params.repartidores_data || {})}'>
            <div class="form-text mt-n2 text-muted small"><i class="fas fa-info-circle"></i> These catalogs are read-only and managed in the equipment settings.</div>
        `;
    }
}

function populateFormFromCanonical(canonical, name = '') {
    const nameInput = document.getElementById('dataset_name');
    if (nameInput) nameInput.value = name || '';

    renderGeneralParamsForm(canonical);

    // 1. Populate Apartments Table from Map
    const apartmentsBody = document.getElementById("apartmentsBody");
    if (apartmentsBody && canonical.tus_requeridos_por_apartamento) {
        apartmentsBody.innerHTML = '';
        Object.entries(canonical.tus_requeridos_por_apartamento).forEach(([key, tus]) => {
            const [piso, apto] = key.split('|');
            const derivLen = canonical.largo_cable_derivador_repartidor?.[key] || 0;
            addApartmentRow(piso, apto, tus, derivLen); 
        });
    }

    // 2. Populate TUs Table from Map
    const tuBody = document.getElementById("tuBody");
    if (tuBody && canonical.largo_cable_tu) {
        tuBody.innerHTML = '';
        Object.entries(canonical.largo_cable_tu).forEach(([key, len]) => {
            const [piso, apto, idx] = key.split('|');
            addTuRow(piso, apto, idx, len);
        });
    }
    
    checkFormValidity();
}

/* =========================================
   DOM Extraction
========================================= */
function buildMapsFromTables(canonical) {
    const tusReq = {};
    const largoDR = {};
    const largoTU = {};

    // Extract from Apartments table
    document.querySelectorAll('#apartmentsBody tr').forEach(row => {
        const inputs = row.querySelectorAll('input');
        if (inputs.length < 4) return;
        const piso = inputs[0].value;
        const apto = inputs[1].value;
        const tus = Number(inputs[2].value);
        const deriv = Number(inputs[3].value);
        
        const key = `${piso}|${apto}`;
        tusReq[key] = tus;
        largoDR[key] = deriv;
    });

    // Extract from TUs table
    document.querySelectorAll('#tuBody tr').forEach(row => {
        const inputs = row.querySelectorAll('input');
        if (inputs.length < 4) return;
        const piso = inputs[0].value;
        const apto = inputs[1].value;
        const idx = inputs[2].value;
        const len = Number(inputs[3].value);
        
        const key = `${piso}|${apto}|${idx}`;
        largoTU[key] = len;
    });

    canonical.tus_requeridos_por_apartamento = tusReq;
    canonical.largo_cable_derivador_repartidor = largoDR;
    canonical.largo_cable_tu = largoTU;
}

/* =========================================
   Floor Repetition
========================================= */
function applyRepetition() {
    const sourceFloorId = document.querySelector('input[name="sourceFloorId"]').value;
    const targetStart = Number(document.querySelector('input[name="targetStartFloor"]').value);
    const targetEnd = Number(document.querySelector('input[name="targetEndFloor"]').value);

    if (!sourceFloorId || isNaN(targetStart) || isNaN(targetEnd)) {
        alert("Please fill all repetition fields (Source Floor, Target Start, Target End).");
        return;
    }

    if (targetStart > targetEnd) {
        alert("Target Start cannot be greater than Target End.");
        return;
    }

    // 1. Extract current state from tables to ensure we work with latest UI data
    const current = {};
    buildMapsFromTables(current);

    const tusReq = current.tus_requeridos_por_apartamento;
    const largoDR = current.largo_cable_derivador_repartidor;
    const largoTU = current.largo_cable_tu;

    // 2. Identify all source entries for the given sourceFloorId
    const sourceApts = Object.keys(tusReq).filter(k => k.split('|')[0] === sourceFloorId);
    const sourceTus = Object.keys(largoTU).filter(k => k.split('|')[0] === sourceFloorId);

    if (sourceApts.length === 0) {
        alert(`No data found for source floor ${sourceFloorId}. Add at least one apartment to this floor first.`);
        return;
    }

    // 3. Duplicate source data into the target range
    for (let f = targetStart; f <= targetEnd; f++) {
        const floorStr = f.toString();
        
        // Remove existing entries for this target floor to avoid duplicates/ghost data
        Object.keys(tusReq).forEach(k => { if (k.split('|')[0] === floorStr) delete tusReq[k]; });
        Object.keys(largoDR).forEach(k => { if (k.split('|')[0] === floorStr) delete largoDR[k]; });
        Object.keys(largoTU).forEach(k => { if (k.split('|')[0] === floorStr) delete largoTU[k]; });

        // Copy Apartment-level data
        sourceApts.forEach(oldKey => {
            const [_, apto] = oldKey.split('|');
            const newKey = `${floorStr}|${apto}`;
            tusReq[newKey] = tusReq[oldKey];
            largoDR[newKey] = largoDR[oldKey];
        });

        // Copy TU-level data
        sourceTus.forEach(oldKey => {
            const [_, apto, idx] = oldKey.split('|');
            const newKey = `${floorStr}|${apto}|${idx}`;
            largoTU[newKey] = largoTU[oldKey];
        });
    }

    // 4. Update the global data object and refresh the UI
    const updatedCanonical = {
        ...window.CANONICAL_DATA,
        tus_requeridos_por_apartamento: tusReq,
        largo_cable_derivador_repartidor: largoDR,
        largo_cable_tu: largoTU
    };
    
    // Crucial: Update the reference used by other parts of the app
    window.CANONICAL_DATA = updatedCanonical;
    
    populateFormFromCanonical(updatedCanonical);
    alert(`Successfully applied configuration from floor ${sourceFloorId} to floors ${targetStart}-${targetEnd}.`);
}

/* =========================================
   API Helpers (Task D)
========================================= */
async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
        const text = await response.text();
        console.error("Non-JSON response from " + url + ":", text);
        throw new Error("Invalid API response type. Expected JSON but received " + (contentType || "text/plain"));
    }
    const json = await response.json();
    return { json, status: response.status };
}

/* =========================================
   Bootstrap & Event Listeners
========================================= */
document.addEventListener('DOMContentLoaded', async function () {
    const urlParams = new URLSearchParams(window.location.search);
    let datasetId = urlParams.get('dataset_id');

    if (!datasetId) {
        const match = window.location.pathname.match(/\/enter-data\/(\d+)/);
        if (match) datasetId = match[1];
    }

    const BASE = (document.querySelector('base')?.getAttribute('href') || '/').replace(/\/?$/, '/');
    const isDirectPhp = window.location.pathname.endsWith('.php');
    const ROUTE_PREFIX = isDirectPhp ? 'index.php/' : '';
    const BASE_API = BASE + ROUTE_PREFIX + 'api'; 
    const DATASET_API = BASE + ROUTE_PREFIX + 'dataset';
    const DATASETS_LIST_API = DATASET_API + '/list';

    // 0. Fetch Managed Catalogs from DB
    let managedCatalogs = { derivadores_data: {}, repartidores_data: {}, general_params: {} };
    try {
        const { json } = await fetchJson(`${BASE_API}/catalogs`);
        if (json.success) {
            managedCatalogs = json.data;
        }
    } catch (e) {
        console.warn("Could not fetch latest catalogs from DB:", e);
    }

    // 0.1 Fetch Datasets History
    const historySelect = document.getElementById('historySelect');
    if (historySelect) {
        try {
            const { json } = await fetchJson(DATASETS_LIST_API);
            if (json.success && json.datasets) {
                json.datasets.forEach(ds => {
                    const date = new Date(ds.created_at).toLocaleString();
                    const name = ds.dataset_name || 'Unnamed Dataset';
                    const option = document.createElement('option');
                    option.value = ds.dataset_id;
                    option.textContent = `${name} (ID: ${ds.dataset_id}) - ${date}`;
                    historySelect.appendChild(option);
                });
            }
        } catch (e) {
            console.warn("Could not fetch datasets history:", e);
        }
    }

    const historyLoadForm = document.getElementById('historyLoadForm');
    if (historyLoadForm) {
        historyLoadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const selectedId = historySelect.value;
            if (selectedId) {
                window.location.href = `${BASE}enter-data/${selectedId}`;
            }
        });
    }

    const repeatBtn = document.getElementById('applyRepetitionBtn');
    if (repeatBtn) repeatBtn.onclick = applyRepetition;

    if (!datasetId) {
        renderGeneralParamsForm({
            ...managedCatalogs,
            ...(managedCatalogs.general_params || {})
        });
    } else {
        document.getElementById('current_dataset_id').value = datasetId;
        const statusBadge = document.getElementById('status_badge');
        if (statusBadge) {
            statusBadge.textContent = __("mode_edit", { id: datasetId });
            statusBadge.classList.replace('bg-secondary', 'bg-primary');
        }
        try {
            const { json } = await fetchJson(`${DATASET_API}/${datasetId}`);
            if (json.success && json.canonical) {
                // Merge managed catalogs into canonical (DB takes precedence)
                const canonical = {
                    ...json.canonical,
                    derivadores_data: managedCatalogs.derivadores_data,
                    repartidores_data: managedCatalogs.repartidores_data
                };
                window.CANONICAL_DATA = canonical;
                populateFormFromCanonical(canonical, json.dataset_name || json.canonical.dataset_name);
                showTab('manual_tab');
                document.getElementById('runOptimizationBtn').classList.remove('hidden');
            } else {
                alert('Failed to load dataset: ' + (json.error?.message || json.message || 'Unknown error'));
            }
        } catch (e) {
            console.error('Load error:', e);
            alert('Error loading dataset: ' + e.message);
        }
    }

    const excelUploadForm = document.getElementById('excelUploadForm');
    if (excelUploadForm) {
        excelUploadForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const { json } = await fetchJson(`${BASE_API}/upload/excel`, { method: 'POST', body: formData });
                if (json.success && json.data && json.data.dataset_id) {
                    window.location.href = `${BASE}enter-data/${json.data.dataset_id}`;
                } else {
                    alert('Upload failed: ' + (json.error?.message || 'Check logs'));
                }
            } catch (e) { 
                alert('Upload error: ' + e.message);
            }
        });
    }

    const manualInputForm = document.getElementById('manualInputForm');
    if (manualInputForm) {
        manualInputForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const targetDatasetId = document.getElementById('current_dataset_id').value;
            const datasetName = document.getElementById('dataset_name').value;

            const canonical = {};
            document.querySelectorAll('#generalParamsContainer input[name^="param_"]').forEach(input => {
                const name = input.name.replace('param_', '');
                canonical[name] = Number(input.value);
            });

            buildMapsFromTables(canonical);

            try {
                canonical.derivadores_data = JSON.parse(document.getElementById('derivadores_data_json').value);
                canonical.repartidores_data = JSON.parse(document.getElementById('repartidores_data_json').value);
            } catch (e) { alert('Invalid JSON in catalogs.'); return; }

            try {
                let url = targetDatasetId ? `${DATASET_API}/update` : `${BASE_API}/datasets`;
                const { json } = await fetchJson(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        dataset_id: targetDatasetId, 
                        dataset_name: datasetName,
                        canonical: canonical 
                    })
                });

                if (json.success) {
                    alert('Saved!');
                    const newId = json.data?.dataset_id || targetDatasetId;
                    if (newId && newId !== targetDatasetId) {
                        window.location.href = `${BASE}enter-data/${newId}`;
                    }
                } else {
                    alert('Save failed: ' + (json.error?.message || json.message || 'Error'));
                }
            } catch (e) { 
                alert('Save error: ' + e.message);
            }
        });
    }

    const runBtn = document.getElementById('runOptimizationBtn');
    if (runBtn) {
        runBtn.addEventListener('click', async function () {
            const datasetId = document.getElementById('current_dataset_id').value;
            this.disabled = true;
            this.textContent = 'Running...';
            try {
                const { json } = await fetchJson(`${DATASET_API}/run/${datasetId}`, { method: 'POST' });
                const optId = json.result?.opt_id || json.opt_id;
                if (json.success && optId) {
                    window.location.href = `${BASE}view-result/${optId}`;
                } else {
                    alert('Execution failed: ' + (json.error?.message || json.message || 'Error'));
                }
            } catch (e) { 
                alert('Run error: ' + e.message);
            }
            finally { this.disabled = false; this.textContent = '▶ Run Optimization'; }
        });
    }
});

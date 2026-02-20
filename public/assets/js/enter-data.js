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
        if (el) el.classList.add("hidden");
        
        const btn = document.querySelector(`.tab-btn[onclick*="${id}"]`);
        if (btn) btn.classList.remove("btn-primary");
        if (btn) btn.classList.add("btn-secondary");
    });
    
    const showEl = document.getElementById(tabId);
    if (showEl) showEl.classList.remove("hidden");
    
    const activeBtn = document.querySelector(`.tab-btn[onclick*="${tabId}"]`);
    if (activeBtn) activeBtn.classList.remove("btn-secondary");
    if (activeBtn) activeBtn.classList.add("btn-primary");
}

/* =========================================
   Editable Row Builders
========================================= */
const paramLabels = {
    "Nivel_maximo": "Nivel Máximo (dBuV)",
    "Nivel_minimo": "Nivel Mínimo (dBuV)",
    "Piso_Maximo": "Piso Máximo",
    "Potencia_Objetivo_TU": "Potencia Objetivo TU (dBuV)",
    "apartamentos_por_piso": "Apartamentos por Piso",
    "atenuacion_cable_470mhz": "Atenuación Cable 470MHz",
    "atenuacion_cable_698mhz": "Atenuación Cable 698MHz",
    "atenuacion_cable_por_metro": "Atenuación Cable por Metro",
    "atenuacion_conector": "Atenuación Conector",
    "atenuacion_conexion_tu": "Atenuación Conexión TU",
    "conectores_por_union": "Conectores por Unión",
    "largo_cable_amplificador_ultimo_piso": "Largo Cable Amplificador Último Piso (m)",
    "largo_cable_entre_pisos": "Largo Cable entre Pisos (m)",
    "largo_cable_feeder_bloque": "Largo Cable Feeder Bloque (m)",
    "p_troncal": "P Troncal",
    "potencia_entrada": "Potencia Entrada (dBuV)",
};

function addApartmentRow(piso = '', apto = '', tus = '', deriv = '', rep = '') {
    const apartmentsBody = document.getElementById("apartmentsBody");
    if (!apartmentsBody) return;
    const newRowHtml = `
        <tr>
            <td><input type="number" name="piso[]" class="form-control validate-field" data-field="piso" value="${piso}" required></td>
            <td><input type="number" name="apartamento[]" class="form-control validate-field" data-field="apartamento" value="${apto}" required></td>
            <td><input type="number" name="tus_requeridos[]" class="form-control validate-field" data-field="tus_requeridos" value="${tus}" required></td>
            <td><input type="number" name="largo_cable_derivador[]" class="form-control validate-field" data-field="largo_cable_derivador" step="any" value="${deriv}" required></td>
            <td><input type="number" name="largo_cable_repartidor[]" class="form-control validate-field" data-field="largo_cable_repartidor" step="any" value="${rep}" required></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); checkFormValidity();">X</button>
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
            <td><input type="number" name="tu_piso[]" class="form-control validate-field" data-field="piso" value="${piso}" required></td>
            <td><input type="number" name="tu_apartamento[]" class="form-control validate-field" data-field="apartamento" value="${apto}" required></td>
            <td><input type="number" name="tu_index[]" class="form-control validate-field" data-field="tu_index" value="${idx}" required></td>
            <td><input type="number" name="largo_tu[]" class="form-control validate-field" data-field="largo_cable_tu" step="any" value="${len}" required></td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove(); checkFormValidity();">X</button>
            </td>
        </tr>
    `;
    tuBody.insertAdjacentHTML("beforeend", newRowHtml);
}

function renderGeneralParamsForm(params) {
    const formDiv = document.getElementById('generalParamsForm');
    if (!formDiv) return;
    formDiv.innerHTML = '';

    for (const name in paramLabels) {
        const value = params[name] ?? '';
        formDiv.insertAdjacentHTML("beforeend", `
            <div class="col-md-3 mb-3">
                <label class="form-label">${paramLabels[name]}</label>
                <input type="number"
                    step="any"
                    class="form-control validate-field"
                    name="param_${name}"
                    data-field="${name}"
                    value="${value}" required>
            </div>
        `);
    }
    
    const complex = [
        { id: 'derivadores_data_json', label: 'Derivadores Data', data: params.derivadores_data },
        { id: 'repartidores_data_json', label: 'Repartidores Data', data: params.repartidores_data },
        { id: 'largo_cable_derivador_repartidor_json', label: 'Largo Cable Derivador Repartidor', data: params.largo_cable_derivador_repartidor },
        { id: 'largo_cable_tu_json', label: 'Largo Cable TU', data: params.largo_cable_tu },
        { id: 'tus_requeridos_por_apartamento_json', label: 'TUs Requeridos por Apartamento', data: params.tus_requeridos_por_apartamento }
    ];

    complex.forEach(c => {
        formDiv.insertAdjacentHTML("beforeend", `
            <div class="col-md-12 mb-3">
                <label class="form-label">${c.label} (JSON)</label>
                <textarea class="form-control" id="${c.id}" rows="5">${JSON.stringify(c.data ?? {}, null, 2)}</textarea>
            </div>
        `);
    });
}

function populateFormFromCanonical(canonical) {
    renderGeneralParamsForm(canonical);

    // 1. Populate Apartments Table from Map
    const apartmentsBody = document.getElementById("apartmentsBody");
    if (apartmentsBody && canonical.tus_requeridos_por_apartamento) {
        apartmentsBody.innerHTML = '';
        Object.entries(canonical.tus_requeridos_por_apartamento).forEach(([key, tus]) => {
            const [piso, apto] = key.split('|');
            const derivLen = canonical.largo_cable_derivador_repartidor?.[key] || 0;
            // Note: Repartidor length defaults to 0 as it's not explicitly in the Excel map
            addApartmentRow(piso, apto, tus, derivLen, 0); 
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
        if (inputs.length < 5) return;
        const piso = inputs[0].value;
        const apto = inputs[1].value;
        const tus = Number(inputs[2].value);
        const deriv = Number(inputs[3].value);
        // rep is currently ignored as Excel map only has one length
        
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
        alert("Fill repetition fields.");
        return;
    }

    // Extract current state from tables first to ensure we repeat what is on screen
    const current = {};
    buildMapsFromTables(current);

    const tusReq = current.tus_requeridos_por_apartamento;
    const largoDR = current.largo_cable_derivador_repartidor;
    const largoTU = current.largo_cable_tu;

    // Filter source data
    const sourceApts = Object.keys(tusReq).filter(k => k.split('|')[0] === sourceFloorId);
    const sourceTus = Object.keys(largoTU).filter(k => k.split('|')[0] === sourceFloorId);

    // Overwrite range
    for (let f = targetStart; f <= targetEnd; f++) {
        // Clear existing
        Object.keys(tusReq).forEach(k => { if (parseInt(k.split('|')[0]) === f) delete tusReq[k]; });
        Object.keys(largoDR).forEach(k => { if (parseInt(k.split('|')[0]) === f) delete largoDR[k]; });
        Object.keys(largoTU).forEach(k => { if (parseInt(k.split('|')[0]) === f) delete largoTU[k]; });

        // Copy source
        sourceApts.forEach(oldKey => {
            const [_, apto] = oldKey.split('|');
            const newKey = `${f}|${apto}`;
            tusReq[newKey] = tusReq[oldKey];
            largoDR[newKey] = largoDR[oldKey];
        });
        sourceTus.forEach(oldKey => {
            const [_, apto, idx] = oldKey.split('|');
            const newKey = `${f}|${apto}|${idx}`;
            largoTU[newKey] = largoTU[oldKey];
        });
    }

    // Refresh UI
    const canonical = {
        ...window.CANONICAL_DATA,
        tus_requeridos_por_apartamento: tusReq,
        largo_cable_derivador_repartidor: largoDR,
        largo_cable_tu: largoTU
    };
    populateFormFromCanonical(canonical);
}

/* =========================================
   API Helpers (Task D)
========================================= */
async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    
    // Strict verification of Content-Type
    const contentType = response.headers.get("content-type");
    if (!contentType || !contentType.includes("application/json")) {
        // Try to get some text for debugging
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

    // Extract datasetId from path if not in query (for /enter-data/123)
    if (!datasetId) {
        const pathParts = window.location.pathname.split('/');
        const enterDataIdx = pathParts.indexOf('enter-data');
        if (enterDataIdx !== -1 && pathParts[enterDataIdx + 1]) {
            datasetId = pathParts[enterDataIdx + 1];
        }
    }

    const BASE = (document.querySelector('base')?.getAttribute('href') || '/').replace(/\/?$/, '/');
    const isDirectPhp = window.location.pathname.endsWith('.php');
    const ROUTE_PREFIX = isDirectPhp ? 'index.php/' : '';
    const BASE_API = BASE + ROUTE_PREFIX + 'api'; 
    const DATASET_API = BASE + ROUTE_PREFIX + 'dataset';

    // Repetition button
    const repeatBtn = document.getElementById('applyRepetitionBtn');
    if (repeatBtn) repeatBtn.onclick = applyRepetition;

    if (datasetId) {
        document.getElementById('current_dataset_id').value = datasetId;
        try {
            const { json } = await fetchJson(`${DATASET_API}/${datasetId}`);
            if (json.success && json.canonical) {
                window.CANONICAL_DATA = json.canonical;
                populateFormFromCanonical(json.canonical);
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

    // Excel Upload
    const excelUploadForm = document.getElementById('excelUploadForm');
    if (excelUploadForm) {
        excelUploadForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            try {
                const { json } = await fetchJson(`${BASE_API}/upload/excel`, { method: 'POST', body: formData });

                if (json.success && json.data && json.data.dataset_id) {
                    window.location.href = `enter-data/${json.data.dataset_id}`;
                } else {
                    const errorMsg = json.error?.message || json.message || 'Check logs';
                    alert('Upload failed: ' + errorMsg);
                }
            } catch (e) { 
                console.error('Upload error:', e); 
                alert('Upload error: ' + e.message);
            }
        });
    }

    // Manual Save
    const manualInputForm = document.getElementById('manualInputForm');
    if (manualInputForm) {
        manualInputForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const targetDatasetId = document.getElementById('current_dataset_id').value;

            const canonical = {};
            document.querySelectorAll('#generalParamsForm input[name^="param_"]').forEach(input => {
                const name = input.name.replace('param_', '');
                canonical[name] = Number(input.value);
            });

            // Merge maps from tables
            buildMapsFromTables(canonical);

            // Merge catalog from textareas
            try {
                canonical.derivadores_data = JSON.parse(document.getElementById('derivadores_data_json').value);
                canonical.repartidores_data = JSON.parse(document.getElementById('repartidores_data_json').value);
            } catch (e) { alert('Invalid JSON in catalogs.'); return; }

            try {
                let url = targetDatasetId ? `${DATASET_API}/update` : `${BASE_API}/datasets`;
                const { json } = await fetchJson(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ dataset_id: targetDatasetId, canonical: canonical })
                });

                if (json.success) {
                    alert('Saved!');
                    if (!targetDatasetId && json.data?.dataset_id) {
                        window.location.href = `enter-data/${json.data.dataset_id}`;
                    }
                } else {
                    const errorMsg = json.error?.message || json.message || 'Error';
                    alert('Save failed: ' + errorMsg);
                }
            } catch (e) { 
                console.error('Save error:', e); 
                alert('Save error: ' + e.message);
            }
        });
    }

    // Run
    const runBtn = document.getElementById('runOptimizationBtn');
    if (runBtn) {
        runBtn.addEventListener('click', async function () {
            const datasetId = document.getElementById('current_dataset_id').value;
            this.disabled = true;
            this.textContent = 'Running...';
            try {
                const { json } = await fetchJson(`${DATASET_API}/run/${datasetId}`, { method: 'POST' });
                // Robust extraction of opt_id from the result
                // The API returns {success:true, result: {summary: { ... }, detail: [...], opt_id: ...}} 
                // OR it might be in the top level if modified recently
                const optId = json.result?.opt_id || json.opt_id;
                
                if (json.success && optId) {
                    window.location.href = `view-result/${optId}`;
                } else if (json.success) {
                    // Fallback if optId is missing but success is true (shouldn't happen with current API)
                    window.location.href = `results.php?dataset_id=${datasetId}`;
                } else {
                    alert('Execution failed: ' + (json.error?.message || json.message || 'Error'));
                }
            } catch (e) { 
                console.error('Run error:', e); 
                alert('Run error: ' + e.message);
            }
            finally { this.disabled = false; this.textContent = '▶ Run Optimization'; }
        });
    }
});

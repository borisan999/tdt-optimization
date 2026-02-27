<?php
require_once __DIR__ . '/../app/auth/require_login.php';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
        <div>
            <h2 class="text-primary mb-0"><i class="fas fa-magic me-2"></i><?= __('gen_title') ?></h2>
            <p class="text-muted small mb-0"><?= __('gen_subtitle') ?></p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="fas fa-question-circle me-1"></i> <?= __('help_guide') ?>
            </button>
            <a href="dashboard" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> <?= __('back') ?>
            </a>
        </div>
    </div>

    <!-- Quick Info Banner -->
    <div class="alert alert-light border border-info border-start-4 shadow-sm mb-4 py-3 animate__animated animate__fadeIn">
        <div class="d-flex align-items-center">
            <div class="me-3"><i class="fas fa-info-circle text-info fa-2x"></i></div>
            <div>
                <h6 class="fw-bold mb-1"><?= __('step_by_step') ?>:</h6>
                <div class="d-flex gap-4 flex-wrap small text-muted">
                    <span><span class="badge bg-primary me-1">1</span> <?= __('global_config') ?></span>
                    <span><i class="fas fa-chevron-right mx-1 small"></i></span>
                    <span><span class="badge bg-success me-1">2</span> <?= __('apartment_models') ?></span>
                    <span><i class="fas fa-chevron-right mx-1 small"></i></span>
                    <span><span class="badge bg-info text-dark me-1">3</span> <?= __('building_mapping') ?></span>
                </div>
            </div>
        </div>
    </div>

    <form id="template-form">
        <div class="row">
            <!-- Sidebar: General Parameters -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4 sticky-top" style="top: 20px; z-index: 10;">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i><?= __('global_config') ?></h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label for="template-project-name" class="form-label fw-bold" title="<?= __('tooltip_project') ?>">
                                <?= __('project_name') ?> <i class="fas fa-info-circle text-muted ms-1 small"></i>
                            </label>
                            <input type="text" class="form-control border-primary-subtle shadow-sm" id="template-project-name" placeholder="<?= __('project_placeholder') ?>" required>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-6">
                                <label for="general-Piso_Maximo" class="form-label fw-bold" title="<?= __('tooltip_max_floor') ?>">
                                    <?= __('max_floor') ?> <i class="fas fa-info-circle text-muted ms-1 small"></i>
                                </label>
                                <input type="number" class="form-control shadow-sm" id="general-Piso_Maximo" value="10" step="1">
                            </div>
                            <div class="col-6">
                                <label for="general-Apartamentos_Piso" class="form-label fw-bold" title="<?= __('tooltip_apts') ?>">
                                    <?= __('apts_per_floor') ?> <i class="fas fa-info-circle text-muted ms-1 small"></i>
                                </label>
                                <input type="number" class="form-control shadow-sm" id="general-Apartamentos_Piso" value="4" required step="1">
                            </div>
                        </div>
                        
                        <div class="accordion mb-4" id="paramsAccordion">
                            <div class="accordion-item border-0 bg-light shadow-sm rounded-3 overflow-hidden">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light py-2 small fw-bold text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTech">
                                        <i class="fas fa-tools me-2"></i> <?= __('tech_params') ?>
                                    </button>
                                </h2>
                                <div id="collapseTech" class="accordion-collapse collapse" data-bs-parent="#paramsAccordion">
                                    <div class="accordion-body px-3 py-3 border-top border-2 border-white">
                                        <div class="mb-3">
                                            <label class="form-label small mb-1 fw-bold" title="<?= __('tooltip_amp_dist') ?>"><?= __('tech_amp_length') ?></label>
                                            <input type="number" class="form-control form-control-sm" id="general-Largo_Cable_Amplificador_Ultimo_Piso" value="4" step="any">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small mb-1 fw-bold"><?= __('tech_input_power') ?></label>
                                            <input type="number" class="form-control form-control-sm" id="general-Potencia_Entrada_dBuV" value="110" step="any">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small mb-1 fw-bold"><?= __('tech_feeder_length') ?></label>
                                            <input type="number" class="form-control form-control-sm" id="general-Largo_Feeder_Bloque_m (Mínimo)" value="3.5" step="any">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small mb-1 fw-bold"><?= __('tech_cable_atten') ?></label>
                                            <input type="number" class="form-control form-control-sm" id="general-Atenuacion_Cable_dBporM" value="0.2" step="any">
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small mb-1 fw-bold"><?= __('tech_floor_dist') ?></label>
                                            <input type="number" class="form-control form-control-sm" id="general-Largo_Entre_Pisos_m" value="3.5" step="any">
                                        </div>
                                        <div class="mb-0">
                                            <label class="form-label small mb-1 fw-bold"><?= __('tech_norm_band') ?></label>
                                            <div class="input-group input-group-sm">
                                                <input type="number" class="form-control" id="general-Nivel_Minimo_dBuV" value="47" step="any">
                                                <span class="input-group-text bg-white"><?= __('tech_to') ?></span>
                                                <input type="number" class="form-control" id="general-Nivel_Maximo_dBuV" value="70" step="any">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button id="generate-db-btn" type="submit" class="btn btn-primary btn-lg w-100 shadow-sm fw-bold">
                            <i class="fas fa-rocket me-2"></i><?= __('generate_btn') ?>
                        </button>
                    </div>
                </div>
                
                <div id="message-panel" class="alert d-none shadow-sm animate__animated animate__fadeIn"></div>
            </div>

            <!-- Main Content: Types and Assignments -->
            <div class="col-lg-8">
                <!-- Step 1: Apartment Types -->
                <div class="card shadow-sm border-0 mb-4 overflow-hidden rounded-3">
                    <div class="card-header bg-success text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-door-open me-2"></i>1. <?= __('apartment_models') ?></h5>
                        <button type="button" id="add-apartment-type-btn" class="btn btn-light btn-sm fw-bold shadow-sm">
                            <i class="fas fa-plus-circle me-1"></i><?= __('add_model') ?>
                        </button>
                    </div>
                    <div class="card-body bg-light-subtle p-4">
                        <div id="apartment-types-container" class="row g-4"></div>
                    </div>
                </div>

                <!-- Step 2: Assignments -->
                <div class="card shadow-sm border-0 mb-4 overflow-hidden rounded-3">
                    <div class="card-header bg-info text-dark py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fas fa-network-wired me-2"></i>2. <?= __('building_mapping') ?></h5>
                        <button type="button" id="add-assignment-btn" class="btn btn-light btn-sm fw-bold shadow-sm">
                            <i class="fas fa-plus-circle me-1"></i><?= __('add_range') ?>
                        </button>
                    </div>
                    <div class="card-body bg-light-subtle p-4">
                        <div id="assignments-container"></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-dark">
                <h5 class="modal-title fw-bold"><i class="fas fa-lightbulb me-2"></i><?= __('gen_guide_title') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6><strong><?= __('concept_1_title') ?></strong></h6>
                <p><?= __('concept_1_desc') ?></p>
                
                <hr>
                
                <h6><strong><?= __('concept_2_title') ?></strong></h6>
                <p><?= __('concept_2_desc') ?></p>
                
                <hr>
                
                <h6><strong><?= __('variation_title') ?></strong></h6>
                <p><?= __('variation_desc') ?> 
                   <br><br>
                   <strong><?= __('variation_example_title') ?></strong> 
                   <br><?= __('variation_example_1') ?>
                   <ul>
                       <li><?= __('variation_example_2') ?></li>
                       <li><?= __('variation_example_3') ?></li>
                   </ul>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('got_it') ?></button>
            </div>
        </div>
    </div>
</div>

<style>
    body { background-color: #f0f2f5; }
    .cursor-pointer { cursor: pointer; }
    .template-block { border: 1px solid #dee2e6; border-radius: 12px; padding: 20px; margin-bottom: 5px; background-color: #fff; transition: all 0.2s ease; position: relative; }
    .template-block:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.08); }
    .type-card { border-top: 5px solid #28a745; }
    .assignment-card { border-top: 5px solid #0dcaf0; }
    .assignment-rule-block { background: #f8f9fa; border-radius: 10px; padding: 15px; margin-bottom: 12px; border: 1px dashed #dee2e6; transition: background 0.2s; }
    .assignment-rule-block:hover { background: #fff; border-color: #0dcaf0; }
    .form-control:focus, .form-select:focus { border-color: #0d6efd; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15); }
    .btn-close-custom { position: absolute; top: 10px; right: 10px; opacity: 0.5; transition: opacity 0.2s; }
    .btn-close-custom:hover { opacity: 1; }
    .accordion-button:not(.collapsed) { color: #0d6efd; background-color: #e7f1ff; }
    .range-preview { min-height: 30px; padding: 4px 0; }
    .range-preview .badge { font-weight: normal; margin-bottom: 2px; transition: all 0.2s; cursor: default; }
    .range-preview .badge:hover { transform: scale(1.1); opacity: 1 !important; }
    .preset-btn { font-size: 0.75rem; padding: 0.1rem 0.5rem; }
    .tu-grid { background: #fdfdfd; border: 1px solid #eee; border-radius: 8px; padding: 12px; }
</style>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let apartmentTypeCounter = 0;
    let assignmentCounter = 0;

    // Pass translations to JS
    window.LANG = <?= json_encode(\app\helpers\Translation::getDictionary(), JSON_UNESCAPED_UNICODE) ?>;
    function __(key, placeholders = {}) {
        let text = window.LANG[key] || key;
        for (const [k, v] of Object.entries(placeholders)) { text = text.replace(`{${k}}`, v); }
        return text;
    }

    function initTooltips() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    }

    function showMessage(message, isError = false) {
        const panel = document.getElementById('message-panel');
        panel.innerHTML = `<i class="fas fa-${isError ? 'exclamation-triangle' : 'check-circle'} me-2"></i>${message}`;
        panel.className = `alert shadow-sm ${isError ? 'alert-danger' : 'alert-success'} animate__animated animate__fadeInDown`;
        panel.classList.remove('d-none');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function numOnly(str) {
        return str.toString().replace(/[^0-9]/g, '');
    }

    function parseRangeString(rangeStr) {
        if (!rangeStr) return [];
        let result = new Set();
        const parts = rangeStr.split(',');
        parts.forEach(part => {
            part = part.trim();
            if (!part) return;
            if (part.includes('-')) {
                const rangeParts = part.split('-');
                const start = parseInt(numOnly(rangeParts[0]));
                const end = parseInt(numOnly(rangeParts[1]));
                if (!isNaN(start) && !isNaN(end)) {
                    for (let i = Math.min(start, end); i <= Math.max(start, end); i++) {
                        result.add(i);
                    }
                }
            } else {
                const num = parseInt(numOnly(part));
                if (!isNaN(num)) result.add(num);
            }
        });
        return Array.from(result).sort((a, b) => a - b);
    }

    function updateRangePreview(inputElement) {
        const value = inputElement.value;
        const parentWrapper = inputElement.closest('.col-md-8, .col-md-6, .mb-3');
        const previewContainer = parentWrapper ? parentWrapper.querySelector('.range-preview') : null;
        
        if (!previewContainer) return;

        const type = inputElement.dataset.rangeType;
        const maxVal = type === 'piso' 
            ? parseInt(document.getElementById('general-Piso_Maximo').value) 
            : parseInt(document.getElementById('general-Apartamentos_Piso').value);

        const selected = parseRangeString(value);
        
        if (selected.length === 0) {
            previewContainer.innerHTML = `<span class="text-muted small italic">${__('none_selected')}</span>`;
            return;
        }

        let html = '<div class="d-flex flex-wrap gap-1">';
        selected.forEach(num => {
            const isError = num > maxVal || num < 1;
            const colorClass = isError ? 'bg-danger text-white' : (type === 'piso' ? 'bg-info text-dark' : 'bg-secondary text-white');
            const label = type === 'apto' ? __('label_apt') : __('label_floor');
            html += `<span class="badge ${colorClass} opacity-75 shadow-sm" style="font-size: 0.65rem;">${label}${num}</span>`;
        });
        html += '</div>';
        previewContainer.innerHTML = html;
    }

    function updateTuLengthFields(typeId) {
        const container = document.getElementById(`tu-lengths-container-${typeId}`);
        if (!container) return;

        const numTus = parseInt(document.getElementById(`tus-${typeId}`).value) || 0;
        container.innerHTML = `<h6 class="mt-3 mb-2 small fw-bold text-secondary"><i class="fas fa-bezier-curve me-1"></i>${__('outlets_dist')}</h6>`;
        
        const grid = document.createElement('div');
        grid.className = 'row row-cols-2 row-cols-sm-3 g-2 tu-grid';
        
        if (numTus === 0) {
            grid.innerHTML = `<div class="col-12 text-center text-muted small p-2">${__('indicate_tus')}</div>`;
        } else {
            for (let i = 1; i <= numTus; i++) {
                const col = document.createElement('div');
                col.className = 'col-6 col-sm-4 col-md-3';
                col.innerHTML = `
                    <div class="input-group input-group-sm shadow-sm border rounded overflow-hidden">
                        <span class="input-group-text bg-light border-0 small fw-bold" style="width: 35px; justify-content: center;">#${i}</span>
                        <input type="number" class="form-control border-0" id="len-tu-${typeId}-${i}" value="5" step="any" required title="${__('tooltip_tu_dist', {i: i})}">
                    </div>`;
                grid.appendChild(col);
            }
        }
        container.appendChild(grid);
        initTooltips();
    }

    function updateAssignmentDropdowns() {
        const types = [];
        document.querySelectorAll('.apartment-type-block').forEach(block => {
            const typeNameInput = block.querySelector('input[id^="type-name-"]');
            if (typeNameInput && typeNameInput.value) {
                types.push(typeNameInput.value);
            }
        });
        document.querySelectorAll('.assignment-rule-block select').forEach(select => {
            const currentVal = select.value;
            select.innerHTML = `<option value="" disabled selected>${__('choose_model')}</option>`;
            types.forEach(type => {
                const isSelected = type === currentVal ? ' selected' : '';
                select.innerHTML += `<option value="${type}"${isSelected}>${type}</option>`;
            });
        });
    }

    window.addApartmentType = function() {
        apartmentTypeCounter++;
        const container = document.getElementById('apartment-types-container');
        const col = document.createElement('div');
        col.className = 'col-md-6 animate__animated animate__fadeInUp';
        col.innerHTML = `
            <div class="template-block type-card apartment-type-block h-100 shadow-sm" id="apartment-type-${apartmentTypeCounter}">
                <button type="button" class="btn-close btn-close-custom btn-sm" onclick="this.closest('.col-md-6').remove(); updateAssignmentDropdowns();" title="${__('delete_model')}"></button>
                <div class="mb-3">
                    <label class="small fw-bold mb-1"><i class="fas fa-tag me-1 text-success"></i>${__('model_name')}</label>
                    <input type="text" class="form-control form-control-sm border-success-subtle shadow-sm" id="type-name-${apartmentTypeCounter}" placeholder="${__('model_placeholder')}" required title="${__('tooltip_model_name')}">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="small fw-bold mb-1" title="${__('tooltip_num_outlets')}">${__('num_outlets')}</label>
                        <input type="number" class="form-control form-control-sm shadow-sm" id="tus-${apartmentTypeCounter}" value="2" min="1" required>
                    </div>
                    <div class="col-6">
                        <label class="small fw-bold mb-1" title="${__('tooltip_entry_dist')}">${__('entry_length')}</label>
                        <input type="number" class="form-control form-control-sm shadow-sm" id="len-deriv-${apartmentTypeCounter}" value="8" step="any" required>
                    </div>
                </div>
                <div id="tu-lengths-container-${apartmentTypeCounter}"></div>
            </div>`;
        container.appendChild(col);
        
        const typeNameInput = col.querySelector(`#type-name-${apartmentTypeCounter}`);
        typeNameInput.addEventListener('input', updateAssignmentDropdowns);
        const tusInput = col.querySelector(`#tus-${apartmentTypeCounter}`);
        tusInput.addEventListener('input', () => updateTuLengthFields(apartmentTypeCounter));
        
        updateTuLengthFields(apartmentTypeCounter);
        updateAssignmentDropdowns();
        initTooltips();
    };

    window.addApartmentRule = function(assignmentId) {
        const container = document.getElementById(`assignment-rules-container-${assignmentId}`);
        const ruleBlock = document.createElement('div');
        ruleBlock.className = 'assignment-rule-block animate__animated animate__fadeIn shadow-sm';
        ruleBlock.innerHTML = `
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="small fw-bold mb-1 text-muted">${__('apt_model')}</label>
                    <select class="form-select form-select-sm border-info-subtle shadow-sm" required title="${__('choose_model_title')}"></select>
                </div>
                <div class="col-md-6">
                    <label class="small fw-bold mb-1 text-muted" title="${__('tooltip_apt_numbers')}">${__('applicable_apts')}</label>
                    <div class="input-group input-group-sm shadow-sm">
                        <input type="text" class="form-control range-input" data-range-type="apto" placeholder="${__('apts_placeholder')}" required>
                        <button class="btn btn-outline-secondary preset-btn" type="button" title="${__('use_all_apts')}">${__('all_btn')}</button>
                    </div>
                    <div class="range-preview"></div>
                </div>
                <div class="col-md-2 text-end pb-1">
                    <button type="button" class="btn btn-outline-danger btn-sm border-0" onclick="this.closest('.assignment-rule-block').remove()" title="${__('delete_rule')}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>`;
        container.appendChild(ruleBlock);
        updateAssignmentDropdowns();

        const input = ruleBlock.querySelector('.range-input');
        input.addEventListener('input', () => updateRangePreview(input));
        ruleBlock.querySelector('.preset-btn').addEventListener('click', () => {
            const aptsPiso = document.getElementById('general-Apartamentos_Piso').value;
            input.value = `1-${aptsPiso}`;
            updateRangePreview(input);
        });
        updateRangePreview(input);
        initTooltips();
    };

    window.addAssignment = function() {
        assignmentCounter++;
        const container = document.getElementById('assignments-container');
        const block = document.createElement('div');
        block.className = 'template-block assignment-card assignment-block animate__animated animate__fadeInUp shadow-sm';
        block.id = `assignment-${assignmentCounter}`;
        block.innerHTML = `
            <button type="button" class="btn-close btn-close-custom" onclick="this.closest('.assignment-block').remove()" title="${__('delete_range')}"></button>
            <div class="row mb-3">
                <div class="col-md-8">
                    <label class="fw-bold text-info mb-1"><i class="fas fa-layer-group me-1"></i>${__('floor_range')}</label>
                    <div class="input-group input-group-sm shadow-sm">
                        <input type="text" class="form-control range-input" data-range-type="piso" id="floors-${assignmentCounter}" placeholder="${__('range_placeholder')}" required title="${__('tooltip_floor_range')}">
                        <button class="btn btn-outline-info preset-btn" type="button" title="${__('use_all_floors')}">${__('all_btn')}</button>
                    </div>
                    <div class="range-preview"></div>
                </div>
            </div>
            <div id="assignment-rules-container-${assignmentCounter}"></div>
            <div class="text-center mt-2 border-top pt-3">
                <button type="button" class="btn btn-outline-info btn-sm px-4 rounded-pill shadow-sm fw-bold" onclick="addApartmentRule(${assignmentCounter})" title="${__('variation_btn_title')}">
                    <i class="fas fa-plus me-1"></i>${__('variation_btn')}
                </button>
            </div>`;
        container.appendChild(block);
        
        const input = block.querySelector('.range-input');
        input.addEventListener('input', () => updateRangePreview(input));
        block.querySelector('.preset-btn').addEventListener('click', () => {
            const pisoMax = document.getElementById('general-Piso_Maximo').value;
            input.value = `1-${pisoMax}`;
            updateRangePreview(input);
        });

        addApartmentRule(assignmentCounter);
        updateRangePreview(input);
        initTooltips();
    };

    document.getElementById('add-apartment-type-btn').addEventListener('click', addApartmentType);
    document.getElementById('add-assignment-btn').addEventListener('click', addAssignment);

    // Initial setup
    addApartmentType();
    addAssignment();
    initTooltips();

    // Form submission
    document.getElementById('template-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const genBtn = document.getElementById('generate-db-btn'); 
        
        const projectName = document.getElementById('template-project-name').value.trim();
        if (!projectName) {
            showMessage(__('project_name_error'), true);
            return;
        }

        genBtn.disabled = true;
        const originalText = genBtn.innerHTML;
        genBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + __('loading');

        const generalParams = {};
        document.querySelectorAll('#template-form input[id^="general-"]').forEach(input => {
            generalParams[input.id.replace('general-', '')] = parseFloat(input.value);
        });

        const apartmentTypesData = [];
        document.querySelectorAll('.apartment-type-block').forEach(block => {
            const typeId = block.id.replace('apartment-type-', '');
            const typeName = document.getElementById(`type-name-${typeId}`).value;
            const tuLengths = [];
            const numTus = parseInt(document.getElementById(`tus-${typeId}`).value || 0);
            for (let i = 1; i <= numTus; i++) { 
                const val = parseFloat(document.getElementById(`len-tu-${typeId}-${i}`).value || 5);
                tuLengths.push(val); 
            }
            apartmentTypesData.push({ 
                type_name: typeName, 
                tomas_count: numTus, 
                len_deriv_repart: parseFloat(document.getElementById(`len-deriv-${typeId}`).value), 
                len_tu_cables: tuLengths 
            });
        });

        const assignmentsData = [];
        document.querySelectorAll('.assignment-block').forEach(block => {
            const assignId = block.id.replace('assignment-', '');
            const rules = [];
            block.querySelectorAll('.assignment-rule-block').forEach(rule => {
                const select = rule.querySelector('select');
                const aptInput = rule.querySelector('input');
                if (select.value && aptInput.value) {
                    rules.push({
                        type_name: select.value,
                        apartments: aptInput.value
                    });
                }
            });
            const floorsInput = document.getElementById(`floors-${assignId}`);
            if (floorsInput.value && rules.length > 0) {
                assignmentsData.push({ floors: floorsInput.value, rules: rules });
            }
        });

        if (assignmentsData.length === 0) {
            showMessage(__('assignment_error'), true);
            genBtn.disabled = false;
            genBtn.innerHTML = originalText;
            return;
        }

        const templateData = {
            project_name: projectName,
            general_parameters: generalParams,
            apartment_types: apartmentTypesData,
            assignments: assignmentsData
        };
        
        try {
            const response = await fetch('api/template/generate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(templateData)
            });
            const result = await response.json();
            if (!result.success) throw new Error(result.error?.message || 'Error');
            
            showMessage(__('success_redirecting'));
            setTimeout(() => {
                window.location.href = `enter-data/${result.data.dataset_id}`;
            }, 1500);
        } catch (error) { 
            showMessage(`${__('error_prefix')}${error.message}`, true); 
            genBtn.disabled = false;
            genBtn.innerHTML = originalText;
        } 
    });
});
</script>

<?php include __DIR__ . '/templates/header.php'; ?>
<?php include __DIR__ . '/templates/footer.php'; ?>

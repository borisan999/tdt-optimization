<?php
/**
 * Interactive Tree Visualization for TDT Optimization Results
 * ---------------------------------------------------------
 * Uses vis-network to display the network topology.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../app/auth/require_login.php';
require_once __DIR__ . '/../app/controllers/ResultsController.php';

use app\controllers\ResultsController;

$opt_id = intval($_GET['opt_id'] ?? 0);
if (!$opt_id) {
    die("Missing optimization ID.");
}

$controller = new ResultsController($opt_id);
$response = $controller->execute();

if (($response['status'] ?? 'error') !== 'success') {
    die("Error loading result: " . ($response['message'] ?? 'Unknown error'));
}

/** @var \app\viewmodels\ResultViewModel $viewModel */
$viewModel = $response['viewModel'];

// Colors
$C_CABECERA = "#E74C3C";
$C_TRONCAL  = "#8C8C8C";
$C_BLOQUE   = "#8E44AD";
$C_PISO     = "#2980B9";
$C_APTO     = "#F39C12";
$C_OK       = "#27AE60";
$C_BAD      = "#C0392B";

$hierarchy_map = [];

function add_child(&$hierarchy_map, $parent_id, $child_node, $edge_data) {
    if (!isset($hierarchy_map[$parent_id])) {
        $hierarchy_map[$parent_id] = [];
    }
    $hierarchy_map[$parent_id][] = [
        'node' => $child_node,
        'edge' => $edge_data
    ];
}

// Prepare data
$details = $viewModel->details;
if (empty($details)) {
    die("No details found for this result.");
}

$inputs = $viewModel->inputs;
$specs_derivadores = $inputs['derivadores_data'] ?? [];
$specs_repartidores = $inputs['repartidores_data'] ?? [];

// Sort details by Bloque, Piso (desc), Apto, TU
usort($details, function($a, $b) {
    $a_bloque = $a['bloque'] ?? 0;
    $b_bloque = $b['bloque'] ?? 0;
    if ($a_bloque !== $b_bloque) return $a_bloque <=> $b_bloque;
    
    $a_piso = $a['piso'] ?? 0;
    $b_piso = $b['piso'] ?? 0;
    if ($a_piso !== $b_piso) return $b_piso <=> $a_piso;
    
    $a_apto = $a['apto'] ?? 0;
    $b_apto = $b['apto'] ?? 0;
    if ($a_apto !== $b_apto) return $a_apto <=> $b_apto;
    
    return ($a['tu_id'] ?? '') <=> ($b['tu_id'] ?? '');
});

$row_ref = $details[0];
$max_piso = 0;
foreach($details as $d) {
    $p = $d['piso'] ?? 0;
    if ($p > $max_piso) $max_piso = $p;
}

// NIVEL 0: CABECERA
$potencia_cabecera = $inputs['potencia_entrada'] ?? 0;
$id_cabecera = "Cabecera_Master";
$node_cabecera = [
    "id" => $id_cabecera,
    "label" => "CABECERA\n(Piso $max_piso)\n$potencia_cabecera dBµV",
    "title" => "Origen Señal TDT",
    "color" => $C_CABECERA,
    "level" => 0,
    "shape" => "diamond",
    "size" => 55,
    "font" => ["color" => "black", "face" => "arial", "size" => 12, "vadjust" => -75]
];

// NIVEL 1: TRONCAL
$id_troncal = "Troncal";
$piso_troncal = $inputs['p_troncal'] ?? 0;

$original_row = $viewModel->results;
$decoded_detail = json_decode($original_row['detail_json'] ?? '[]', true);
$first_tu_raw = $decoded_detail[0] ?? [];

$modelo_rep_troncal = $first_tu_raw['Repartidor Troncal'] ?? 'N/A';
$spec_troncal = $specs_repartidores[$modelo_rep_troncal] ?? ['perdida_insercion' => '?'];

$title_troncal = "Repartidor Troncal, Modelo: $modelo_rep_troncal, Pérdida Inserción: " . ($spec_troncal['perdida_insercion'] ?? '?') . " dB";
$len_ant = $first_tu_raw['Longitud Antena→Troncal (m)'] ?? $inputs['largo_cable_amplificador_ultimo_piso'] ?? 0;

$loss_ant_total = ($first_tu_raw['Pérdida Antena→Troncal (cable) (dB)'] ?? 0) + ($first_tu_raw['Pérdida Antena↔Troncal (conectores) (dB)'] ?? 0);

$node_troncal = [
    "id" => $id_troncal,
    "label" => "TRONCAL\n(Piso $piso_troncal)",
    "title" => $title_troncal,
    "color" => $C_TRONCAL,
    "level" => 1,
    "shape" => "box",
    "font" => ["color" => "black"]
];
add_child($hierarchy_map, $id_cabecera, $node_troncal, [
    "from" => $id_cabecera,
    "to" => $id_troncal,
    "label" => $len_ant . "m\n-" . number_format((float)$loss_ant_total, 1) . "dB"
]);

// NIVEL 2: BLOQUES
$mapa_bloques = [];
$bloques_seen = [];
foreach($decoded_detail as $row) {
    $b_id = $row['Bloque'] ?? $row['bloque'] ?? null;
    if ($b_id === null || in_array($b_id, $bloques_seen)) continue;
    $bloques_seen[] = $b_id;
    
    $piso_in = $row['Piso Entrada Riser Bloque'] ?? '??';
    $len_feed = $row['Feeder Troncal→Entrada Bloque (m)'] ?? 0;
    $loss_feed_total = ($row['Pérdida Feeder (cable) (dB)'] ?? 0) + ($row['Pérdida Feeder (conectores) (dB)'] ?? 0);
    $id_bloque = "Bloque_$b_id";
    
    $node_bloque = [
        "id" => $id_bloque,
        "label" => "BLOQUE $b_id\nPiso $piso_in",
        "title" => "Entrada Riser: P$piso_in",
        "color" => $C_BLOQUE,
        "level" => 2,
        "shape" => "box",
        "font" => ["color" => "black"]
    ];
    add_child($hierarchy_map, $id_troncal, $node_bloque, [
        "from" => $id_troncal,
        "to" => $id_bloque,
        "label" => $len_feed . "m\n-" . number_format((float)$loss_feed_total, 1) . "dB"
    ]);
    $mapa_bloques[$b_id] = $id_bloque;
}

// NIVELES 3, 4, 5
$pisos_creados = [];
$aptos_creados = [];

foreach($decoded_detail as $row) {
    $codigo = $row['Toma'] ?? $row['tu_id'] ?? 'unknown';
    $bloque = $row['Bloque'] ?? $row['bloque'] ?? 0;
    $piso = $row['Piso'] ?? $row['piso'] ?? 0;
    $apto = $row['Apto'] ?? $row['apto'] ?? 0;
    $toma_num = "";
    if (preg_match('/TU(\d+)/', $codigo, $m)) {
        $toma_num = $m[1];
    } else {
        $toma_num = $codigo;
    }

    $id_bloque_parent = $mapa_bloques[$bloque] ?? null;
    if (!$id_bloque_parent) continue;

    $id_piso = "B{$bloque}_P{$piso}";
    $id_apto = "B{$bloque}_P{$piso}_A{$apto}";
    $id_toma = $codigo;

    // Distances and losses
    $d_riser = $row['Distancia riser dentro bloque (m)'] ?? 0;
    $l_riser = $row['Pérdida Riser dentro del Bloque (dB)'] ?? 0;
    $d_total = $row['Distancia total hasta la toma (m)'] ?? 0;
    
    $d_upstream = ($row['Longitud Antena→Troncal (m)'] ?? 0) + ($row['Feeder Troncal→Entrada Bloque (m)'] ?? 0) + $d_riser;
    $d_remaining = max(0, $d_total - $d_upstream);
    
    $l_piso_apto = $row['Pérdida Cable Deriv→Rep (dB)'] ?? 0;
    $l_apto_toma = $row['Pérdida Cable Rep→TU (dB)'] ?? 0;
    
    $total_local_loss = $l_piso_apto + $l_apto_toma;
    $d_piso_apto = $total_local_loss > 0 ? ($l_piso_apto / $total_local_loss) * $d_remaining : $d_remaining / 2;
    $d_apto_toma = $total_local_loss > 0 ? ($l_apto_toma / $total_local_loss) * $d_remaining : $d_remaining / 2;

    // NODO PISO (DERIVADOR)
    if (!isset($pisos_creados[$id_piso])) {
        $modelo_deriv = $row['Derivador Piso'] ?? 'N/A';
        $spec_deriv = $specs_derivadores[$modelo_deriv] ?? ['derivacion' => '?', 'paso' => '?'];
        $title_piso = "Piso $piso, Derivador: $modelo_deriv, Pérdida Paso: " . ($spec_deriv['paso'] ?? '?') . " dB, Pérdida Derivación: " . ($spec_deriv['derivacion'] ?? '?') . " dB";
        
        $node_piso = [
            "id" => $id_piso,
            "label" => "Piso $piso",
            "title" => $title_piso,
            "color" => $C_PISO,
            "level" => 3,
            "shape" => "ellipse",
            "font" => ["color" => "black"]
        ];
        add_child($hierarchy_map, $id_bloque_parent, $node_piso, [
            "from" => $id_bloque_parent,
            "to" => $id_piso,
            "label" => $d_riser . "m\n-" . number_format((float)$l_riser, 1) . "dB"
        ]);
        $pisos_creados[$id_piso] = true;
    }

    // NODO APTO (REPARTIDOR)
    if (!isset($aptos_creados[$id_apto])) {
        $modelo_rep_apt = $row['Repartidor Apt'] ?? 'N/A';
        $spec_rep_apt = $specs_repartidores[$modelo_rep_apt] ?? ['perdida_insercion' => '?'];
        $title_apto = "Apto $apto, Repartidor: $modelo_rep_apt, Pérdida Inserción: " . ($spec_rep_apt['perdida_insercion'] ?? '?') . " dB";
        
        $node_apto = [
            "id" => $id_apto,
            "label" => "Apto $apto",
            "title" => $title_apto,
            "color" => $C_APTO,
            "level" => 4,
            "shape" => "dot",
            "size" => 15
        ];
        $l_piso_apto_total = $l_piso_apto + (2 * 0.2); // Add connector loss
        add_child($hierarchy_map, $id_piso, $node_apto, [
            "from" => $id_piso,
            "to" => $id_apto,
            "label" => number_format((float)$d_piso_apto, 1) . "m\n-" . number_format((float)$l_piso_apto_total, 1) . "dB"
        ]);
        $aptos_creados[$id_apto] = true;
    }

    // NODO TOMA
    $nivel = $row['Nivel TU Final (dBµV)'] ?? 0;
    $min_n = $inputs['Nivel_minimo'] ?? 47;
    $max_n = $inputs['Nivel_maximo'] ?? 77;
    $color_toma = ($nivel >= $min_n && $nivel <= $max_n) ? $C_OK : $C_BAD;
    
    $node_toma = [
        "id" => $id_toma,
        "label" => "TU$toma_num\n" . number_format((float)$nivel, 1) . "dB",
        "title" => "Toma: $codigo, Pérdida TU: 1 dBµV",
        "color" => $color_toma,
        "level" => 5,
        "shape" => "dot",
        "size" => 10
    ];
    $l_apto_toma_total = $l_apto_toma + (2 * 0.2); // Add connector loss
    add_child($hierarchy_map, $id_apto, $node_toma, [
        "from" => $id_apto,
        "to" => $id_toma,
        "label" => number_format((float)$d_apto_toma, 1) . "m\n-" . number_format((float)$l_apto_toma_total, 1) . "dB"
    ]);
}

// Initial nodes/edges for the view
$initial_nodes = [$node_cabecera];
$initial_edges = [];
if (isset($hierarchy_map[$id_cabecera])) {
    foreach ($hierarchy_map[$id_cabecera] as $child) { // Troncal
        $initial_nodes[] = $child['node'];
        $initial_edges[] = $child['edge'];
        if (isset($hierarchy_map[$child['node']['id']])) {
            foreach ($hierarchy_map[$child['node']['id']] as $b) { // Bloques
                $initial_nodes[] = $b['node'];
                $initial_edges[] = $b['edge'];
            }
        }
    }
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/navbar.php';
?>

<script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
<style>
    #mynetwork { width: 100%; height: 75vh; background-color: #ffffff; border: 1px solid #ddd; border-radius: 8px; }
    .controls { position: absolute; bottom: 80px; right: 30px; display: flex; flex-direction: column; gap: 8px; z-index: 999; }
    .btn-circle { width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
    .legend-box { position: absolute; top: 100px; left: 40px; background: rgba(255, 255, 255, 0.95); padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; box-shadow: 0 4px 15px rgba(0,0,0,0.1); font-size: 13px; pointer-events: none; color: #333; z-index: 1000; }
    .dot-legend { height: 12px; width: 12px; border-radius: 50%; display: inline-block; margin-right: 8px; border: 1px solid rgba(0,0,0,0.1); }
    .line-sample { display: inline-block; width: 30px; height: 2px; background: #aaa; vertical-align: middle; margin-right: 5px; }
    .tree-container { position: relative; }
</style>

<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-3 px-3">
        <div>
            <h2 class="mb-0 text-primary">Topología de Red Interactiva</h2>
            <div class="text-muted">Resultados de Optimización #<?= $opt_id ?></div>
        </div>
        <a href="view-result/<?= $opt_id ?>" class="btn btn-secondary shadow-sm">
            <i class="fas fa-arrow-left"></i> Volver a Resultados
        </a>
    </div>

    <div class="tree-container">
        <!-- Leyenda de colores -->
        <div class="legend-box">
            <h5 class="mb-3" style="color:#2C3E50;">Simbología</h5>
            <div class="mb-2"><span class="dot-legend" style="background:<?= $C_CABECERA ?>"></span> Cabecera</div>
            <div class="mb-2"><span class="dot-legend" style="background:<?= $C_TRONCAL ?>"></span> Troncal</div>
            <div class="mb-2"><span class="dot-legend" style="background:<?= $C_BLOQUE ?>"></span> Bloque</div>
            <div class="mb-2"><span class="dot-legend" style="background:<?= $C_PISO ?>"></span> Piso (Derivador)</div>
            <div class="mb-2"><span class="dot-legend" style="background:<?= $C_APTO ?>"></span> Apto (Repartidor)</div>
            <div class="mb-2"><span class="dot-legend" style="background:<?= $C_OK ?>"></span> Toma OK</div>
            <div class="mb-2"><span class="dot-legend" style="background:<?= $C_BAD ?>"></span> Fuera de Norma</div>
            <div class="mt-3 pt-2 border-top">
                <span class="line-sample"></span> <b>Etiqueta:</b><br/>Distancia (m) | Pérdida (dB)
            </div>
            <div class="mt-2 small text-muted">
                <i class="fas fa-mouse-pointer"></i> Clic para expandir hijos.
            </div>
        </div>

        <!-- Contenedor para la red -->
        <div id="mynetwork"></div>

        <!-- Controles de zoom y ajuste -->
        <div class="controls">
            <button class="btn btn-light btn-circle" onclick="zoomIn()" title="Acercar"><i class="fas fa-plus"></i></button>
            <button class="btn btn-light btn-circle" onclick="zoomOut()" title="Alejar"><i class="fas fa-minus"></i></button>
            <button class="btn btn-primary btn-circle" onclick="fitAnimated()" title="Ajuste Automático"><i class="fas fa-expand"></i></button>
        </div>
    </div>
</div>

<script type="text/javascript">
    // --- INICIALIZACIÓN DE DATOS PARA VIS.JS ---
    var nodesArray = <?= json_encode($initial_nodes) ?>;
    var edgesArray = <?= json_encode($initial_edges) ?>;
    var hierarchyMap = <?= json_encode($hierarchy_map) ?>;
    
    // Creación de los DataSets de vis.js
    var nodes = new vis.DataSet(nodesArray);
    var edges = new vis.DataSet(edgesArray);
    
    var container = document.getElementById('mynetwork');
    var data = { nodes: nodes, edges: edges };
    
    // --- OPCIONES DE CONFIGURACIÓN DE LA RED ---
    var options = {
        layout: {
            hierarchical: {
                direction: "UD", // Dirección de arriba hacia abajo
                sortMethod: "directed",
                levelSeparation: 170,
                nodeSpacing: 180,
                treeSpacing: 250,
            }
        },
        physics: { // Opciones de físicas para la estabilización inicial
            hierarchicalRepulsion: {
                nodeDistance: 180,
                springLength: 120,
                damping: 0.2
            },
            stabilization: { iterations: 250 }
        },
        interaction: { // Interactividad del usuario
            hover: true,
            tooltipDelay: 200,
            zoomView: true
        },
        edges: { // Estilo de las conexiones
            arrows: 'to',
            smooth: { type: 'cubicBezier', forceDirection: 'vertical', roundness: 0.5 },
            font: { color: '#333', size: 10, face: 'arial', background: '#ffffff', strokeWidth: 0, align: 'horizontal', multi: 'html' },
            color: { color: '#aaa', highlight: '#555' }
        },
        nodes: { // Estilo de los nodos
            borderWidth: 1,
            shadow: true,
            font: { color: 'black' }
        }
    };
    
    // Creación de la red
    var network = new vis.Network(container, data, options);
    
    // --- FUNCIONES DE INTERACTIVIDAD ---
    function zoomIn() { network.moveTo({ scale: network.getScale() + 0.3, animation: true }); }
    function zoomOut() { network.moveTo({ scale: network.getScale() - 0.3, animation: true }); }
    function fitAnimated() { network.fit({ animation: { duration: 800, easingFunction: 'easeInOutQuad' } }); }
    
    // Evento de clic en un nodo para expandir o colapsar
    network.on("click", function (params) {
        if (params.nodes.length === 0) return; // Si no se hace clic en un nodo, no hacer nada.
        var nodeId = params.nodes[0];
        if (hierarchyMap.hasOwnProperty(nodeId)) {
            var children = hierarchyMap[nodeId];
            if (children.length === 0) return;
            
            // Comprobar si el nodo está expandido o colapsado.
            var firstChildId = children[0].node.id;
            var isExpanded = nodes.get(firstChildId);
            
            if (isExpanded) {
                collapseNode(nodeId);
            } else {
                expandNode(nodeId);
            }
        }
    });
    
    // Función para añadir los hijos de un nodo al grafo.
    function expandNode(parentId) {
        if (!hierarchyMap[parentId]) return;
        var newNodes = []; 
        var newEdges = [];
        hierarchyMap[parentId].forEach(function(child) { 
            if (!nodes.get(child.node.id)) {
                newNodes.push(child.node); 
            }
            var edgeId = child.edge.from + "_" + child.edge.to;
            if (!edges.get(edgeId)) {
                child.edge.id = edgeId;
                newEdges.push(child.edge);
            }
        });
        nodes.add(newNodes); 
        edges.add(newEdges);
    }
    
    // Función para eliminar todos los descendientes de un nodo del grafo.
    function collapseNode(parentId) {
        var descendants = getAllDescendants(parentId);
        nodes.remove(descendants);
        var edgesToRemove = edges.get({
            filter: function(edge) {
                return descendants.indexOf(edge.to) !== -1;
            }
        }).map(e => e.id);
        edges.remove(edgesToRemove);
    }
    
    function getAllDescendants(parentId) {
        var found = [];
        if (hierarchyMap[parentId]) {
            hierarchyMap[parentId].forEach(function(child) {
                var childId = child.node.id;
                if (nodes.get(childId)) {
                    found.push(childId);
                    found = found.concat(getAllDescendants(childId));
                }
            });
        }
        return found;
    }

    network.once("stabilizationIterationsDone", function() {
        fitAnimated();
    });
</script>

<?php include __DIR__ . '/templates/footer.php'; ?>

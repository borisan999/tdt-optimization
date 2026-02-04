<?php
require_once __DIR__ . '/../models/RepartidorModel.php';
if (!class_exists('RepartidorModel')) {
    die('RepartidorModel NOT LOADED');
}
class RepartidorController
{
    private $model;

    public function __construct()
    {
        
        $this->model = new RepartidorModel();
    }

    public function index()
    {
        $repartidores = $this->model->getAll();
        require __DIR__ . '/../../public/templates/repartidores_list.php';
    }

    public function edit(int $id)
    {
        if ($id <= 0) {
            die('ID inválido');
        }

        $repartidor = $this->model->getById($id);
        require __DIR__ . '/../../public/templates/repartidores_form.php';
    }

    public function delete(int $id)
    {
        if ($id > 0) {
            $this->model->delete($id);
        }

        header('Location: repartidores.php');
        exit;
    }

    public function create()
    {
        require __DIR__ . '/../../public/templates/repartidores_form.php';
    }

    public function save()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            die('Método no permitido');
        }

        $data = [
            'rep_id'            => isset($_POST['rep_id']) ? (int)$_POST['rep_id'] : null,
            'modelo'            => trim($_POST['modelo'] ?? ''),
            'salidas'           => (int)($_POST['salidas'] ?? 0),
            'perdida_insercion' => (float)($_POST['perdida_insercion'] ?? 0),
            'frecuencia'        => trim($_POST['frecuencia'] ?? ''),
            'descripcion'       => trim($_POST['descripcion'] ?? ''),
        ];

        // basic validation (engineering-safe)
        if ($data['modelo'] === '' || $data['salidas'] <= 0) {
            die('Datos inválidos');
        }
        try {
            if ($data['rep_id']) {
                $this->model->update($data);
            } else {
                $this->model->insert($data);
            }
        } catch (PDOException $e) {
            die("DB ERROR: " . $e->getMessage());
        }

        header('Location: repartidores.php');
        exit;
    }

}

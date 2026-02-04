<?php
require_once __DIR__ . '/../models/DerivadorModel.php';

class DerivadorController
{
    private $model;

    public function __construct()
    {
        $this->model = new DerivadorModel();
    }

    public function index()
    {
        $derivadores = $this->model->getAll();
        require __DIR__ . '/../../public/templates/derivadores_list.php';
    }

    public function create()
    {
        require __DIR__ . '/../../public/templates/derivadores_form.php';
    }

    public function edit(int $id)
    {
        $derivador = $this->model->getById($id);
        require __DIR__ . '/../../public/templates/derivadores_form.php';
    }

    public function save()
    {
        $d = [
            'deriv_id'         => $_POST['deriv_id'] ?? null,
            'modelo'           => trim($_POST['modelo']),
            'derivacion'       => (float)$_POST['derivacion'],
            'paso'             => (float)$_POST['paso'],
            'salidas'          => (int)$_POST['salidas'],
            'perdida_insercion'=> (float)$_POST['perdida_insercion'],
            'descripcion'      => trim($_POST['descripcion']),
        ];

        if ($d['deriv_id']) {
            $this->model->update($d);
        } else {
            $this->model->insert($d);
        }

        header('Location: derivadores.php');
        exit;
    }

    public function delete(int $id)
    {
        $this->model->delete($id);
        header('Location: derivadores.php');
        exit;
    }
}

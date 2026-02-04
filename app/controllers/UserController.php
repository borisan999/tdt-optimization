<?php
require_once __DIR__ . '/../models/UserModel.php';

class UserController
{
    private $model;

    public function __construct()
    {
        $this->model = new UserModel();
    }

    public function index()
    {
        $users = $this->model->getAll();
        require __DIR__ . '/../../public/templates/users_list.php';
    }

    public function create()
    {
        $user = null;
        require __DIR__ . '/../../public/templates/users_form.php';
    }

    public function edit(int $id)
    {
        $user = $this->model->getById($id);
        require __DIR__ . '/../../public/templates/users_form.php';
    }

   /* public function save()
    {
        $data = [
            'user_id'  => $_POST['user_id'] ?? null,
            'username' => trim($_POST['username']),
            'password' => $_POST['password'],
            'role'     => $_POST['role'] ?? 'admin',
        ];

        if ($data['user_id']) {
            $this->model->update($data);
        } else {
            $this->model->insert($data);
        }

        header('Location: users.php');
        exit;
    }*/
        /*authoritative version*/
    public function save(): void
    {
        $data = [
            'user_id'  => $_POST['user_id'] ?? null,
            'username' => trim($_POST['username']),
            'email'    => $_POST['email'] ?? null,
            'password' => $_POST['password'] ?? null,
            'is_active'=> $_POST['is_active'] ?? 1,
        ];
        $mode = $_POST['mode'] ?? 'create';
        // 1️⃣ Check username first (reactivation logic)
        $existing = $this->model->findByUsername($data['username']);

        if ($existing) {
            // Reactivate if inactive
            if ((int)$existing['is_active'] === 0) {
                $this->model->reactivate((int)$existing['user_id'], $data);
                header('Location: users.php?reactivated=1');
                exit;
            }

            // Active username conflict (and not editing same user)
            if (empty($data['user_id']) || (int)$data['user_id'] !== (int)$existing['user_id']) {
                header('Location: users.php?error=username_exists');
                exit;
            }
        }

        // 2️⃣ Update existing active user (edit form)
        if (!empty($data['user_id'])) {
            $this->model->update($data);
            header('Location: users.php');
            exit;
        }

        // 3️⃣ Insert brand-new user
        $this->model->insert($data);
        header('Location: users.php');
        exit;
    }



    public function delete(int $id)
    {
        $this->model->delete($id);
        header('Location: users.php');
        exit;
    }

    public function disable(int $id): void
    {
        if ($id === (int)$_SESSION['user_id']) {
            // Optional: flash message later
            header('Location: users.php?error=self_disable');
            exit;
        }

        $this->model->disable($id);
        header('Location: users.php');
        exit;
    }

}

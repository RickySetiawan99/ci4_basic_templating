<?php

namespace App\Controllers\UserManagement;

use App\Controllers\BaseController;
use App\Models\UserModel;
use Myth\Auth\Entities\User;
use Myth\Auth\Models\GroupModel;
use Myth\Auth\Models\PermissionModel;

class UserController extends BaseController
{
    private $title      = 'User Management | User';
    private $route      = 'user-management/users'; //path awal foldernya ajah (misal folder di admin/dashboard) => 'admin.dashboard'
    private $namespace  = 'user_management/users/';
    private $header     = 'User';
    private $sub_header = 'User';
    private $modelName  = UserModel::class;

    protected $model;

    public function __construct()
    {
        $this->model = new $this->modelName;
    }

    public function index()
    {
        $data = [
            'title'         => $this->title,
            'route'         => $this->route,
            'header'        => $this->header,
            'sub_header'    => $this->sub_header,
        ];
        
        return view($this->namespace.'index', $data);
    }

    public function fetchData()
    {
        $request = service('request');
        $userModel = $this->model;

        // Ambil parameter dari inputan filter (misal: nama, email)
        $name       = $request->getPost('name');
        $email      = $request->getPost('email');
        $start      = (int) $request->getPost('start');
        $length     = (int) $request->getPost('length');
        $draw       = $request->getPost('draw');

        $users = $userModel->select('id, username, email, created_at');

        $totalRecords = $users->countAllResults(false);

        if (!empty($name)) {
            $users->like('username', $name);
        }

        if (!empty($email)) {
            $users->like('email', $email);
        }

        $totalFiltered = $users->countAllResults(false);

        $data = $users->limit($length, $start)->get()->getResultArray();

        $formattedData = [];
        foreach ($data as $key => $value) {
            // Tambahkan nomor urut (index + 1 + $start) untuk memperhitungkan pagination
            $btnEdit    = '<a href="' . site_url($this->route.'/edit/' . encode_id($value['id'])) . '" class="btn btn-md btn-primary mx-1" data-bs-toggle="tooltip" title="Edit"><i class="fas fa-pencil-alt"></i></a>';
            $btnDelete  = '<a href="javascript:;" data-route="' . site_url($this->route.'/destroy/' . encode_id($value['id'])) . '" class="btn btn-delete btn-md btn-danger mx-1" data-bs-toggle="tooltip" title="Delete" data-container="body" data-animation="true"><i class="fas fa-trash"></i></a>';
            
            $formattedData[] = [
                'no'            => $start + $key + 1, // Nomor urut
                'username'      => $value['username'],
                'email'         => $value['email'],
                'created_at'    => $value['created_at'],
                'action'        => $btnEdit.$btnDelete
            ];
        }

        $jsonData = [
            "draw"              => intval($draw),
            "recordsTotal"      => $totalRecords,
            "recordsFiltered"   => $totalFiltered,
            "data"              => $formattedData
        ];

        return $this->response->setJSON($jsonData);
    }

    public function create()
    {
        $data = [
            'title'         => $this->title,
            'route'         => $this->route,
            'header'        => $this->header,
            'sub_header'    => $this->sub_header,
            'route_back'    => base_url($this->route),
            'permissions'   => $this->authorize->permissions(),
            'roles'         => $this->authorize->groups(),
        ];

        return view($this->namespace.'create', $data);
    }

    public function store()
    {
        $validationRules = [
            'active'       => 'required',
            'username'     => 'required|alpha_numeric_space|min_length[3]|is_unique[users.username]',
            'email'        => 'required|valid_email|is_unique[users.email]',
            'password'     => 'required',
            'pass_confirm' => 'required|matches[password]',
            'permission'   => 'required',
            'role'         => 'required',
        ];

        $permissions = $this->request->getPost('permission');
        $roles = $this->request->getPost('role');

        if (!$this->validate($validationRules)) {
            return redirect()->back()->withInput()->with('error', $this->validator->getErrors());
        }

        $this->db->transBegin();

        try {
            $id = $this->model->insert(new User([
                'active'   => $this->request->getPost('active'),
                'email'    => $this->request->getPost('email'),
                'username' => $this->request->getPost('username'),
                'password' => $this->request->getPost('password'),
            ]));

            foreach ($permissions as $permission) {
                $this->authorize->addPermissionToUser($permission, $id);
            }

            foreach ($roles as $role) {
                $this->authorize->addUserToGroup($id, $role);
            }

            $this->db->transCommit();
        } catch (\Exception $e) {
            $this->db->transRollback();

            $message = $e->getMessage();
            return redirect()->back()->with('error', parsingAlert($message));
        }

        $message = 'Data User saved succesfully';
        return redirect()->to(base_url($this->route))->with('success', parsingAlert($message));
    }

    public function edit($user_id)
    {
        $id = decode_id($user_id)[0];

        $data = [
            'title'         => $this->title,
            'route'         => $this->route,
            'header'        => $this->header,
            'sub_header'    => $this->sub_header,
            'route_back'    => base_url($this->route),
            'user'          => $this->model->find($id),
            'permissions'   => $this->authorize->permissions(),
            'permission'    => (new PermissionModel())->getPermissionsForUser($id),
            'roles'         => $this->authorize->groups(),
            'role'          => (new GroupModel())->getGroupsForUser($id),
        ];

        return view($this->namespace.'/update', $data);
    }

    public function update($user_id)
    {
        $id = decode_id($user_id)[0];

        // Fetch the current user data
        $currentUser = $this->model->find($id);

        // Prepare validation rules
        $validationRules = [
            'active'       => 'required',
            'username'     => [
                'rules'  => ($this->request->getPost('username') !== $currentUser->username) 
                            ? "required|alpha_numeric_space|min_length[3]|is_unique[users.username]" 
                            : "required|alpha_numeric_space|min_length[3]",
                'errors' => [
                    'is_unique' => 'The username is already taken.'
                ]
            ],
            'email'        => [
                'rules'  => ($this->request->getPost('email') !== $currentUser->email) 
                            ? "required|valid_email|is_unique[users.email]" 
                            : "required|valid_email",
                'errors' => [
                    'is_unique' => 'The email is already in use.'
                ]
            ],
            'password'     => 'if_exist',
            'pass_confirm' => 'matches[password]',
            'permission'   => 'required',
            'role'         => 'required',
        ];

        // Validate input data
        if (!$this->validate($validationRules)) {
            return redirect()->back()->withInput()->with('error', $this->validator->getErrors());
        }

        $this->db->transBegin();

        try {
            $user = new User();

            // Check if the password is set
            if ($this->request->getPost('password')) {
                $user->password = $this->request->getPost('password');
            }

            // Update user data
            $user->active = $this->request->getPost('active');
            $user->email = $this->request->getPost('email');
            $user->username = $this->request->getPost('username');

            $this->model->skipValidation(true)->update($id, $user);

            // Update permissions and roles
            $this->db->table('auth_users_permissions')->where('user_id', $id)->delete();
            foreach ($this->request->getPost('permission') as $permission) {
                $this->authorize->addPermissionToUser($permission, $id);
            }

            $this->db->table('auth_groups_users')->where('user_id', $id)->delete();
            foreach ($this->request->getPost('role') as $role) {
                $this->authorize->addUserToGroup($id, $role);
            }

            $this->db->transCommit();

        } catch (\Exception $e) {
            $this->db->transRollback();
            
            $message = $e->getMessage();
            return redirect()->back()->with('error', parsingAlert($message));
        }

        $message = 'Data User updated successfully';
        return redirect()->to(base_url($this->route))->with('success', parsingAlert($message));
    }

    public function destroy($user_id)
    {
        $id = decode_id($user_id);

        if ($this->model->delete($id)) {
            return $this->response->setJSON([
                'status'    => true,
                'message'   => 'User deleted successfully'
            ]);
        } else {
            return $this->response->setJSON([
                'status'    => false,
                'message'   => 'Failed to delete user'
            ]);
        }
    }
}

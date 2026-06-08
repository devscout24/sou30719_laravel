<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use Yajra\DataTables\DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Mail\WelcomeUserMail;
use Illuminate\Support\Facades\Mail;
use App\Models\CompanySetting;
use Illuminate\Support\Str;

use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function data(Request $request)
    {
        $query = User::query()->with('roles');

        // Filter by Role
        if ($request->role && $request->role != 'All') {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Filter by Status
        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        return DataTables::of($query)

            ->addIndexColumn()

            ->addColumn('role', function ($user) {
                return $user->getRoleNames()[0] ?? 'No Role';
            })

            ->addColumn('joined', function ($user) {
                return $user->created_at->format('d M Y');
            })

            ->addColumn('status_badge', function ($user) {

                if ($user->status == 'active') {
                    return '<span class="badge bg-success-subtle text-success">Active</span>';
                }

                return '<span class="badge bg-warning-subtle text-warning">Inactive</span>';
            })

            ->addColumn('action', function ($user) {

                return '
                <div class="d-flex justify-content-center gap-1">

                    <a href="' . route('admin.user.show', $user->id) . '"
                       class="btn btn-default btn-icon btn-sm">
                        <i class="ti ti-eye fs-lg"></i>
                    </a>

                    <a href="' . route('admin.user.edit', $user->id) . '"
                       class="btn btn-default btn-icon btn-sm">
                        <i class="ti ti-edit fs-lg"></i>
                    </a>

                </div>
            ';
            })

            ->rawColumns(['status_badge', 'action'])

            ->make(true);
    }

    // Index
    public function create()
    {
        $roles = Role::all();
        return view('backend.layouts.user_management.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'username' => 'nullable|unique:users,username',
            'phone'    => 'nullable|max:20',
            'password' => 'nullable|string|min:6|confirmed',
            'avatar'   => 'nullable|image|mimes:jpg,png,jpeg|max:2048',
            'role'     => 'required|exists:roles,name',
        ]);

        if ($validation->fails()) {
            return redirect()
                ->route('admin.user.create')
                ->with('error', $validation->errors()->first())
                ->withInput();
        }


        /* Upload Avatar */
        $avatar = 'user.png';

        if ($request->hasFile('avatar')) {

            $avatar = $this->uploadImage($request->file('avatar'), null, 'uploads/avatar', true, 150, 150, 'avatar_' . time());
        }

        $tempPassword = $request->password ? $request->password :  Str::random(8);

        /* Create User */
        $user = User::create([

            'name'     => $request->name,
            'email'    => $request->email,
            'username' => $request->username,
            'phone'    => $request->phone,

            'address'  => $request->address,
            'location' => $request->location,
            'password' => $request->password ? Hash::make($request->password) : Hash::make($tempPassword),
            'avatar'   => $avatar,
            'status'   => $request->status,
        ]);



        /* Assign Role */
        $user->assignRole($request->role);

        /* Get Company Info */
        $company = CompanySetting::first();


        /* Login URL Based On Role */
        $role = $request->role;

        if ($role == 'admin') {
            $loginUrl = url('/login');
        } elseif ($role == 'provider') {

            $loginUrl = url('/provider/login');
        } else {

            $loginUrl = url('/login');
        }


        /* Send Welcome Email */
        Mail::to($user->email)->send(
            new WelcomeUserMail(
                $user,
                $tempPassword,
                $company,
                $loginUrl
            )
        );




        return redirect()
            ->route('admin.user.lists')
            ->with('success', 'User created successfully');
    }

    // Index
    public function index()
    {
        $allUsers = User::all();
        return view('backend.layouts.user_management.index', compact('allUsers'));
    }

    // Show
    public function show($id)
    {
        $user = User::with('roles')->findOrFail($id);

        return view('backend.layouts.user_management.show', compact('user'));
    }

    public function edit($id)
    {
        $user = User::with('roles')->findOrFail($id);
        $roles = Role::all();

        return view('backend.layouts.user_management.edit', compact('user', 'roles'));
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);


        $validation = Validator::make($request->all(), [

            'name'     => 'required|max:100',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'username' => 'nullable|unique:users,username,' . $user->id,

            'phone'    => 'nullable|max:20',

            'avatar'   => 'nullable|image|max:2048',

            'role'     => 'required|exists:roles,name',

            'password' => 'nullable|min:6|confirmed',
        ]);


        if ($validation->fails()) {
            return back()
                ->with('error', $validation->errors()->first())
                ->withInput();
        }


        /* Upload Avatar */
        if ($request->hasFile('avatar')) {

            if ($user->avatar && file_exists(public_path($user->avatar))) {
                unlink(public_path($user->avatar));
            }

            $user->avatar = $this->uploadImage(
                $request->file('avatar'),
                null,
                'uploads/avatar',
                true,
                150,
                150,
                'avatar_' . time()
            );
        }


        /* Update Data */

        $user->name     = $request->name;
        $user->email    = $request->email;
        $user->username = $request->username;
        $user->phone    = $request->phone;

        $user->address  = $request->address;
        $user->location = $request->location;
        $user->status   = $request->status;


        /* Update Password (Optional) */

        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }


        $user->save();


        /* Update Role */

        $user->syncRoles([$request->role]);


        return redirect()
            ->route('admin.user.lists')
            ->with('success', 'User updated successfully');
    }
}

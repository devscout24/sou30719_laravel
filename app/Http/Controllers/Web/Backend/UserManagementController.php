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
use App\Models\SubscriptionPlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function data(Request $request)
    {
        $query = User::query()->with(['roles', 'activeSubscription.plan']);

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

        // Filter by Gender
        if ($request->gender && $request->gender != 'All') {
            $query->where('gender', $request->gender);
        }

        // Filter by Plan
        if ($request->plan && $request->plan != 'All') {
            if ($request->plan === 'free') {
                $query->whereDoesntHave('activeSubscription');
            } else {
                $query->whereHas('activeSubscription.plan', function ($q) use ($request) {
                    $q->where('slug', $request->plan);
                });
            }
        }

        return DataTables::of($query)

            ->addIndexColumn()

            ->addColumn('checkbox', function ($user) {
                return '<input type="checkbox" class="form-check-input row-checkbox" value="' . $user->id . '">';
            })

            ->addColumn('user_id', function ($user) {
                return '#' . $user->id;
            })

            ->addColumn('user_info', function ($user) {
                $avatar = asset($user->avatar && $user->avatar !== 'user.png' ? $user->avatar : 'admin.png');

                return '
                <div class="d-flex align-items-center gap-2">
                    <img src="' . $avatar . '" class="rounded-circle avatar-sm" alt="avatar">
                    <a href="' . route('admin.user.show', $user->id) . '" class="fw-semibold text-body">' . e($user->name ?? 'N/A') . '</a>
                </div>
            ';
            })

            ->addColumn('gender_label', function ($user) {
                return $user->gender ? ucfirst($user->gender) : '—';
            })

            ->addColumn('location_label', function ($user) {
                return $user->location ?: ($user->country ?: '—');
            })

            ->addColumn('plan', function ($user) {
                $plan = $user->activeSubscription->plan->name ?? 'Free';

                return '<span class="badge bg-primary-subtle text-primary badge-label">' . e($plan) . '</span>';
            })

            ->addColumn('verified_badge', function ($user) {
                return $user->email_verified_at
                    ? '<span class="badge bg-success-subtle text-success">Verified</span>'
                    : '<span class="badge bg-secondary-subtle text-secondary">Unverified</span>';
            })

            ->addColumn('status_badge', function ($user) {

                $map = [
                    'active'   => 'success',
                    'inactive' => 'warning',
                    'banned'   => 'danger',
                ];

                $color = $map[$user->status] ?? 'secondary';

                return '<span class="badge bg-' . $color . '-subtle text-' . $color . '">' . ucfirst($user->status) . '</span>';
            })

            ->addColumn('action', function ($user) {

                return '
                <div class="dropdown">
                    <a href="#" class="btn btn-default btn-icon btn-sm" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="ti ti-dots-vertical fs-lg"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="' . route('admin.user.show', $user->id) . '">
                                <i class="ti ti-eye fs-sm me-1 align-middle"></i> View
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="' . route('admin.user.edit', $user->id) . '">
                                <i class="ti ti-edit fs-sm me-1 align-middle"></i> Edit
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="' . route('admin.user.destroy', $user->id) . '" method="POST" class="delete-form">
                                ' . csrf_field() . method_field('DELETE') . '
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="ti ti-trash fs-sm me-1 align-middle"></i> Delete
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            ';
            })

            ->rawColumns(['checkbox', 'user_info', 'plan', 'verified_badge', 'status_badge', 'action'])

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
    public function index(Request $request)
    {
        $period = $request->get('period', 'daily');

        [$from, $to] = $this->resolvePeriodRange($period, $request->get('from'), $request->get('to'));

        $totalUsers       = User::count();
        $totalActiveUsers = User::where('status', 'active')->count();
        $totalNewUsers    = User::whereBetween('created_at', [$from, $to])->count();

        $plans = SubscriptionPlan::active()->orderBy('price')->get();

        return view('backend.layouts.user_management.index', compact(
            'totalUsers',
            'totalActiveUsers',
            'totalNewUsers',
            'period',
            'plans'
        ));
    }

    private function resolvePeriodRange(string $period, $from = null, $to = null): array
    {
        $now = Carbon::now();

        return match ($period) {
            'weekly'  => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'yearly'  => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'custom'  => [
                $from ? Carbon::parse($from)->startOfDay() : $now->copy()->startOfMonth(),
                $to ? Carbon::parse($to)->endOfDay() : $now->copy()->endOfDay(),
            ],
            default   => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };
    }

    // Show
    public function show($id)
    {
        $user = User::with([
            'roles',
            'datingProfile.images',
            'galleryImages',
            'datingPreference',
            'activeSubscription.plan',
            'subscriptions.plan',
            'payments' => function ($q) {
                $q->latest();
            },
            'supportTickets' => function ($q) {
                $q->latest();
            },
            'knowledgeBaseItems',
        ])->findOrFail($id);

        $connectionsCount = $user->connectionsCount();
        $postsCount       = $user->posts()->count();

        $visualKnowledgeItems = $user->knowledgeBaseItems->where('type', 'image');
        $textKnowledgeItems   = $user->knowledgeBaseItems->where('type', 'text');

        return view('backend.layouts.user_management.show', compact(
            'user',
            'connectionsCount',
            'postsCount',
            'visualKnowledgeItems',
            'textKnowledgeItems'
        ));
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

    /**
     * Update a user's account status (used for the Block / Unblock user action).
     */
    public function updateUserStatus(Request $request, User $user)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,banned',
        ]);

        if ($user->id === auth()->id()) {
            return $this->statusResponse($request, false, 'You cannot change your own account status.');
        }

        $user->update(['status' => $validated['status']]);

        $messages = [
            'banned'   => 'User has been blocked.',
            'active'   => 'User has been activated.',
            'inactive' => 'User has been marked inactive.',
        ];

        return $this->statusResponse($request, true, $messages[$validated['status']]);
    }

    public function updateUserRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user->syncRoles([$validated['role']]);

        return $this->statusResponse($request, true, 'User role updated successfully.');
    }

    private function statusResponse(Request $request, bool $success, string $message)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => $success, 'message' => $message]);
        }

        return redirect()->back()->with($success ? 'success' : 'error', $message);
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->route('admin.user.lists')->with('success', 'User deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))
            ->map(fn($id) => (int) $id)
            ->reject(fn($id) => $id === (int) auth()->id())
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No users selected.']);
        }

        User::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Selected users deleted successfully.']);
    }

    public function export(Request $request)
    {
        $query = User::query()->with(['activeSubscription.plan']);

        if ($request->role && $request->role != 'All') {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->status && $request->status != 'All') {
            $query->where('status', $request->status);
        }

        if ($request->gender && $request->gender != 'All') {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('ids')) {
            $query->whereIn('id', explode(',', $request->get('ids')));
        }

        $filename = 'users-' . now()->format('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Name', 'Email', 'Gender', 'Location', 'Plan', 'Verified', 'Status', 'Joined']);

            $query->orderBy('id')->chunk(200, function ($users) use ($handle) {
                foreach ($users as $user) {
                    fputcsv($handle, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->gender ? ucfirst($user->gender) : '',
                        $user->location ?: $user->country,
                        $user->activeSubscription->plan->name ?? 'Free',
                        $user->email_verified_at ? 'Yes' : 'No',
                        ucfirst($user->status),
                        optional($user->created_at)->format('Y-m-d'),
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}

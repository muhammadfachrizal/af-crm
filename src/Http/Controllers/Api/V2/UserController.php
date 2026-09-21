<?php

namespace VentureDrake\LaravelCrm\Http\Controllers\Api\V2;

use App\Models\User;
use Illuminate\Http\Request;
use VentureDrake\LaravelCrm\Http\Resources\Api\V2\Related\UserBriefResource;

class UserController extends ApiController
{
    /**
     * Daftar user buat picker "Owner" (user_owner_id) di form Lead/Deal/
     * Organization/Person/Product/Quote/Order/Invoice — field ini sudah lama
     * diterima di semua Store/Update request, tapi belum ada cara buat
     * client tahu daftar user yang bisa dipilih. Sengaja TIDAK di-filter per
     * team di sini (sama seperti web — lihat PersonIndex::users(),
     * OrderIndex::users(), dkk yang semuanya cuma `User::orderBy('name')->get()`
     * tanpa scoping team); validasi keanggotaan tim tetap dijaga di belakang
     * layar oleh rule OwnerInCurrentTeam pas nyimpen.
     */
    public function index(Request $request)
    {
        $query = User::query()->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->input('search').'%');
        }

        return UserBriefResource::collection($query->get());
    }
}

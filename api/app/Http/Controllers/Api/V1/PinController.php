<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\LendingLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PinController extends Controller
{
    public function group(Request $request, Group $group): JsonResponse
    {
        Gate::authorize('view', $group);

        return $this->save($request, 'group_id', $group->id);
    }

    public function contact(Request $request, int $contact, LendingLedger $ledger): JsonResponse
    {
        $ledger->contact($request->user(), $contact);

        return $this->save($request, 'lending_contact_id', $contact);
    }

    private function save(Request $request, string $column, int $id): JsonResponse
    {
        $data = $request->validate(['pinned' => ['required', 'boolean']]);
        $key = ['user_id' => $request->user()->id, $column => $id];
        if ($data['pinned']) {
            DB::table('user_pins')->insertOrIgnore($key);
        } else {
            DB::table('user_pins')->where($key)->delete();
        }

        return response()->json(['data' => ['is_pinned' => (bool) $data['pinned']]]);
    }
}

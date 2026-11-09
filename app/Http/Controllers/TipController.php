<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\StaffAccess;
use App\Support\TipAddresses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipController extends Controller
{
    /** The public tip page. Addresses are only rendered for the owner. */
    public function show(Request $request): Response
    {
        $owner = $this->owner();
        $viewer = $request->user();

        return Inertia::render('Tip/Index', [
            // every visitor gets the full address: a tip page without the address
            // is useless. They are public keys, not secrets.
            'networks' => TipAddresses::publicList($owner, true),
            'ownerName' => $owner?->name ?? 'the site owner',
            'isOwner' => StaffAccess::owner($viewer ?? new User),
        ]);
    }

    /** The owner saves their addresses. */
    public function update(Request $request): RedirectResponse
    {
        abort_unless(StaffAccess::owner($request->user()), 403);
        $owner = $this->owner();

        $data = $request->validate([
            'addresses' => ['array'],
            'addresses.*' => ['nullable', 'string', 'max:128'],
        ]);

        TipAddresses::save($owner, $data['addresses'] ?? []);

        return back()->with('success', 'Tip addresses saved.');
    }

    private function owner(): User
    {
        $id = (int) config('admin.owner_id');

        return ($id ? User::find($id) : null) ?? User::where('is_admin', true)->orderBy('id')->firstOrFail();
    }
}

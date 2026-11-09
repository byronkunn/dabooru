<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TipAddresses;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Where the admin edits the wallets the tip page shows.
 *
 * Kept in the admin panel rather than on the public tip page: the addresses are
 * what the site gets paid at, so changing them belongs behind the admin gate
 * (and any moderator can be given access without becoming the owner).
 */
class TipSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $owner = $this->owner();

        return Inertia::render('Admin/Tips', [
            'networks' => TipAddresses::publicList($owner, true),
            'receivesInto' => $owner?->only(['id', 'name', 'username']),
            'totals' => [
                'configured' => count(array_filter(TipAddresses::stored($owner))),
                'available' => count(TipAddresses::NETWORKS),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'addresses' => ['array'],
            'addresses.*' => ['nullable', 'string', 'max:128'],
        ]);

        TipAddresses::save($this->owner(), $data['addresses'] ?? []);

        return back()->with('success', 'Wallet addresses saved.');
    }

    private function owner(): User
    {
        $id = (int) config('admin.owner_id');

        return ($id ? User::find($id) : null) ?? User::where('is_admin', true)->orderBy('id')->firstOrFail();
    }
}

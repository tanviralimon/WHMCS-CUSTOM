<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Whmcs\WhmcsService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountController extends Controller
{
    public function __construct(protected WhmcsService $whmcs) {}

    public function profile(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $details  = $this->whmcs->getClientsDetails($clientId);

        return Inertia::render('Client/Account/Profile', [
            'client' => $details,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $request->validate([
            'firstname'   => 'required|string|max:100',
            'lastname'    => 'required|string|max:100',
            'companyname' => 'nullable|string|max:100',
            'email'       => 'required|email|max:255',
            'address1'    => 'nullable|string|max:255',
            'address2'    => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'state'       => 'nullable|string|max:100',
            'postcode'    => 'nullable|string|max:20',
            'country'     => 'nullable|string|max:2',
            'phonenumber' => 'nullable|string|max:30',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        $this->whmcs->updateClient($clientId, $request->only([
            'firstname', 'lastname', 'companyname', 'email',
            'address1', 'address2', 'city', 'state', 'postcode',
            'country', 'phonenumber',
        ]));

        // Sync name/email to local user
        $request->user()->update([
            'name'  => $request->firstname . ' ' . $request->lastname,
            'email' => $request->email,
        ]);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $clientId = $request->user()->whmcs_client_id;

        $this->whmcs->updateClient($clientId, [
            'password2' => $request->new_password,
        ]);

        return back()->with('success', 'Password changed successfully.');
    }

    public function contacts(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $result   = $this->whmcs->getContacts($clientId);

        return Inertia::render('Client/Account/Contacts', [
            'contacts' => $result['contacts']['contact'] ?? [],
            'total'    => (int) ($result['totalresults'] ?? 0),
        ]);
    }

    public function storeContact(Request $request)
    {
        $request->validate([
            'firstname'   => 'required|string|max:100',
            'lastname'    => 'required|string|max:100',
            'email'       => 'required|email|max:255',
            'phonenumber' => 'nullable|string|max:30',
        ]);

        $clientId = $request->user()->whmcs_client_id;
        $this->whmcs->addContact($clientId, $request->only([
            'firstname', 'lastname', 'email', 'phonenumber',
        ]));

        return back()->with('success', 'Contact added successfully.');
    }

    public function deleteContact(Request $request, int $id)
    {
        $this->whmcs->deleteContact($id);
        return back()->with('success', 'Contact deleted.');
    }

    public function security(Request $request)
    {
        $clientId = $request->user()->whmcs_client_id;
        $details  = $this->whmcs->getClientsDetails($clientId);

        return Inertia::render('Client/Account/Security', [
            'client'   => $details,
            'whmcsUrl' => rtrim(config('whmcs.base_url'), '/') . '/clientarea.php?action=security',
        ]);
    }
}

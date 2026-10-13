<?php

namespace App\Http\Controllers;

use App\Models\Address;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    protected const MAX_ADDRESSES = 10;

    public function index(Request $request): Response
    {
        return Inertia::render('Addresses/Index', [
            'addresses' => $request->user()->addresses()->get(),
            'max' => self::MAX_ADDRESSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->addresses()->count() >= self::MAX_ADDRESSES) {
            return back()->with('error', 'You can save up to '.self::MAX_ADDRESSES.' addresses. Delete one first.');
        }

        $data = $this->validated($request);
        $first = ! $user->addresses()->exists();

        $wantsDefault = (bool) ($data['is_default'] ?? false);
        $data['is_default'] = $first;

        $address = $user->addresses()->create($data);

        if ($wantsDefault && ! $first) {
            $this->makeDefault($request, $address);
        }

        return back()->with('success', 'Address saved.');
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        $data = $this->validated($request);
        unset($data['is_default']);
        $address->update($data);

        if ($request->boolean('is_default')) {
            $this->makeDefault($request, $address);
        }

        return back()->with('success', 'Address updated.');
    }

    public function default(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        $this->makeDefault($request, $address);

        return back()->with('success', 'Default address changed.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        abort_if($address->user_id !== $request->user()->id, 403);

        $wasDefault = $address->is_default;
        $address->delete();

        if ($wasDefault && ($next = $request->user()->addresses()->first())) {
            $next->update(['is_default' => true]);
        }

        return back()->with('success', 'Address deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }

    protected function makeDefault(Request $request, Address $address): void
    {
        $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);
    }
}

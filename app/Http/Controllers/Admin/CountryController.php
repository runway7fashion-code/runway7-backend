<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CountryController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Settings/Countries', [
            'countries' => Country::ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'  => 'required|string|max:100|unique:countries,name',
            'code'  => 'required|string|max:5|unique:countries,code',
            'phone' => 'required|string|max:10',
            'flag'  => 'nullable|string|max:10',
        ]);

        Country::create([
            'name'  => $request->name,
            'code'  => strtoupper($request->code),
            'phone' => $request->phone,
            'flag'  => $request->flag,
            'order' => (Country::max('order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Country created.');
    }

    public function update(Request $request, Country $country)
    {
        $request->validate([
            'name'      => 'sometimes|string|max:100|unique:countries,name,' . $country->id,
            'code'      => 'sometimes|string|max:5|unique:countries,code,' . $country->id,
            'phone'     => 'sometimes|string|max:10',
            'flag'      => 'sometimes|nullable|string|max:10',
            'order'     => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $data = $request->only(['name', 'code', 'phone', 'flag', 'order', 'is_active']);
        if (isset($data['code'])) {
            $data['code'] = strtoupper($data['code']);
        }

        $country->update($data);

        return back()->with('success', 'Country updated.');
    }

    public function destroy(Country $country)
    {
        $country->delete();

        return back()->with('success', 'Country deleted.');
    }
}

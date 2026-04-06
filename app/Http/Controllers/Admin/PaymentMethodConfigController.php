<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethodConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PaymentMethodConfigController extends Controller
{
    public function index(): Response
    {
        $methods = PaymentMethodConfig::ordered()->get();

        return Inertia::render('Admin/Accounting/PaymentMethods', [
            'methods' => $methods,
        ]);
    }

    public function store(Request $request)
    {
        // Parse config from JSON string (comes as string via FormData)
        if (is_string($request->input('config'))) {
            $request->merge(['config' => json_decode($request->input('config'), true) ?? []]);
        }

        $request->validate([
            'name' => 'required|string|max:50|unique:payment_method_configs,name',
            'label' => 'required|string|max:100',
            'type' => 'required|in:bank,app,other',
            'config' => 'required|array',
            'logo' => 'nullable|image|max:2048',
            'qr_image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['name', 'label', 'type', 'config', 'is_active']);
        $data['name'] = Str::snake($data['name']);
        $data['order'] = (PaymentMethodConfig::max('order') ?? 0) + 1;

        if ($request->hasFile('logo')) {
            $data['logo_url'] = $request->file('logo')->store('payment-methods', 'public');
        }

        if ($request->hasFile('qr_image')) {
            $data['qr_image_url'] = $request->file('qr_image')->store('payment-methods/qr', 'public');
        }

        PaymentMethodConfig::create($data);

        return back()->with('success', 'Payment method created.');
    }

    public function update(Request $request, PaymentMethodConfig $paymentMethodConfig)
    {
        if (is_string($request->input('config'))) {
            $request->merge(['config' => json_decode($request->input('config'), true) ?? []]);
        }

        $request->validate([
            'label' => 'required|string|max:100',
            'type' => 'required|in:bank,app,other',
            'config' => 'required|array',
            'logo' => 'nullable|image|max:2048',
            'qr_image' => 'nullable|image|max:2048',
            'is_active' => 'boolean',
        ]);

        $data = $request->only(['label', 'type', 'config', 'is_active']);

        if ($request->hasFile('logo')) {
            if ($paymentMethodConfig->logo_url) {
                Storage::disk('public')->delete($paymentMethodConfig->logo_url);
            }
            $data['logo_url'] = $request->file('logo')->store('payment-methods', 'public');
        }

        if ($request->hasFile('qr_image')) {
            if ($paymentMethodConfig->qr_image_url) {
                Storage::disk('public')->delete($paymentMethodConfig->qr_image_url);
            }
            $data['qr_image_url'] = $request->file('qr_image')->store('payment-methods/qr', 'public');
        }

        $paymentMethodConfig->update($data);

        return back()->with('success', 'Payment method updated.');
    }

    public function destroy(PaymentMethodConfig $paymentMethodConfig)
    {
        if ($paymentMethodConfig->logo_url) {
            Storage::disk('public')->delete($paymentMethodConfig->logo_url);
        }
        if ($paymentMethodConfig->qr_image_url) {
            Storage::disk('public')->delete($paymentMethodConfig->qr_image_url);
        }

        $paymentMethodConfig->delete();

        return back()->with('success', 'Payment method deleted.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'order' => 'required|array',
            'order.*.id' => 'required|exists:payment_method_configs,id',
            'order.*.order' => 'required|integer|min:0',
        ]);

        foreach ($request->order as $item) {
            PaymentMethodConfig::where('id', $item['id'])->update(['order' => $item['order']]);
        }

        return back()->with('success', 'Order updated.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionsController extends Controller
{
    private const DEPARTMENTS = ['web', 'marketing', 'design', 'sales', 'operations', 'finance', 'general'];
    private const CATEGORIES = [
        'hosting', 'infrastructure', 'email', 'sms', 'ai', 'seo',
        'wordpress', 'ecommerce', 'design_tools', 'productivity',
        'analytics', 'communications', 'other',
    ];
    private const BILLING_CYCLES = ['monthly', 'quarterly', 'annual', 'one_time'];
    private const STATUSES = ['active', 'paused', 'cancelled', 'trial'];

    public function index(Request $request): Response
    {
        $query = Subscription::with('paymentMethod');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('vendor', 'ilike', "%{$search}%")
                    ->orWhere('account_email', 'ilike', "%{$search}%");
            });
        }
        foreach (['department', 'category', 'billing_cycle', 'status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->$field);
            }
        }

        $totalsQuery = clone $query;
        $allActive = (clone $totalsQuery)->where('status', 'active')->get();
        $totals = [
            'monthly' => round($allActive->sum(fn ($s) => $s->monthly_equivalent), 2),
            'annual'  => round($allActive->sum(fn ($s) => $s->annual_equivalent), 2),
            'count'   => $totalsQuery->count(),
            'active_count' => $allActive->count(),
        ];

        $subscriptions = $query->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Subscription $s) => $this->mapSubscription($s));

        return Inertia::render('Admin/Accounting/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'totals' => $totals,
            'filters' => $request->only(['search', 'department', 'category', 'billing_cycle', 'status']),
            'options' => $this->options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Accounting/Subscriptions/Create', [
            'options' => $this->options(),
            'paymentMethods' => $this->paymentMethodList(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateSubscription($request);

        Subscription::create($data);

        return redirect()->route('admin.accounting.subscriptions.index')
            ->with('success', 'Subscription created successfully.');
    }

    public function show(Subscription $subscription): Response
    {
        $subscription->load('paymentMethod', 'payments.paymentMethod', 'payments.registeredBy');

        return Inertia::render('Admin/Accounting/Subscriptions/Show', [
            'subscription' => $this->mapSubscription($subscription, includePayments: true),
            'paymentMethods' => $this->paymentMethodList(),
        ]);
    }

    public function edit(Subscription $subscription): Response
    {
        return Inertia::render('Admin/Accounting/Subscriptions/Edit', [
            'subscription' => $this->mapSubscription($subscription->load('paymentMethod')),
            'options' => $this->options(),
            'paymentMethods' => $this->paymentMethodList(),
        ]);
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $this->validateSubscription($request, $subscription);

        if ($data['status'] === 'cancelled' && $subscription->status !== 'cancelled') {
            $data['cancelled_at'] = now();
        } elseif ($data['status'] !== 'cancelled') {
            $data['cancelled_at'] = null;
            $data['cancellation_reason'] = null;
        }

        $subscription->update($data);

        return redirect()->route('admin.accounting.subscriptions.show', $subscription)
            ->with('success', 'Subscription updated successfully.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return redirect()->route('admin.accounting.subscriptions.index')
            ->with('success', 'Subscription deleted successfully.');
    }

    public function dashboard(): Response
    {
        $active = Subscription::active()->get();

        $byDepartment = $active->groupBy('department')->map(fn ($group) => [
            'count' => $group->count(),
            'monthly' => round($group->sum(fn ($s) => $s->monthly_equivalent), 2),
            'annual' => round($group->sum(fn ($s) => $s->annual_equivalent), 2),
        ]);

        $byCategory = $active->groupBy('category')->map(fn ($group) => [
            'count' => $group->count(),
            'monthly' => round($group->sum(fn ($s) => $s->monthly_equivalent), 2),
            'annual' => round($group->sum(fn ($s) => $s->annual_equivalent), 2),
        ]);

        $upcoming = Subscription::with('paymentMethod')
            ->renewingWithin(30)
            ->orderBy('next_renewal_date')
            ->get()
            ->map(fn (Subscription $s) => $this->mapSubscription($s));

        return Inertia::render('Admin/Accounting/Subscriptions/Dashboard', [
            'totals' => [
                'monthly' => round($active->sum(fn ($s) => $s->monthly_equivalent), 2),
                'annual' => round($active->sum(fn ($s) => $s->annual_equivalent), 2),
                'active_count' => $active->count(),
                'total_count' => Subscription::count(),
            ],
            'byDepartment' => $byDepartment,
            'byCategory' => $byCategory,
            'upcoming' => $upcoming,
        ]);
    }

    public function renewals(Request $request): Response
    {
        $days = (int) $request->input('days', 30);
        $days = max(7, min($days, 365));

        $subs = Subscription::with('paymentMethod')
            ->renewingWithin($days)
            ->orderBy('next_renewal_date')
            ->get()
            ->map(fn (Subscription $s) => $this->mapSubscription($s));

        return Inertia::render('Admin/Accounting/Subscriptions/Renewals', [
            'subscriptions' => $subs,
            'days' => $days,
        ]);
    }

    public function storePayment(Request $request, Subscription $subscription): RedirectResponse
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'paid_at' => 'required|date',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'payment_method_id' => 'nullable|exists:subscription_payment_methods,id',
            'invoice_url' => 'nullable|url|max:500',
            'notes' => 'nullable|string',
            'receipt' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'advance_renewal' => 'nullable|boolean',
        ]);

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('accounting/subscription-receipts', 'public');
        }

        $subscription->payments()->create([
            'amount' => $data['amount'],
            'paid_at' => $data['paid_at'],
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? $subscription->payment_method_id,
            'invoice_url' => $data['invoice_url'] ?? null,
            'receipt_path' => $receiptPath,
            'notes' => $data['notes'] ?? null,
            'registered_by' => $request->user()->id,
        ]);

        if (!empty($data['advance_renewal']) && $subscription->next_renewal_date) {
            $next = $subscription->next_renewal_date;
            $subscription->next_renewal_date = match ($subscription->billing_cycle) {
                'monthly' => $next->copy()->addMonth(),
                'quarterly' => $next->copy()->addMonths(3),
                'annual' => $next->copy()->addYear(),
                default => $next,
            };
            $subscription->save();
        }

        return back()->with('success', 'Payment registered successfully.');
    }

    public function destroyPayment(Subscription $subscription, SubscriptionPayment $payment): RedirectResponse
    {
        if ($payment->subscription_id !== $subscription->id) {
            abort(404);
        }

        if ($payment->receipt_path) {
            Storage::disk('public')->delete($payment->receipt_path);
        }

        $payment->delete();

        return back()->with('success', 'Payment deleted successfully.');
    }

    public function paymentMethods(): Response
    {
        $methods = SubscriptionPaymentMethod::withCount('subscriptions')
            ->orderBy('nickname')
            ->get()
            ->map(fn (SubscriptionPaymentMethod $m) => [
                'id' => $m->id,
                'nickname' => $m->nickname,
                'card_type' => $m->card_type,
                'last_four' => $m->last_four,
                'masked' => $m->masked,
                'holder_name' => $m->holder_name,
                'notes' => $m->notes,
                'subscriptions_count' => $m->subscriptions_count,
            ]);

        return Inertia::render('Admin/Accounting/Subscriptions/PaymentMethods', [
            'methods' => $methods,
        ]);
    }

    public function storePaymentMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nickname' => 'required|string|max:100',
            'card_type' => 'required|in:visa,mastercard',
            'last_four' => ['required', 'string', 'regex:/^\d{4}$/'],
            'holder_name' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        SubscriptionPaymentMethod::create($data);

        return back()->with('success', 'Card added successfully.');
    }

    public function updatePaymentMethod(Request $request, SubscriptionPaymentMethod $method): RedirectResponse
    {
        $data = $request->validate([
            'nickname' => 'required|string|max:100',
            'card_type' => 'required|in:visa,mastercard',
            'last_four' => ['required', 'string', 'regex:/^\d{4}$/'],
            'holder_name' => 'nullable|string|max:150',
            'notes' => 'nullable|string',
        ]);

        $method->update($data);

        return back()->with('success', 'Card updated successfully.');
    }

    public function destroyPaymentMethod(SubscriptionPaymentMethod $method): RedirectResponse
    {
        $method->delete();

        return back()->with('success', 'Card deleted successfully.');
    }

    private function validateSubscription(Request $request, ?Subscription $existing = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'vendor' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'account_email' => 'nullable|email|max:200',
            'department' => 'required|in:' . implode(',', self::DEPARTMENTS),
            'category' => 'required|in:' . implode(',', self::CATEGORIES),
            'billing_cycle' => 'required|in:' . implode(',', self::BILLING_CYCLES),
            'amount' => 'required|numeric|min:0',
            'payment_method_id' => 'nullable|exists:subscription_payment_methods,id',
            'purchase_date' => 'nullable|date',
            'next_renewal_date' => 'nullable|date',
            'auto_renew' => 'boolean',
            'status' => 'required|in:' . implode(',', self::STATUSES),
            'plan_tier' => 'nullable|string|max:100',
            'seats' => 'nullable|integer|min:0',
            'website_url' => 'nullable|url|max:500',
            'notes' => 'nullable|string',
            'cancellation_reason' => 'nullable|string',
        ]);
    }

    private function mapSubscription(Subscription $s, bool $includePayments = false): array
    {
        $base = [
            'id' => $s->id,
            'name' => $s->name,
            'vendor' => $s->vendor,
            'description' => $s->description,
            'account_email' => $s->account_email,
            'department' => $s->department,
            'category' => $s->category,
            'billing_cycle' => $s->billing_cycle,
            'amount' => (float) $s->amount,
            'monthly_equivalent' => round($s->monthly_equivalent, 2),
            'annual_equivalent' => round($s->annual_equivalent, 2),
            'purchase_date' => $s->purchase_date?->toDateString(),
            'next_renewal_date' => $s->next_renewal_date?->toDateString(),
            'next_renewal_in_days' => $s->next_renewal_date
                ? (int) now()->startOfDay()->diffInDays($s->next_renewal_date->startOfDay(), false)
                : null,
            'auto_renew' => (bool) $s->auto_renew,
            'status' => $s->status,
            'plan_tier' => $s->plan_tier,
            'seats' => $s->seats,
            'website_url' => $s->website_url,
            'notes' => $s->notes,
            'cancelled_at' => $s->cancelled_at?->toDateTimeString(),
            'cancellation_reason' => $s->cancellation_reason,
            'payment_method' => $s->paymentMethod ? [
                'id' => $s->paymentMethod->id,
                'nickname' => $s->paymentMethod->nickname,
                'masked' => $s->paymentMethod->masked,
            ] : null,
        ];

        if ($includePayments) {
            $base['payments'] = $s->payments->map(fn (SubscriptionPayment $p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'paid_at' => $p->paid_at?->toDateString(),
                'period_start' => $p->period_start?->toDateString(),
                'period_end' => $p->period_end?->toDateString(),
                'invoice_url' => $p->invoice_url,
                'receipt_path' => $p->receipt_path,
                'receipt_url' => $p->receipt_path ? Storage::disk('public')->url($p->receipt_path) : null,
                'notes' => $p->notes,
                'payment_method' => $p->paymentMethod ? [
                    'nickname' => $p->paymentMethod->nickname,
                    'masked' => $p->paymentMethod->masked,
                ] : null,
                'registered_by' => $p->registeredBy
                    ? trim(($p->registeredBy->first_name ?? '') . ' ' . ($p->registeredBy->last_name ?? ''))
                    : null,
            ])->values();
            $base['totals'] = [
                'paid_total' => round($s->payments->sum(fn ($p) => (float) $p->amount), 2),
                'payments_count' => $s->payments->count(),
            ];
        }

        return $base;
    }

    private function paymentMethodList()
    {
        return SubscriptionPaymentMethod::orderBy('nickname')->get()->map(fn ($m) => [
            'id' => $m->id,
            'nickname' => $m->nickname,
            'card_type' => $m->card_type,
            'last_four' => $m->last_four,
            'masked' => $m->masked,
        ]);
    }

    private function options(): array
    {
        return [
            'departments' => self::DEPARTMENTS,
            'categories' => self::CATEGORIES,
            'billing_cycles' => self::BILLING_CYCLES,
            'statuses' => self::STATUSES,
        ];
    }
}

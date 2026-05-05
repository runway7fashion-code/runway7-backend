<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MediaRegistrationController extends Controller
{
    public function __construct(
        protected ActivityLogService $activityLog,
    ) {}

    public function events(): JsonResponse
    {
        $events = Event::where('status', 'active')
            ->orderBy('start_date')
            ->get(['id', 'name', 'city', 'start_date', 'end_date'])
            ->map(fn($e) => [
                'id'         => $e->id,
                'name'       => $e->name,
                'city'       => $e->city,
                'start_date' => $e->start_date?->format('Y-m-d'),
                'end_date'   => $e->end_date?->format('Y-m-d'),
            ]);

        return response()->json($events);
    }

    /**
     * Catalog of media kits and add-ons (read from config/media_kits.php).
     * Used by the WordPress form to render kit selection.
     */
    public function products(): JsonResponse
    {
        $kits = collect(config('media_kits.kits'))->map(fn($kit, $key) => [
            'key'             => $key,
            'name'            => $kit['name'],
            'price'           => (float) $kit['price'],
            'description'     => $kit['description'],
            'allowed_addons'  => $kit['allowed_addons'],
        ])->values();

        $addons = collect(config('media_kits.addons'))->map(fn($addon, $key) => [
            'key'         => $key,
            'name'        => $addon['name'],
            'price'       => (float) $addon['price'],
            'description' => $addon['description'],
        ])->values();

        return response()->json([
            'kits'   => $kits,
            'addons' => $addons,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Honeypot
        if ($request->filled('website_url')) {
            return response()->json([
                'message' => 'Your application has been received successfully!',
            ], 201);
        }

        // Sanitize Instagram
        if ($request->filled('instagram')) {
            $ig = $request->input('instagram');
            $ig = strtok($ig, '?');
            $ig = preg_replace('#^https?://(www\.)?instagram\.com/#i', '', $ig);
            $ig = rtrim($ig, '/');
            $ig = ltrim($ig, '@');
            $request->merge(['instagram' => $ig]);
        }

        $kitsConfig   = config('media_kits.kits');
        $addonsConfig = config('media_kits.addons');

        $validated = $request->validate([
            'first_name'    => 'required|string|max:255',
            'last_name'     => 'required|string|max:255',
            'email'         => 'required|email',
            'phone'         => 'required|string',
            'category'      => 'required|in:videographer,photographer',
            'portfolio_url' => 'required|url|max:500',
            'instagram'     => 'required|string|max:255',
            'location'      => 'required|string|max:255',
            'will_travel'   => 'required|in:yes,no',
            'event_id'      => 'required|exists:events,id',
            'kit_type'      => 'required|in:' . implode(',', array_keys($kitsConfig)),
            'addons'        => 'nullable|array',
            'addons.*'      => 'in:' . implode(',', array_keys($addonsConfig)),
        ], [
            'first_name.required'    => 'First name is required.',
            'last_name.required'     => 'Last name is required.',
            'email.required'         => 'Email is required.',
            'email.email'            => 'Please enter a valid email.',
            'phone.required'         => 'Phone is required.',
            'category.required'      => 'Category is required.',
            'portfolio_url.required' => 'Portfolio link is required.',
            'portfolio_url.url'      => 'Please enter a valid URL.',
            'instagram.required'     => 'Instagram is required.',
            'location.required'      => 'Location is required.',
            'will_travel.required'   => 'This field is required.',
            'event_id.required'      => 'Please select an event.',
            'event_id.exists'        => 'The selected event is not valid.',
            'kit_type.required'      => 'Please select a media kit.',
            'kit_type.in'            => 'The selected kit is not valid.',
        ]);

        $eventId   = (int) $validated['event_id'];
        $kitKey    = $validated['kit_type'];
        $addonKeys = array_values(array_unique($validated['addons'] ?? []));

        // Validate that selected addons are allowed for the chosen kit
        $allowedForKit = $kitsConfig[$kitKey]['allowed_addons'];
        $invalidAddons = array_diff($addonKeys, $allowedForKit);
        if (!empty($invalidAddons)) {
            return response()->json([
                'message' => 'One or more selected add-ons are not available for the chosen kit.',
                'errors' => ['addons' => ['The selected add-ons are not valid for this kit.']],
            ], 422);
        }

        $existingUser = User::where('email', $validated['email'])->first();

        // Block inactive users
        if ($existingUser && $existingUser->status === 'inactive') {
            return response()->json([
                'message' => 'Your account has been deactivated. Please contact us for assistance.',
                'errors' => ['email' => ['Your account has been deactivated. Please contact us at operations@runway7fashion.com']],
            ], 422);
        }

        // Reject if email exists with different role
        if ($existingUser && $existingUser->role !== 'media') {
            return response()->json([
                'message' => 'This email is already registered with a different role.',
                'errors' => ['email' => ['This email is already registered as ' . $existingUser->role . '. Please use a different email or contact us at operations@runway7fashion.com']],
            ], 422);
        }

        // Check duplicate event registration (only block if it was already paid)
        if ($existingUser) {
            $alreadyInEvent = DB::table('event_media')
                ->where('media_id', $existingUser->id)
                ->where('event_id', $eventId)
                ->where('payment_status', 'paid')
                ->exists();

            if ($alreadyInEvent) {
                return response()->json([
                    'message' => 'You are already registered for this event.',
                    'errors' => ['email' => ['You are already registered for this event. Please contact us at operations@runway7fashion.com']],
                ], 422);
            }
        }

        try {
            $totalAmount = $this->calculateTotal($kitKey, $addonKeys, $kitsConfig, $addonsConfig);
            $registrationToken = (string) Str::uuid();

            $result = DB::transaction(function () use ($validated, $existingUser, $eventId, $kitKey, $addonKeys, $totalAmount, $registrationToken) {
                if ($existingUser) {
                    $existingUser->update([
                        'first_name' => $validated['first_name'],
                        'last_name'  => $validated['last_name'],
                        'phone'      => $validated['phone'],
                    ]);

                    $existingUser->mediaProfile()->updateOrCreate(
                        ['user_id' => $existingUser->id],
                        [
                            'category'      => $validated['category'],
                            'portfolio_url' => $validated['portfolio_url'],
                            'instagram'     => $validated['instagram'],
                            'location'      => $validated['location'],
                            'will_travel'   => $validated['will_travel'],
                        ],
                    );

                    $user = $existingUser;
                } else {
                    $user = User::create([
                        'first_name' => $validated['first_name'],
                        'last_name'  => $validated['last_name'],
                        'email'      => $validated['email'],
                        'phone'      => $validated['phone'],
                        'role'       => 'media',
                        'status'     => 'applicant',
                        'password'   => Hash::make('runway7'),
                    ]);

                    $user->mediaProfile()->create([
                        'category'      => $validated['category'],
                        'portfolio_url' => $validated['portfolio_url'],
                        'instagram'     => $validated['instagram'],
                        'location'      => $validated['location'],
                        'will_travel'   => $validated['will_travel'],
                    ]);
                }

                // Upsert event_media as pending_payment (cancel any previous unpaid attempt for the same event)
                DB::table('event_media')
                    ->where('media_id', $user->id)
                    ->where('event_id', $eventId)
                    ->where('payment_status', '!=', 'paid')
                    ->delete();

                $eventMediaId = DB::table('event_media')->insertGetId([
                    'media_id'           => $user->id,
                    'event_id'           => $eventId,
                    'status'             => 'pending_payment',
                    'kit_type'           => $kitKey,
                    'addons'             => json_encode($addonKeys),
                    'payment_status'     => 'pending',
                    'total_amount'       => $totalAmount,
                    'registration_token' => $registrationToken,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                return ['user' => $user, 'event_media_id' => $eventMediaId];
            });

            $user = $result['user'];
            $eventMediaId = $result['event_media_id'];

            $checkoutUrl = $this->buildCheckoutUrl($kitKey, $addonKeys, $registrationToken, $eventMediaId, $user->email);

            $event = Event::find($eventId);
            $this->activityLog->log(
                ActivityAction::Registered,
                $user,
                null,
                "Pre-registro media (pending payment): {$user->first_name} {$user->last_name} para {$event->name} — kit {$kitKey}",
                [
                    'source'             => 'wordpress',
                    'event_id'           => $event->id,
                    'event_media_id'     => $eventMediaId,
                    'kit_type'           => $kitKey,
                    'addons'             => $addonKeys,
                    'total_amount'       => $totalAmount,
                    'registration_token' => $registrationToken,
                ]
            );

            return response()->json([
                'message'         => 'Registration created. Redirecting to payment...',
                'checkout_url'    => $checkoutUrl,
                'registration_id' => $eventMediaId,
                'total_amount'    => $totalAmount,
            ], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => 'An error occurred processing your application. Please try again.',
            ], 500);
        }
    }

    /**
     * Calculate the total cart amount based on kit + add-ons.
     */
    private function calculateTotal(string $kitKey, array $addonKeys, array $kitsConfig, array $addonsConfig): float
    {
        $total = (float) $kitsConfig[$kitKey]['price'];
        foreach ($addonKeys as $addonKey) {
            $total += (float) $addonsConfig[$addonKey]['price'];
        }
        return round($total, 2);
    }

    /**
     * Build a Shopify cart permalink with note attributes that the
     * orders/paid webhook will use to identify the registration.
     *
     * Format:
     * https://shoprunway7.com/cart/VID:1,VID:1?
     *   attributes[registration_token]=UUID&
     *   attributes[event_media_id]=X&
     *   checkout[email]=...
     */
    private function buildCheckoutUrl(string $kitKey, array $addonKeys, string $token, int $eventMediaId, string $email): string
    {
        $domain = config('services.shopify.storefront_domain');

        $items = [config("media_kits.kits.{$kitKey}.shopify_variant_id") . ':1'];
        foreach ($addonKeys as $addonKey) {
            $items[] = config("media_kits.addons.{$addonKey}.shopify_variant_id") . ':1';
        }

        $query = http_build_query([
            'attributes[registration_token]' => $token,
            'attributes[event_media_id]'     => $eventMediaId,
            'checkout[email]'                => $email,
        ]);

        return "https://{$domain}/cart/" . implode(',', $items) . "?{$query}";
    }
}

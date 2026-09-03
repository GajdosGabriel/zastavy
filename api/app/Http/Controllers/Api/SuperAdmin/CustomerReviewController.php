<?php

namespace App\Http\Controllers\Api\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\CustomerReviewResource;
use App\Models\Customer;
use App\Services\Customers\CustomerReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Posudok údajov zákazníka pre administráciu.
 *
 * Štyri veci, ktoré s ním admin robí: pozrie ho, pustí ho odznova, prijme
 * z neho návrhy, alebo ho odbaví ako „viem o tom, nechaj tak".
 *
 * Autorizuje sa cez zákazníka, nie cez posudok — kto smie zákazníka upraviť,
 * smie prijať aj návrh na jeho údaje; nič viac tu nevzniká.
 */
class CustomerReviewController extends Controller
{
    public function __construct(private readonly CustomerReviewService $service)
    {
    }

    public function show(Customer $customer)
    {
        Gate::authorize('view', $customer);

        $review = $this->service->reviewFor($customer);

        return $review === null
            ? response()->json(['data' => null])
            : new CustomerReviewResource($review);
    }

    /** Pustí kontrolu hneď, bez čakania na odklad a na príkaz. */
    public function store(Customer $customer)
    {
        Gate::authorize('update', $customer);

        $review = $this->service->reviewFor($customer);

        if ($review === null) {
            $this->service->schedule($customer);
            $review = $this->service->reviewFor($customer);
        }

        if ($review === null) {
            return response()->json(['message' => 'Kontrola je vypnutá konfiguráciou.'], 422);
        }

        try {
            $this->service->run($review);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Kontrolu sa nepodarilo dokončiť: '.$e->getMessage()], 502);
        }

        return new CustomerReviewResource($review->refresh());
    }

    /**
     * Prijme návrhy, ktoré admin odklikol.
     *
     * Poslať sa dajú len poradové čísla výhrad z posudku — nie hodnoty. Zápis
     * tak nikdy nevychádza z toho, čo prišlo z prehliadača, ale z toho, čo
     * kontrola naozaj navrhla.
     */
    public function update(Customer $customer, Request $request)
    {
        Gate::authorize('update', $customer);

        $validated = $request->validate([
            'issues' => 'required|array|min:1',
            'issues.*' => 'integer|min:0',
        ]);

        $review = $this->service->reviewFor($customer);

        if ($review === null) {
            return response()->json(['message' => 'Zákazník nemá posudok.'], 404);
        }

        $changes = $this->service->applySuggestions($review, $validated['issues'], $request->user()?->id);

        return response()->json([
            'data' => (new CustomerReviewResource($review->refresh()))->toArray($request),
            'applied' => $changes,
            'customer' => (new CustomerResource($customer->refresh()->load('users')))->toArray($request),
        ]);
    }

    /**
     * Vráti automatické opravy späť.
     *
     * Rovnako ako pri prijatí návrhu sa posielajú len poradové čísla —
     * hodnota, ktorá sa zapíše, je tá, čo je uložená v audite.
     */
    public function revert(Customer $customer, Request $request)
    {
        Gate::authorize('update', $customer);

        $validated = $request->validate([
            'applied' => 'required|array|min:1',
            'applied.*' => 'integer|min:0',
        ]);

        $review = $this->service->reviewFor($customer);

        if ($review === null) {
            return response()->json(['message' => 'Zákazník nemá posudok.'], 404);
        }

        $reverted = $this->service->revertApplied($review, $validated['applied'], $request->user()?->id);

        if ($reverted === []) {
            return response()->json([
                'message' => 'Nebolo čo vrátiť — hodnotu medzitým zmenil niekto iný.',
            ], 422);
        }

        return response()->json([
            'data' => (new CustomerReviewResource($review->refresh()))->toArray($request),
            'reverted' => $reverted,
            'customer' => (new CustomerResource($customer->refresh()->load('users')))->toArray($request),
        ]);
    }

    /** Odbaví posudok — nálezy ostanú zapísané, ale prestanú svietiť. */
    public function destroy(Customer $customer, Request $request)
    {
        Gate::authorize('update', $customer);

        $review = $this->service->reviewFor($customer);

        if ($review === null) {
            return response()->json(['message' => 'Zákazník nemá posudok.'], 404);
        }

        $this->service->resolve($review, $request->user()?->id);

        return new CustomerReviewResource($review->refresh());
    }
}

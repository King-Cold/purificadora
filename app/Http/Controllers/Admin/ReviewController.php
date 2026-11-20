<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderReview;
use App\Services\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    /**
     * Listado y moderación de comentarios y calificaciones de clientes.
     */
    public function index(Request $request, SettingService $settingService): Response
    {
        $search = trim((string) $request->query('search', ''));
        $ratingFilter = (string) $request->query('rating', 'all');
        $landingFilter = (string) $request->query('landing', 'all');

        $query = OrderReview::with([
            'order:id,folio,status,delivery_zone,created_at',
            'customer:id,name,nickname,code',
        ])->latest('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('comment', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($oq) => $oq->where('folio', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($cq) => $cq
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('nickname', 'like', "%{$search}%"));
            });
        }

        if (in_array($ratingFilter, ['1', '2', '3', '4', '5'], true)) {
            $query->where('rating', (int) $ratingFilter);
        }

        if ($landingFilter === 'featured') {
            $query->where('show_on_landing', true);
        } elseif ($landingFilter === 'hidden') {
            $query->where('show_on_landing', false);
        }

        $zonesMap = $settingService->getDeliveryZonesMap();

        $reviews = $query->paginate(12)->withQueryString();
        $reviews->through(function (OrderReview $review) use ($zonesMap, $settingService) {
            $zoneKey = $settingService->normalizeDeliveryZoneKey($review->order?->delivery_zone ?? '');
            $zoneLabel = $zonesMap[$zoneKey] ?? null;

            return [
                'id' => $review->id,
                'rating' => (int) $review->rating,
                'comment' => $review->comment,
                'show_on_landing' => (bool) $review->show_on_landing,
                'featured_at' => $review->featured_at?->copy()->setTimezone('America/Merida')->format('d/m/Y H:i'),
                'created_at' => $review->created_at?->copy()->setTimezone('America/Merida')->format('d/m/Y H:i'),
                'order' => [
                    'id' => $review->order?->id,
                    'folio' => $review->order?->folio ?? '—',
                    'zone_label' => $zoneLabel,
                ],
                'customer' => [
                    'id' => $review->customer?->id,
                    'name' => $review->customer?->name ?: 'Cliente',
                    'nickname' => $review->customer?->nickname,
                    'code' => $review->customer?->code,
                ],
            ];
        });

        $statsQuery = OrderReview::query();
        $totalCount = (clone $statsQuery)->count();
        $featuredCount = (clone $statsQuery)->where('show_on_landing', true)->count();
        $avgRating = $totalCount > 0 ? round((float) (clone $statsQuery)->avg('rating'), 1) : 0.0;

        return Inertia::render('Admin/Reviews/Index', [
            'reviews' => $reviews,
            'stats' => [
                'total' => $totalCount,
                'featured' => $featuredCount,
                'average_rating' => $avgRating,
            ],
            'filters' => [
                'search' => $search,
                'rating' => $ratingFilter,
                'landing' => $landingFilter,
            ],
        ]);
    }

    /**
     * Activa o desactiva la visibilidad de una reseña en la landing page.
     */
    public function toggleLanding(Request $request, OrderReview $review): RedirectResponse
    {
        $nextState = !$review->show_on_landing;

        $review->update([
            'show_on_landing' => $nextState,
            'featured_at' => $nextState ? now() : null,
            'reviewed_by_user_id' => $request->user()?->id,
        ]);

        return back()->with(
            'success',
            $nextState
                ? 'El comentario ahora se muestra en el landing page.'
                : 'El comentario se ocultó del landing page.'
        );
    }

    /**
     * Elimina una reseña si el administrador lo requiere.
     */
    public function destroy(OrderReview $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Comentario eliminado correctamente.');
    }
}

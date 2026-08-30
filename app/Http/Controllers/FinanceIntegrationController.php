<?php

namespace App\Http\Controllers;

use App\Enums\IntegrationOutboxStatus;
use App\Models\IntegrationOutbox;
use App\Models\PlantationEntity;
use App\Services\IntegrationOutboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FinanceIntegrationController extends Controller
{
    public function __construct(private readonly IntegrationOutboxService $outbox) {}

    public function show(PlantationEntity $plantationEntity): View
    {
        $recentFailed = IntegrationOutbox::query()
            ->where('plantation_entity_public_id', $plantationEntity->public_id)
            ->where('status', IntegrationOutboxStatus::FAILED)
            ->latest('id')
            ->limit(20)
            ->get();

        $lastSent = IntegrationOutbox::query()
            ->where('plantation_entity_public_id', $plantationEntity->public_id)
            ->where('status', IntegrationOutboxStatus::SENT)
            ->latest('processed_at')
            ->first();

        return view('integration.show', [
            'entity' => $plantationEntity,
            'pendingCount' => IntegrationOutbox::query()
                ->where('plantation_entity_public_id', $plantationEntity->public_id)
                ->whereIn('status', [IntegrationOutboxStatus::PENDING, IntegrationOutboxStatus::PROCESSING])
                ->count(),
            'failedCount' => IntegrationOutbox::query()
                ->where('plantation_entity_public_id', $plantationEntity->public_id)
                ->where('status', IntegrationOutboxStatus::FAILED)
                ->count(),
            'lastSuccessfulAt' => $lastSent?->processed_at,
            'recentFailed' => $recentFailed,
            'eventsEnabled' => (bool) config('services.integration.events_enabled'),
        ]);
    }

    public function retry(PlantationEntity $plantationEntity, IntegrationOutbox $outbox): RedirectResponse
    {
        if ($outbox->plantation_entity_public_id !== $plantationEntity->public_id) {
            abort(404);
        }

        $this->outbox->retryFailed((int) $outbox->id);

        return back()->with('success', 'Event dijadwalkan ulang.');
    }
}

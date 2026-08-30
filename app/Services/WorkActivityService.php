<?php

namespace App\Services;

use App\Enums\WorkActivityStatus;
use App\Models\Plantation;
use App\Models\PlantationBlock;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use App\Models\WorkType;
use InvalidArgumentException;

class WorkActivityService
{
    /**
     * @param  array{plantation_id: int, plantation_block_id?: int|null, work_type_id: int, activity_date: string, title: string, description?: string|null, status?: string, started_at?: string|null, finished_at?: string|null}  $data
     */
    public function create(PlantationEntity $entity, array $data): WorkActivity
    {
        $this->assertRelations($entity, $data);

        return $entity->workActivities()->create([
            'plantation_id' => $data['plantation_id'],
            'plantation_block_id' => $data['plantation_block_id'] ?? null,
            'work_type_id' => $data['work_type_id'],
            'activity_date' => $data['activity_date'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? WorkActivityStatus::DRAFT->value,
            'started_at' => $data['started_at'] ?? null,
            'finished_at' => $data['finished_at'] ?? null,
        ]);
    }

    /**
     * @param  array{plantation_id: int, plantation_block_id?: int|null, work_type_id: int, activity_date: string, title: string, description?: string|null, status?: string, started_at?: string|null, finished_at?: string|null}  $data
     */
    public function update(WorkActivity $activity, array $data): WorkActivity
    {
        $this->assertRelations($activity->plantationEntity, $data);

        $activity->update([
            'plantation_id' => $data['plantation_id'],
            'plantation_block_id' => $data['plantation_block_id'] ?? null,
            'work_type_id' => $data['work_type_id'],
            'activity_date' => $data['activity_date'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? $activity->status->value,
            'started_at' => $data['started_at'] ?? null,
            'finished_at' => $data['finished_at'] ?? null,
        ]);

        return $activity->fresh() ?? $activity;
    }

    public function complete(WorkActivity $activity): WorkActivity
    {
        if ($activity->status === WorkActivityStatus::CANCELLED) {
            throw new InvalidArgumentException('Aktivitas yang dibatalkan tidak dapat diselesaikan.');
        }

        $activity->update([
            'status' => WorkActivityStatus::COMPLETED,
            'finished_at' => $activity->finished_at ?? now(),
        ]);

        return $activity->fresh() ?? $activity;
    }

    public function cancel(WorkActivity $activity): WorkActivity
    {
        $activity->update(['status' => WorkActivityStatus::CANCELLED]);

        return $activity->fresh() ?? $activity;
    }

    public function delete(WorkActivity $activity): void
    {
        if ($activity->attendances()->exists() || $activity->payrolls()->exists() || $activity->harvests()->exists()) {
            throw new InvalidArgumentException(
                'Aktivitas yang sudah memiliki absensi, upah, atau panen tidak dapat dihapus. Gunakan status Dibatalkan.'
            );
        }

        $activity->delete();
    }

    /**
     * @param  array{plantation_id: int, plantation_block_id?: int|null, work_type_id: int}  $data
     */
    private function assertRelations(PlantationEntity $entity, array $data): void
    {
        $plantation = Plantation::query()
            ->forEntity($entity)
            ->whereKey($data['plantation_id'])
            ->first();

        if (! $plantation instanceof Plantation) {
            throw new InvalidArgumentException('Kebun tidak valid untuk unit ini.');
        }

        $workType = WorkType::query()
            ->forEntity($entity)
            ->whereKey($data['work_type_id'])
            ->first();

        if (! $workType instanceof WorkType) {
            throw new InvalidArgumentException('Jenis pekerjaan tidak valid untuk unit ini.');
        }

        $blockId = $data['plantation_block_id'] ?? null;

        if ($blockId === null) {
            return;
        }

        $block = PlantationBlock::query()
            ->whereKey($blockId)
            ->where('plantation_id', $plantation->id)
            ->first();

        if (! $block instanceof PlantationBlock) {
            throw new InvalidArgumentException('Blok harus milik kebun yang dipilih.');
        }
    }
}

<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollStatus;
use App\Models\WorkActivity;
use App\Models\WorkAttendance;
use App\Models\Worker;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AttendanceService
{
    /**
     * @param  list<int>  $workerIds
     * @return list<WorkAttendance>
     */
    public function assignWorkers(WorkActivity $activity, array $workerIds, AttendanceStatus $status = AttendanceStatus::PRESENT): array
    {
        $created = [];

        DB::transaction(function () use ($activity, $workerIds, $status, &$created): void {
            foreach (array_unique($workerIds) as $workerId) {
                $created[] = $this->assignWorker($activity, (int) $workerId, $status);
            }
        });

        return array_values(array_filter($created));
    }

    public function assignWorker(WorkActivity $activity, int $workerId, AttendanceStatus $status = AttendanceStatus::PRESENT): WorkAttendance
    {
        $worker = Worker::query()
            ->forEntity($activity->plantationEntity)
            ->whereKey($workerId)
            ->first();

        if (! $worker instanceof Worker) {
            throw new InvalidArgumentException('Pekerja tidak valid untuk unit ini.');
        }

        if (! $worker->is_active) {
            throw new InvalidArgumentException('Pekerja nonaktif tidak dapat ditambahkan ke aktivitas baru.');
        }

        $existing = WorkAttendance::query()
            ->where('work_activity_id', $activity->id)
            ->where('worker_id', $worker->id)
            ->first();

        if ($existing instanceof WorkAttendance) {
            throw new InvalidArgumentException('Pekerja ini sudah tercatat pada aktivitas yang sama.');
        }

        return $activity->attendances()->create([
            'worker_id' => $worker->id,
            'attendance_status' => $status,
        ]);
    }

    /**
     * @param  array{attendance_status: string, check_in?: string|null, check_out?: string|null, work_units?: float|int|string|null, notes?: string|null}  $data
     */
    public function update(WorkAttendance $attendance, array $data): WorkAttendance
    {
        $payroll = $attendance->payroll;

        if ($payroll && $payroll->payroll_status === PayrollStatus::POSTED) {
            throw new InvalidArgumentException('Absensi yang sudah memiliki upah terposting tidak dapat diubah.');
        }

        $attendance->update([
            'attendance_status' => $data['attendance_status'],
            'check_in' => $data['check_in'] ?? null,
            'check_out' => $data['check_out'] ?? null,
            'work_units' => $data['work_units'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return $attendance->fresh() ?? $attendance;
    }

    public function remove(WorkAttendance $attendance): void
    {
        $payroll = $attendance->payroll;

        if ($payroll && $payroll->payroll_status === PayrollStatus::POSTED) {
            throw new InvalidArgumentException('Pekerja tidak dapat dihapus dari aktivitas setelah upah diposting.');
        }

        if ($payroll) {
            $payroll->delete();
        }

        $attendance->delete();
    }
}

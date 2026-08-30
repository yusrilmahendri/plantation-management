<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollRateType;
use App\Enums\WorkActivityStatus;
use App\Http\Requests\GeneratePayrollRequest;
use App\Http\Requests\MarkPayrollPaidRequest;
use App\Http\Requests\PostPayrollRequest;
use App\Http\Requests\StoreWorkActivityRequest;
use App\Http\Requests\StoreWorkAttendanceRequest;
use App\Http\Requests\UpdatePayrollRequest;
use App\Http\Requests\UpdateWorkActivityRequest;
use App\Http\Requests\UpdateWorkAttendanceRequest;
use App\Models\BudgetAllocationItem;
use App\Models\Plantation;
use App\Models\PlantationEntity;
use App\Models\WorkActivity;
use App\Models\WorkAttendance;
use App\Models\Worker;
use App\Models\WorkerPayroll;
use App\Models\WorkType;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use App\Services\WorkActivityService;
use App\Support\EntityRouteBinder;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class WorkActivityController extends Controller
{
    public function __construct(
        private readonly WorkActivityService $activities,
        private readonly AttendanceService $attendances,
        private readonly PayrollService $payrolls,
    ) {}

    public function index(PlantationEntity $plantationEntity): View
    {
        $activities = WorkActivity::query()
            ->forEntity($plantationEntity)
            ->with(['plantation', 'block', 'workType'])
            ->latest('activity_date')
            ->latest('id')
            ->paginate(15);

        return view('work-activities.index', [
            'entity' => $plantationEntity,
            'activities' => $activities,
        ]);
    }

    public function create(PlantationEntity $plantationEntity): View
    {
        return view('work-activities.create', [
            'entity' => $plantationEntity,
            'plantations' => Plantation::query()->forEntity($plantationEntity)->where('is_active', true)->orderBy('name')->get(),
            'workTypes' => WorkType::query()->forEntity($plantationEntity)->where('is_active', true)->orderBy('name')->get(),
            'blocks' => $this->blocksPayload($plantationEntity),
            'statuses' => WorkActivityStatus::cases(),
        ]);
    }

    public function store(StoreWorkActivityRequest $request, PlantationEntity $plantationEntity): RedirectResponse
    {
        $activity = $this->activities->create($plantationEntity, $request->payload());

        return redirect()
            ->route('plantation.work-activities.show', [$plantationEntity, $activity])
            ->with('success', 'Aktivitas kerja berhasil dibuat.');
    }

    public function show(PlantationEntity $plantationEntity, WorkActivity $workActivity): View
    {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);

        $workActivity->load([
            'plantation',
            'block',
            'workType',
            'attendances.worker',
            'attendances.payroll',
            'payrolls.worker',
            'payrolls.budgetItem',
        ]);

        $presentWithoutPayroll = $workActivity->attendances
            ->filter(fn (WorkAttendance $attendance) => $attendance->attendance_status === AttendanceStatus::PRESENT
                && ($attendance->payroll === null || $attendance->payroll->payroll_status->value !== 'POSTED'));

        return view('work-activities.show', [
            'entity' => $plantationEntity,
            'activity' => $workActivity,
            'workers' => Worker::query()->forEntity($plantationEntity)->where('is_active', true)->orderBy('name')->get(),
            'attendanceStatuses' => AttendanceStatus::cases(),
            'rateTypes' => PayrollRateType::cases(),
            'wageItems' => $this->payrolls->wageItemsFor($workActivity),
            'presentWithoutPayroll' => $presentWithoutPayroll,
            'summary' => $this->summary($workActivity),
            'money' => Money::class,
        ]);
    }

    public function edit(PlantationEntity $plantationEntity, WorkActivity $workActivity): View
    {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);

        return view('work-activities.edit', [
            'entity' => $plantationEntity,
            'activity' => $workActivity,
            'plantations' => Plantation::query()->forEntity($plantationEntity)->orderBy('name')->get(),
            'workTypes' => WorkType::query()->forEntity($plantationEntity)->orderBy('name')->get(),
            'blocks' => $this->blocksPayload($plantationEntity),
            'statuses' => WorkActivityStatus::cases(),
        ]);
    }

    public function update(
        UpdateWorkActivityRequest $request,
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);

        try {
            $this->activities->update($workActivity, $request->payload());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return redirect()
            ->route('plantation.work-activities.show', [$plantationEntity, $workActivity])
            ->with('success', 'Aktivitas kerja diperbarui.');
    }

    public function destroy(PlantationEntity $plantationEntity, WorkActivity $workActivity): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);

        try {
            $this->activities->delete($workActivity);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('plantation.work-activities.index', $plantationEntity)
            ->with('success', 'Aktivitas kerja dihapus.');
    }

    public function complete(PlantationEntity $plantationEntity, WorkActivity $workActivity): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);

        try {
            $this->activities->complete($workActivity);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Aktivitas ditandai selesai.');
    }

    public function cancel(PlantationEntity $plantationEntity, WorkActivity $workActivity): RedirectResponse
    {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);
        $this->activities->cancel($workActivity);

        return back()->with('success', 'Aktivitas dibatalkan.');
    }

    public function storeAttendance(
        StoreWorkAttendanceRequest $request,
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);

        $ids = Worker::query()
            ->forEntity($plantationEntity)
            ->whereIn('public_id', $request->validated('worker_public_ids'))
            ->pluck('id')
            ->all();

        $status = AttendanceStatus::from($request->validated('attendance_status') ?? AttendanceStatus::PRESENT->value);

        try {
            $this->attendances->assignWorkers($workActivity, $ids, $status);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Pekerja ditambahkan ke aktivitas.');
    }

    public function updateAttendance(
        UpdateWorkAttendanceRequest $request,
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
        WorkAttendance $workAttendance,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($workAttendance, $plantationEntity);

        if ((int) $workAttendance->work_activity_id !== (int) $workActivity->id) {
            abort(404);
        }

        try {
            $this->attendances->update($workAttendance, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Kehadiran diperbarui.');
    }

    public function destroyAttendance(
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
        WorkAttendance $workAttendance,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($workAttendance, $plantationEntity);

        if ((int) $workAttendance->work_activity_id !== (int) $workActivity->id) {
            abort(404);
        }

        try {
            $this->attendances->remove($workAttendance);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pekerja dihapus dari aktivitas.');
    }

    public function generatePayroll(
        GeneratePayrollRequest $request,
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);

        $attendanceIds = WorkAttendance::query()
            ->where('work_activity_id', $workActivity->id)
            ->whereIn('public_id', $request->validated('attendance_public_ids'))
            ->pluck('id')
            ->all();

        if ($attendanceIds === []) {
            return back()->with('error', 'Absensi tidak valid untuk aktivitas ini.');
        }

        try {
            $this->payrolls->generateDrafts($workActivity, $attendanceIds, $request->safe()->except('attendance_public_ids'));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Payroll draf dibuat. Periksa tarif sebelum posting.');
    }

    public function updatePayroll(
        UpdatePayrollRequest $request,
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
        WorkerPayroll $workerPayroll,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($workerPayroll, $plantationEntity);

        if ((int) $workerPayroll->work_activity_id !== (int) $workActivity->id) {
            abort(404);
        }

        try {
            $this->payrolls->updateDraft($workerPayroll, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Payroll draf diperbarui.');
    }

    public function postPayroll(
        PostPayrollRequest $request,
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
        WorkerPayroll $workerPayroll,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($workerPayroll, $plantationEntity);

        if ((int) $workerPayroll->work_activity_id !== (int) $workActivity->id) {
            abort(404);
        }

        $item = BudgetAllocationItem::query()
            ->where('public_id', $request->validated('budget_allocation_item_public_id'))
            ->whereHas('allocation', fn ($query) => $query->forEntity($plantationEntity))
            ->first();

        if (! $item instanceof BudgetAllocationItem) {
            return back()->with('error', 'Item anggaran tidak valid untuk unit ini.');
        }

        try {
            $this->payrolls->post($workerPayroll, $item);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Payroll diposting dan realisasi anggaran dicatat.');
    }

    public function cancelPayroll(
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
        WorkerPayroll $workerPayroll,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($workerPayroll, $plantationEntity);

        if ((int) $workerPayroll->work_activity_id !== (int) $workActivity->id) {
            abort(404);
        }

        $this->payrolls->cancel($workerPayroll);

        return back()->with('success', 'Payroll dibatalkan.');
    }

    public function markPayrollPaid(
        MarkPayrollPaidRequest $request,
        PlantationEntity $plantationEntity,
        WorkActivity $workActivity,
        WorkerPayroll $workerPayroll,
    ): RedirectResponse {
        EntityRouteBinder::assertOwnedBy($workActivity, $plantationEntity);
        EntityRouteBinder::assertOwnedBy($workerPayroll, $plantationEntity);

        if ((int) $workerPayroll->work_activity_id !== (int) $workActivity->id) {
            abort(404);
        }

        try {
            $this->payrolls->markPaid($workerPayroll, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Pembayaran tunai dicatat.');
    }

    /**
     * @return list<array{public_id: string, plantation_public_id: string, label: string}>
     */
    private function blocksPayload(PlantationEntity $entity): array
    {
        return Plantation::query()
            ->forEntity($entity)
            ->with(['blocks' => fn ($query) => $query->orderBy('code')])
            ->get()
            ->flatMap(function (Plantation $plantation) {
                return $plantation->blocks->map(fn ($block) => [
                    'public_id' => $block->public_id,
                    'plantation_public_id' => $plantation->public_id,
                    'label' => $block->code.($block->name ? ' — '.$block->name : ''),
                ]);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, int|string>
     */
    private function summary(WorkActivity $activity): array
    {
        $payrolls = $activity->payrolls;
        $posted = $payrolls->filter(fn (WorkerPayroll $payroll) => $payroll->isPosted());

        return [
            'workers' => $activity->attendances->count(),
            'present' => $activity->attendances->where('attendance_status', AttendanceStatus::PRESENT)->count(),
            'absent' => $activity->attendances->where('attendance_status', AttendanceStatus::ABSENT)->count(),
            'draft' => $payrolls->filter(fn (WorkerPayroll $payroll) => $payroll->isDraft())->count(),
            'posted' => $posted->count(),
            'total_wages' => $posted->reduce(fn (string $carry, WorkerPayroll $payroll) => Money::add($carry, $payroll->final_amount), '0.00'),
            'paid' => $posted->filter(fn (WorkerPayroll $payroll) => $payroll->payment_status->value === 'PAID')
                ->reduce(fn (string $carry, WorkerPayroll $payroll) => Money::add($carry, $payroll->final_amount), '0.00'),
            'unpaid' => $posted->filter(fn (WorkerPayroll $payroll) => $payroll->payment_status->value === 'UNPAID')
                ->reduce(fn (string $carry, WorkerPayroll $payroll) => Money::add($carry, $payroll->final_amount), '0.00'),
        ];
    }
}

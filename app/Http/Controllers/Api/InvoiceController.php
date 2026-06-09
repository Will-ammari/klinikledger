<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\InvoiceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly InvoiceCalculator $calculator
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $invoices = Invoice::query()
            ->with(['patient', 'appointment', 'items'])
            ->where('clinic_id', $request->user()->clinic_id)
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->string('status')->toString());
            })
            ->when($request->filled('patient_id'), function ($query) use ($request) {
                $query->where('patient_id', $request->integer('patient_id'));
            })
            ->latest()
            ->paginate($this->perPage($request));

        return InvoiceResource::collection($invoices);
    }

    public function store(StoreInvoiceRequest $request)
    {
        $validated = $request->validated();

        $invoice = DB::transaction(function () use ($request, $validated) {
            $totals = $this->calculator->calculate(
                $validated['items'],
                (float) ($validated['tax_rate'] ?? 0)
            );

            $invoice = Invoice::create([
                'clinic_id' => $request->user()->clinic_id,
                'patient_id' => $validated['patient_id'],
                'appointment_id' => $validated['appointment_id'] ?? null,
                'status' => InvoiceStatus::Draft,
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'total' => $totals['total'],
                'due_date' => $validated['due_date'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'line_total' => $this->calculator->lineTotal($item),
                ]);
            }

            $this->auditLogger->log(
                actor: $request->user(),
                action: AuditAction::InvoiceCreated,
                auditable: $invoice,
                metadata: [
                    'invoice_id' => $invoice->id,
                    'patient_id' => $invoice->patient_id,
                    'appointment_id' => $invoice->appointment_id,
                    'total' => $invoice->total,
                ],
                request: $request
            );

            return $invoice;
        });

        return response()->json([
            'data' => new InvoiceResource(
                $invoice->load(['patient', 'appointment', 'items'])
            ),
        ], 201);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::InvoiceViewed,
            auditable: $invoice,
            metadata: [
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new InvoiceResource(
                $invoice->load(['patient', 'appointment', 'items'])
            ),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($invoice, $validated, $request) {
            $invoiceData = collect($validated)
                ->only(['patient_id', 'appointment_id', 'due_date'])
                ->toArray();

            if (array_key_exists('items', $validated)) {
                $totals = $this->calculator->calculate(
                    $validated['items'],
                    (float) ($validated['tax_rate'] ?? 0)
                );

                $invoiceData = array_merge($invoiceData, $totals);
            }

            $invoice->update($invoiceData);

            if (array_key_exists('items', $validated)) {
                $invoice->items()->delete();

                foreach ($validated['items'] as $item) {
                    $invoice->items()->create([
                        'description' => $item['description'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => $this->calculator->lineTotal($item),
                    ]);
                }
            }

            $this->auditLogger->log(
                actor: $request->user(),
                action: AuditAction::InvoiceUpdated,
                auditable: $invoice,
                metadata: [
                    'invoice_id' => $invoice->id,
                    'changed_fields' => array_keys($validated),
                ],
                request: $request
            );
        });

        return response()->json([
            'data' => new InvoiceResource(
                $invoice->fresh()->load(['patient', 'appointment', 'items'])
            ),
        ]);
    }

    public function issue(Request $request, Invoice $invoice)
    {
        $this->authorize('issue', $invoice);

        if ($invoice->items()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => ['Invoice cannot be issued without items.'],
            ]);
        }

        $invoice->update([
            'status' => InvoiceStatus::Issued,
            'issued_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::InvoiceIssued,
            auditable: $invoice,
            metadata: [
                'invoice_id' => $invoice->id,
                'total' => $invoice->total,
            ],
            request: $request
        );

        return response()->json([
            'data' => new InvoiceResource(
                $invoice->fresh()->load(['patient', 'appointment', 'items'])
            ),
        ]);
    }

    public function markPaid(Request $request, Invoice $invoice)
    {
        $this->authorize('markPaid', $invoice);

        $invoice->update([
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::InvoiceMarkedPaid,
            auditable: $invoice,
            metadata: [
                'invoice_id' => $invoice->id,
                'total' => $invoice->total,
            ],
            request: $request
        );

        return response()->json([
            'data' => new InvoiceResource(
                $invoice->fresh()->load(['patient', 'appointment', 'items'])
            ),
        ]);
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorize('cancel', $invoice);

        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $request->user(),
            action: AuditAction::InvoiceCancelled,
            auditable: $invoice,
            metadata: [
                'invoice_id' => $invoice->id,
            ],
            request: $request
        );

        return response()->json([
            'data' => new InvoiceResource(
                $invoice->fresh()->load(['patient', 'appointment', 'items'])
            ),
        ]);
    }
}

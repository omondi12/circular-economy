<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\WasteCategories;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lot 2 service orders: Finance/Admin attach a client's order for disposal work (e.g. a hospital's Local Service
 * Order), with its scanned document, to the RM's Lot 2 collections it covers. The document is private, streamed only
 * to people who can see that RM.
 */
class ServiceOrderController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless(Auth::user()->canManageLsoFinance(), 403);

        $rms = User::visibleRmsFor(Auth::user())->orderBy('name')->get(['id', 'name']);
        $rm = $rms->firstWhere('id', (int) $request->query('rm_id'));

        return view('admin.service-orders.create', [
            'rms' => $rms,
            'rm' => $rm,
            'collections' => $rm ? $this->attachable($rm) : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()->canManageLsoFinance(), 403);

        $data = $request->validate([
            'rm_id' => ['required', 'integer'],
            'reference_number' => ['required', 'string', 'max:100'],
            'customer_name' => ['required', 'string', 'max:255'],
            'order_date' => ['nullable', 'date'],
            'amount' => ['nullable', 'integer', 'min:1'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
            'collection_ids' => ['required', 'array', 'min:1'],
            'collection_ids.*' => ['integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], ['collection_ids.required' => 'Tick the collections this order covers.']);

        $rm = User::visibleRmsFor(Auth::user())->findOrFail((int) $data['rm_id']);
        $path = $request->file('document')->store('service-order-documents', 'local');

        try {
            $order = DB::transaction(function () use ($data, $rm, $path, $request) {
                $collections = Collection::query()->whereKey($data['collection_ids'])->lockForUpdate()->get();

                if ($collections->count() !== count(array_unique($data['collection_ids']))
                    || $collections->contains(fn (Collection $c) => $c->user_id !== $rm->id || (int) $c->lot !== WasteCategories::LOT_DISPOSAL || $c->service_order_id !== null)) {
                    throw ValidationException::withMessages(['collection_ids' => 'Only this RM\'s Lot 2 collections without a service order can be attached.']);
                }

                $order = ServiceOrder::create([
                    'reference_number' => trim($data['reference_number']),
                    'customer_name' => trim($data['customer_name']),
                    'user_id' => $rm->id,
                    'order_date' => $data['order_date'] ?? null,
                    'amount_minor' => isset($data['amount']) ? $data['amount'] * 100 : null,
                    'document_path' => $path,
                    'document_original_filename' => $request->file('document')->getClientOriginalName(),
                    'document_mime' => $request->file('document')->getClientMimeType(),
                    'notes' => $data['notes'] ?? null,
                    'created_by' => Auth::id(),
                ]);
                Collection::query()->whereKey($collections->pluck('id'))->update(['service_order_id' => $order->id]);

                AuditLog::record('service_order.created', $order, [
                    'reference_number' => $order->reference_number,
                    'customer_name' => $order->customer_name,
                    'rm' => $rm->name,
                    'amount' => $order->amount_minor !== null ? $order->amount_minor / 100 : null,
                    'collection_ids' => $collections->pluck('id')->all(),
                ]);

                return $order;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }

        $order->load('collections.lsoLot');
        $expected = $order->expectedRevenueMinor();
        $message = "Service order {$order->reference_number} attached to {$order->collections->count()} collections.";
        if ($order->amount_minor !== null && $expected !== $order->amount_minor) {
            $message .= ' Note: the order says KES '.number_format($order->amount_minor / 100).' but the collections come to KES '.number_format($expected / 100).' at the contract rates.';
        }

        return redirect()->route('admin.lso-lots.index')->with('status', $message);
    }

    public function document(ServiceOrder $serviceOrder): StreamedResponse
    {
        $viewer = Auth::user();
        // The RM themselves; staff who can see that RM (supervisors only their team). Never another RM.
        abort_unless($viewer->id === $serviceOrder->user_id
            || (! $viewer->isRm() && User::visibleRmsFor($viewer)->whereKey($serviceOrder->user_id)->exists()), 403);
        abort_unless(Storage::disk('local')->exists($serviceOrder->document_path), 404);

        return Storage::disk('local')->response($serviceOrder->document_path, $serviceOrder->document_original_filename, [
            'Content-Type' => $serviceOrder->document_mime ?? 'application/octet-stream',
        ]);
    }

    /**
     * The RM's Lot 2 collections not yet under a service order, newest first.
     */
    private function attachable(User $rm)
    {
        return Collection::query()->with('lsoLot')->where('user_id', $rm->id)->where('lot', WasteCategories::LOT_DISPOSAL)
            ->whereNull('service_order_id')->orderByDesc('collection_date')->get();
    }
}

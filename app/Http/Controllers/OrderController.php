<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OrderBook;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WIC's Orders dashboard (the boss: every order in WIC, which Agent 360 then reads): sale orders (Lot 1 LSOs) and
 * disposal orders (Lot 2 service orders) with their value, what has been received or confirmed, and status. On the
 * public dashboard link like the other boss pages; documents only open for signed-in staff allowed to see them.
 */
class OrderController extends Controller
{
    public function index(Request $request, OrderBook $book): View
    {
        $filters = $this->filters($request);
        $rows = $this->filtered($book->rows(), $filters);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('orders.index', [
            'summary' => $book->summary($rows),
            // The headline, one row per lot: Lot 1 sale orders, Lot 2 disposal orders
            'lots' => collect(OrderBook::KINDS)->map(fn ($label, $kind) => $book->summary($rows->where('kind', $kind)->values())),
            'orders' => new LengthAwarePaginator($rows->forPage($page, 25)->values(), $rows->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]),
            'filters' => $filters,
            'rms' => User::assignableRms()->orderBy('name')->pluck('name', 'id'),
            'statuses' => $book->rows()->pluck('status')->unique()->sort()->mapWithKeys(fn ($s) => [$s => OrderBook::statusLabel($s)]),
            'canOpenDocuments' => Auth::check() && ! Auth::user()->isRm(),
        ]);
    }

    public function export(Request $request, OrderBook $book): StreamedResponse
    {
        $rows = $this->filtered($book->rows(), $this->filters($request));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Order', 'Type', 'Customer', 'Client', 'RM', 'Status', 'Order value (KES)', 'Received / confirmed (KES)', 'Westport earned (KES)', 'Not yet received / confirmed (KES)'], escape: '');
            foreach ($rows as $o) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'".$v : $v, [
                    $o['date']?->toDateString(), $o['reference'], $o['kind_label'], $o['customer'], $o['client'], $o['rm']?->name, OrderBook::statusLabel($o['status']),
                    $o['value'] / 100, $o['received'] / 100, $o['earned'] / 100, $o['outstanding'] !== null ? $o['outstanding'] / 100 : null,
                ]), escape: '');
            }
            fclose($out);
        }, 'wic-orders-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{q: string, kind: string, status: string, rm_id: string, from: string, to: string}
     */
    private function filters(Request $request): array
    {
        $date = fn (string $k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($k)) ? (string) $request->query($k) : '';

        return [
            'q' => trim((string) $request->query('q')),
            'kind' => array_key_exists((string) $request->query('kind'), OrderBook::KINDS) ? (string) $request->query('kind') : '',
            'status' => (string) $request->query('status'),
            'rm_id' => ctype_digit((string) $request->query('rm_id')) ? (string) $request->query('rm_id') : '',
            'from' => $date('from'),
            'to' => $date('to'),
        ];
    }

    private function filtered(Collection $rows, array $f): Collection
    {
        $q = mb_strtolower($f['q']);

        return $rows
            ->when($f['kind'], fn ($c) => $c->where('kind', $f['kind']))
            ->when($f['status'], fn ($c) => $c->where('status', $f['status']))
            ->when($f['rm_id'], fn ($c) => $c->where('rm_id', (int) $f['rm_id']))
            ->when($f['from'], fn ($c) => $c->filter(fn ($r) => $r['date'] && $r['date']->toDateString() >= $f['from']))
            ->when($f['to'], fn ($c) => $c->filter(fn ($r) => $r['date'] && $r['date']->toDateString() <= $f['to']))
            ->when($q !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['reference'].' '.$r['customer'].' '.$r['client'].' '.$r['rm']?->name), $q)))
            ->values();
    }
}

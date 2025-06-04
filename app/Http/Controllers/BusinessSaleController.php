<?php

namespace App\Http\Controllers;

use App\Models\BusinessSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BusinessSaleController extends Controller
{
    // List all sales/notes for a business
    public function index($businessId)
    {
        $sales = BusinessSale::where('business_id', $businessId)->orderBy('date', 'desc')->get();
        return response()->json($sales);
    }

    // Store a new sale/note
    public function store(Request $request, $businessId)
    {
        $request->validate([
            'date' => 'nullable|date',
            'amount' => 'nullable|numeric',
            'type' => 'required|string',
            'note' => 'nullable|string',
            'details' => 'nullable|array',
        ]);

        $sale = BusinessSale::create([
            'business_id' => $businessId,
            'date' => $request->date,
            'amount' => $request->amount,
            'type' => $request->type,
            'note' => $request->note,
            'details' => $request->details,
            'created_by' => Auth::id(),
        ]);

        return response()->json($sale, 201);
    }

    // Show a single sale/note
    public function show($businessId, $id)
    {
        $sale = BusinessSale::where('business_id', $businessId)->findOrFail($id);
        return response()->json($sale);
    }

    // Update a sale/note
    public function update(Request $request, $businessId, $id)
    {
        $sale = BusinessSale::where('business_id', $businessId)->findOrFail($id);
        $request->validate([
            'date' => 'nullable|date',
            'amount' => 'nullable|numeric',
            'type' => 'required|string',
            'note' => 'nullable|string',
            'details' => 'nullable|array',
        ]);
        $sale->update($request->only(['date', 'amount', 'type', 'note', 'details']));
        return response()->json($sale);
    }

    // Delete a sale/note
    public function destroy($businessId, $id)
    {
        $sale = BusinessSale::where('business_id', $businessId)->findOrFail($id);
        $sale->delete();
        return response()->json(['message' => 'Sale/note deleted']);
    }

    // Sales summary for reporting (total sales per day or month)
    public function summary(Request $request, $businessId)
    {
        $group = $request->input('grouping', 'week'); // 'day', 'week' or 'month', match frontend param
        $query = BusinessSale::where('business_id', $businessId)
            ->where('type', 'sale');

        if ($request->filled('from')) {
            $query->where('date', '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->where('date', '<=', $request->input('to'));
        }

        if ($group === 'month') {
            $summary = $query->selectRaw("to_char(date, 'YYYY-MM') as date, MIN(date) as period_start, MAX(date) as period_end, SUM(amount)::float as total_sales, COUNT(*) as sales_count, AVG(amount)::float as avg_sale, MIN(amount)::float as min_sale, MAX(amount)::float as max_sale")
                ->groupByRaw("to_char(date, 'YYYY-MM')")
                ->orderBy('date')
                ->get();
        } elseif ($group === 'week') {
            $summary = $query->selectRaw("to_char(date, 'IYYY-IW') as date, MIN(date) as period_start, MAX(date) as period_end, SUM(amount)::float as total_sales, COUNT(*) as sales_count, AVG(amount)::float as avg_sale, MIN(amount)::float as min_sale, MAX(amount)::float as max_sale")
                ->groupByRaw("to_char(date, 'IYYY-IW')")
                ->orderBy('date')
                ->get();
        } elseif ($group === 'day') {
            $summary = $query->selectRaw("date as date, date as period_start, date as period_end, SUM(amount)::float as total_sales, COUNT(*) as sales_count, AVG(amount)::float as avg_sale, MIN(amount)::float as min_sale, MAX(amount)::float as max_sale")
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        } else {
            // fallback: group by day
            $summary = $query->selectRaw("date as date, date as period_start, date as period_end, SUM(amount)::float as total_sales, COUNT(*) as sales_count, AVG(amount)::float as avg_sale, MIN(amount)::float as min_sale, MAX(amount)::float as max_sale")
                ->groupBy('date')
                ->orderBy('date')
                ->get();
        }

        // Optionally, fill missing periods for chart continuity
        if ($request->filled('from') && $request->filled('to')) {
            $from = $request->input('from');
            $to = $request->input('to');
            $result = collect();
            if ($group === 'month') {
                $period = \Carbon\Carbon::parse($from)->startOfMonth();
                $end = \Carbon\Carbon::parse($to)->startOfMonth();
                $summaryByDate = $summary->keyBy('date');
                while ($period <= $end) {
                    $key = $period->format('Y-m');
                    $row = isset($summaryByDate[$key]) ? $summaryByDate[$key] : [
                        'date' => $key,
                        'period_start' => $period->copy()->startOfMonth()->toDateString(),
                        'period_end' => $period->copy()->endOfMonth()->toDateString(),
                        'total_sales' => 0,
                        'sales_count' => 0,
                        'avg_sale' => null,
                        'min_sale' => null,
                        'max_sale' => null,
                    ];
                    $result->push($row);
                    $period->addMonth();
                }
            } elseif ($group === 'week') {
                $period = \Carbon\Carbon::parse($from)->startOfWeek();
                $end = \Carbon\Carbon::parse($to)->startOfWeek();
                $summaryByDate = $summary->keyBy('date');
                while ($period <= $end) {
                    $key = $period->format('o-W');
                    $row = isset($summaryByDate[$key]) ? $summaryByDate[$key] : [
                        'date' => $key,
                        'period_start' => $period->copy()->startOfWeek()->toDateString(),
                        'period_end' => $period->copy()->endOfWeek()->toDateString(),
                        'total_sales' => 0,
                        'sales_count' => 0,
                        'avg_sale' => null,
                        'min_sale' => null,
                        'max_sale' => null,
                    ];
                    $result->push($row);
                    $period->addWeek();
                }
            } elseif ($group === 'day') {
                $period = \Carbon\Carbon::parse($from);
                $end = \Carbon\Carbon::parse($to);
                $summaryByDate = $summary->keyBy('date');
                while ($period <= $end) {
                    $key = $period->format('Y-m-d');
                    $row = isset($summaryByDate[$key]) ? $summaryByDate[$key] : [
                        'date' => $key,
                        'period_start' => $key,
                        'period_end' => $key,
                        'total_sales' => 0,
                        'sales_count' => 0,
                        'avg_sale' => null,
                        'min_sale' => null,
                        'max_sale' => null,
                    ];
                    $result->push($row);
                    $period->addDay();
                }
            }
            return response()->json($result->values());
        }

        return response()->json($summary);
    }
}

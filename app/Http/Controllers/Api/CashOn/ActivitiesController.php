<?php

namespace App\Http\Controllers\Api\CashOn;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deposit;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ActivitiesController extends Controller
{

    public function walletTransactions(){

        $result=WalletTransaction::where('user_id', auth()->user()->id)->orderBy('id', 'desc')->get();
        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => 'Wallet Transactions List'
        ]);
    }
    public function walletTransactionsDetail($id){

        $result=WalletTransaction::where('user_id', auth()->user()->id)->where('id', $id)->first();
        if (!$result){
            return response()->json([
                'status' => false,
                'message' => 'Wallet Transactions not found'
            ]);
        }
        return response()->json([
            'status' => true,
            'data' => $result,
            'message' => 'Wallet Transactions'
        ]);
    }

    function allActivities(Request $request)
    {
        $user =$request->user();

    // Get query parameters for filtering and pagination
    $perPage =$request->query('per_page', 20);
    $page =$request->query('page', 1);
    $type =$request->query('type'); // 'credit' or 'debit'
    $category =$request->query('category'); // specific category filter
    $status =$request->query('status', 'completed'); // default to completed
    $dateFrom =$request->query('date_from');
    $dateTo =$request->query('date_to');
    $search =$request->query('search');

    // Build the query
    $query = Activity::where('user_id', $user->id)
        ->where('is_visible', true)
        ->with(['related', 'initiatedBy']);

    // Apply filters
    if ($type) {
        $query->where('type', $type);
    }

    if ($category) {
        $query->where('category', $category);
    }

    if ($status) {
        $query->where('status', $status);
    }

    // Date range filter
    if ($dateFrom) {
        $query->whereDate('created_at', '>=', $dateFrom);
    }

    if ($dateTo) {
        $query->whereDate('created_at', '<=', $dateTo);
    }

    // Search filter
    if ($search) {
        $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('reference', 'like', "%{$search}%");
        });
    }

    // Get paginated results
    $activities =$query->orderBy('created_at', 'desc')
        ->paginate($perPage);

    // Calculate summary statistics
    $summary = [
        'total_credits' => Activity::where('user_id', $user->id)
            ->where('type', 'credit')
            ->where('status', 'completed')
            ->sum('amount'),

        'total_debits' => Activity::where('user_id', $user->id)
            ->where('type', 'debit')
            ->where('status', 'completed')
            ->sum('amount'),

        'total_transactions' => Activity::where('user_id', $user->id)
            ->where('status', 'completed')
            ->count(),

        'pending_transactions' => Activity::where('user_id', $user->id)
            ->where('status', 'pending')
            ->count(),
    ];

    // Calculate net balance change
    $summary['net_change'] = $summary['total_credits'] - $summary['total_debits'];

    // Group by category for breakdown
    $categoryBreakdown = Activity::where('user_id', $user->id)
        ->where('status', 'completed')
        ->selectRaw('category, type, SUM(amount) as total, COUNT(*) as count')
        ->groupBy('category', 'type')
        ->get()
        ->groupBy('category');

    return response()->json([
        'status' => true,
        'message' => 'Activities retrieved successfully',
        'data' => [
            'activities' => $activities->items(),
            'pagination' => [
                'current_page' => $activities->currentPage(),
                'last_page' => $activities->lastPage(),
                'per_page' => $activities->perPage(),
                'total' => $activities->total(),
                'from' => $activities->firstItem(),
                'to' => $activities->lastItem(),
            ],
            'summary' => $summary,
            'category_breakdown' => $categoryBreakdown,
            'filters_applied' => [
                'type' => $type,
                'category' => $category,
                'status' => $status,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'search' => $search,
            ]
        ]
    ]);
}
    function allActivitiesS(Request $request)
    {
        $user =$request->user();

    // Validate query parameters
    $validated =$request->validate([
        'per_page' => 'integer|min:1|max:100',
        'type' => 'in:credit,debit',
        'category' => 'string',
        'status' => 'in:pending,processing,completed,failed,cancelled,reversed',
        'date_from' => 'date',
        'date_to' => 'date|after_or_equal:date_from',
        'search' => 'string|max:255'
    ]);

    $query = Activity::where('user_id', $user->id)
        ->where('is_visible', true)
        ->with(['related']);

    // Apply validated filters
    foreach (['type', 'category', 'status'] as $filter) {
        if (isset($validated[$filter])) {
            $query->where($filter,$validated[$filter]);
        }
    }

    if (isset($validated['date_from'])) {
        $query->whereDate('created_at', '>=', $validated['date_from']);
    }

    if (isset($validated['date_to'])) {
        $query->whereDate('created_at', '<=', $validated['date_to']);
    }

    if (isset($validated['search'])) {
        $query->where('title', 'like', "%{$validated['search']}%");
    }

    $activities =$query->orderBy('created_at', 'desc')
        ->paginate($validated['per_page'] ?? 20);

    return response()->json([
        'status' => true,
        'data' => $activities
    ]);
}

    function activityDetails(Request $request,$id)
{
    $user =$request->user();

    // Find the activity
    $activity = Activity::where('id', $id)
        ->where('user_id', $user->id)
        ->first();

    if (!$activity) {
        return response()->json([
            'status' => false,
            'message' => 'Activity not found'
        ], 404);
    }

return response()->json([
    'status' => true,
    'data' => [
        'id' => $activity->id,
        'reference' => $activity->reference,
        'type' => $activity->type,
        'category' => $activity->category,
        'amount' => number_format($activity->amount, 2),
        'status' => $activity->status,
        'title' => $activity->title,
        'description' => $activity->description,
        'date' => $activity->created_at->format('M d, Y H:i'),
        'metadata' => $activity->metadata
    ]
]);
}
    public function getAllUserDeposits(Request $request): JsonResponse
    {
        try {
            $authenticatedUser = $request->user();

            $userDeposits = Deposit::where('user_id', $authenticatedUser->id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Deposits retrieved successfully',
                'data' => $userDeposits,
                'count' => $userDeposits->count()
            ]);

        } catch (\Exception $exception) {
            log::critical('Getting all deposits failed', ['message' => $exception->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deposits',
                'error' => $exception->getMessage()
            ]);
        }
    }

    public function getDepositDetails(Request $request, int $depositId): JsonResponse
    {
        try {
            $authenticatedUser = $request->user();

            $deposit = Deposit::where('id', $depositId)
                ->where('user_id', $authenticatedUser->id)
                ->first();

            if (!$deposit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Deposit not found or access denied'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Deposit details retrieved successfully',
                'data' => $deposit
            ]);

        } catch (\Exception $exception) {
            log::critical('Getting  deposits details failed', ['message' => $exception->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve deposit details',
                'error' => $exception->getMessage()
            ]);
        }
    }
}

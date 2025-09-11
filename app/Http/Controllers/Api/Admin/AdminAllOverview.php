<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\AuditHelper;
use App\Models\Deposit;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminAllOverview
{
    public function overview(Request $request)
    {
        try {
            // Check if request wants to bypass cache
            $useCache = !$request->has('refresh');
            $cacheKey = 'admin_dashboard_' . auth('admin')->id();
            $cacheDuration = 5; // minutes

            // Return cached data if available and cache is not bypassed
            if ($useCache && Cache::has($cacheKey)) {
                return response()->json([
                    'status' => true,
                    'message' => 'Dashboard data retrieved from cache',
                    'data' => Cache::get($cacheKey),
                    'cached' => true
                ], 200);
            }

            // Get date ranges
            $today = Carbon::today();
            $yesterday = Carbon::yesterday();
            $currentMonth = Carbon::now()->startOfMonth();
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
            $last7Days = Carbon::now()->subDays(7);

            // Get time period from request or use default
            $period = $request->input('period', 'month');
            $customStart = $request->input('start_date');
            $customEnd = $request->input('end_date');

            // Handle custom date ranges
            if ($period === 'custom' && $customStart && $customEnd) {
                $startDate = Carbon::parse($customStart)->startOfDay();
                $endDate = Carbon::parse($customEnd)->endOfDay();
            } else {
                // Set date range based on period
                switch ($period) {
                    case 'today':
                        $startDate = $today;
                        $endDate = Carbon::now();
                        break;
                    case 'yesterday':
                        $startDate = $yesterday;
                        $endDate = $yesterday->copy()->endOfDay();
                        break;
                    case 'week':
                        $startDate = Carbon::now()->startOfWeek();
                        $endDate = Carbon::now();
                        break;
                    case 'month':
                    default:
                        $startDate = $currentMonth;
                        $endDate = Carbon::now();
                        break;
                    case 'year':
                        $startDate = Carbon::now()->startOfYear();
                        $endDate = Carbon::now();
                        break;
                }
            }

            // Get admin name for personalized greeting
            $admin = auth('admin')->user();

            // Get key metrics with percentage changes
            $metrics = $this->getKeyMetrics($startDate, $endDate, $period);

            // Get transaction data for chart
            $transactionData = $this->getTransactionChartData($period);

            // Get revenue data for gauge chart
            $revenueData = $this->getRevenueData();

            // Get recent transactions
            $limit = $request->input('transaction_limit', 5);
            $recentTransactions = $this->getRecentTransactions($limit);

            // Get pending approvals
            $approvalLimit = $request->input('approval_limit', 5);
            $pendingApprovals = $this->getPendingApprovals($approvalLimit);

            // Prepare response data
            $responseData = [
                'admin_name' => $admin->name,
                'metrics' => $metrics,
                'transaction_data' => [
                    'chart_data' => $transactionData,
                    'total_value' => $this->getTotalTransactionValue($last7Days),
                    'period' => 'Last 7 days',
                    'formatted_total' => $this->formatCurrency($this->getTotalTransactionValue($last7Days))
                ],
                'revenue_data' => $revenueData,
                'recent_transactions' => $recentTransactions,
                'pending_approvals' => $pendingApprovals,
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'period' => $period
                ],
                'timestamp' => now()->toDateTimeString()
            ];

            // Cache the response
            if ($useCache) {
                Cache::put($cacheKey, $responseData, now()->addMinutes($cacheDuration));
            }

            // Log dashboard access
            AuditHelper::log([
                'action' => 'dashboard_viewed',
                'description' => 'Admin viewed dashboard',
                'metadata' => [
                    'period' => $period,
                    'custom_range' => $period === 'custom',
                    'ip_address' => $request->ip()
                ],
                'risk_level' => 'low'
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Dashboard data retrieved successfully',
                'data' => $responseData,
                'cached' => false
            ], 200);

        } catch (\Exception $e) {
            // Log error
            \Log::error('Dashboard error: ' . $e->getMessage(), [
                'admin_id' => auth('admin')->id() ?? 'unauthenticated',
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'error_code' => 'DASHBOARD_ERROR'
            ], 500);
        }
    }

    /**
     * Get key metrics with percentage changes
     */
    private function getKeyMetrics($startDate, $endDate, $period)
    {
        // Calculate comparison period
        $periodDuration = $startDate->diffInDays($endDate) + 1;
        $comparisonStartDate = $startDate->copy()->subDays($periodDuration);
        $comparisonEndDate = $startDate->copy()->subDay();

        // Total Deposits
        $currentPeriodDeposits = Deposit::where('status', 'successful')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $previousPeriodDeposits = Deposit::where('status', 'successful')
            ->whereBetween('created_at', [$comparisonStartDate, $comparisonEndDate])
            ->sum('amount');

        $depositPercentage = $this->calculatePercentageChange($previousPeriodDeposits, $currentPeriodDeposits);

        // Total Withdrawals
        $currentPeriodWithdrawals = Withdrawal::where('status', 'successful')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $previousPeriodWithdrawals = Withdrawal::where('status', 'successful')
            ->whereBetween('created_at', [$comparisonStartDate, $comparisonEndDate])
            ->sum('amount');

        $withdrawalPercentage = $this->calculatePercentageChange($previousPeriodWithdrawals, $currentPeriodWithdrawals);

        // Active Users
        $currentPeriodActiveUsers = User::where('status', 'active')
            ->where('last_login_at', '>=', $startDate)
            ->where('last_login_at', '<=', $endDate)
            ->count();

        $previousPeriodActiveUsers = User::where('status', 'active')
            ->where('last_login_at', '>=', $comparisonStartDate)
            ->where('last_login_at', '<=', $comparisonEndDate)
            ->count();

        $activeUsersPercentage = $this->calculatePercentageChange($previousPeriodActiveUsers, $currentPeriodActiveUsers);

        // Revenue
        $currentPeriodRevenue = Revenue::whereBetween('created_at', [$startDate, $endDate])
            ->sum('amount');

        $previousPeriodRevenue = Revenue::whereBetween('created_at', [$comparisonStartDate, $comparisonEndDate])
            ->sum('amount');

        $revenuePercentage = $this->calculatePercentageChange($previousPeriodRevenue, $currentPeriodRevenue);

        // Get comparison period description
        $comparisonDesc = $this->getComparisonDescription($period);

        return [
            'total_deposit' => [
                'value' => $currentPeriodDeposits,
                'formatted' => $this->formatCurrency($currentPeriodDeposits),
                'percentage' => $depositPercentage,
                'trend' => $depositPercentage >= 0 ? 'up' : 'down',
                'comparison' => $comparisonDesc
            ],
            'total_withdrawals' => [
                'value' => $currentPeriodWithdrawals,
                'formatted' => $this->formatCurrency($currentPeriodWithdrawals),
                'percentage' => $withdrawalPercentage,
                'trend' => $withdrawalPercentage >= 0 ? 'up' : 'down',
                'comparison' => $comparisonDesc
            ],
            'active_users' => [
                'value' => $currentPeriodActiveUsers,
                'formatted' => number_format($currentPeriodActiveUsers),
                'percentage' => $activeUsersPercentage,
                'trend' => $activeUsersPercentage >= 0 ? 'up' : 'down',
                'comparison' => $comparisonDesc
            ],
            'revenue' => [
                'value' => $currentPeriodRevenue,
                'formatted' => $this->formatCurrency($currentPeriodRevenue),
                'percentage' => $revenuePercentage,
                'trend' => $revenuePercentage >= 0 ? 'up' : 'down',
                'comparison' => $comparisonDesc
            ]
        ];
    }

    /**
     * Get comparison period description
     */
    private function getComparisonDescription($period)
    {
        switch ($period) {
            case 'today':
                return 'from yesterday';
            case 'yesterday':
                return 'from previous day';
            case 'week':
                return 'from previous week';
            case 'month':
                return 'from last month';
            case 'year':
                return 'from last year';
            case 'custom':
                return 'from previous period';
            default:
                return 'from previous period';
        }
    }

    /**
     * Calculate percentage change between two values
     */
    private function calculatePercentageChange($oldValue, $newValue)
    {
        if ($oldValue == 0) {
            return $newValue > 0 ? 100 : 0;
        }

        return round((($newValue - $oldValue) / $oldValue) * 100, 1);
    }

    /**
     * Format currency value
     */
    private function formatCurrency($amount)
    {
        return '₦' . number_format($amount, 2);
    }

    /**
     * Get transaction data for chart
     */
    private function getTransactionChartData($period = 'month')
    {
        $currentYear = Carbon::now()->year;

        // Adjust chart data based on period
        switch ($period) {
            case 'today':
            case 'yesterday':
                // Hourly data for today/yesterday
                $hours = range(0, 23);
                $data = [];

                $targetDate = $period === 'today' ? Carbon::today() : Carbon::yesterday();

                foreach ($hours as $hour) {
                    $startHour = $targetDate->copy()->addHours($hour);
                    $endHour = $targetDate->copy()->addHours($hour + 1);

                    $deposits = Deposit::where('status', 'successful')
                        ->whereBetween('created_at', [$startHour, $endHour])
                        ->sum('amount');

                    $withdrawals = Withdrawal::where('status', 'successful')
                        ->whereBetween('created_at', [$startHour, $endHour])
                        ->sum('amount');

                    $data[] = [
                        'label' => sprintf('%02d:00', $hour),
                        'deposits' => $deposits,
                        'withdrawals' => $withdrawals,
                        'total' => $deposits + $withdrawals
                    ];
                }
                break;

            case 'week':
                // Daily data for current week
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                $data = [];

                $startOfWeek = Carbon::now()->startOfWeek();

                foreach ($days as $index => $day) {
                    $currentDay = $startOfWeek->copy()->addDays($index);

                    $deposits = Deposit::where('status', 'successful')
                        ->whereDate('created_at', $currentDay)
                        ->sum('amount');

                    $withdrawals = Withdrawal::where('status', 'successful')
                        ->whereDate('created_at', $currentDay)
                        ->sum('amount');

                    $data[] = [
                        'label' => $day,
                        'deposits' => $deposits,
                        'withdrawals' => $withdrawals,
                        'total' => $deposits + $withdrawals
                    ];
                }
                break;

            case 'year':
                // Monthly data for current year
                $months = [
                    'January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December'
                ];
                $data = [];

                foreach ($months as $index => $month) {
                    $monthNumber = $index + 1;

                    $deposits = Deposit::where('status', 'successful')
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $monthNumber)
                        ->sum('amount');

                    $withdrawals = Withdrawal::where('status', 'successful')
                        ->whereYear('created_at', $currentYear)
                        ->whereMonth('created_at', $monthNumber)
                        ->sum('amount');

                    $data[] = [
                        'label' => $month,
                        'deposits' => $deposits,
                        'withdrawals' => $withdrawals,
                        'total' => $deposits + $withdrawals
                    ];
                }
                break;

            case 'month':
            default:
                // Daily data for current month
                $currentMonth = Carbon::now()->month;
                $daysInMonth = Carbon::now()->daysInMonth;
                $data = [];

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $currentDate = Carbon::createFromDate(null, $currentMonth, $day);

                    $deposits = Deposit::where('status', 'successful')
                        ->whereDate('created_at', $currentDate)
                        ->sum('amount');

                    $withdrawals = Withdrawal::where('status', 'successful')
                        ->whereDate('created_at', $currentDate)
                        ->sum('amount');

                    $data[] = [
                        'label' => $day,
                        'deposits' => $deposits,
                        'withdrawals' => $withdrawals,
                        'total' => $deposits + $withdrawals
                    ];
                }
                break;
        }

        return $data;
    }

    /**
     * Get total transaction value for a period
     */
    private function getTotalTransactionValue($startDate)
    {
        $deposits = Deposit::where('status', 'successful')
            ->where('created_at', '>=', $startDate)
            ->sum('amount');

        $withdrawals = Withdrawal::where('status', 'successful')
            ->where('created_at', '>=', $startDate)
            ->sum('amount');

        return $deposits + $withdrawals;
    }

    /**
     * Get revenue data for gauge chart
     */
    private function getRevenueData()
    {
        $today = Carbon::today();
        $currentRevenue = Revenue::whereDate('created_at', $today)->sum('amount');

        // Get target and thresholds for gauge chart
        $dailyTarget = config('app.daily_revenue_target', 20000);
        $lowThreshold = $dailyTarget * 0.25; // 25% of target
        $mediumThreshold = $dailyTarget * 0.5; // 50% of target
        $highThreshold = $dailyTarget * 0.75; // 75% of target

        // Calculate percentage safely
        $percentage = $dailyTarget > 0 ? ($currentRevenue / $dailyTarget) * 100 : 0;

        return [
            'current' => $currentRevenue,
            'formatted' => $this->formatCurrency($currentRevenue),
            'target' => $dailyTarget,
            'formatted_target' => $this->formatCurrency($dailyTarget),
            'percentage' => round($percentage, 1),
            'thresholds' => [
                'low' => $lowThreshold,
                'medium' => $mediumThreshold,
                'high' => $highThreshold
            ],
            'status' => $this->getRevenueStatus($percentage)
        ];
    }

    /**
     * Get revenue status based on percentage
     */
    private function getRevenueStatus($percentage)
    {
        if ($percentage >= 100) {
            return 'excellent';
        } elseif ($percentage >= 75) {
            return 'good';
        } elseif ($percentage >= 50) {
            return 'average';
        } elseif ($percentage >= 25) {
            return 'below_average';
        } else {
            return 'poor';
        }
    }

    /**
     * Get recent transactions
     */
    private function getRecentTransactions($limit = 5)
    {
        // Combine deposits, withdrawals and payouts
        $deposits = Deposit::with('user')
            ->select(
                'id',
                'user_id',
                DB::raw("'Deposit' as type"),
                'amount',
                'status',
                'created_at'
            )
            ->latest()
            ->limit($limit);

        $withdrawals = Withdrawal::with('user')
            ->select(
                'id',
                'user_id',
                DB::raw("'Withdrawal' as type"),
                'amount',
                'status',
                'created_at'
            )
            ->latest()
            ->limit($limit);

        $payouts = Withdrawal::with('user')
            ->select(
                'id',
                'user_id',
                DB::raw("'Boost Payout' as type"),
                'amount',
                'status',
                'created_at'
            )
            ->latest()
            ->limit($limit);

        $transactions = $deposits->union($withdrawals)
            ->union($payouts)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'user' => [
                    'id' => $transaction->user->id ?? null,
                    'name' => $transaction->user->name ?? 'Unknown User',
                    'email' => $transaction->user->email ?? null,
                    'avatar' => $transaction->user->avatar ?? null
                ],
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'formatted_amount' => $this->formatCurrency($transaction->amount),
                'status' => $transaction->status,
                'status_color' => $this->getStatusColor($transaction->status),
                'date' => $transaction->created_at->format('d M Y, H:i'),
                'timestamp' => $transaction->created_at->toISOString()
            ];
        });
    }

    /**
     * Get status color for UI
     */
    private function getStatusColor($status)
    {
        return match(strtolower($status)) {
            'successful', 'success', 'completed' => 'green',
            'pending', 'processing' => 'yellow',
            'failed', 'rejected', 'cancelled' => 'red',
            default => 'gray'
        };
    }

    /**
     * Get pending approvals
     */
    private function getPendingApprovals($limit = 5)
    {
        $pendingUsers = User::where('status', 'pending')
            ->select('id', 'name', 'email', 'avatar', 'created_at')
            ->latest()
            ->limit($limit)
            ->get();

        return $pendingUsers->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar ?? $this->getDefaultAvatar($user->name),
                'date_registered' => $user->created_at->format('M d, Y'),
                'days_pending' => $user->created_at->diffInDays(now()),
                'timestamp' => $user->created_at->toISOString()
            ];
        });
    }

    /**
     * Generate default avatar URL from name
     */
    private function getDefaultAvatar($name)
    {
        $initials = collect(explode(' ', $name))
            ->map(function ($segment) {
                return $segment[0] ?? '';
            })
            ->take(2)
            ->join('');

        return "https://ui-avatars.com/api/?name=" . urlencode($initials) . "&background=random";
    }

    /**
     * Get dashboard stats for a specific metric
     */
    public function getMetricStats(Request $request, $metric)
    {
        try {
            $period = $request->input('period', 'month');
            $startDate = null;
            $endDate = null;

            // Set date range based on period
            switch ($period) {
                case 'today':
                    $startDate = Carbon::today();
                    $endDate = Carbon::now();
                    break;
                case 'yesterday':
                    $startDate = Carbon::yesterday();
                    $endDate = Carbon::yesterday()->endOfDay();
                    break;
                case 'week':
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate = Carbon::now();
                    break;
                case 'month':
                default:
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now();
                    break;
                case 'year':
                    $startDate = Carbon::now()->startOfYear();
                    $endDate = Carbon::now();
                    break;
                case 'custom':
                    $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
                    break;
            }

            $data = [];

            switch ($metric) {
                case 'deposits':
                    $data = $this->getDetailedDepositStats($startDate, $endDate);
                    break;
                case 'withdrawals':
                    $data = $this->getDetailedWithdrawalStats($startDate, $endDate);
                    break;
                case 'users':
                    $data = $this->getDetailedUserStats($startDate, $endDate);
                    break;
                case 'revenue':
                    $data = $this->getDetailedRevenueStats($startDate, $endDate);
                    break;
                default:
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid metric specified',
                        'error_code' => 'INVALID_METRIC'
                    ], 400);
            }

            return response()->json([
                'status' => true,
                'message' => ucfirst($metric) . ' statistics retrieved successfully',
                'data' => $data,
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                    'period' => $period
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve ' . $metric . ' statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'error_code' => 'STATS_ERROR'
            ], 500);
        }
    }

    /**
     * Get detailed deposit statistics
     */
    private function getDetailedDepositStats($startDate, $endDate)
    {
        $totalDeposits = Deposit::whereBetween('created_at', [$startDate, $endDate])->count();
        $successfulDeposits = Deposit::where('status', 'successful')->whereBetween('created_at', [$startDate, $endDate])->count();
        $pendingDeposits = Deposit::where('status', 'pending')->whereBetween('created_at', [$startDate, $endDate])->count();
        $failedDeposits = Deposit::where('status', 'failed')->whereBetween('created_at', [$startDate, $endDate])->count();

        $totalAmount = Deposit::where('status', 'successful')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        $averageAmount = $successfulDeposits > 0 ? $totalAmount / $successfulDeposits : 0;

        // Get top deposit methods
        $topMethods = Deposit::where('status', 'successful')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('payment_method')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        // Get top depositors
        $topDepositors = Deposit::where('status', 'successful')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('user_id', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->with('user:id,name,email,avatar')
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => [
                        'id' => $item->user->id ?? null,
                        'name' => $item->user->name ?? 'Unknown User',
                        'email' => $item->user->email ?? null,
                        'avatar' => $item->user->avatar ?? null
                    ],
                    'count' => $item->count,
                    'total_amount' => $item->total_amount,
                    'formatted_amount' => $this->formatCurrency($item->total_amount)
                ];
            });

        return [
            'summary' => [
                'total_count' => $totalDeposits,
                'successful_count' => $successfulDeposits,
                'pending_count' => $pendingDeposits,
                'failed_count' => $failedDeposits,
                'success_rate' => $totalDeposits > 0 ? round(($successfulDeposits / $totalDeposits) * 100, 1) : 0,
                'total_amount' => $totalAmount,
                'formatted_total' => $this->formatCurrency($totalAmount),
                'average_amount' => $averageAmount,
                'formatted_average' => $this->formatCurrency($averageAmount)
            ],
            'top_methods' => $topMethods,
            'top_depositors' => $topDepositors
        ];
    }

    /**
     * Get detailed withdrawal statistics
     */
    private function getDetailedWithdrawalStats($startDate, $endDate)
    {
        // Similar implementation to getDetailedDepositStats but for withdrawals
        $totalWithdrawals = Withdrawal::whereBetween('created_at', [$startDate, $endDate])->count();
        $successfulWithdrawals = Withdrawal::where('status', 'successful')->whereBetween('created_at', [$startDate, $endDate])->count();
        $pendingWithdrawals = Withdrawal::where('status', 'pending')->whereBetween('created_at', [$startDate, $endDate])->count();
        $failedWithdrawals = Withdrawal::where('status', 'failed')->whereBetween('created_at', [$startDate, $endDate])->count();

        $totalAmount = Withdrawal::where('status', 'successful')->whereBetween('created_at', [$startDate, $endDate])->sum('amount');
        $averageAmount = $successfulWithdrawals > 0 ? $totalAmount / $successfulWithdrawals : 0;

        // Get top withdrawal methods
        $topMethods = Withdrawal::where('status', 'successful')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('withdrawal_method', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->groupBy('withdrawal_method')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        // Get top withdrawers
        $topWithdrawers = Withdrawal::where('status', 'successful')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('user_id', DB::raw('count(*) as count'), DB::raw('sum(amount) as total_amount'))
            ->with('user:id,name,email,avatar')
            ->groupBy('user_id')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'user' => [
                        'id' => $item->user->id ?? null,
                        'name' => $item->user->name ?? 'Unknown User',
                        'email' => $item->user->email ?? null,
                        'avatar' => $item->user->avatar ?? null
                    ],
                    'count' => $item->count,
                    'total_amount' => $item->total_amount,
                    'formatted_amount' => $this->formatCurrency($item->total_amount)
                ];
            });

        return [
            'summary' => [
                'total_count' => $totalWithdrawals,
                'successful_count' => $successfulWithdrawals,
                'pending_count' => $pendingWithdrawals,
                'failed_count' => $failedWithdrawals,
                'success_rate' => $totalWithdrawals > 0 ? round(($successfulWithdrawals / $totalWithdrawals) * 100, 1) : 0,
                'total_amount' => $totalAmount,
                'formatted_total' => $this->formatCurrency($totalAmount),
                'average_amount' => $averageAmount,
                'formatted_average' => $this->formatCurrency($averageAmount)
            ],
            'top_methods' => $topMethods,
            'top_withdrawers' => $topWithdrawers
        ];
    }

    /**
     * Get detailed user statistics
     */
    private function getDetailedUserStats($startDate, $endDate)
    {
        $totalUsers = User::count();
        $newUsers = User::whereBetween('created_at', [$startDate, $endDate])->count();
        $activeUsers = User::where('status', 'active')
            ->where('last_login_at', '>=', $startDate)
            ->where('last_login_at', '<=', $endDate)
            ->count();
        $pendingUsers = User::where('status', 'pending')->count();

        // User growth rate
        $previousPeriodDuration = $startDate->diffInDays($endDate) + 1;
        $previousPeriodStart = $startDate->copy()->subDays($previousPeriodDuration);
        $previousPeriodUsers = User::whereBetween('created_at', [$previousPeriodStart, $startDate])->count();
        $growthRate = $previousPeriodUsers > 0 ? round((($newUsers - $previousPeriodUsers) / $previousPeriodUsers) * 100, 1) : 0;

        // Most active users
        $mostActiveUsers = User::where('status', 'active')
            ->where('last_login_at', '>=', $startDate)
            ->where('last_login_at', '<=', $endDate)
            ->select('id', 'name', 'email', 'avatar', 'last_login_at', 'created_at')
            ->orderByDesc('last_login_at')
            ->limit(5)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar ?? $this->getDefaultAvatar($user->name),
                    'last_login' => $user->last_login_at->format('d M Y, H:i'),
                    'member_since' => $user->created_at->format('M d, Y')
                ];
            });

        // User registration trend
        $registrationTrend = [];

        // Adjust trend data based on period length
        $daysDiff = $startDate->diffInDays($endDate);

        if ($daysDiff <= 31) {
            // Daily trend for shorter periods
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $count = User::whereDate('created_at', $currentDate)->count();
                $registrationTrend[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'label' => $currentDate->format('M d'),
                    'count' => $count
                ];
                $currentDate->addDay();
            }
        } else {
            // Weekly trend for longer periods
            $currentDate = $startDate->copy()->startOfWeek();
            while ($currentDate <= $endDate) {
                $weekEnd = $currentDate->copy()->endOfWeek();
                $count = User::whereBetween('created_at', [$currentDate, $weekEnd])->count();
                $registrationTrend[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'label' => $currentDate->format('M d') . ' - ' . $weekEnd->format('M d'),
                    'count' => $count
                ];
                $currentDate->addWeek();
            }
        }

        return [
            'summary' => [
                'total_users' => $totalUsers,
                'new_users' => $newUsers,
                'active_users' => $activeUsers,
                'pending_users' => $pendingUsers,
                'growth_rate' => $growthRate,
                'active_rate' => $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0
            ],
            'most_active_users' => $mostActiveUsers,
            'registration_trend' => $registrationTrend
        ];
    }

    /**
     * Get detailed revenue statistics
     */
    private function getDetailedRevenueStats($startDate, $endDate)
    {
        $totalRevenue = Revenue::whereBetween('created_at', [$startDate, $endDate])->sum('amount');

        // Calculate previous period for comparison
        $periodDuration = $startDate->diffInDays($endDate) + 1;
        $previousPeriodStart = $startDate->copy()->subDays($periodDuration);
        $previousPeriodEnd = $startDate->copy()->subDay();
        $previousPeriodRevenue = Revenue::whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])->sum('amount');

        // Calculate growth rate
        $growthRate = $previousPeriodRevenue > 0 ? round((($totalRevenue - $previousPeriodRevenue) / $previousPeriodRevenue) * 100, 1) : 0;

        // Revenue by source
        $revenueBySource = Revenue::whereBetween('created_at', [$startDate, $endDate])
            ->select('source', DB::raw('sum(amount) as total_amount'))
            ->groupBy('source')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function ($item) use ($totalRevenue) {
                $percentage = $totalRevenue > 0 ? round(($item->total_amount / $totalRevenue) * 100, 1) : 0;
                return [
                    'source' => $item->source,
                    'amount' => $item->total_amount,
                    'formatted_amount' => $this->formatCurrency($item->total_amount),
                    'percentage' => $percentage
                ];
            });

        // Revenue trend
        $revenueTrend = [];

        // Adjust trend data based on period length
        $daysDiff = $startDate->diffInDays($endDate);

        if ($daysDiff <= 31) {
            // Daily trend for shorter periods
            $currentDate = $startDate->copy();
            while ($currentDate <= $endDate) {
                $amount = Revenue::whereDate('created_at', $currentDate)->sum('amount');
                $revenueTrend[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'label' => $currentDate->format('M d'),
                    'amount' => $amount,
                    'formatted_amount' => $this->formatCurrency($amount)
                ];
                $currentDate->addDay();
            }
        } else {
            // Weekly trend for longer periods
            $currentDate = $startDate->copy()->startOfWeek();
            while ($currentDate <= $endDate) {
                $weekEnd = $currentDate->copy()->endOfWeek();
                $amount = Revenue::whereBetween('created_at', [$currentDate, $weekEnd])->sum('amount');
                $revenueTrend[] = [
                    'date' => $currentDate->format('Y-m-d'),
                    'label' => $currentDate->format('M d') . ' - ' . $weekEnd->format('M d'),
                    'amount' => $amount,
                    'formatted_amount' => $this->formatCurrency($amount)
                ];
                $currentDate->addWeek();
            }
        }

        // Daily average
        $days = max(1, $startDate->diffInDays($endDate) + 1);
        $dailyAverage = $totalRevenue / $days;

        return [
            'summary' => [
                'total_revenue' => $totalRevenue,
                'formatted_total' => $this->formatCurrency($totalRevenue),
                'previous_period' => $previousPeriodRevenue,
                'formatted_previous' => $this->formatCurrency($previousPeriodRevenue),
                'growth_rate' => $growthRate,
                'daily_average' => $dailyAverage,
                'formatted_average' => $this->formatCurrency($dailyAverage)
            ],
            'by_source' => $revenueBySource,
            'trend' => $revenueTrend
        ];
    }

    /**
     * Get dashboard export data
     */
    public function exportDashboard(Request $request)
    {
        try {
            $period = $request->input('period', 'month');
            $format = $request->input('format', 'json');

            // Get date ranges
            $startDate = null;
            $endDate = null;

            switch ($period) {
                case 'today':
                    $startDate = Carbon::today();
                    $endDate = Carbon::now();
                    break;
                case 'yesterday':
                    $startDate = Carbon::yesterday();
                    $endDate = Carbon::yesterday()->endOfDay();
                    break;
                case 'week':
                    $startDate = Carbon::now()->startOfWeek();
                    $endDate = Carbon::now();
                    break;
                case 'month':
                default:
                    $startDate = Carbon::now()->startOfMonth();
                    $endDate = Carbon::now();
                    break;
                case 'year':
                    $startDate = Carbon::now()->startOfYear();
                    $endDate = Carbon::now();
                    break;
                case 'custom':
                    $startDate = Carbon::parse($request->input('start_date'))->startOfDay();
                    $endDate = Carbon::parse($request->input('end_date'))->endOfDay();
                    break;
            }

            // Get all dashboard data
            $metrics = $this->getKeyMetrics($startDate, $endDate, $period);
            $depositStats = $this->getDetailedDepositStats($startDate, $endDate);
            $withdrawalStats = $this->getDetailedWithdrawalStats($startDate, $endDate);
            $userStats = $this->getDetailedUserStats($startDate, $endDate);
            $revenueStats = $this->getDetailedRevenueStats($startDate, $endDate);

            $exportData = [
                'report_title' => 'Dashboard Report',
                'period' => $period,
                'date_range' => [
                    'start' => $startDate->toDateString(),
                    'end' => $endDate->toDateString(),
                ],
                'generated_at' => now()->toDateTimeString(),
                'metrics' => $metrics,
                'deposits' => $depositStats['summary'],
                'withdrawals' => $withdrawalStats['summary'],
                'users' => $userStats['summary'],
                'revenue' => $revenueStats['summary']
            ];

            // Log export activity
            AuditHelper::log([
                'action' => 'dashboard_exported',
                'description' => 'Admin exported dashboard data',
                'metadata' => [
                    'period' => $period,
                    'format' => $format,
                    'date_range' => [
                        'start' => $startDate->toDateString(),
                        'end' => $endDate->toDateString(),
                    ]
                ],
                'risk_level' => 'low'
            ]);

            // Return data in requested format
            if ($format === 'csv') {
                // Implementation for CSV export would go here
                return response()->json([
                    'status' => true,
                    'message' => 'CSV export not implemented yet',
                    'data' => $exportData
                ], 200);
            } else {
                return response()->json([
                    'status' => true,
                    'message' => 'Dashboard data exported successfully',
                    'data' => $exportData
                ], 200);
            }

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to export dashboard data',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'error_code' => 'EXPORT_ERROR'
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Gift;
use App\Models\Quiz;
use App\Models\Moment;
use App\Models\Challenge;
use App\Models\PromoCode;
use App\Models\ReferralEarning;
use App\Models\AdminSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    /**
     * Dashboard principal avec statistiques globales
     */
    public function dashboard(Request $request)
    {
        try {
            $period = $request->input('period', 'all'); // all, day, week, month, year
            $startDate = $this->getStartDate($period);
            
            $stats = [
                'users' => $this->getUserStats($startDate),
                'gifts' => $this->getGiftStats($startDate),
                'quizzes' => $this->getQuizStats($startDate),
                'moments' => $this->getMomentStats($startDate),
                'challenges' => $this->getChallengeStats($startDate),
                'financial' => $this->getFinancialStats($startDate),
                'referrals' => $this->getReferralStats($startDate),
            ];

            return response()->json([
                'success' => true,
                'period' => $period,
                'stats' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir la date de début selon la période
     */
    private function getStartDate($period)
    {
        switch ($period) {
            case 'day':
                return Carbon::today();
            case 'week':
                return Carbon::now()->startOfWeek();
            case 'month':
                return Carbon::now()->startOfMonth();
            case 'year':
                return Carbon::now()->startOfYear();
            default:
                return null;
        }
    }

    /**
     * Statistiques utilisateurs
     */
    private function getUserStats($startDate)
    {
        $query = User::query();
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        return [
            'total' => $query->count(),
            'active' => (clone $query)->where('status', 'active')->count(),
            'inactive' => (clone $query)->where('status', 'inactive')->count(),
            'suspended' => (clone $query)->where('status', 'suspended')->count(),
            'with_referral_code' => (clone $query)->whereNotNull('referral_code')->count(),
        ];
    }

    /**
     * Statistiques cadeaux
     */
    private function getGiftStats($startDate)
    {
        $query = Gift::query();
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        return [
            'total' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'by_status' => (clone $query)->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }

    /**
     * Statistiques quiz
     */
    private function getQuizStats($startDate)
    {
        $query = Quiz::query();
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        return [
            'total' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'by_status' => (clone $query)->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }

    /**
     * Statistiques moments
     */
    private function getMomentStats($startDate)
    {
        $query = Moment::query();
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        return [
            'total' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'by_status' => (clone $query)->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }

    /**
     * Statistiques challenges
     */
    private function getChallengeStats($startDate)
    {
        $query = Challenge::query();
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        return [
            'total' => $query->count(),
            'by_status' => (clone $query)->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status')
                ->toArray(),
        ];
    }

    /**
     * Statistiques financières
     */
    private function getFinancialStats($startDate)
    {
        $giftQuery = Gift::query();
        $quizQuery = Quiz::query();
        $momentQuery = Moment::query();

        if ($startDate) {
            $giftQuery->where('created_at', '>=', $startDate);
            $quizQuery->where('created_at', '>=', $startDate);
            $momentQuery->where('created_at', '>=', $startDate);
        }

        return [
            'total_gifts_amount' => $giftQuery->sum('amount'),
            'total_quizzes_amount' => $quizQuery->sum('total_amount'),
            'total_moments_amount' => $momentQuery->sum('amount'),
            'total_referral_earnings' => ReferralEarning::when($startDate, function($q) use ($startDate) {
                return $q->where('created_at', '>=', $startDate);
            })->where('status', 'paid')->sum('earning_amount'),
        ];
    }

    /**
     * Statistiques de parrainage
     */
    private function getReferralStats($startDate)
    {
        $query = User::whereNotNull('referred_by');
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        return [
            'total_referrals' => $query->count(),
            'total_earnings' => ReferralEarning::when($startDate, function($q) use ($startDate) {
                return $q->where('created_at', '>=', $startDate);
            })->where('status', 'paid')->sum('earning_amount'),
            'pending_earnings' => ReferralEarning::when($startDate, function($q) use ($startDate) {
                return $q->where('created_at', '>=', $startDate);
            })->where('status', 'pending')->sum('earning_amount'),
        ];
    }

    /**
     * Liste des utilisateurs avec filtres
     */
    public function users(Request $request)
    {
        try {
            $query = User::query();

            // Filtres
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('role')) {
                $query->where('role', $request->role);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $users = $query->with('referrer:id,first_name,last_name,email')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'users' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des cadeaux avec filtres
     */
    public function gifts(Request $request)
    {
        try {
            $query = Gift::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            if ($request->has('min_amount')) {
                $query->where('amount', '>=', $request->min_amount);
            }

            if ($request->has('max_amount')) {
                $query->where('amount', '<=', $request->max_amount);
            }

            $gifts = $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'gifts' => $gifts
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des quiz avec filtres
     */
    public function quizzes(Request $request)
    {
        try {
            $query = Quiz::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $quizzes = $query->withCount('attempts')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'quizzes' => $quizzes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des moments avec filtres
     */
    public function moments(Request $request)
    {
        try {
            $query = Moment::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $moments = $query->withCount('attempts')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'moments' => $moments
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Liste des challenges avec filtres
     */
    public function challenges(Request $request)
    {
        try {
            $query = Challenge::query();

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $challenges = $query->withCount('participants')
                ->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'challenges' => $challenges
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gérer les codes promo (admin)
     */
    public function promoCodes(Request $request)
    {
        try {
            $query = PromoCode::with('creator:id,first_name,last_name,email')
                ->withCount('usages');

            if ($request->has('is_active')) {
                $query->where('is_active', $request->is_active);
            }

            if ($request->has('created_by')) {
                $query->where('created_by', $request->created_by);
            }

            $promoCodes = $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'promo_codes' => $promoCodes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un code promo (admin)
     */
    public function createPromoCode(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'code' => 'required|string|max:50|unique:promo_codes,code',
                'discount_percentage' => 'required|numeric|min:0|max:100',
                'max_uses' => 'nullable|integer|min:1',
                'valid_from' => 'nullable|date',
                'valid_until' => 'nullable|date|after:valid_from',
                'is_active' => 'boolean',
                'created_by_user_id' => 'nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $promoCode = PromoCode::create([
                'created_by' => $request->created_by_user_id ?? $request->user()->id,
                'code' => strtoupper($request->code),
                'discount_percentage' => $request->discount_percentage,
                'max_uses' => $request->max_uses,
                'valid_from' => $request->valid_from,
                'valid_until' => $request->valid_until,
                'is_active' => $request->is_active ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Code promo créé avec succès',
                'promo_code' => $promoCode
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les paramètres admin
     */
    public function getSettings()
    {
        try {
            $settings = AdminSetting::all()->pluck('value', 'key')->toArray();

            return response()->json([
                'success' => true,
                'settings' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour les paramètres admin
     */
    public function updateSettings(Request $request)
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'referral_earning_percentage_min' => 'nullable|numeric|min:10|max:100',
                'referral_earning_percentage_max' => 'nullable|numeric|min:10|max:100',
                'default_referral_earning_percentage' => 'nullable|numeric|min:10|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            foreach ($request->all() as $key => $value) {
                AdminSetting::set($key, $value);
            }

            return response()->json([
                'success' => true,
                'message' => 'Paramètres mis à jour avec succès',
                'settings' => AdminSetting::all()->pluck('value', 'key')->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Statistiques de parrainage détaillées
     */
    public function referralEarnings(Request $request)
    {
        try {
            $query = ReferralEarning::with(['referrer:id,first_name,last_name,email', 'referredUser:id,first_name,last_name,email']);

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('referrer_id')) {
                $query->where('referrer_id', $request->referrer_id);
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->date_to);
            }

            $earnings = $query->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'earnings' => $earnings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}


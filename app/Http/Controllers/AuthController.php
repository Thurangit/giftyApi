<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Helpers\CodeGenerator;
use App\Helpers\EyamoUserResolver;
use App\Models\AdminSetting;
use App\Notifications\AccountDuplicateAttemptNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request)
    {
        try {
            // Vérifier si un utilisateur existe déjà avec cet email ou téléphone avant la validation
            // pour envoyer une notification à l'utilisateur existant
            $existingUserByEmail = null;
            $existingUserByPhone = null;
            
            if ($request->has('email') && !empty($request->email)) {
                $existingUserByEmail = User::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();
            }
            
            if ($request->has('phone') && !empty($request->phone)) {
                $existingUserByPhone = User::where('phone', $request->phone)->first();
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    function ($attribute, $value, $fail) {
                        // Vérifier l'unicité insensible à la casse
                        $exists = User::whereRaw('LOWER(email) = ?', [strtolower($value)])->exists();
                        if ($exists) {
                            $fail('Cette adresse email est déjà utilisée.');
                        }
                    },
                ],
                'phone' => 'required|string|max:20|unique:users,phone|regex:/^[0-9]{9,15}$/',
                'password' => ['required', 'string', 'min:6', 'confirmed'],
                'referral_code' => 'nullable|string|exists:users,referral_code',
            ], [
                'first_name.required' => 'Le prénom est obligatoire.',
                'last_name.required' => 'Le nom est obligatoire.',
                'email.required' => 'L\'adresse email est obligatoire.',
                'email.email' => 'L\'adresse email doit être valide.',
                'phone.required' => 'Le numéro de téléphone est obligatoire.',
                'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
                'phone.regex' => 'Le numéro de téléphone doit contenir entre 9 et 15 chiffres.',
                'password.required' => 'Le mot de passe est obligatoire.',
                'password.min' => 'Le mot de passe doit contenir au moins 6 caractères.',
                'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
                'referral_code.exists' => 'Le code de parrainage fourni est invalide.',
            ]);

            // Envoyer des notifications aux utilisateurs existants avant de retourner l'erreur
            if ($existingUserByEmail) {
                try {
                    $existingUserByEmail->notify(new AccountDuplicateAttemptNotification('email', $request->email, 'creation'));
                } catch (\Exception $e) {
                    // Logger l'erreur mais ne pas bloquer la validation
                    \Log::error('Erreur lors de l\'envoi de la notification: ' . $e->getMessage());
                }
            }
            
            if ($existingUserByPhone) {
                try {
                    $existingUserByPhone->notify(new AccountDuplicateAttemptNotification('phone', $request->phone, 'creation'));
                } catch (\Exception $e) {
                    // Logger l'erreur mais ne pas bloquer la validation
                    \Log::error('Erreur lors de l\'envoi de la notification: ' . $e->getMessage());
                }
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Trouver le parrain si un code de parrainage est fourni
            $referredBy = null;
            if ($request->has('referral_code') && !empty($request->referral_code)) {
                $referrer = User::where('referral_code', $request->referral_code)->first();
                if ($referrer) {
                    $referredBy = $referrer->id;
                }
            }

            // Générer un code de parrainage unique pour le nouvel utilisateur
            $referralCode = CodeGenerator::generateReferralCode();
            $eyamoCode = CodeGenerator::generateEyamoUserCode();

            // Créer l'utilisateur avec l'email en minuscules
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => strtolower($request->email),
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'status' => 'active',
                'role' => 'user',
                'referral_code' => $referralCode,
                'referred_by' => $referredBy,
                'eyamo_code' => $eyamoCode,
            ]);

            // Créer le token d'authentification
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Inscription réussie',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'balance' => $user->balance,
                    'status' => $user->status,
                    'role' => $user->role,
                    'referral_code' => $user->referral_code,
                    'eyamo_code' => $user->eyamo_code,
                ],
                'token' => $token
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'identifier' => 'required|string', // Email ou téléphone
                'password' => 'required|string',
            ], [
                'identifier.required' => 'L\'email ou le numéro de téléphone est obligatoire.',
                'password.required' => 'Le mot de passe est obligatoire.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $identifier = trim($request->identifier);
            
            // Chercher l'utilisateur par email, téléphone ou code Eyamo (E##-#######)
            $user = null;
            if (preg_match('/^E[A-Za-z]{2}-\d{7}$/', $identifier)) {
                $user = EyamoUserResolver::resolve($identifier);
            }
            if (!$user) {
                $user = User::where(function ($query) use ($identifier) {
                    $query->whereRaw('LOWER(email) = ?', [strtolower($identifier)])
                          ->orWhere('phone', $identifier);
                })->first();
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants incorrects'
                ], 401);
            }

            // Vérifier le mot de passe
            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Identifiants incorrects'
                ], 401);
            }

            // Vérifier le statut de l'utilisateur
            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Votre compte est ' . ($user->status === 'suspended' ? 'suspendu' : 'inactif')
                ], 403);
            }

            if (empty($user->eyamo_code)) {
                $user->eyamo_code = CodeGenerator::generateEyamoUserCode();
                $user->save();
            }

            // Créer le token d'authentification
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $user->avatar,
                    'bio' => $user->bio,
                    'balance' => $user->balance,
                    'status' => $user->status,
                    'role' => $user->role,
                    'referral_code' => $user->referral_code,
                    'eyamo_code' => $user->eyamo_code,
                ],
                'token' => $token
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la connexion: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Déconnexion de l'utilisateur
     */
    public function logout(Request $request)
    {
        try {
            // Révoquer le token actuel
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la déconnexion'
            ], 500);
        }
    }

    /**
     * Récupérer le profil de l'utilisateur connecté
     */
    public function profile(Request $request)
    {
        try {
            $user = $request->user();

            if (empty($user->eyamo_code)) {
                $user->eyamo_code = CodeGenerator::generateEyamoUserCode();
                $user->save();
            }

            // Construire l'URL complète de l'avatar si elle existe
            $avatarUrl = $user->avatar;
            if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                // Si c'est un chemin relatif, construire l'URL complète
                $avatarUrl = Storage::disk('public')->url($user->avatar);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $avatarUrl,
                    'bio' => $user->bio,
                    'balance' => $user->balance,
                    'status' => $user->status,
                    'role' => $user->role,
                    'referral_code' => $user->referral_code,
                    'eyamo_code' => $user->eyamo_code,
                    'created_at' => $user->created_at,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du profil'
            ], 500);
        }
    }

    /**
     * Mettre à jour le profil de l'utilisateur
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            // Vérifier si l'email ou le téléphone change
            $emailChanged = $request->has('email') && !empty($request->email) && strtolower($request->email) !== strtolower($user->email);
            $phoneChanged = $request->has('phone') && !empty($request->phone) && $request->phone !== $user->phone;

            // Si l'email ou le téléphone change, vérifier que le mot de passe est fourni
            if (($emailChanged || $phoneChanged) && !$request->has('password')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe est requis pour modifier l\'email ou le numéro de téléphone',
                    'errors' => ['password' => ['Le mot de passe est requis pour modifier l\'email ou le numéro de téléphone']]
                ], 422);
            }

            // Vérifier le mot de passe si l'email ou le téléphone change
            if (($emailChanged || $phoneChanged) && $request->has('password')) {
                if (!\Hash::check($request->password, $user->password)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Mot de passe incorrect',
                        'errors' => ['password' => ['Le mot de passe est incorrect']]
                    ], 422);
                }
            }

            // Vérifier si un autre utilisateur existe déjà avec cet email ou téléphone avant la validation
            // pour envoyer une notification à l'utilisateur existant
            $existingUserByEmail = null;
            $existingUserByPhone = null;
            
            // Vérifier l'email seulement si c'est différent de l'email actuel
            if ($emailChanged) {
                $existingUserByEmail = User::whereRaw('LOWER(email) = ?', [strtolower($request->email)])
                    ->where('id', '!=', $user->id)
                    ->first();
            }
            
            // Vérifier le téléphone seulement si c'est différent du téléphone actuel
            if ($phoneChanged) {
                $existingUserByPhone = User::where('phone', $request->phone)
                    ->where('id', '!=', $user->id)
                    ->first();
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => [
                    'sometimes',
                    'string',
                    'email',
                    'max:255',
                    function ($attribute, $value, $fail) use ($user) {
                        // Vérifier l'unicité insensible à la casse
                        $exists = User::whereRaw('LOWER(email) = ?', [strtolower($value)])
                            ->where('id', '!=', $user->id)
                            ->exists();
                        if ($exists) {
                            $fail('Cette adresse email est déjà utilisée.');
                        }
                    },
                ],
                'phone' => 'sometimes|string|max:20|unique:users,phone,' . $user->id . '|regex:/^[0-9]{9,15}$/',
                'bio' => 'sometimes|string|max:500',
                'avatar' => 'sometimes|string|max:255',
                'password' => 'sometimes|string',
            ], [
                'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.',
                'phone.regex' => 'Le numéro de téléphone doit contenir entre 9 et 15 chiffres.',
            ]);

            // Envoyer des notifications aux utilisateurs existants avant de retourner l'erreur
            if ($existingUserByEmail) {
                try {
                    $existingUserByEmail->notify(new AccountDuplicateAttemptNotification('email', $request->email, 'modification'));
                } catch (\Exception $e) {
                    // Logger l'erreur mais ne pas bloquer la validation
                    \Log::error('Erreur lors de l\'envoi de la notification: ' . $e->getMessage());
                }
            }
            
            if ($existingUserByPhone) {
                try {
                    $existingUserByPhone->notify(new AccountDuplicateAttemptNotification('phone', $request->phone, 'modification'));
                } catch (\Exception $e) {
                    // Logger l'erreur mais ne pas bloquer la validation
                    \Log::error('Erreur lors de l\'envoi de la notification: ' . $e->getMessage());
                }
            }

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Stocker l'ancien email pour la mise à jour des éléments associés
            $oldEmail = $user->email;

            // Mettre à jour les champs
            if ($request->has('first_name')) {
                $user->first_name = $request->first_name;
            }
            if ($request->has('last_name')) {
                $user->last_name = $request->last_name;
            }
            if ($request->has('email')) {
                $user->email = strtolower($request->email);
            }
            if ($request->has('phone')) {
                $user->phone = $request->phone;
            }
            if ($request->has('bio')) {
                $user->bio = $request->bio;
            }
            if ($request->has('avatar')) {
                $user->avatar = $request->avatar;
            }

            $user->save();

            // Si l'email a changé, mettre à jour l'email dans tous les éléments associés
            if ($emailChanged && !empty($oldEmail)) {
                $newEmail = strtolower($request->email);
                
                // Mettre à jour l'email dans les gifts
                DB::table('gifts')
                    ->where('email', $oldEmail)
                    ->update(['email' => $newEmail]);
                
                // Mettre à jour l'email dans les quizzes
                DB::table('quizzes')
                    ->where('creator_email', $oldEmail)
                    ->update(['creator_email' => $newEmail]);
                
                // Mettre à jour l'email dans les moments
                DB::table('moments')
                    ->where('creator_email', $oldEmail)
                    ->update(['creator_email' => $newEmail]);
            }

            // Construire l'URL complète de l'avatar si elle existe
            $avatarUrl = $user->avatar;
            if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                // Si c'est un chemin relatif, construire l'URL complète
                $avatarUrl = Storage::disk('public')->url($user->avatar);
            }

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $avatarUrl,
                    'bio' => $user->bio,
                    'balance' => $user->balance,
                    'status' => $user->status,
                    'role' => $user->role,
                    'referral_code' => $user->referral_code,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => ['required', 'string', 'min:6', 'confirmed'],
            ], [
                'current_password.required' => 'Le mot de passe actuel est obligatoire.',
                'new_password.required' => 'Le nouveau mot de passe est obligatoire.',
                'new_password.min' => 'Le nouveau mot de passe doit contenir au moins 6 caractères.',
                'new_password.confirmed' => 'La confirmation du nouveau mot de passe ne correspond pas.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();

            // Vérifier le mot de passe actuel
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Le mot de passe actuel est incorrect'
                ], 400);
            }

            // Mettre à jour le mot de passe
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe'
            ], 500);
        }
    }

    /**
     * Uploader une photo de profil
     */
    public function uploadAvatar(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB max
            ], [
                'avatar.required' => 'La photo de profil est obligatoire.',
                'avatar.image' => 'Le fichier doit être une image.',
                'avatar.mimes' => 'L\'image doit être au format JPEG, PNG, JPG ou GIF.',
                'avatar.max' => 'L\'image ne doit pas dépasser 2MB.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Supprimer l'ancienne photo si elle existe
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            // Uploader la nouvelle photo
            $file = $request->file('avatar');
            $filename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('avatars', $filename, 'public');

            // Mettre à jour l'avatar de l'utilisateur
            $user->avatar = $path;
            $user->save();

            // Retourner l'URL complète de l'avatar
            $avatarUrl = Storage::disk('public')->url($path);

            return response()->json([
                'success' => true,
                'message' => 'Photo de profil uploadée avec succès',
                'avatar_url' => $avatarUrl,
                'avatar' => $path,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'avatar' => $avatarUrl,
                    'bio' => $user->bio,
                    'balance' => $user->balance,
                    'status' => $user->status,
                    'role' => $user->role,
                    'referral_code' => $user->referral_code,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload de la photo de profil: ' . $e->getMessage()
            ], 500);
        }
    }
}


<?php

namespace App\Http\Controllers;

use App\Models\benefit;
use App\Models\embassadors;
use App\Models\embassadorsGift;
use App\Models\Gift;
use App\Models\GiftRef;
use App\Helpers\CodeGenerator;
use App\Helpers\EyamoUserResolver;
use DateTime;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class PayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

    }

    public function sendMoney(Request $request)
    {
        $opIn = $request->input('operator');

        if ($opIn === 'eyamo') {
            $validated = $request->validate([
                'amount' => 'required|integer|min:1',
                'message' => 'required|string|max:4000',
                'name' => 'nullable|string|max:100',
                'email' => 'nullable|email|max:255',
                'operator' => 'required|string|in:eyamo',
                'eyamo_identifier' => 'required|string|max:255',
                'promoCode' => 'nullable|string|max:100',
            ]);
            $eyamoUser = EyamoUserResolver::resolve($validated['eyamo_identifier']);
            if (! $eyamoUser) {
                return response()->json([
                    'message' => 'Compte Eyamo introuvable. Utilisez votre code E##-#######, email ou numéro enregistré.',
                ], 422);
            }
            $rawPhone = preg_replace('/\D/', '', (string) $eyamoUser->phone);
            if ($rawPhone === '' || strlen($rawPhone) < 9) {
                return response()->json([
                    'message' => 'Ce compte Eyamo ne dispose pas d\'un numéro de téléphone valide pour le paiement.',
                ], 422);
            }
            $phoneNumber = $rawPhone;
            $operator = 'eyamo';
        } else {
            $validated = $request->validate(
                [
                    'amount' => 'required|integer|min:1',
                    'message' => 'required|string|max:4000',
                    'name' => 'nullable|string|max:100',
                    'email' => 'nullable|email|max:255',
                    'phoneNumber' => 'required|string|regex:/^[0-9]{9,15}$/',
                    'operator' => 'required|string|in:orange,mtn,operator3',
                    'promoCode' => 'nullable|string|max:100',
                ]
            );
            $phoneNumber = $validated['phoneNumber'];
            $operator = $validated['operator'];
        }

        $amount = $validated['amount'];
        $message = $validated['message'];
        $name = $validated['name'] ?? null;
        $email = isset($validated['email']) && ! empty($validated['email']) ? $validated['email'] : null;
        $amountBenefit = 500;
        $promoCode = $validated['promoCode'];
        $promoAmount = 0;
        $embassor_amount = 0;


        if ($amount < 600) {
            $amountBenefit = 25;
            $promoAmount = 25;
        } else if ($amount > 601 && $amount <= 2000) {
            $amountBenefit = 75;
            $promoAmount = $amountBenefit - 25;
        } else if ($amount > 2000 && $amount <= 3000) {
            $amountBenefit = 100;
            $promoAmount = $amountBenefit - 50;
        } else if ($amount > 3000 && $amount <= 4000) {
            $amountBenefit = 175;
            $promoAmount = $amountBenefit - 50;
        } else if ($amount > 4000 && $amount <= 5000) {
            $amountBenefit = 200;
            $promoAmount = $amountBenefit - 50;
        } else if ($amount > 5000 && $amount <= 8000) {
            $amountBenefit = 200;
            $promoAmount = $amountBenefit - 50;
        } else if ($amount > 8000 && $amount <= 20000) {
            $amountBenefit = 350;
            $promoAmount = $amountBenefit - 75;
        } else if ($amount > 20000 && $amount <= 40000) {
            $amountBenefit = 500;
            $promoAmount = $amountBenefit - 75;
        } else if ($amount > 40000 && $amount <= 60000) {
            $amountBenefit = 800;
            $promoAmount = $amountBenefit - 75;
        } else if ($amount > 60000 && $amount <= 90000) {
            $amountBenefit = 1100;
            $promoAmount = $amountBenefit - 75;
        } else if ($amount > 90000 && $amount <= 100000) {
            $amountBenefit = 1300;
            $promoAmount = $amountBenefit - 100;
        } else if ($amount > 100000 && $amount <= 150000) {
            $amountBenefit = 1800;
            $promoAmount = $amountBenefit - 150;
        } else if ($amount > 150000 && $amount <= 200000) {
            $amountBenefit = 2300;
            $promoAmount = $amountBenefit - 100;
        } else if ($amount > 200000 && $amount <= 250000) {
            $amountBenefit = 2800;
            $promoAmount = $amountBenefit - 200;
        } else if ($amount > 250000 && $amount <= 300000) {
            $amountBenefit = 3300;
            $promoAmount = $amountBenefit - 300;
        } else if ($amount > 300000 && $amount <= 350000) {
            $amountBenefit = 3800;
            $promoAmount = $amountBenefit - 300;
        } else if ($amount > 350000 && $amount <= 400000) {
            $amountBenefit = 4400;
            $promoAmount = $amountBenefit - 300;
        } else if ($amount > 400000 && $amount <= 450000) {
            $amountBenefit = 4900;
            $promoAmount = $amountBenefit - 300;
        } else if ($amount > 450000 && $amount <= 500000) {
            $amountBenefit = 5500;
            $promoAmount = $amountBenefit - 300;
        } else if ($amount > 500000 && $amount <= 800000) {
            $amountBenefit = 9000;
            $promoAmount = $amountBenefit - 500;
        } else if ($amount > 800000 && $amount <= 1100000) {
            $amountBenefit = 15000;
            $promoAmount = $amountBenefit - 1000;
        } else if ($amount > 900000 && $amount <= 2000000) {
            $amountBenefit = 25000;
            $promoAmount = $amountBenefit - 5000;
        } else {
            $amountBenefit = 50000;
            $promoAmount = $amountBenefit - 10000;
        }

        if (isset($promoCode) && $promoCode != null) {
            $existPromo = embassadors::where('code', '=', $promoCode)->first();
            if ($existPromo == null) {
                $promoAmount = 0;
            } else {
                $embassor_amount = $amountBenefit - $promoAmount;
            }

        } else {
            $promoAmount = 0;
        }


        if ($operator === 'orange') {
            $operator = 'OM';
        } elseif ($operator === 'mtn') {
            $operator = 'MOMO';
        } elseif ($operator === 'eyamo') {
            $operator = 'EYAMO';
        }
        function generateTransactionReference()
        { // Obtenir la date et l'heure actuelles
            $date = new DateTime(); // Formater la date et l'heure
            $timestamp = $date->format('YmdHis'); // Générer la référence unique
            $reference = 'Gift-00' . $timestamp;
            return $reference;
        } // Exemple d'utilisation
        $transactionReference = generateTransactionReference();

        // ============================================
        // API DE PAIEMENT COMMENTÉE POUR TESTS
        // ============================================
        // TODO: Décommenter quand l'API de paiement Campay sera prête
        /*
        $client = new Client();
        $maxRetries = 60; // 60 secondes
        $retryInterval = 1; // 1 seconde

        for ($i = 0; $i < $maxRetries; $i++) {
            $response = $client->post(
                'https://www.campay.net/api/collect/',
                [
                    'headers' => [
                        'Authorization' => 'Token 4c298a57e6e3bc67e07ac1f84c752b0076d61685',
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'amount' => $amount + $amountBenefit, // Un entier valide, par exemple "5"
                        'currency' => "XAF", // "XAF"
                        'from' => "$phoneNumber", // OPTIONAL. Numéro de téléphone valide avec indicatif pays, ex: "2376xxxxxxxx"
                        'description' => "Dépot", // Description du paiement
                        'first_name' => "blo", // OPTIONAL. Chaîne de caractères
                        'last_name' => "aucun", // OPTIONAL. Chaîne de caractères
                        'email' => "aucun@mail.com",
                        'external_reference' => $transactionReference, // OPTIONAL. Référence de la transaction générée par ton système
                        'redirect_url' => "null", // URL de redirection après succès
                        'failure_redirect_url' => "null", // URL de redirection après échec
                        'payment_options' => $operator // Options de paiement, ex: "MOMO"
                    ]
                ]
            );

            $responseBody = json_decode($response->getBody(), true);
            if (isset($responseBody['status']) && $responseBody['status'] === 'SUCCESSFUL') {
        */
        
        // Simulation de réponse API pour tests (à supprimer quand l'API sera prête)
        $simulatedReference = 'CAMP-' . time() . '-' . rand(1000, 9999);
        $responseBody = [
            'status' => 'SUCCESSFUL',
            'reference' => $simulatedReference
        ];
        
        // Simuler le comportement de l'API de paiement
        // Simuler un succès immédiat (remplace la boucle for et l'appel API)
        //$data = $request->validate(['ref_one' => 'required|string|max:255', 'ref_two' => 'required|string|max:255', 'ref_three' => 'required|string|max:255', 'name' => 'required|string|max:255', 'amount' => 'required|integer', 'sender_opertor' => 'required|string|max:255', 'sender' => 'required|string|max:255', 'receiver_opertor' => 'required|string|max:255', 'receiver' => 'required|string|max:255', 'message' => 'nullable|string', 'image' => 'nullable|string|max:255', 'email' => 'required|string|email|max:255', 'commentaire' => 'nullable|string', 'other_one' => 'nullable|string|max:255', 'other_two' => 'nullable|string|max:255', 'status' => 'required|string|max:255',]); // Vérifier que ref_one est unique
        $existingGift = Gift::where('ref_one', $transactionReference)->first();
        if ($existingGift) {
            return response()->json(['error' => 'ref_one doit être unique'], 400);
        } // Créer un nouvel enregistrement si ref_one est unique
        try {
            $gift = Gift::create([
                'ref_one' => $responseBody['reference'],
                'ref_two' => $transactionReference,
                'access_code' => CodeGenerator::generateAccessCode('gift'),
                'name' => $name,
                'amount' => $amount,
                'sender' => $phoneNumber,
                'sender_opertor' => $operator,
                'message' => $message,
                'email' => $email,
                'other_one' => $request->gift_,
                'status' => "Send"
            ]);
            $giftRef = GiftRef::create([
                'ref' => $responseBody['reference'],
                'amount' => $amount,
                'status' => "Send"
            ]);
            $benefi = benefit::create([
                'ref' => $responseBody['reference'],
                'amount' => $amountBenefit - $embassor_amount,
                'status' => "Send"
            ]);
            if (isset($promoCode) && $promoCode != null) {
                $existPromo = embassadors::where('code', '=', $promoCode)->first();
                if ($existPromo == null) {
                    $promoAmount = 0;
                } else {
                    $add = embassadorsGift::create([
                        'transaction' => $transactionReference,
                        'code' => $promoCode,
                        'amount' => $embassor_amount,
                        'status' => 'Send'
                    ]);
                }

            } else {
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la création du cadeau'], 500);
        }
        
        // Retourner la réponse de succès (simulation)
        return response()->json([
            'message' => 'Transaction réussie',
            'reference' => $responseBody['reference'],
            'amount' => $amount,
            'phoneNumber' => $phoneNumber,
            'operator' => $operator,
            'gift' => $transactionReference,
            'access_code' => $gift->access_code,
        ]);
            /*
            } elseif ($i == $maxRetries - 1) {
                echo json_encode([
                    'message' => 'Transaction échouée ou en attente après 60 secondes',
                    'reference' => $transactionReference
                ]);
            }

            sleep($retryInterval); // Attend une seconde avant la prochaine tentative
        }
        */



        /*  while ($response['status'] == 'PENDING') {
             echo $response;
         } */

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

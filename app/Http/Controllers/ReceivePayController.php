<?php

namespace App\Http\Controllers;

use App\Models\benefit;
use App\Models\Gift;
use App\Models\GiftRef;
use DateTime;
use Illuminate\Http\Request;
use GuzzleHttp\Client;

class ReceivePayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function infoGift($ref)
    {
        $infos = Gift::where("ref_two", '=', $ref)->first();
        return response()->json($infos);
    }
    public function sendMoney(Request $request)
    {

        $validated = $request->validate(
            [
                'amount' => 'required|integer|min:1',
                'message' => 'required|string|max:255',
                'name' => 'nullable|string|max:100',
                'phoneNumber' => 'required|string|regex:/^[0-9]{9,15}$/',
                'operator' => 'required|string|in:orange,mtn,operator3', // Remplacer par les opérateurs valides
            ]
        ); // Les données sont maintenant validées et sécurisées
        $amount = $validated['amount'];
        $message = $validated['message'];
        $name = $validated['name'];
        $phoneNumber = $validated['phoneNumber'];
        $operator = $validated['operator'];
        $amountBenefit = 500;
        if ($amount < 600) {
            $amountBenefit = 25;
        }
        if ($operator == 'orange') {
            $operator = 'OM';
        }
        if ($operator == 'mtn') {
            $operator = 'MOMO';
        }
        function generateTransactionReference()
        { // Obtenir la date et l'heure actuelles
            $date = new DateTime(); // Formater la date et l'heure
            $timestamp = $date->format('YmdHis'); // Générer la référence unique
            $reference = 'Gift-00' . $timestamp;
            return $reference;
        } // Exemple d'utilisation
        $transactionReference = generateTransactionReference();

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
                        'email' => "aucun@mail.com", // OPTIONAL. Chaîne de caractères
                        'external_reference' => $transactionReference, // OPTIONAL. Référence de la transaction générée par ton système
                        'redirect_url' => "null", // URL de redirection après succès
                        'failure_redirect_url' => "null", // URL de redirection après échec
                        'payment_options' => $operator // Options de paiement, ex: "MOMO"
                    ]
                ]
            );

            $responseBody = json_decode($response->getBody(), true);
            if (isset($responseBody['status']) && $responseBody['status'] === 'SUCCESSFUL') {
                echo json_encode([
                    'message' => 'Transaction réussie',
                    'reference' => $responseBody['reference'],
                    'amount' => $amount,
                    'phoneNumber' => $phoneNumber,
                    'operator' => $operator,
                    'gift' => $transactionReference
                ]);
                //$data = $request->validate(['ref_one' => 'required|string|max:255', 'ref_two' => 'required|string|max:255', 'ref_three' => 'required|string|max:255', 'name' => 'required|string|max:255', 'amount' => 'required|integer', 'sender_opertor' => 'required|string|max:255', 'sender' => 'required|string|max:255', 'receiver_opertor' => 'required|string|max:255', 'receiver' => 'required|string|max:255', 'message' => 'nullable|string', 'image' => 'nullable|string|max:255', 'email' => 'required|string|email|max:255', 'commentaire' => 'nullable|string', 'other_one' => 'nullable|string|max:255', 'other_two' => 'nullable|string|max:255', 'status' => 'required|string|max:255',]); // Vérifier que ref_one est unique
                $existingGift = Gift::where('ref_one', $transactionReference)->first();
                if ($existingGift) {
                    return response()->json(['error' => 'ref_one doit être unique'], 400);
                } // Créer un nouvel enregistrement si ref_one est unique
                try {
                    $gift = Gift::create([
                        'ref_one' => $responseBody['reference'],
                        'ref_two' => $transactionReference,
                        'name' => $name,
                        'amount' => $amount,
                        'sender' => $phoneNumber,
                        'sender_opertor' => $operator,
                        'status' => "pending"
                    ]);
                    $giftRef = GiftRef::create([
                        'ref' => $responseBody['reference'],
                        'amount' => $amount,
                        'status' => "pending"
                    ]);
                    $benefi = benefit::create([
                        'ref' => $responseBody['reference'],
                        'amount' => $amountBenefit,
                        'status' => "pending"
                    ]);
                } catch (\Exception $e) {
                    return response()->json(['error' => 'Erreur lors de la création du cadeau'], 500);
                }
                break;
            } elseif ($i == $maxRetries - 1) {
                echo json_encode([
                    'message' => 'Transaction échouée ou en attente après 60 secondes',
                    'reference' => $transactionReference
                ]);
            }

            sleep($retryInterval); // Attend une seconde avant la prochaine tentative
        }



        /*  while ($response['status'] == 'PENDING') {
             echo $response;
         } */

    }

    public function withdraw(Request $request)
    {

        $phoneNumber = $request->input('phoneNumber');
        $amount = $request->input('giftAmount');
        $ref = $request->input('refGift');
        $operator = $request->input('paymentMethod');
        $infoGift = Gift::where('ref_two', '=', $ref)->first();
        $Gift_Ref = GiftRef::where('ref', '=', $infoGift->ref_one)->first();
        $Gift_App = benefit::where('ref', '=', $infoGift->ref_one)->first();

        if (!$infoGift) {
            return response()->json([
                'error' => 'Cadeau inexistant',
                'reference' => $ref
            ], 408);
        } else {
            if ($infoGift->status == "Send") {
                if ($Gift_Ref->status == "Send") {
                    function generateUuid()
                    {
                        $data = random_bytes(16); // Modifier quelques bits pour respecter le format UUID v4
                        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
                        return vsprintf('%08s-%04s-%04x-%04x-%12s', str_split(bin2hex($data), 4));
                    } // Exemple d'utilisation
                    $uuid = generateUuid();
                    
                    // ============================================
                    // API DE PAIEMENT COMMENTÉE POUR TESTS
                    // ============================================
                    // TODO: Décommenter quand l'API de paiement Campay sera prête
                    /*
                    $client = new Client();
                    $maxRetries = 120; // 2 minutes (1 tentative par seconde)
                    $retryInterval = 1; // 1 seconde entre chaque tentative

                    for ($i = 0; $i < $maxRetries; $i++) {
                        $response = $client->post(
                            'https://www.campay.net/api/withdraw/',
                            [
                                'headers' => [
                                    'Authorization' => 'Token 4c298a57e6e3bc67e07ac1f84c752b0076d61685',
                                    'Content-Type' => 'application/json',
                                ],
                                'json' => [
                                    'amount' => $amount,
                                    'to' => "237" . $phoneNumber,
                                    'description' => "Reception cadeau",
                                    'external_reference' => $uuid,
                                    'uuid' => $uuid,
                                ]
                            ]
                        );

                        $responseBody = json_decode($response->getBody(), true);

                        // Si le statut est SUCCESSFUL, retournez immédiatement le succès
                        if (isset($responseBody['status']) && $responseBody['status'] === 'SUCCESSFUL') {
                    */
                    
                    // Simulation de réponse API pour tests (à supprimer quand l'API sera prête)
                    $responseBody = [
                        'status' => 'SUCCESSFUL',
                        'reference' => $uuid,
                        'message' => 'Transaction réussie (simulation)'
                    ];
                    
                    // Simuler le comportement de l'API de paiement - succès immédiat
                            $infoGift->update([
                                'receiver_opertor' => $operator,
                                'receiver' => $phoneNumber,
                                'status' => 'received'
                            ]);
                            $Gift_Ref->update([
                                'status' => 'received'
                            ]);
                            $Gift_App->update([
                                'status' => 'received'
                            ]);

                            return response()->json($responseBody, 200);

                    /*
                        // Si le statut est autre chose que PENDING, retournez l'erreur
                        if (isset($responseBody['status']) && $responseBody['status'] !== 'PENDING') {
                            return response()->json($responseBody, $response->getStatusCode());
                        }

                        // Si le statut est PENDING, attendez 1 seconde avant de réessayer
                        sleep($retryInterval);
                    }

                    // Si après 2 minutes (120 tentatives), le statut reste PENDING
                    return response()->json([
                        'error' => 'Transaction en attente après 2 minutes',
                        'reference' => $ref
                    ], 408); // Code HTTP 408 pour Request Timeout
                    */
                }
            } else {

                return response()->json([
                    'error' => 'Cadeau déjà retirer',
                    'reference' => $ref
                ], 408); // Code HTTP 408 pour Request Timeout

            }
        }

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

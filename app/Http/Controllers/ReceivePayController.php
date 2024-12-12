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
                'phoneNumber' => 'required|string|regex:/^[0-9]{12}$/',
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
                'https://demo.campay.net/api/collect/',
                [
                    'headers' => [
                        'Authorization' => 'Token b7a8bcef814b1c221e89ada3ddb2babf33605a13',
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
                        'status' => "Delivery"
                    ]);
                    $giftRef = GiftRef::create([
                        'ref' => $responseBody['reference'],
                        'amount' => $amount,
                        'status' => "Delivery"
                    ]);
                    $benefi = benefit::create([
                        'ref' => $responseBody['reference'],
                        'amount' => $amountBenefit,
                        'status' => "Delivery"
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
        $amount = $request->input('amount');
        function generateUuid()
        {
            $data = random_bytes(16); // Modifier quelques bits pour respecter le format UUID v4
            $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
            $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
            return vsprintf('%08s-%04s-%04x-%04x-%12s', str_split(bin2hex($data), 4));
        } // Exemple d'utilisation
        $uuid = generateUuid();
        $client = new Client();
        $response = $client->post(
            'https://demo.campay.net/api/withdraw/',
            [
                'headers' => [
                    'Authorization' => 'Token b7a8bcef814b1c221e89ada3ddb2babf33605a13',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'amount' => "25", // Un entier valide, par exemple "5"
                    'to' => "237" . $phoneNumber, // Un numéro de téléphone valide avec indicatif du pays, ex: "237679587525"
                    'description' => "Reception cadeau", // Description du paiement
                    'external_reference' => '5d457dca-e510-4cf8-a4be-0c6f6049b859', // FACULTATIF. Référence de la transaction générée par ton système
                    'uuid' => '5d457dca-e510-4cf8-a4be-0c6f6049b859', // Un UUID4 valide
                ]
            ]
        );
        return response()->json(json_decode($response->getBody()), $response->getStatusCode());

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

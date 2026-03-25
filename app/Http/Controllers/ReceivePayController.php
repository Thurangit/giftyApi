<?php

namespace App\Http\Controllers;

use App\Models\benefit;
use App\Models\Gift;
use App\Models\GiftRef;
use DateTime;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use App\Support\LinkUnavailable;
use App\Helpers\EyamoUserResolver;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;

class ReceivePayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function infoGift($ref)
    {
        $gift = Gift::where('ref_two', '=', $ref)->first();

        if (! $gift) {
            return LinkUnavailable::response(LinkUnavailable::DELETED, 404);
        }

        if ($gift->status === 'cancelled') {
            return LinkUnavailable::response(LinkUnavailable::CANCELLED, 410);
        }

        if (in_array($gift->status, ['received', 'completed'], true)) {
            return LinkUnavailable::response(LinkUnavailable::ALREADY_WON, 410);
        }

        return response()->json($gift);
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
        $request->validate([
            'refGift' => 'required|string|max:255',
            'giftAmount' => 'required|numeric|min:1',
            'paymentMethod' => 'required|string|max:50',
            'identity_phone' => 'required|string|max:32',
            'identity_email' => 'nullable|string|email|max:255',
            'receiver_name' => 'nullable|string|max:120',
        ]);

        $amount = (int) $request->input('giftAmount');
        $ref = $request->input('refGift');
        $operator = $request->input('paymentMethod');
        $phoneNumber = $request->input('phoneNumber');

        if ($operator === 'Eyamo') {
            $request->validate([
                'eyamo_identifier' => 'required|string|max:255',
            ]);
            $eyamoUser = EyamoUserResolver::resolve((string) $request->input('eyamo_identifier'));
            if (! $eyamoUser) {
                return response()->json([
                    'message' => 'Compte Eyamo introuvable. Utilisez votre code E##-#######, email ou numéro enregistré.',
                ], 422);
            }
            $rawPhone = preg_replace('/\D/', '', (string) $eyamoUser->phone);
            if ($rawPhone === '' || strlen($rawPhone) < 9) {
                return response()->json([
                    'message' => 'Ce compte Eyamo ne dispose pas d\'un numéro de téléphone valide pour le versement.',
                ], 422);
            }
            $phoneNumber = $rawPhone;
        } elseif (in_array($operator, ['Orange', 'MTN'], true)) {
            $request->validate([
                'phoneNumber' => 'required|string|max:20',
            ]);
            $phoneNumber = preg_replace('/\D/', '', (string) $phoneNumber);
        } else {
            /* Carte ou autre : versement mobile non utilisé ; enregistrer uniquement l’identité côté tracking */
            $phoneNumber = $request->input('phoneNumber')
                ? preg_replace('/\D/', '', (string) $request->input('phoneNumber'))
                : '';
        }

        $normIdentity = PhoneNormalizer::normalizeCm($request->input('identity_phone'));
        if ($normIdentity === '') {
            return response()->json([
                'message' => 'Numéro d\'identification invalide (requis pour sécuriser votre retrait, distinct du compte de versement).',
            ], 422);
        }

        $identityEmail = $request->input('identity_email')
            ? strtolower(trim((string) $request->input('identity_email')))
            : null;
        $receiverDisplayName = $request->input('receiver_name')
            ? mb_substr(trim((string) $request->input('receiver_name')), 0, 120)
            : null;

        try {
            return DB::transaction(function () use ($ref, $amount, $operator, $phoneNumber, $normIdentity, $identityEmail, $receiverDisplayName) {
                $infoGift = Gift::where('ref_two', '=', $ref)->lockForUpdate()->first();

                if (! $infoGift) {
                    return response()->json([
                        'error' => 'Cadeau inexistant',
                        'reference' => $ref,
                    ], 404);
                }

                if ($infoGift->status !== 'Send') {
                    return response()->json([
                        'error' => 'Cadeau déjà retiré ou indisponible',
                        'reference' => $ref,
                    ], 408);
                }

                if ((int) $infoGift->amount !== $amount) {
                    return response()->json([
                        'message' => 'Le montant ne correspond pas à ce cadeau.',
                    ], 422);
                }

                $existingTrack = $infoGift->receiver_tracking_phone
                    ? PhoneNormalizer::normalizeCm($infoGift->receiver_tracking_phone)
                    : '';
                if ($existingTrack !== '') {
                    if (! PhoneNormalizer::same($existingTrack, $normIdentity)) {
                        return response()->json([
                            'message' => 'Ce numéro d\'identification ne correspond pas à celui enregistré pour ce cadeau.',
                        ], 403);
                    }
                }

                $Gift_Ref = GiftRef::where('ref', '=', $infoGift->ref_one)->lockForUpdate()->first();
                $Gift_App = benefit::where('ref', '=', $infoGift->ref_one)->lockForUpdate()->first();

                if (! $Gift_Ref || $Gift_Ref->status !== 'Send') {
                    return response()->json([
                        'error' => 'Référence de paiement invalide ou déjà utilisée',
                        'reference' => $ref,
                    ], 409);
                }

                if (! $Gift_App || $Gift_App->status !== 'Send') {
                    return response()->json([
                        'error' => 'Référence de commission invalide ou déjà utilisée',
                        'reference' => $ref,
                    ], 409);
                }

                $data = random_bytes(16);
                $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
                $uuid = vsprintf('%08s-%04s-%04x-%04x-%12s', str_split(bin2hex($data), 4));

                $responseBody = [
                    'status' => 'SUCCESSFUL',
                    'reference' => $uuid,
                    'message' => 'Transaction réussie (simulation)',
                ];

                $updateGift = [
                    'receiver_opertor' => $operator,
                    'receiver' => $phoneNumber,
                    'receiver_tracking_phone' => $normIdentity,
                    'status' => 'received',
                ];
                if ($identityEmail) {
                    $updateGift['receiver_tracking_email'] = $identityEmail;
                }
                if ($receiverDisplayName) {
                    $updateGift['receiver_identity_name'] = $receiverDisplayName;
                }

                $infoGift->update($updateGift);
                $Gift_Ref->update(['status' => 'received']);
                $Gift_App->update(['status' => 'received']);

                return response()->json($responseBody, 200);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Erreur lors du traitement du retrait',
                'reference' => $ref,
            ], 500);
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

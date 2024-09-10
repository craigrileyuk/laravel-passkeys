<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Passkey;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Webauthn\PublicKeyCredential;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Illuminate\Validation\ValidationException;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;

class PasskeyController extends Controller
{
    public function authenticate(Request $request)
    {
        $validated = $request->validate([
            'answer' => ['required', 'array']
        ]);

        $options = Session::get('passkey-authentication-options');
        $options->challenge = base64_decode($options->challenge);

        $requestCSM = (new CeremonyStepManagerFactory)->requestCeremony();

        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
            ->create()
            ->deserialize(json_encode($validated['answer']), PublicKeyCredential::class, 'json');


        $passkey = Passkey::firstWhere('credential_id', $publicKeyCredential->rawId);
        $passkey->data->userHandle = base64_decode($passkey->data->userHandle);

        if (empty($passkey)) {
            throw ValidationException::withMessages([
                'answer' => 'This passkey is not valid'
            ]);
        }


        if (! $publicKeyCredential->response instanceof AuthenticatorAssertionResponse) {
            return to_route('profile.edit');
        }

        try {
            $publicKeyCredentialSource = AuthenticatorAssertionResponseValidator::create($requestCSM)->check(
                publicKeyCredentialSource: $passkey->data,
                authenticatorAssertionResponse: $publicKeyCredential->response,
                publicKeyCredentialRequestOptions: $options,
                host: $request->getHost(),
                userHandle: null,
            );
        } catch (\Throwable $e) {
            dd($e, $passkey->data);
            Log::debug($e->getMessage());
            throw ValidationException::withMessages([
                'name' => 'The given passkey was invalid'
            ]);
        }

        $passkey->update(['data' => $publicKeyCredentialSource]);

        Auth::loginUsingId($passkey->user_id);
        $request->session()->regenerate();

        return to_route('dashboard');
    }

    /**
     * Display a listing of the resource.
     */
    public function registerOptions(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255']
        ]);

        $options = new PublicKeyCredentialCreationOptions(
            rp: new PublicKeyCredentialRpEntity(
                name: config('app.name'),
                id: parse_url(config('app.url'), PHP_URL_HOST)
            ),
            challenge: Str::random(),
            user: new PublicKeyCredentialUserEntity(
                name: $request->user()->email,
                id: base64_encode($request->user()->id),
                displayName: $request->user()->name
            ),
            authenticatorSelection: new AuthenticatorSelectionCriteria(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
            )
        );

        Session::flash('passkey-registration-options', $options);

        return response()->json($options);
    }

    public function authenticateOptions()
    {

        $options = new PublicKeyCredentialRequestOptions(
            challenge: Str::random(),
            rpId: parse_url(config('app.url'), PHP_URL_HOST)
        );

        Session::flash('passkey-authentication-options', $options);

        return response()->json($options);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string'],
            'passkey' => ['required', 'json']
        ]);

        $options = Session::get('passkey-registration-options');
        $options->challenge = base64_decode($options->challenge);
        $csmFactory = new CeremonyStepManagerFactory;
        $creationCSM = $csmFactory->creationCeremony();

        /** @var PublicKeyCredential $publicKeyCredential */
        $publicKeyCredential = (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
            ->create()
            ->deserialize($validated['passkey'], PublicKeyCredential::class, 'json');

        if (! $publicKeyCredential->response instanceof AuthenticatorAttestationResponse) {
            return to_route('login');
        }

        try {
            $publicKeyCredentialSource = AuthenticatorAttestationResponseValidator::create($creationCSM)->check(
                authenticatorAttestationResponse: $publicKeyCredential->response,
                publicKeyCredentialCreationOptions: $options,
                host: $request->getHost()
            );
        } catch (\Throwable $e) {
            Log::debug($e->getMessage());
            throw ValidationException::withMessages([
                'name' => 'The given passkey was invalid'
            ]);
        }

        $request->user()->passkeys()->create([
            'name' => $validated['name'],
            'data' => $publicKeyCredentialSource
        ]);

        return back();
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Passkey $passkey)
    {
        if ($request->user()->cannot('delete', $passkey)) {
            abort(403);
        }

        $passkey->delete();

        return back();
    }
}

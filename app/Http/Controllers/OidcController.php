<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OidcController extends Controller
{
    /**
     * OpenID Connect Discovery Document
     * https://openid.net/specs/openid-connect-discovery-1_0.html
     */
    public function discovery(): JsonResponse
    {
        $issuer = rtrim(config('app.url'), '/');

        return response()->json([
            'issuer'                                => $issuer,
            'authorization_endpoint'                => $issuer . '/oauth/authorize',
            'token_endpoint'                        => $issuer . '/oauth/token',
            'userinfo_endpoint'                     => $issuer . '/api/userinfo',
            'jwks_uri'                              => $issuer . '/oauth/jwks',
            'scopes_supported'                      => ['openid', 'profile', 'email'],
            'response_types_supported'              => ['code'],
            'grant_types_supported'                 => ['authorization_code', 'refresh_token'],
            'subject_types_supported'               => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'client_secret_basic'],
            'claims_supported'                      => [
                'sub', 'name', 'email', 'email_verified',
            ],
        ]);
    }

    /**
     * JSON Web Key Set (JWKS) endpoint
     * Returns the public key used to verify token signatures.
     */
    public function jwks(): JsonResponse
    {
        $publicKeyPath = storage_path('oauth-public.key');

        if (! file_exists($publicKeyPath)) {
            abort(500, 'OAuth public key not found.');
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($publicKeyPath));
        $details   = openssl_pkey_get_details($publicKey);

        return response()->json([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'n'   => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($details['rsa']['n'])), '='),
                    'e'   => rtrim(str_replace(['+', '/'], ['-', '_'], base64_encode($details['rsa']['e'])), '='),
                ],
            ],
        ]);
    }

    /**
     * OIDC UserInfo endpoint
     * Returns claims about the authenticated user.
     */
    public function userinfo(Request $request): JsonResponse
    {
        $user   = $request->user();
        $scopes = $request->user()->token()->scopes;

        $claims = [
            'sub' => (string) $user->id,
        ];

        if (in_array('profile', $scopes) || in_array('openid', $scopes)) {
            $claims['name'] = $user->name;
        }

        if (in_array('email', $scopes) || in_array('openid', $scopes)) {
            $claims['email']          = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        return response()->json($claims);
    }
}

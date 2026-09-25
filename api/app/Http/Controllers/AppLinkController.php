<?php

namespace App\Http\Controllers;

use App\Models\GroupInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AppLinkController extends Controller
{
    public function show(Request $request): Response
    {
        $route = $request->route()->getName();
        $parameters = [];
        $title = 'Open Ovezi';
        $error = null;
        if ($route === 'app.invite') {
            $parameters = $request->validate(['token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/']]);
            $invite = GroupInvite::query()->where('token_hash', hash('sha256', $parameters['token']))->first();
            $error = ! $invite || $invite->revoked_at || $invite->expires_at->isPast() || $invite->accepted_at
                ? 'This invitation has expired, was revoked, or has already been used. Ask the group owner for a new link.' : null;
            $title = 'You’re invited to Ovezi';
        } elseif ($route === 'app.reset') {
            $parameters = $request->validate(['token' => ['required', 'string', 'max:255'], 'email' => ['required', 'email:rfc', 'max:255']]);
            $title = 'Reset your password';
        } else {
            $parameters = $request->validate(['verification_url' => ['required', 'url', 'max:2048']]);
            $url = parse_url($parameters['verification_url']);
            $expected = parse_url(config('app.url'));
            abort_unless(($url['host'] ?? '') === ($expected['host'] ?? '')
                && ($url['scheme'] ?? '') === ($expected['scheme'] ?? '')
                && ($url['port'] ?? null) === ($expected['port'] ?? null)
                && preg_match('#^/api/v1/auth/email/verify/[0-9]+/[a-f0-9]+$#', $url['path'] ?? ''), 422);
            $title = 'Verify your email';
        }
        $deepLink = config('ovezi.deep_link_scheme').'://'.$request->path().'?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return response()->view('app-link', compact('title', 'error', 'deepLink'))
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function apple(): JsonResponse
    {
        $team = config('ovezi.social.apple_team_id');
        $bundle = config('ovezi.social.apple_client_id');

        return response()->json(['applinks' => ['apps' => [], 'details' => $team && $bundle ? [[
            'appID' => $team.'.'.$bundle,
            'paths' => ['/group-invites/accept', '/auth/reset-password', '/auth/verify-email'],
        ]] : []]]);
    }

    public function android(): JsonResponse
    {
        $fingerprints = config('ovezi.android_sha256_fingerprints');

        return response()->json($fingerprints ? [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => config('ovezi.android_package'), 'sha256_cert_fingerprints' => $fingerprints],
        ]] : []);
    }
}

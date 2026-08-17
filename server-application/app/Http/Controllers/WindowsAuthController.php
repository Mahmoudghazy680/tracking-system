<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\ScreenshotsState;
use App\Exceptions\Entities\AuthorizationException;
use App\Http\Requests\Auth\WindowsLoginRequest;
use App\Http\Transformers\AuthTokenTransformer;
use App\Models\User;
use App\Services\MonitoringTaskProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WindowsAuthController extends BaseController
{
    private static ?bool $hasDomainUserColumn = null;

    /**
     * @return array{lookup: string, canonical: string, username: string, domain: string, email_domain: string}
     */
    private static function normalizeIdentity(string $identity): array
    {
        $identity = trim($identity);
        if ($identity === '') {
            return [
                'lookup' => '',
                'canonical' => '',
                'username' => '',
                'domain' => '',
                'email_domain' => '',
            ];
        }

        $identity = str_replace('/', '\\', $identity);
        $identity = preg_replace('/\\\\+/', '\\\\', $identity);
        $lookup = mb_strtolower($identity);

        $domain = '';
        $emailDomain = '';
        $username = $lookup;

        if (str_contains($lookup, '\\')) {
            [$domain, $username] = explode('\\', $lookup, 2);
        } elseif (str_contains($lookup, '@')) {
            [$username, $domain] = explode('@', $lookup, 2);
            $emailDomain = $domain;
            $domain = explode('.', $domain, 2)[0] ?? $domain;
        }

        $domain = trim($domain);
        $emailDomain = trim($emailDomain);
        $username = trim($username);
        $canonical = $domain !== '' && $username !== ''
            ? mb_strtoupper($domain) . '\\' . $username
            : $username;

        return [
            'lookup' => $lookup,
            'canonical' => $canonical,
            'username' => $username,
            'domain' => $domain,
            'email_domain' => $emailDomain,
        ];
    }

    /**
     * @return string[]
     */
    private static function buildIdentityCandidates(string $identity): array
    {
        $normalizedIdentity = self::normalizeIdentity($identity);
        $normalized = $normalizedIdentity['lookup'];
        if ($normalized === '') {
            return [];
        }

        $candidates = [$normalized];
        if ($normalizedIdentity['canonical'] !== '') {
            $candidates[] = mb_strtolower($normalizedIdentity['canonical']);
        }

        if (str_contains($normalized, '\\')) {
            $parts = explode('\\', $normalized);
            $short = end($parts);
            if (is_string($short) && $short !== '') {
                $candidates[] = $short;
            }
        } elseif (str_contains($normalized, '@')) {
            $parts = explode('@', $normalized);
            $short = $parts[0] ?? '';
            if ($short !== '') {
                $candidates[] = $short;
            }
        }

        return array_values(array_unique($candidates));
    }

    private static function hasDomainUserColumn(): bool
    {
        if (self::$hasDomainUserColumn !== null) {
            return self::$hasDomainUserColumn;
        }

        self::$hasDomainUserColumn = Schema::hasColumn('users', 'domain_user');

        return self::$hasDomainUserColumn;
    }

    private static function createDomainUser(array $identity): User
    {
        $username = $identity['username'];
        $domain = $identity['domain'];
        $emailDomain = $identity['email_domain'] !== ''
            ? $identity['email_domain']
            : trim($domain . '.local', '.');

        $email = $emailDomain !== ''
            ? "{$username}@{$emailDomain}"
            : "{$username}@domain.local";
        $email = self::buildUniqueEmail($email);

        $user = User::withoutGlobalScopes()->create([
            'full_name' => $username,
            'email' => $email,
            'windows_username' => $identity['canonical'],
            'domain_user' => self::hasDomainUserColumn() ? $identity['canonical'] : null,
            'url' => '',
            'company_id' => 1,
            'avatar' => '',
            'screenshots_state' => ScreenshotsState::REQUIRED,
            'manual_time' => 0,
            'computer_time_popup' => 300,
            'blur_screenshots' => 0,
            'web_and_app_monitoring' => 1,
            'screenshots_interval' => 5,
            'active' => (int) config('auth.windows_auth_jit_active', true),
            'password' => Hash::make(Str::random(64)),
            'change_password' => 0,
            'user_language' => 'en',
            'role_id' => Role::USER->value,
            'type' => 'employee',
            'invitation_sent' => false,
            'client_installed' => 1,
            'last_activity' => now(),
        ]);

        Log::info('Windows domain auth JIT user created', [
            'user_id' => $user->id,
            'domain_user' => $identity['canonical'],
            'active' => (bool) $user->active,
        ]);

        return $user;
    }

    private static function buildUniqueEmail(string $email): string
    {
        if (!User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->exists()) {
            return $email;
        }

        [$localPart, $domain] = explode('@', $email, 2) + [1 => 'domain.local'];

        do {
            $candidate = sprintf('%s+%s@%s', $localPart, Str::lower(Str::random(8)), $domain);
        } while (User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [mb_strtolower($candidate)])->exists());

        return $candidate;
    }

    public function login(WindowsLoginRequest $request): JsonResponse
    {
        $configuredSecret = (string) config('auth.windows_auth_secret', '');
        $incomingSecret = (string) $request->input('device_secret', '');

        if ($configuredSecret === '' || !hash_equals($configuredSecret, $incomingSecret)) {
            Log::warning('Windows domain auth rejected: invalid device secret', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            throw new AuthorizationException(AuthorizationException::ERROR_TYPE_UNAUTHORIZED);
        }

        $rawIdentity = (string) ($request->input('domain_user') ?: $request->input('windows_username', ''));
        $normalizedIdentity = self::normalizeIdentity($rawIdentity);
        $identityCandidates = self::buildIdentityCandidates($rawIdentity);

        if (count($identityCandidates) === 0) {
            Log::warning('Windows domain auth rejected: empty identity', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            throw new AuthorizationException(AuthorizationException::ERROR_TYPE_UNAUTHORIZED);
        }

        $query = User::withoutGlobalScopes()->where(static function ($query) use ($identityCandidates) {
            foreach ($identityCandidates as $candidate) {
                $query->orWhereRaw('LOWER(windows_username) = ?', [$candidate]);
            }

            if (self::hasDomainUserColumn()) {
                foreach ($identityCandidates as $candidate) {
                    $query->orWhereRaw('LOWER(domain_user) = ?', [$candidate]);
                }
            }
        });

        $user = $query->first();

        // Backward-compatible fallback for deployments where windows_username/domain_user
        // has not been populated yet, but email local-part matches domain login.
        if (!$user) {
            $emailMatchQuery = User::withoutGlobalScopes()->where(static function ($query) use ($identityCandidates) {
                foreach ($identityCandidates as $candidate) {
                    $query->orWhereRaw('LOWER(SUBSTRING_INDEX(email, "@", 1)) = ?', [$candidate]);
                }
            });

            $emailMatches = $emailMatchQuery->limit(2)->get();
            if ($emailMatches->count() === 1) {
                $user = $emailMatches->first();
            }
        }

        if (!$user && config('auth.windows_auth_jit_enabled', true) && $normalizedIdentity['canonical'] !== '') {
            $user = self::createDomainUser($normalizedIdentity);
        } elseif (!$user) {
            Log::warning('Windows domain auth rejected: user not mapped', [
                'domain_user' => $normalizedIdentity['canonical'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            throw new AuthorizationException(AuthorizationException::ERROR_TYPE_UNAUTHORIZED);
        }

        if (!$user->active) {
            Log::warning('Windows domain auth rejected: user disabled', [
                'user_id' => $user->id,
                'domain_user' => $normalizedIdentity['canonical'],
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            throw new AuthorizationException(AuthorizationException::ERROR_TYPE_USER_DISABLED);
        }

        auth()->login($user);

        if ($user->invitation_sent) {
            $user->invitation_sent = false;
        }

        if (preg_match('/' . config('auth.Tracker-client-agent') . '/', (string) $request->header('User_agent'))) {
            $user->client_installed = 1;
        }

        try {
            MonitoringTaskProvisioner::ensureForUser($user);
        } catch (\Throwable $e) {
            report($e);
        }

        $user->save();

        Log::info('Windows domain auth succeeded', [
            'user_id' => $user->id,
            'domain_user' => $normalizedIdentity['canonical'],
            'ip' => $request->ip(),
        ]);

        return responder()->success([
            'token' => $user->createToken(Str::uuid())->plainTextToken,
        ], new AuthTokenTransformer)->respond();
    }
}

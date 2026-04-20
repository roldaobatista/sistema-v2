<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\EnsurePortalAccess;
use App\Models\ClientPortalUser;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * sec-11: EnsurePortalAccess deve respeitar campos de hardening
 * ja existentes no model ClientPortalUser (locked_until,
 * two_factor_confirmed_at) — nao so is_active/tenant_id/customer_id.
 */
class EnsurePortalAccessHardeningTest extends TestCase
{
    private function runMiddleware(ClientPortalUser $user): Response
    {
        $middleware = new EnsurePortalAccess;
        $request = Request::create('/api/v1/portal/me');
        $request->setUserResolver(fn () => $user);

        // Token com ability 'portal:access' — o middleware checa
        // currentAccessToken()->tokenCan('portal:access').
        $tokenResult = $user->createToken('portal-test', ['portal:access']);
        $request->headers->set('Authorization', 'Bearer '.$tokenResult->plainTextToken);
        $request->setUserResolver(function () use ($user, $tokenResult) {
            $user->withAccessToken($tokenResult->accessToken);

            return $user;
        });

        try {
            return $middleware->handle($request, fn () => response('OK', 200));
        } catch (HttpException $e) {
            return response(
                json_encode(['message' => $e->getMessage(), 'details' => $e->getHeaders()]),
                $e->getStatusCode(),
                array_merge(['Content-Type' => 'application/json'], $e->getHeaders())
            );
        } catch (HttpResponseException $e) {
            // abort(response()->json([...], 403)) lança HttpResponseException.
            return $e->getResponse();
        }
    }

    private function makePortalUser(array $overrides = []): ClientPortalUser
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        $attrs = array_merge([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'name' => 'Portal User '.uniqid(),
            'email' => 'portal-'.uniqid().'@example.com',
            'password' => 'SenhaForte@123456!',
            'is_active' => true,
            'locked_until' => null,
            'two_factor_enabled' => false,
            'two_factor_confirmed_at' => null,
        ], $overrides);

        // sec-18: locked_until e two_factor_confirmed_at saíram de $fillable;
        // cenário de teste precisa seedar via forceFill.
        $user = new ClientPortalUser;
        $user->forceFill($attrs);
        $user->save();

        return $user->fresh();
    }

    public function test_locked_until_in_future_blocks_access(): void
    {
        $user = $this->makePortalUser([
            'locked_until' => now()->addMinutes(15),
        ]);

        $response = $this->runMiddleware($user);

        $this->assertSame(403, $response->getStatusCode(), 'Usuario bloqueado (locked_until futuro) deveria receber 403.');
    }

    public function test_locked_until_in_past_allows_access(): void
    {
        $user = $this->makePortalUser([
            'locked_until' => now()->subMinutes(5),
        ]);

        $response = $this->runMiddleware($user);

        $this->assertSame(200, $response->getStatusCode(), 'locked_until expirado (passado) nao deve bloquear.');
    }

    public function test_two_factor_enabled_without_confirmation_blocks_access(): void
    {
        $user = $this->makePortalUser([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => null,
        ]);

        $response = $this->runMiddleware($user);

        $this->assertSame(403, $response->getStatusCode(), '2FA habilitado sem confirmacao deve bloquear com 403.');
    }

    public function test_two_factor_enabled_and_confirmed_allows_access(): void
    {
        $user = $this->makePortalUser([
            'two_factor_enabled' => true,
            'two_factor_confirmed_at' => now()->subDay(),
        ]);

        $response = $this->runMiddleware($user);

        $this->assertSame(200, $response->getStatusCode(), '2FA confirmado deve permitir acesso normal.');
    }
}

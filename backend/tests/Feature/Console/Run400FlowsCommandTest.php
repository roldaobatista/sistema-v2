<?php

namespace Tests\Feature\Console;

use App\Console\Commands\Run400FlowsCommand;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

class Run400FlowsCommandTest extends TestCase
{
    public function test_quote_whatsapp_flow_uses_current_endpoint_contract(): void
    {
        Http::fake([
            'http://kalibrium.test/api/v1/quotes/321/whatsapp*' => Http::response([
                'data' => ['url' => 'https://wa.me/5566999887766'],
            ], 200),
        ]);

        $command = $this->makeCommandWithQuoteContext(321);

        $result = $this->invokePrivateMethod($command, 'runFlow311');

        $this->assertSame('PASSOU', $result['status']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/api/v1/quotes/321/whatsapp')
                && ($request->data()['phone'] ?? null) === '(66) 99988-7766';
        });
    }

    public function test_quote_email_flow_uses_current_endpoint_contract(): void
    {
        Http::fake([
            'http://kalibrium.test/api/v1/quotes/654/email' => Http::response([
                'message' => 'queued',
            ], 200),
        ]);

        $command = $this->makeCommandWithQuoteContext(654);

        $result = $this->invokePrivateMethod($command, 'runFlow312');

        $this->assertSame('PASSOU', $result['status']);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'http://kalibrium.test/api/v1/quotes/654/email'
                && ($payload['to'] ?? null) === 'cliente@empresa.com';
        });
    }

    public function test_portal_quote_flow_uses_current_quotes_listing_endpoint(): void
    {
        Http::fake([
            'http://kalibrium.test/api/v1/portal/quotes' => Http::response([
                'message' => 'Acesso restrito ao portal do cliente.',
            ], 403),
        ]);

        $command = $this->makeCommandWithQuoteContext(987);

        $result = $this->invokePrivateMethod($command, 'runFlow206');

        $this->assertSame('PASSOU', $result['status']);

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === 'http://kalibrium.test/api/v1/portal/quotes';
        });
    }

    private function makeCommandWithQuoteContext(int $quoteId): Run400FlowsCommand
    {
        $command = new Run400FlowsCommand;
        $reflection = new ReflectionClass($command);

        $baseUrlProperty = $reflection->getProperty('baseUrl');
        $baseUrlProperty->setAccessible(true);
        $baseUrlProperty->setValue($command, 'http://kalibrium.test');

        $tokenProperty = $reflection->getProperty('token');
        $tokenProperty->setAccessible(true);
        $tokenProperty->setValue($command, 'fake-token');

        $ctxProperty = $reflection->getProperty('ctx');
        $ctxProperty->setAccessible(true);
        $ctx = $ctxProperty->getValue($command);
        $ctx['quoteId'] = $quoteId;
        $ctxProperty->setValue($command, $ctx);

        return $command;
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function invokePrivateMethod(Run400FlowsCommand $command, string $method): array
    {
        $reflection = new ReflectionClass($command);
        $privateMethod = $reflection->getMethod($method);
        $privateMethod->setAccessible(true);

        /** @var array{status: string, detail: string} $result */
        $result = $privateMethod->invoke($command);

        return $result;
    }
}

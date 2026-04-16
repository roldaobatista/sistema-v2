# Padrão de log em catch blocks

Para novos controllers ou ao alterar controllers existentes, use:

```php
} catch (\Throwable $e) {
    Log::error('NomeController::metodo failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'user_id' => auth()->id(),
        'input' => $request->except(['password', 'password_confirmation']),
    ]);
    return ApiResponse::error('Erro ao processar', 500);
}
```

Com Sentry instalado, o stack trace completo já é enviado automaticamente. O log local serve como backup.

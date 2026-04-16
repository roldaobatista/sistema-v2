<?php

namespace App\Services;

use App\Models\InmetroOwner;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InmetroEnrichmentService
{
    /**
     * Enrich owner contact data from multiple sources (deep extraction).
     */
    public function enrichOwner(InmetroOwner $owner): array
    {
        $enriched = ['source' => [], 'data' => []];

        $document = $owner->document;
        if (str_starts_with($document, 'SEM-DOC')) {
            return ['success' => false, 'error' => 'No document available for enrichment'];
        }

        $isCnpj = strlen(preg_replace('/\D/', '', $document)) === 14;

        if ($isCnpj) {
            // 1. OpenCNPJ (primary — free, unlimited, CDN-fast)
            $result = $this->enrichFromOpenCnpj($document);
            if ($result) {
                $enriched = $this->mergeEnrichment($enriched, $result, 'opencnpj');
            }

            // 2. BrasilAPI (secondary — more data)
            if (empty($enriched['data']['phone'])) {
                $result = $this->enrichFromBrasilApi($document);
                if ($result) {
                    $enriched = $this->mergeEnrichment($enriched, $result, 'brasilapi');
                }
            }

            // 3. ReceitaWS (fallback)
            if (empty($enriched['data']['phone']) && empty($enriched['data']['email'])) {
                $result = $this->enrichFromReceitaWs($document);
                if ($result) {
                    $enriched = $this->mergeEnrichment($enriched, $result, 'receitaws');
                }
            }
        } else {
            // CPF — try SEFAZ-MT for rural producers
            $result = $this->enrichFromSintegraMt($document);
            if ($result) {
                $enriched = $this->mergeEnrichment($enriched, $result, 'sintegra_mt');
            }
        }

        // 4. ViaCEP — complement address for locations with CEP but missing details
        $this->enrichLocationsFromViaCep($owner);

        // 5. Web Search — find additional contact info
        if (empty($enriched['data']['phone']) && empty($enriched['data']['email'])) {
            $result = $this->enrichFromWebSearch($owner->name, $document);
            if ($result) {
                $enriched = $this->mergeEnrichment($enriched, $result, 'web_search');
            }
        }

        if (! empty($enriched['data'])) {
            $updateData = array_filter([
                'name' => $enriched['data']['name'] ?? null,
                'phone' => $enriched['data']['phone'] ?? null,
                'phone2' => $enriched['data']['phone2'] ?? null,
                'email' => $enriched['data']['email'] ?? null,
                'trade_name' => $enriched['data']['trade_name'] ?? null,
                'contact_source' => implode(',', $enriched['source']),
                'contact_enriched_at' => now(),
            ]);

            $owner->update($updateData);

            if (! empty($enriched['data']['locations'])) {
                foreach ($enriched['data']['locations'] as $locData) {
                    $city = $locData['city'] ?? '';
                    if (empty($city)) {
                        continue;
                    }

                    $existing = $owner->locations()->where('address_city', $city)->first();
                    if ($existing) {
                        $existing->update(array_filter([
                            'address_street' => $locData['street'] ?? null,
                            'address_number' => $locData['number'] ?? null,
                            'address_complement' => $locData['complement'] ?? null,
                            'address_neighborhood' => $locData['neighborhood'] ?? null,
                            'address_zip' => $locData['zip'] ?? null,
                            'state_registration' => $locData['ie'] ?? null,
                            'phone_local' => $locData['phone'] ?? null,
                            'email_local' => $locData['email'] ?? null,
                        ]));
                    }
                }
            }

            return ['success' => true, 'enriched' => $enriched];
        }

        return ['success' => false, 'error' => 'No data found from any source'];
    }

    /**
     * Deep enrichment — runs ALL sources including web search. For manual trigger.
     */
    public function deepEnrichOwner(InmetroOwner $owner): array
    {
        $results = ['sources_tried' => [], 'sources_success' => [], 'data' => []];
        $document = $owner->document;
        $isCnpj = strlen(preg_replace('/\D/', '', $document)) === 14;

        $sources = $isCnpj
            ? ['opencnpj', 'brasilapi', 'receitaws', 'viacep', 'web_search']
            : ['sintegra_mt', 'viacep', 'web_search'];

        foreach ($sources as $source) {
            $results['sources_tried'][] = $source;
            $data = match ($source) {
                'opencnpj' => $this->enrichFromOpenCnpj($document),
                'brasilapi' => $this->enrichFromBrasilApi($document),
                'receitaws' => $this->enrichFromReceitaWs($document),
                'sintegra_mt' => $this->enrichFromSintegraMt($document),
                'viacep' => null, // handled separately
                'web_search' => $this->enrichFromWebSearch($owner->name, $document),
                default => null,
            };

            if ($data) {
                $results['sources_success'][] = $source;
                $results['data'] = array_merge($results['data'], $data);
            }

            if ($source !== 'viacep') {
                usleep(300000); // 300ms between requests
            }
        }

        // ViaCEP enrichment for locations
        $this->enrichLocationsFromViaCep($owner);

        // Apply enrichment
        $basicResult = $this->enrichOwner($owner);

        return [
            'success' => true,
            'deep_results' => $results,
            'basic_result' => $basicResult,
        ];
    }

    /**
     * Batch enrich multiple owners.
     */
    public function enrichBatch(array $ownerIds, int $tenantId): array
    {
        $stats = ['enriched' => 0, 'failed' => 0, 'skipped' => 0];

        $owners = InmetroOwner::where('tenant_id', $tenantId)
            ->whereIn('id', $ownerIds)
            ->whereNull('contact_enriched_at')
            ->get();

        foreach ($owners as $owner) {
            if (str_starts_with($owner->document, 'SEM-DOC')) {
                $stats['skipped']++;
                continue;
            }

            $result = $this->enrichOwner($owner);
            if ($result['success']) {
                $stats['enriched']++;
            } else {
                $stats['failed']++;
            }

            usleep(500000); // 500ms rate limit
        }

        return $stats;
    }

    // ─── Source: OpenCNPJ (FREE, unlimited, CDN) ───

    private function enrichFromOpenCnpj(string $cnpj): ?array
    {
        $cleanCnpj = preg_replace('/\D/', '', $cnpj);
        $cacheKey = "opencnpj_{$cleanCnpj}";

        return Cache::remember($cacheKey, 86400 * 7, function () use ($cleanCnpj) {
            try {
                // OpenCNPJ uses CDN-hosted static files
                $p1 = substr($cleanCnpj, 0, 2);
                $p2 = substr($cleanCnpj, 2, 3);
                $p3 = substr($cleanCnpj, 5, 3);
                $rest = substr($cleanCnpj, 8);

                $response = Http::timeout(10)
                    ->get("https://open.cnpja.com/office/{$cleanCnpj}");

                if (! $response->successful()) {
                    // Fallback: try publica.cnpj.ws
                    $response = Http::timeout(10)
                        ->get("https://publica.cnpj.ws/cnpj/{$cleanCnpj}");
                    if (! $response->successful()) {
                        return null;
                    }
                }

                $data = $response->json();

                // Normalize — different APIs have different field names
                $phone1 = $data['telefone1'] ?? $data['ddd_telefone_1']
                    ?? ($data['estabelecimento']['ddd1'] ?? '').($data['estabelecimento']['telefone1'] ?? '')
                    ?? null;
                $phone2 = $data['telefone2'] ?? $data['ddd_telefone_2']
                    ?? ($data['estabelecimento']['ddd2'] ?? '').($data['estabelecimento']['telefone2'] ?? '')
                    ?? null;

                return [
                    'name' => $data['razao_social'] ?? $data['razaoSocial'] ?? $data['company']['name'] ?? null,
                    'trade_name' => $data['nome_fantasia'] ?? $data['nomeFantasia']
                        ?? $data['estabelecimento']['nome_fantasia'] ?? $data['alias'] ?? null,
                    'phone' => $phone1 ?: null,
                    'phone2' => $phone2 ?: null,
                    'email' => $data['email'] ?? $data['estabelecimento']['email'] ?? null,
                    'locations' => [
                        [
                            'street' => $data['logradouro'] ?? $data['estabelecimento']['logradouro'] ?? $data['address']['street'] ?? null,
                            'number' => $data['numero'] ?? $data['estabelecimento']['numero'] ?? $data['address']['number'] ?? null,
                            'complement' => $data['complemento'] ?? $data['estabelecimento']['complemento'] ?? $data['address']['details'] ?? null,
                            'neighborhood' => $data['bairro'] ?? $data['estabelecimento']['bairro'] ?? $data['address']['district'] ?? null,
                            'city' => $data['municipio'] ?? $data['estabelecimento']['cidade']['nome'] ?? $data['address']['city'] ?? null,
                            'zip' => $data['cep'] ?? $data['estabelecimento']['cep'] ?? $data['address']['zip'] ?? null,
                        ],
                    ],
                ];
            } catch (\Exception $e) {
                Log::warning('OpenCNPJ enrichment failed', ['cnpj' => $cleanCnpj, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    // ─── Source: BrasilAPI (FREE, rate-limited) ───

    private function enrichFromBrasilApi(string $cnpj): ?array
    {
        $cleanCnpj = preg_replace('/\D/', '', $cnpj);
        $cacheKey = "brasilapi_cnpj_{$cleanCnpj}";

        return Cache::remember($cacheKey, 86400 * 7, function () use ($cleanCnpj) {
            try {
                $response = Http::timeout(10)->get("https://brasilapi.com.br/api/cnpj/v1/{$cleanCnpj}");
                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();

                return [
                    'name' => $data['razao_social'] ?? null,
                    'trade_name' => $data['nome_fantasia'] ?? null,
                    'phone' => $data['ddd_telefone_1'] ?? null,
                    'phone2' => $data['ddd_telefone_2'] ?? null,
                    'email' => $data['email'] ?? null,
                    'locations' => [
                        [
                            'street' => ($data['descricao_tipo_de_logradouro'] ?? '').' '.($data['logradouro'] ?? ''),
                            'number' => $data['numero'] ?? null,
                            'complement' => $data['complemento'] ?? null,
                            'neighborhood' => $data['bairro'] ?? null,
                            'city' => $data['municipio'] ?? null,
                            'zip' => $data['cep'] ?? null,
                        ],
                    ],
                ];
            } catch (\Exception $e) {
                Log::warning('BrasilAPI enrichment failed', ['cnpj' => $cleanCnpj, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    // ─── Source: ReceitaWS (FREE, 3/min, cache-based) ───

    private function enrichFromReceitaWs(string $cnpj): ?array
    {
        $cleanCnpj = preg_replace('/\D/', '', $cnpj);
        $cacheKey = "receitaws_cnpj_{$cleanCnpj}";

        return Cache::remember($cacheKey, 86400 * 7, function () use ($cleanCnpj) {
            try {
                $response = Http::timeout(10)->get("https://receitaws.com.br/v1/cnpj/{$cleanCnpj}");
                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json();
                if (($data['status'] ?? '') === 'ERROR') {
                    return null;
                }

                return [
                    'name' => $data['nome'] ?? null,
                    'trade_name' => $data['fantasia'] ?? null,
                    'phone' => $data['telefone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'locations' => [
                        [
                            'street' => $data['logradouro'] ?? null,
                            'number' => $data['numero'] ?? null,
                            'complement' => $data['complemento'] ?? null,
                            'neighborhood' => $data['bairro'] ?? null,
                            'city' => $data['municipio'] ?? null,
                            'zip' => $data['cep'] ?? null,
                        ],
                    ],
                ];
            } catch (\Exception $e) {
                Log::warning('ReceitaWS enrichment failed', ['cnpj' => $cleanCnpj, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    // ─── Source: SEFAZ-MT / SINTEGRA (CPF → IE for rural producers) ───

    private function enrichFromSintegraMt(string $cpf): ?array
    {
        $cleanCpf = preg_replace('/\D/', '', $cpf);
        $cacheKey = "sintegra_mt_cpf_{$cleanCpf}";

        return Cache::remember($cacheKey, 86400 * 3, function () use ($cleanCpf) {
            try {
                // Try SEFAZ-MT public consultation
                $response = Http::timeout(15)
                    ->asForm()
                    ->post('https://www.sefaz.mt.gov.br/servidor/consultaCadastro', [
                        'tipoBusca' => 'CPF',
                        'cpfCnpj' => $cleanCpf,
                    ]);

                if (! $response->successful()) {
                    // Fallback: try CCC API
                    return $this->enrichFromCcc($cleanCpf);
                }

                $html = $response->body();

                // Check for CAPTCHA or error
                if (str_contains($html, 'captcha') || str_contains($html, 'Nenhum cadastro')) {
                    Log::info('SEFAZ-MT requires CAPTCHA or no results', ['cpf' => $cleanCpf]);

                    return $this->enrichFromCcc($cleanCpf);
                }

                // Parse IE, name, address from HTML
                $ie = $this->extractHtmlField($html, 'Inscrição Estadual');
                $name = $this->extractHtmlField($html, 'Nome/Razão Social');
                $address = $this->extractHtmlField($html, 'Endereço');
                $city = $this->extractHtmlField($html, 'Município');
                $phone = $this->extractHtmlField($html, 'Telefone');

                if (! $ie && ! $name) {
                    return null;
                }

                return [
                    'name' => $name,
                    'phone' => $phone,
                    'locations' => [
                        [
                            'street' => $address,
                            'city' => $city,
                            'ie' => $ie,
                        ],
                    ],
                ];
            } catch (\Exception $e) {
                Log::warning('SEFAZ-MT enrichment failed', ['cpf' => $cleanCpf, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /**
     * CCC (Cadastro Centralizado de Contribuintes) fallback for CPF.
     */
    private function enrichFromCcc(string $cpf): ?array
    {
        try {
            $response = Http::timeout(10)
                ->get("https://brasilapi.com.br/api/registrobr/v1/{$cpf}");

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            return [
                'name' => $data['nome'] ?? $data['name'] ?? null,
                'phone' => $data['telefone'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::info('CCC enrichment unavailable', ['cpf' => $cpf, 'error' => $e->getMessage()]);

            return null;
        }
    }

    // ─── Source: ViaCEP (address completion) ───

    private function enrichLocationsFromViaCep(InmetroOwner $owner): void
    {
        $locations = $owner->locations()
            ->whereNotNull('address_zip')
            ->where(function ($q) {
                $q->whereNull('address_street')
                    ->orWhere('address_street', '');
            })
            ->get();

        foreach ($locations as $location) {
            $cep = preg_replace('/\D/', '', $location->address_zip);
            if (strlen($cep) !== 8) {
                continue;
            }

            $cacheKey = "viacep_{$cep}";
            $data = Cache::remember($cacheKey, 86400 * 30, function () use ($cep) {
                try {
                    $response = Http::timeout(5)->get("https://viacep.com.br/ws/{$cep}/json/");
                    if (! $response->successful()) {
                        return null;
                    }
                    $json = $response->json();
                    if (! empty($json['erro'])) {
                        return null;
                    }

                    return $json;
                } catch (\Exception) {
                    return null;
                }
            });

            if ($data) {
                $location->update(array_filter([
                    'address_street' => $data['logradouro'] ?? null,
                    'address_neighborhood' => $data['bairro'] ?? null,
                    'address_complement' => $location->address_complement ?: ($data['complemento'] ?? null),
                    'address_city' => ! empty($data['localidade']) ? $data['localidade'] : $location->address_city,
                    'address_state' => $data['uf'] ?? $location->address_state,
                ]));
            }

            usleep(100000); // 100ms between ViaCEP requests
        }
    }

    // ─── Source: Web Search (Google Custom Search or scraping) ───

    private function enrichFromWebSearch(string $companyName, string $document): ?array
    {
        $cleanDoc = preg_replace('/\D/', '', $document);
        $cacheKey = "web_search_{$cleanDoc}";

        return Cache::remember($cacheKey, 86400 * 7, function () use ($companyName, $cleanDoc) {
            try {
                // Try Google Custom Search API (100 free queries/day)
                $apiKey = config('services.google.search_api_key');
                $cx = config('services.google.search_cx');

                if ($apiKey && $cx) {
                    $query = urlencode("{$companyName} contato telefone email");
                    $response = Http::timeout(10)
                        ->get('https://www.googleapis.com/customsearch/v1', [
                            'key' => $apiKey,
                            'cx' => $cx,
                            'q' => "{$companyName} contato telefone email",
                            'num' => 5,
                        ]);

                    if ($response->successful()) {
                        return $this->parseSearchResults($response->json());
                    }
                }

                // Fallback: DuckDuckGo instant answer (no API key needed)
                $ddgResponse = Http::timeout(10)
                    ->get('https://api.duckduckgo.com/', [
                        'q' => "{$companyName} {$cleanDoc}",
                        'format' => 'json',
                        'no_redirect' => 1,
                    ]);

                if ($ddgResponse->successful()) {
                    $ddg = $ddgResponse->json();
                    if (! empty($ddg['Abstract'])) {
                        $phone = $this->extractPhoneFromText($ddg['Abstract']);
                        $email = $this->extractEmailFromText($ddg['Abstract']);
                        if ($phone || $email) {
                            return array_filter(['phone' => $phone, 'email' => $email]);
                        }
                    }
                }

                return null;
            } catch (\Exception $e) {
                Log::warning('Web search enrichment failed', ['company' => $companyName, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    private function parseSearchResults(array $searchData): ?array
    {
        $result = [];
        foreach (($searchData['items'] ?? []) as $item) {
            $snippet = $item['snippet'] ?? '';
            $phone = $this->extractPhoneFromText($snippet);
            $email = $this->extractEmailFromText($snippet);

            if ($phone && empty($result['phone'])) {
                $result['phone'] = $phone;
            }
            if ($email && empty($result['email'])) {
                $result['email'] = $email;
            }
        }

        return ! empty($result) ? $result : null;
    }

    private function extractPhoneFromText(string $text): ?string
    {
        // Brazilian phone patterns: (XX) XXXXX-XXXX, (XX) XXXX-XXXX, XX XXXXXXXXX
        if (preg_match('/\(?\d{2}\)?\s*\d{4,5}[-\s]?\d{4}/', $text, $m)) {
            return preg_replace('/\D/', '', $m[0]);
        }

        return null;
    }

    private function extractEmailFromText(string $text): ?string
    {
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $m)) {
            return strtolower($m[0]);
        }

        return null;
    }

    private function extractHtmlField(string $html, string $label): ?string
    {
        $pattern = '/'.preg_quote($label, '/').'[:\s]*<[^>]*>([^<]+)</';
        if (preg_match($pattern, $html, $m)) {
            return trim($m[1]);
        }
        // Try simpler pattern
        $simplePattern = '/'.preg_quote($label, '/').'[\s:]+([^\n<]+)/i';
        if (preg_match($simplePattern, $html, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    private function mergeEnrichment(array $existing, array $new, string $source): array
    {
        $existing['source'][] = $source;
        foreach ($new as $key => $value) {
            if ($key === 'locations') {
                $existing['data']['locations'] = array_merge($existing['data']['locations'] ?? [], $value);
            } elseif (! empty($value) && empty($existing['data'][$key])) {
                $existing['data'][$key] = $value;
            }
        }

        return $existing;
    }
}

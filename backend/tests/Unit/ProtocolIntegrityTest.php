<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protocol Integrity Test.
 *
 * Garante que a camada de governança do agente (CLAUDE.md como fonte canônica
 * autocontida + AGENTS.md + GEMINI.md como entrypoints multi-IA) permaneça
 * consistente entre os arquivos que carregam o Iron Protocol e o modo
 * operacional Harness.
 *
 * Histórico: até abr/2026 a fonte canônica vivia em `.agent/rules/iron-protocol.md`
 * + `.agent/rules/harness-engineering.md` + skills always-on em `.agent/skills/`.
 * Em 2026-04-17 o harness foi consolidado e `CLAUDE.md` virou autocontido (5 leis +
 * Modo Operacional Harness 7 passos + formato 6+1). O diretório `.agent/` foi
 * removido. AGENTS.md e GEMINI.md continuam como entrypoints alternativos para
 * outras IAs (Codex/Antigravity) e ainda carregam o conteúdo Harness inline.
 *
 * Se este teste falhar, significa que alguém editou um dos entrypoints e quebrou
 * a presença obrigatória do formato Harness 6+1 ou da palavra "Harness". Corrigir
 * restaurando o conteúdo — NÃO remover o assert.
 */
class ProtocolIntegrityTest extends TestCase
{
    /**
     * Raiz do projeto (um nível acima do diretório backend/).
     */
    private function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    private function readProjectFile(string $relativePath): string
    {
        $absolute = $this->projectRoot().DIRECTORY_SEPARATOR.$relativePath;

        $this->assertFileExists(
            $absolute,
            "Protocol file missing: {$relativePath}. ".
            'A camada de governança do agente depende deste arquivo. '.
            'Restaurar do git history em vez de remover o teste.'
        );

        $contents = file_get_contents($absolute);
        $this->assertIsString($contents);
        $this->assertNotEmpty(
            trim($contents),
            "Protocol file is empty: {$relativePath}. Restore from git history."
        );

        return $contents;
    }

    // ---------------------------------------------------------------------
    // CLAUDE.md é a fonte canônica autocontida (a partir de 2026-04-17)
    // ---------------------------------------------------------------------

    public function test_claude_md_canonical_file_exists_and_has_harness_format(): void
    {
        $contents = $this->readProjectFile('CLAUDE.md');

        // Marcadores obrigatórios do formato Harness 6+1 + fluxo de 7 passos.
        // Estes são os blocos que TODA resposta de alteração de código deve conter.
        $requiredMarkers = [
            'Harness',
            'Resumo do problema',
            'Arquivos alterados',
            'Motivo técnico',
            'Testes executados',
            'Resultado dos testes',
            'Riscos remanescentes',
            'Como desfazer',
            // Fluxo de 7 passos
            'entender',
            'localizar',
            'propor',
            'implementar',
            'verificar',
            'corrigir',
            'evidenciar',
        ];

        foreach ($requiredMarkers as $marker) {
            $this->assertStringContainsStringIgnoringCase(
                $marker,
                $contents,
                "CLAUDE.md missing required Harness marker: '{$marker}'. ".
                'Este marcador é parte do formato canônico de resposta 6+1 ou do '.
                'fluxo Harness de 7 passos. NÃO remover sem aprovação explícita.'
            );
        }
    }

    // ---------------------------------------------------------------------
    // Referência cruzada: todo entrypoint deve carregar o formato Harness
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function bootEntrypointProvider(): array
    {
        return [
            'CLAUDE.md (Claude Code entrypoint)' => ['CLAUDE.md'],
            'AGENTS.md (Codex/multi-IA entrypoint)' => ['AGENTS.md'],
            'GEMINI.md (Antigravity entrypoint)' => ['GEMINI.md'],
        ];
    }

    #[DataProvider('bootEntrypointProvider')]
    public function test_boot_entrypoint_carries_harness_format(string $relativePath): void
    {
        $contents = $this->readProjectFile($relativePath);

        // Cada entrypoint deve nomear o protocolo explicitamente.
        $this->assertStringContainsStringIgnoringCase(
            'Harness',
            $contents,
            "{$relativePath} não menciona 'Harness'. ".
            'Todo entrypoint do agente deve nomear o protocolo explicitamente '.
            'para que o formato 6+1 e o fluxo 7-passos sejam identificáveis.'
        );

        // Subset essencial dos 6 itens obrigatórios — se algum sumir, o formato quebra.
        $coreFormatMarkers = [
            'Resumo do problema',
            'Arquivos alterados',
            'Testes executados',
            'Resultado dos testes',
            'Riscos remanescentes',
        ];

        foreach ($coreFormatMarkers as $marker) {
            $this->assertStringContainsString(
                $marker,
                $contents,
                "{$relativePath} não contém o item Harness obrigatório: '{$marker}'. ".
                'Os 6 itens do formato de resposta devem aparecer em todos os entrypoints '.
                'para garantir consistência cross-IA (Claude/Codex/Antigravity).'
            );
        }
    }

    // ---------------------------------------------------------------------
    // GEMINI.md — formato Harness inline (validação reforçada)
    // ---------------------------------------------------------------------

    public function test_gemini_response_format_section_is_harness_compliant(): void
    {
        $contents = $this->readProjectFile('GEMINI.md');

        $requiredMarkers = [
            'HARNESS',
            'Resumo do problema',
            'Arquivos alterados',
            'Motivo técnico',
            'Testes executados',
            'Resultado dos testes',
            'Riscos remanescentes',
        ];

        foreach ($requiredMarkers as $marker) {
            $this->assertStringContainsString(
                $marker,
                $contents,
                "GEMINI.md missing Harness marker: '{$marker}'. ".
                'GEMINI.md deve carregar o mesmo formato 6 itens que CLAUDE.md e AGENTS.md '.
                'para preservar consistência cross-IA.'
            );
        }
    }

    // ---------------------------------------------------------------------
    // Garantia contra regressão: formato antigo de 4 itens não volta
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function filesThatMustNotReintroduceLegacyFormatProvider(): array
    {
        return [
            'CLAUDE.md' => ['CLAUDE.md'],
            'AGENTS.md' => ['AGENTS.md'],
            'GEMINI.md' => ['GEMINI.md'],
        ];
    }

    #[DataProvider('filesThatMustNotReintroduceLegacyFormatProvider')]
    public function test_file_does_not_reintroduce_legacy_4_item_response_format(string $relativePath): void
    {
        $contents = $this->readProjectFile($relativePath);

        // O formato legado era: "Antes da resposta final, apresentar sempre estes 4 itens"
        // seguido de uma lista numerada 1-4 começando por "O que mudou". Se isso voltar,
        // o agente perde o formato Harness 6+1.
        $this->assertDoesNotMatchRegularExpression(
            '/apresentar\s+sempre\s+estes\s+4\s+itens/i',
            $contents,
            "{$relativePath} reintroduziu o formato legado de 4 itens. ".
            'O formato canônico é Harness 6+1 (6 obrigatórios + 1 opcional "Como desfazer"). '.
            'Ver seção "Modo Operacional — Harness" em CLAUDE.md.'
        );
    }
}

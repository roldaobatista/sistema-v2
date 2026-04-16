<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Protocol Integrity Test.
 *
 * Garante que a camada de governança do agente (Iron Protocol + Harness Engineering)
 * permaneça consistente entre os múltiplos arquivos que a referenciam.
 *
 * Se este teste falhar, significa que alguém editou um dos arquivos da cadeia de
 * carregamento (CLAUDE.md, AGENTS.md, GEMINI.md, arquivos em .agent/rules/ e em
 * .agent/skills/ always-on) e quebrou a referência cruzada ao arquivo
 * .agent/rules/harness-engineering.md. Corrigir restaurando a referência —
 * NÃO remover o assert.
 *
 * Fonte canônica do protocolo: `.agent/rules/harness-engineering.md` (regra H5).
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
            'The Harness Engineering governance layer depends on this file. '.
            'Restore it from git history instead of removing the test.'
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
    // Existência do arquivo canônico
    // ---------------------------------------------------------------------

    public function test_harness_engineering_canonical_file_exists_and_has_required_sections(): void
    {
        $contents = $this->readProjectFile('.agent/rules/harness-engineering.md');

        // Marcadores obrigatórios da regra H5 (formato de resposta 6+1 itens).
        $requiredMarkers = [
            'HARNESS ENGINEERING',
            'H1',
            'H2',
            'H3',
            'H5',
            'H7',
            'H8',
            'Resumo do problema',
            'Arquivos alterados',
            'Motivo técnico',
            'Testes executados',
            'Resultado dos testes',
            'Riscos remanescentes',
            'Como desfazer',
            'entender',
            'localizar',
            'propor',
            'implementar',
            'verificar',
            'corrigir',
            'evidenciar',
        ];

        foreach ($requiredMarkers as $marker) {
            // Case-insensitive: o bloco H5 usa UPPERCASE (RESUMO DO PROBLEMA, ARQUIVOS ALTERADOS, etc.)
            // e o fluxo de 7 passos também é UPPERCASE (ENTENDER → LOCALIZAR → ...).
            // Queremos apenas garantir presença conceitual, não capitalização exata.
            $this->assertStringContainsStringIgnoringCase(
                $marker,
                $contents,
                "harness-engineering.md missing required marker: '{$marker}'. ".
                'This marker is part of the canonical H5 response format or H1-H8 rules. '.
                'Do NOT remove it without updating all referencing files.'
            );
        }
    }

    // ---------------------------------------------------------------------
    // Referência cruzada: toda fonte de boot deve citar harness-engineering.md
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function bootEntrypointProvider(): array
    {
        return [
            'CLAUDE.md (project entrypoint)' => ['CLAUDE.md'],
            'AGENTS.md (project entrypoint)' => ['AGENTS.md'],
            '.agent/rules/iron-protocol.md (canonical laws)' => ['.agent/rules/iron-protocol.md'],
        ];
    }

    #[DataProvider('bootEntrypointProvider')]
    public function test_boot_entrypoint_references_harness_engineering_rule(string $relativePath): void
    {
        $contents = $this->readProjectFile($relativePath);

        $this->assertStringContainsString(
            'harness-engineering.md',
            $contents,
            "{$relativePath} does NOT reference `.agent/rules/harness-engineering.md`. ".
            'Every boot entrypoint must point at the Harness Engineering rule to guarantee '.
            'the 7-step flow and 6+1 response format are loaded. Restore the reference.'
        );

        $this->assertStringContainsString(
            'Harness',
            $contents,
            "{$relativePath} mentions the file path but not the 'Harness' name. ".
            'The boot sequence and response format sections must name the protocol explicitly.'
        );
    }

    // ---------------------------------------------------------------------
    // GEMINI.md — usa formato Harness mesmo sem citar o path completo
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
                'GEMINI.md must carry the same 6-item response format as CLAUDE.md and AGENTS.md.'
            );
        }
    }

    // ---------------------------------------------------------------------
    // Skills sempre-ligadas do projeto devem aplicar Harness
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function alwaysOnSkillProvider(): array
    {
        return [
            'iron-protocol-bootstrap' => [
                '.agent/skills/iron-protocol-bootstrap/SKILL.md',
                'harness-engineering.md',
            ],
            'end-to-end-completeness' => [
                '.agent/skills/end-to-end-completeness/SKILL.md',
                'Harness',
            ],
        ];
    }

    #[DataProvider('alwaysOnSkillProvider')]
    public function test_always_on_skill_references_harness(string $relativePath, string $marker): void
    {
        $contents = $this->readProjectFile($relativePath);

        $this->assertStringContainsString(
            $marker,
            $contents,
            "Always-on skill {$relativePath} does NOT reference '{$marker}'. ".
            'Skills carregadas no boot DEVEM aplicar o Harness Engineering — sem isso, '.
            'o fluxo 7-passos e o formato 6+1 ficam órfãos. Restaurar a referência.'
        );
    }

    // ---------------------------------------------------------------------
    // Boot sequence completa em iron-protocol.md deve carregar Harness
    // ---------------------------------------------------------------------

    public function test_iron_protocol_boot_sequence_loads_harness_at_step_4d(): void
    {
        $contents = $this->readProjectFile('.agent/rules/iron-protocol.md');

        // Verifica que a linha da boot sequence contém o passo 4d com harness-engineering.
        $this->assertMatchesRegularExpression(
            '/4d\.\s*CARREGAR:\s*`\.agent\/rules\/harness-engineering\.md`/',
            $contents,
            'iron-protocol.md boot sequence does NOT include step 4d loading harness-engineering.md. '.
            'This step is required so the operational mode is loaded alongside the laws.'
        );
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
            '.agent/rules/iron-protocol.md' => ['.agent/rules/iron-protocol.md'],
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
            "{$relativePath} reintroduced the legacy 4-item response format. ".
            'The canonical format is Harness H5 (6 obrigatórios + 1 opcional). '.
            'See .agent/rules/harness-engineering.md.'
        );
    }
}

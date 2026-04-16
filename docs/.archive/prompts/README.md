# Central de Prompts (AIDD)

Este diretório contém a programação cognitiva das Inteligências Artificiais que vão operar neste ecossistema. Você não digita coisas aleatórias para a IA; você copia e cola blocos de prompts comprovados daqui.

## Inventário:

| Documento | Propósito | Quem usa? |
| --------- | --------- | --------- |
| [`MASTER-BUILDER.md`](MASTER-BUILDER.md) | **(Obrigatório ler)** Passo a passo de prompts master para construir um módulo ou o ERP do zero, forçando a IA a ler a Arquitetura e os Bounded Contexts. | Desenvolvedor Humano ou Agente Orquestrador |
| [`CLAUDE-TEMPLATE.md`](CLAUDE-TEMPLATE.md) | Checklist e Definição de Pronto (DoD) de 7 passos para garantir que nenhuma IA entregue código sem checar segurança, design e testes. | LLMs integradas na IDE (Cursor, Copilot) |
| [`SYSTEM-PROMPTS.md`](SYSTEM-PROMPTS.md) | Definição da "Persona" dos agentes. Garante que o Agente de Backend não tente desenhar tela e foque em rotas e banco. | Setup de ferramentas genéricas (ChatGPT, Gemini) |

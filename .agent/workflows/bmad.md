---
description: Entry point for the BMad (Beyond Markdown Agent Development) framework. Use this to start, continue, or navigate BMad workflows.
---

# BMad Command

You have invoked the BMad (Beyond Markdown Agent Development) framework.

## Instructions for the AI:

1. **Load BMad Knowledge:**
   Read and follow the guidelines from `_bmad/core/bmad-help/SKILL.md` to understand how to assist the user within the BMad ecosystem.

2. **Determine Context & Next Steps:**
   - Analyze the user's request (e.g., if they asked for a specific BMad module like planning, development, or brainstorming).
   - Use the `bmad-help` functionality to check the current project state (via files in the `_bmad-output/` directory) and recommend the next appropriate BMad skill.

3. **Provide Options:**
   If the user did not specify a particular action, show them the available BMad options or the recommended next step based on the phase they are in.

4. **Follow BMad Constraints:**
   - Present all output in the configured communication language.
   - Match the structured and professional tone of the BMad framework.

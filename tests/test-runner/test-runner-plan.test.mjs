import test from 'node:test';
import assert from 'node:assert/strict';

import { resolveRunnerFlags } from '../../scripts/test-runner-plan.mjs';

test('analysis isolado executa analise backend e frontend', () => {
  const flags = resolveRunnerFlags(['analysis']);

  assert.equal(flags.runBackendTests, false);
  assert.equal(flags.runFrontendTests, false);
  assert.equal(flags.runAnalysis, true);
  assert.equal(flags.runBackendAnalysis, true);
  assert.equal(flags.runFrontendAnalysis, true);
  assert.equal(flags.shouldResolvePhpRuntime, true);
});

test('sem argumentos executa backend, frontend e analise', () => {
  const flags = resolveRunnerFlags([]);

  assert.equal(flags.runBackendTests, true);
  assert.equal(flags.runFrontendTests, true);
  assert.equal(flags.runAnalysis, true);
  assert.equal(flags.runBackendAnalysis, true);
  assert.equal(flags.runFrontendAnalysis, true);
  assert.equal(flags.shouldResolvePhpRuntime, true);
});

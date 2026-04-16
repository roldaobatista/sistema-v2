import test from 'node:test';
import assert from 'node:assert/strict';

import { buildBackendTestInvocation } from '../../scripts/test-runner-command.mjs';

test('backend default usa pest paralelo com 16 processos e sem coverage', () => {
  const invocation = buildBackendTestInvocation();

  assert.equal(invocation.commandType, 'php');
  assert.deepEqual(invocation.args, [
    'vendor/bin/pest',
    '--parallel',
    '--processes=16',
    '--no-coverage',
  ]);
});

test('backend suite especifica preserva pest paralelo e injeta testsuite', () => {
  const invocation = buildBackendTestInvocation({ suite: 'Unit' });

  assert.deepEqual(invocation.args, [
    'vendor/bin/pest',
    '--testsuite=Unit',
    '--parallel',
    '--processes=16',
    '--no-coverage',
  ]);
});

test('backend coverage substitui no-coverage por coverage', () => {
  const invocation = buildBackendTestInvocation({ coverage: true });

  assert.deepEqual(invocation.args, [
    'vendor/bin/pest',
    '--parallel',
    '--processes=16',
    '--coverage',
  ]);
});

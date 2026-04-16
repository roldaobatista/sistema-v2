import test from 'node:test';
import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';

import {
  attachBrokenPipeGuard,
  createStreamState,
  safeWrite,
} from '../../scripts/terminal-streams.mjs';

class FakeStream extends EventEmitter {
  constructor() {
    super();
    this.chunks = [];
    this.shouldThrow = false;
  }

  write(chunk) {
    if (this.shouldThrow) {
      const error = new Error('broken pipe');
      error.code = 'EPIPE';
      throw error;
    }

    this.chunks.push(chunk);
  }
}

test('safeWrite escreve normalmente quando o stream esta disponivel', () => {
  const stream = new FakeStream();
  const state = createStreamState();

  const wrote = safeWrite(stream, state, 'ok');

  assert.equal(wrote, true);
  assert.deepEqual(stream.chunks, ['ok']);
  assert.equal(state.available, true);
});

test('safeWrite desativa o stream quando ocorre EPIPE sincrono', () => {
  const stream = new FakeStream();
  const state = createStreamState();
  stream.shouldThrow = true;

  const wrote = safeWrite(stream, state, 'boom');

  assert.equal(wrote, false);
  assert.equal(state.available, false);
});

test('attachBrokenPipeGuard marca o stream como indisponivel apos erro EPIPE emitido', () => {
  const stream = new FakeStream();
  const state = attachBrokenPipeGuard(stream);

  const error = new Error('broken pipe');
  error.code = 'EPIPE';

  stream.emit('error', error);

  assert.equal(state.available, false);
});

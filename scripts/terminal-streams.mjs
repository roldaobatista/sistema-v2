export function createStreamState() {
  return { available: true };
}

export function attachBrokenPipeGuard(stream, state = createStreamState()) {
  if (typeof stream?.on === 'function') {
    stream.on('error', (error) => {
      if (error?.code === 'EPIPE') {
        state.available = false;
        return;
      }

      throw error;
    });
  }

  return state;
}

export function safeWrite(stream, state, chunk) {
  if (!state.available) {
    return false;
  }

  try {
    stream.write(chunk);
    return true;
  } catch (error) {
    if (error?.code === 'EPIPE') {
      state.available = false;
      return false;
    }

    throw error;
  }
}

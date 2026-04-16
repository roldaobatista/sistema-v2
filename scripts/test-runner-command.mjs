export function buildBackendTestInvocation({ suite = null, coverage = false } = {}) {
  const args = ['vendor/bin/pest'];

  if (suite) {
    args.push(`--testsuite=${suite}`);
  }

  args.push('--parallel', '--processes=16');

  if (coverage) {
    args.push('--coverage');
  } else {
    args.push('--no-coverage');
  }

  return {
    commandType: 'php',
    args,
  };
}

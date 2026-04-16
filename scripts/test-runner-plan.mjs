export function resolveRunnerFlags(args) {
  const runBackendTests = args.length === 0 || args.includes('backend') || args.includes('all');
  const runFrontendTests = args.length === 0 || args.includes('frontend') || args.includes('all');
  const runAnalysis = args.length === 0 || args.includes('analysis') || args.includes('all');
  const onlyAnalysis = args.includes('analysis')
    && !args.includes('backend')
    && !args.includes('frontend')
    && !args.includes('all');

  return {
    runBackendTests,
    runFrontendTests,
    runAnalysis,
    runBackendAnalysis: runAnalysis && (runBackendTests || onlyAnalysis),
    runFrontendAnalysis: runAnalysis && (runFrontendTests || onlyAnalysis),
    onlyAnalysis,
    shouldResolvePhpRuntime: runBackendTests || (runAnalysis && !runFrontendTests),
  };
}

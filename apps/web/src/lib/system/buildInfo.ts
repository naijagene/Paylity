const DEFAULTS = {
  APP_NAME: "PAYLITY NG",
  APP_VERSION: "1.0.0-rc1",
  BUILD_NUMBER: "2026.07.03-rc1",
  BUILD_DATE: "2026-07-03",
  ENVIRONMENT: "Sandbox",
  GIT_COMMIT: "",
} as const;

export const APP_NAME =
  process.env.NEXT_PUBLIC_APP_NAME ?? DEFAULTS.APP_NAME;

export const APP_VERSION =
  process.env.NEXT_PUBLIC_APP_VERSION ?? DEFAULTS.APP_VERSION;

export const BUILD_NUMBER =
  process.env.NEXT_PUBLIC_BUILD_NUMBER ?? DEFAULTS.BUILD_NUMBER;

export const BUILD_DATE =
  process.env.NEXT_PUBLIC_BUILD_DATE ?? DEFAULTS.BUILD_DATE;

export const ENVIRONMENT =
  process.env.NEXT_PUBLIC_ENVIRONMENT ?? DEFAULTS.ENVIRONMENT;

export const GIT_COMMIT =
  process.env.NEXT_PUBLIC_GIT_COMMIT ?? DEFAULTS.GIT_COMMIT;

export const IS_SANDBOX = ENVIRONMENT.toLowerCase() === "sandbox";

export type BuildInfo = {
  appName: string;
  appVersion: string;
  buildNumber: string;
  buildDate: string;
  environment: string;
  gitCommit: string;
  isSandbox: boolean;
};

export function getBuildInfo(): BuildInfo {
  return {
    appName: APP_NAME,
    appVersion: APP_VERSION,
    buildNumber: BUILD_NUMBER,
    buildDate: BUILD_DATE,
    environment: ENVIRONMENT,
    gitCommit: GIT_COMMIT,
    isSandbox: IS_SANDBOX,
  };
}

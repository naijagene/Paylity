import { getBuildInfo } from "@/lib/system/buildInfo";

type SystemIdentityProps = {
  className?: string;
  compact?: boolean;
};

export function SystemIdentity({
  className = "",
  compact = false,
}: SystemIdentityProps) {
  const build = getBuildInfo();

  return (
    <div
      className={`text-center text-[11px] leading-relaxed text-foreground/45 ${className}`}
      aria-label="System build information"
    >
      <p className="font-semibold tracking-wide text-foreground/55">
        {build.appName}
      </p>
      <p>Version {build.appVersion}</p>
      <p>Build {build.buildNumber}</p>
      {!compact ? (
        <>
          <p>
            Environment{" "}
            <span
              className={
                build.isSandbox
                  ? "font-semibold text-amber-700"
                  : "font-semibold text-success"
              }
            >
              {build.environment}
            </span>
          </p>
          {build.gitCommit ? (
            <p className="font-mono text-[10px]">
              Commit {build.gitCommit.slice(0, 7)}
            </p>
          ) : null}
        </>
      ) : null}
    </div>
  );
}

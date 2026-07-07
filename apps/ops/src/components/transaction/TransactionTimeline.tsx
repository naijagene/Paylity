import type { TimelinePhase } from "@/lib/transaction/display";

type TimelineStep = {
  label: string;
  state: "complete" | "active" | "upcoming" | "failed";
};

type TransactionTimelineProps = {
  phase: TimelinePhase;
  className?: string;
  animated?: boolean;
};

function buildSteps(phase: TimelinePhase): TimelineStep[] {
  if (phase === "payment_failed") {
    return [
      { label: "Payment Received", state: "failed" },
      { label: "Payment Failed", state: "failed" },
    ];
  }

  if (phase === "delivery_failed") {
    return [
      { label: "Payment Received", state: "complete" },
      { label: "Delivery Failed", state: "failed" },
    ];
  }

  if (phase === "delivered") {
    return [
      { label: "Payment Received", state: "complete" },
      { label: "Processing Complete", state: "complete" },
      { label: "Delivered", state: "complete" },
    ];
  }

  if (phase === "processing") {
    return [
      { label: "Payment Received", state: "complete" },
      { label: "Processing Order", state: "active" },
      { label: "Delivered", state: "upcoming" },
    ];
  }

  return [
    { label: "Payment Received", state: "complete" },
    { label: "Processing Order", state: "active" },
    { label: "Delivered", state: "upcoming" },
  ];
}

function StepIcon({ state }: { state: TimelineStep["state"] }) {
  if (state === "complete") {
    return (
      <span
        className="flex h-8 w-8 animate-success-pop items-center justify-center rounded-full bg-success text-sm font-bold text-white motion-reduce:animate-none"
        aria-hidden="true"
      >
        ✓
      </span>
    );
  }

  if (state === "active") {
    return (
      <span
        className="flex h-8 w-8 animate-pulse-soft items-center justify-center rounded-full bg-primary text-sm font-bold text-dark"
        aria-hidden="true"
      >
        ⏳
      </span>
    );
  }

  if (state === "failed") {
    return (
      <span
        className="flex h-8 w-8 items-center justify-center rounded-full bg-error text-sm font-bold text-white"
        aria-hidden="true"
      >
        ✕
      </span>
    );
  }

  return (
    <span
      className="flex h-8 w-8 items-center justify-center rounded-full border-2 border-dark/10 bg-white text-xs text-foreground/30"
      aria-hidden="true"
    >
      ○
    </span>
  );
}

export function TransactionTimeline({
  phase,
  className = "",
  animated = false,
}: TransactionTimelineProps) {
  const steps = buildSteps(phase);

  return (
    <ol
      className={`space-y-0 ${animated ? "animate-timeline-in" : ""} ${className}`}
      aria-label="Order progress timeline"
    >
      {steps.map((step, index) => (
        <li
          key={step.label}
          className={`flex gap-3 ${animated ? "animate-timeline-step" : ""}`}
          style={animated ? { animationDelay: `${index * 120}ms` } : undefined}
        >
          <div className="flex flex-col items-center">
            <StepIcon state={step.state} />
            {index < steps.length - 1 ? (
              <div
                className={`my-1 min-h-7 w-0.5 flex-1 ${
                  step.state === "complete" ? "bg-success/30" : "bg-dark/10"
                }`}
                aria-hidden="true"
              />
            ) : null}
          </div>
          <div className="pb-5 pt-1">
            <p
              className={`text-sm font-semibold ${
                step.state === "upcoming"
                  ? "text-foreground/40"
                  : step.state === "failed"
                    ? "text-error"
                    : step.state === "active"
                      ? "text-foreground"
                      : "text-success"
              }`}
            >
              {step.label}
            </p>
          </div>
        </li>
      ))}
    </ol>
  );
}

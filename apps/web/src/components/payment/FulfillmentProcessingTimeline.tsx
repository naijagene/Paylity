type TimelineStep = {
  label: string;
  state: "complete" | "active" | "upcoming";
};

const PROCESSING_STEPS: TimelineStep[] = [
  { label: "Payment Received", state: "complete" },
  { label: "Your request is being processed", state: "active" },
  { label: "Delivering to Recipient", state: "upcoming" },
];

function StepIcon({ state }: { state: TimelineStep["state"] }) {
  if (state === "complete") {
    return (
      <span
        className="flex h-7 w-7 animate-success-pop items-center justify-center rounded-full bg-success text-sm font-bold text-white motion-reduce:animate-none"
        aria-hidden="true"
      >
        ✓
      </span>
    );
  }

  if (state === "active") {
    return (
      <span
        className="flex h-7 w-7 animate-pulse-soft items-center justify-center rounded-full bg-primary text-sm font-bold text-dark"
        aria-hidden="true"
      >
        ⏳
      </span>
    );
  }

  return (
    <span
      className="flex h-7 w-7 items-center justify-center rounded-full border-2 border-dark/10 bg-white text-xs text-foreground/30"
      aria-hidden="true"
    >
      ⭕
    </span>
  );
}

export function FulfillmentProcessingTimeline() {
  return (
    <ol
      className="space-y-0"
      aria-label="Transaction processing timeline"
    >
      {PROCESSING_STEPS.map((step, index) => (
        <li key={step.label} className="flex gap-3">
          <div className="flex flex-col items-center">
            <StepIcon state={step.state} />
            {index < PROCESSING_STEPS.length - 1 ? (
              <div
                className={`my-1 min-h-6 w-0.5 flex-1 ${
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

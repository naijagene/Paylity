const INDICATORS = [
  { icon: "🔒", label: "Secure Payment" },
  { icon: "⚡", label: "Instant Delivery" },
  { icon: "🧾", label: "Digital Receipt" },
] as const;

export function TrustIndicators({ className = "" }: { className?: string }) {
  return (
    <div
      className={`grid grid-cols-1 gap-2 sm:grid-cols-3 ${className}`}
      aria-label="Checkout trust indicators"
    >
      {INDICATORS.map((item) => (
        <div
          key={item.label}
          className="flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-border-green bg-success-light/40 px-3 py-2.5 text-xs font-semibold text-dark sm:text-sm"
        >
          <span aria-hidden="true">{item.icon}</span>
          <span>{item.label}</span>
        </div>
      ))}
    </div>
  );
}

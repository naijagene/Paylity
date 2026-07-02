import { type ReactNode } from "react";

type ValidationMessageProps = {
  message?: string;
};

export function ValidationMessage({ message }: ValidationMessageProps) {
  if (!message) return null;

  return (
    <p className="mt-1.5 text-sm text-error" role="alert">
      {message}
    </p>
  );
}

type FormFieldProps = {
  label: string;
  htmlFor: string;
  error?: string;
  children: ReactNode;
  hint?: string;
};

export function FormField({ label, htmlFor, error, children, hint }: FormFieldProps) {
  return (
    <div className="mb-4">
      <label
        htmlFor={htmlFor}
        className="mb-2 block text-sm font-semibold text-foreground"
      >
        {label}
      </label>
      {children}
      {hint && !error ? (
        <p className="mt-1.5 text-xs text-foreground/50">{hint}</p>
      ) : null}
      <ValidationMessage message={error} />
    </div>
  );
}

const inputClassName =
  "w-full rounded-2xl border border-dark/10 bg-white px-4 py-3.5 text-base text-foreground outline-none transition-colors placeholder:text-foreground/30 focus:border-primary focus:ring-2 focus:ring-primary/20";

export function TextInput({
  id,
  value,
  onChange,
  onBlur,
  type = "text",
  inputMode,
  placeholder,
  maxLength,
}: {
  id: string;
  value: string;
  onChange: (value: string) => void;
  onBlur?: () => void;
  type?: string;
  inputMode?: "text" | "numeric" | "tel" | "email";
  placeholder?: string;
  maxLength?: number;
}) {
  return (
    <input
      id={id}
      type={type}
      inputMode={inputMode}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      onBlur={onBlur}
      placeholder={placeholder}
      maxLength={maxLength}
      className={inputClassName}
    />
  );
}

export { inputClassName };

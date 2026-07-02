import { type ReactNode } from "react";

type PageContainerProps = {
  children: ReactNode;
  className?: string;
};

export function PageContainer({
  children,
  className = "",
}: PageContainerProps) {
  return (
    <div
      className={`mx-auto w-full max-w-lg px-4 py-6 sm:max-w-2xl sm:px-6 sm:py-8 lg:max-w-4xl lg:px-8 ${className}`}
    >
      {children}
    </div>
  );
}

import { type ReactNode } from "react";

type PageContainerProps = {
  children: ReactNode;
  className?: string;
  narrow?: boolean;
};

export function PageContainer({
  children,
  className = "",
  narrow = true,
}: PageContainerProps) {
  return (
    <div
      className={`mx-auto w-full px-4 py-6 sm:px-6 sm:py-8 ${
        narrow ? "max-w-[52rem]" : "max-w-6xl"
      } ${className}`}
    >
      {children}
    </div>
  );
}

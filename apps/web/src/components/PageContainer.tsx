import { type ReactNode } from "react";

export const CONTENT_MAX_WIDTH_CLASS = "max-w-[61.25rem]";

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
        narrow ? CONTENT_MAX_WIDTH_CLASS : "max-w-6xl"
      } ${className}`}
    >
      {children}
    </div>
  );
}

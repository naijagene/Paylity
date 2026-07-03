import Image from "next/image";
import Link from "next/link";

export const PAYLITY_LOGO_PATH = "/brand/paylity-logo.png";
export const PAYLITY_LOGO_WIDTH = 1024;
export const PAYLITY_LOGO_HEIGHT = 512;

type PaylityLogoSize = "sm" | "md" | "lg";

type PaylityLogoProps = {
  size?: PaylityLogoSize;
  /** Kept for API compatibility; official PNG is always the full lockup. */
  showText?: boolean;
  darkMode?: boolean;
  href?: string;
  className?: string;
  priority?: boolean;
};

const sizeStyles: Record<
  PaylityLogoSize,
  {
    heightClass: string;
  }
> = {
  sm: { heightClass: "h-9 w-auto sm:h-10" },
  md: { heightClass: "h-10 w-auto sm:h-12" },
  lg: { heightClass: "h-11 w-auto sm:h-[3rem]" },
};

export function PaylityLogo({
  size = "md",
  href = "/",
  className = "",
  darkMode = false,
  priority = false,
}: PaylityLogoProps) {
  const styles = sizeStyles[size];

  const image = (
    <Image
      src={PAYLITY_LOGO_PATH}
      alt="Paylity NG"
      width={PAYLITY_LOGO_WIDTH}
      height={PAYLITY_LOGO_HEIGHT}
      priority={priority}
      className={`${styles.heightClass} ${darkMode ? "brightness-0 invert" : ""} ${className}`}
    />
  );

  if (href) {
    return (
      <Link
        href={href}
        className="inline-flex focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-success focus-visible:ring-offset-2 rounded-xl"
      >
        {image}
      </Link>
    );
  }

  return image;
}

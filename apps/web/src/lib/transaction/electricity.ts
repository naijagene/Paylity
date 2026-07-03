export type ElectricityTokenDetails = {
  token?: string;
  purchased_code?: string;
  units?: string | number;
  tariff?: string | number;
  resetToken?: string;
  configureToken?: string;
  tokenAmount?: string | number;
  costOfUnit?: string | number;
  tariffBaseRate?: string | number;
};

type ElectricityFieldValue = string | number;

const FIELD_PATHS: Record<keyof ElectricityTokenDetails, string[]> = {
  token: ["token", "content.transactions.token", "content.token"],
  purchased_code: [
    "purchased_code",
    "content.transactions.purchased_code",
    "content.purchased_code",
  ],
  units: ["units", "content.transactions.units", "content.units"],
  tariff: ["tariff", "content.transactions.tariff", "content.tariff"],
  resetToken: [
    "resetToken",
    "content.transactions.resetToken",
    "content.resetToken",
  ],
  configureToken: [
    "configureToken",
    "content.transactions.configureToken",
    "content.configureToken",
  ],
  tokenAmount: [
    "tokenAmount",
    "content.transactions.tokenAmount",
    "content.tokenAmount",
  ],
  costOfUnit: [
    "costOfUnit",
    "content.transactions.costOfUnit",
    "content.costOfUnit",
  ],
  tariffBaseRate: [
    "tariffBaseRate",
    "content.transactions.tariffBaseRate",
    "content.tariffBaseRate",
  ],
};

function readPath(source: Record<string, unknown>, path: string): unknown {
  return path.split(".").reduce<unknown>((current, segment) => {
    if (!current || typeof current !== "object") {
      return undefined;
    }

    return (current as Record<string, unknown>)[segment];
  }, source);
}

function hasValue(value: unknown): value is string | number {
  return value !== null && value !== undefined && value !== "";
}

export function extractElectricityTokenDetails(
  source?: Record<string, unknown> | ElectricityTokenDetails | null,
): ElectricityTokenDetails | null {
  if (!source) {
    return null;
  }

  const details: ElectricityTokenDetails = {};

  (Object.keys(FIELD_PATHS) as Array<keyof ElectricityTokenDetails>).forEach(
    (field) => {
      const directValue = source[field as keyof typeof source];

      if (hasValue(directValue)) {
        (details as Record<string, ElectricityFieldValue>)[field] =
          directValue as ElectricityFieldValue;
        return;
      }

      for (const path of FIELD_PATHS[field]) {
        const nestedValue = readPath(source as Record<string, unknown>, path);

        if (hasValue(nestedValue)) {
          (details as Record<string, ElectricityFieldValue>)[field] =
            nestedValue as ElectricityFieldValue;
          break;
        }
      }
    },
  );

  return Object.keys(details).length > 0 ? details : null;
}

export function getPrimaryElectricityToken(
  details: ElectricityTokenDetails | null,
): string | null {
  if (!details) {
    return null;
  }

  if (details.token) {
    return String(details.token);
  }

  if (details.purchased_code) {
    return String(details.purchased_code);
  }

  return null;
}

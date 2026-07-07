export type ProductType = "airtime" | "data" | "electricity";

export type MeterType = "prepaid" | "postpaid";

export type CheckoutFields = {
  customerPhone: string;
  customerEmail: string;
  network: string;
  recipientPhone: string;
  dataPlan: string;
  disco: string;
  meterType: MeterType;
  meterNumber: string;
  customerName: string;
  useMyNumber: boolean;
  meterVerified: boolean;
};

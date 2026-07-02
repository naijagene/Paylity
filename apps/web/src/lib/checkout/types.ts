export type ProductType = "airtime" | "data" | "electricity";

export type CheckoutStep = "form" | "review" | "processing";

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

export type CheckoutState = {
  product: ProductType;
  step: CheckoutStep;
  fields: CheckoutFields;
  amount: number;
  fee: number;
  total: number;
  customAmount: string;
  transactionRef: string | null;
};

export type FieldErrors = Partial<Record<keyof CheckoutFields | "amount", string>>;

export type DataPlan = {
  id: string;
  network: string;
  name: string;
  size: string;
  validity: string;
  price: number;
};

export type AmountMode = "quick-picks" | "plan-picker";

export type ProductSchema = {
  id: ProductType;
  label: string;
  amountMode: AmountMode;
  guestMaxAmount: number;
};

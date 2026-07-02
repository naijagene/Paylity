import { redirect } from "next/navigation";

export default function ElectricityPage() {
  redirect("/checkout?product=electricity");
}

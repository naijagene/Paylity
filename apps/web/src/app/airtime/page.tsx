import { redirect } from "next/navigation";

export default function AirtimePage() {
  redirect("/checkout?product=airtime");
}

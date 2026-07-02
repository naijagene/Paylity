import { redirect } from "next/navigation";

export default function DataPage() {
  redirect("/checkout?product=data");
}

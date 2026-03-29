"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";
import { vendorToken } from "@/lib/api";

export default function Home() {
  const router = useRouter();

  useEffect(() => {
    if (vendorToken.get()) {
      router.replace("/dashboard");
    } else {
      router.replace("/login");
    }
  }, [router]);

  return null;
}

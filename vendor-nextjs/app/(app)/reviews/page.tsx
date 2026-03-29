"use client";

import { useVendorAuth } from "@/lib/vendor-auth";
import { ReviewsManagement } from "../../vendor/ReviewsManagement";

export default function VendorReviewsPage() {
  const { user } = useVendorAuth();

  if (!user) return null;

  return <ReviewsManagement vendorId={String(user.id)} />;
}

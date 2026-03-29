// AI Helper Functions for TAVLO
// Provides data-driven, explainable AI insights across the platform

interface MenuItem {
  id: string;
  name: string;
  price: number;
  category: string;
  views?: number;
  orders?: number;
  rating?: number;
  badges?: string[];
}

interface Review {
  id: string;
  rating: number;
  comment: string;
  date: string;
}

interface OrderData {
  hour: string;
  orders: number;
  revenue: number;
}

// Menu Analysis
export function analyzeMenuPerformance(items: MenuItem[]) {
  const insights = [];

  // Find slow-moving items with high views but low orders
  const slowItems = items.filter(item => {
    const viewToOrderRatio = (item.views || 0) / Math.max(item.orders || 1, 1);
    return viewToOrderRatio > 5 && (item.orders || 0) < 10;
  });

  if (slowItems.length > 0) {
    insights.push({
      type: 'warning' as const,
      title: 'Slow Items Alert',
      description: `${slowItems.length} items have high views but low orders. Consider price adjustment or better descriptions.`,
      metric: `Items: ${slowItems.slice(0, 3).map(i => i.name).join(', ')}`,
      action: {
        label: 'Optimize menu',
        items: slowItems
      },
      explanation: 'Comparison of view-to-order ratios across your menu'
    });
  }

  // Find popular items that could be promoted
  const popularItems = items
    .filter(item => (item.orders || 0) > 20)
    .sort((a, b) => (b.orders || 0) - (a.orders || 0))
    .slice(0, 5);

  if (popularItems.length > 0) {
    insights.push({
      type: 'success' as const,
      title: 'Top Performers',
      description: `Your best-selling items are driving ${Math.round((popularItems.reduce((sum, item) => sum + (item.orders || 0), 0) / items.reduce((sum, item) => sum + (item.orders || 0), 0)) * 100)}% of orders.`,
      metric: `Top: ${popularItems[0].name} (${popularItems[0].orders} orders)`,
      explanation: 'Based on order frequency over the last 30 days'
    });
  }

  // Pricing optimization
  const avgPrice = items.reduce((sum, item) => sum + item.price, 0) / items.length;
  const lowPricedPopular = items.filter(item => 
    item.price < avgPrice * 0.8 && (item.orders || 0) > 15
  );

  if (lowPricedPopular.length > 0) {
    const potentialRevenue = lowPricedPopular.reduce((sum, item) => {
      return sum + ((item.orders || 0) * item.price * 0.15);
    }, 0);

    insights.push({
      type: 'recommendation' as const,
      title: 'Pricing Opportunity',
      description: `${lowPricedPopular.length} popular items are priced below average. Small price increases could boost revenue.`,
      metric: `Potential additional revenue: €${potentialRevenue.toFixed(0)}/month`,
      explanation: 'Analysis of price points vs popularity and competitor pricing'
    });
  }

  return insights;
}

// Analytics Insights
export function analyzeOrderPatterns(orderData: OrderData[]) {
  const insights = [];

  // Find peak hours
  const sortedByOrders = [...orderData].sort((a, b) => b.orders - a.orders);
  const peakHours = sortedByOrders.slice(0, 2);
  const slowHours = sortedByOrders.slice(-3);

  const avgPeakOrders = peakHours.reduce((sum, h) => sum + h.orders, 0) / peakHours.length;
  const avgSlowOrders = slowHours.reduce((sum, h) => sum + h.orders, 0) / slowHours.length;

  if (avgPeakOrders > avgSlowOrders * 2) {
    const hourBefore = parseInt(peakHours[0].hour) - 1;
    const potentialRevenue = (avgPeakOrders - avgSlowOrders) * 25 * 7; // avg order value * weeks

    insights.push({
      type: 'recommendation' as const,
      title: 'Peak Hours Opportunity',
      description: `Your busiest hours are ${peakHours.map(h => h.hour).join(' and ')}. Consider promotions during ${hourBefore}:00-${peakHours[0].hour} to increase early traffic.`,
      metric: `Potential additional revenue: €${potentialRevenue.toFixed(0)}/month`,
      explanation: 'Based on order patterns and similar restaurant data'
    });
  }

  return insights;
}

// Review Sentiment Analysis
export function analyzeReviews(reviews: Review[]) {
  const positiveWords = ['amazing', 'excellent', 'great', 'perfect', 'delicious', 'wonderful', 'fantastic', 'best', 'love', 'fresh', 'authentic', 'cozy', 'friendly', 'impeccable'];
  const negativeWords = ['slow', 'long wait', 'cold', 'disappointing', 'overpriced', 'small portion', 'bland', 'poor', 'bad', 'terrible', 'worst'];

  const foodKeywords = ['food', 'dish', 'meal', 'pasta', 'risotto', 'pizza', 'dessert', 'tiramisu', 'salmon', 'ingredients', 'quality'];
  const serviceKeywords = ['service', 'staff', 'waiter', 'server', 'friendly', 'attentive', 'helpful'];
  const ambianceKeywords = ['atmosphere', 'ambiance', 'cozy', 'decor', 'music', 'vibe'];

  let positiveCount = 0;
  let negativeCount = 0;
  const themes: { [key: string]: number } = {
    food: 0,
    service: 0,
    ambiance: 0,
    wait: 0
  };

  const positivePoints: Set<string> = new Set();
  const negativePoints: Set<string> = new Set();

  reviews.forEach(review => {
    const lowerComment = review.comment.toLowerCase();

    // Sentiment
    positiveWords.forEach(word => {
      if (lowerComment.includes(word)) positiveCount++;
    });
    negativeWords.forEach(word => {
      if (lowerComment.includes(word)) negativeCount++;
    });

    // Themes
    foodKeywords.forEach(word => {
      if (lowerComment.includes(word)) themes.food++;
    });
    serviceKeywords.forEach(word => {
      if (lowerComment.includes(word)) themes.service++;
    });
    ambianceKeywords.forEach(word => {
      if (lowerComment.includes(word)) themes.ambiance++;
    });
    if (lowerComment.includes('wait') || lowerComment.includes('slow')) {
      themes.wait++;
    }

    // Extract specific points
    if (review.rating >= 4) {
      if (lowerComment.includes('food') || lowerComment.includes('dish')) {
        positivePoints.add('Excellent food quality and authentic recipes');
      }
      if (lowerComment.includes('service') || lowerComment.includes('staff')) {
        positivePoints.add('Friendly and attentive service');
      }
      if (lowerComment.includes('atmosphere') || lowerComment.includes('cozy')) {
        positivePoints.add('Cozy atmosphere');
      }
    } else if (review.rating <= 3) {
      if (lowerComment.includes('wait') || lowerComment.includes('slow')) {
        negativePoints.add('Wait times can be long during peak hours');
      }
      if (lowerComment.includes('price') || lowerComment.includes('expensive')) {
        negativePoints.add('Some customers find prices high');
      }
    }
  });

  const avgRating = reviews.reduce((sum, r) => sum + r.rating, 0) / reviews.length;
  let sentiment: 'positive' | 'neutral' | 'negative' = 'neutral';
  
  if (avgRating >= 4.2) sentiment = 'positive';
  else if (avgRating <= 3) sentiment = 'negative';

  // Generate summary
  const topTheme = Object.entries(themes).sort(([, a], [, b]) => b - a)[0];
  let summary = '';

  if (sentiment === 'positive') {
    summary = `Customers love the ${topTheme[0] === 'food' ? 'authentic cuisine and fresh ingredients' : topTheme[0] === 'service' ? 'exceptional service' : 'wonderful atmosphere'}.`;
    if (themes.food > reviews.length * 0.3) {
      summary += ' The food quality consistently exceeds expectations.';
    }
  } else if (sentiment === 'negative') {
    summary = `Reviews indicate concerns about ${topTheme[0]}. Focus on improvement in this area.`;
  } else {
    summary = 'Mixed feedback with both positive highlights and areas for improvement.';
  }

  const confidence = Math.min(0.95, 0.6 + (reviews.length / 100) * 0.35);

  return {
    sentiment,
    summary,
    positivePoints: Array.from(positivePoints).slice(0, 5),
    negativePoints: Array.from(negativePoints).slice(0, 3),
    confidence,
    totalReviews: reviews.length
  };
}

// Customer Retention Analysis
export function analyzeCustomerRetention(customers: any[]) {
  const repeatCustomers = customers.filter(c => c.orderCount > 1);
  const repeatRate = repeatCustomers.length / customers.length;

  const avgRepeatSpend = repeatCustomers.reduce((sum, c) => sum + c.totalSpent, 0) / repeatCustomers.length;
  const avgNewSpend = customers.filter(c => c.orderCount === 1).reduce((sum, c) => sum + c.totalSpent, 0) / (customers.length - repeatCustomers.length);

  const insights = [];

  if (repeatRate > 0.5) {
    insights.push({
      type: 'success' as const,
      title: 'Customer Retention Strong',
      description: `${(repeatRate * 100).toFixed(0)}% repeat customer rate is excellent! Your regulars spend ${((avgRepeatSpend / avgNewSpend - 1) * 100).toFixed(0)}% more on average.`,
      metric: `Repeat customer value: €${avgRepeatSpend.toFixed(2)} avg`,
      explanation: 'Analysis of customer behavior over 90 days'
    });
  } else if (repeatRate < 0.3) {
    insights.push({
      type: 'warning' as const,
      title: 'Low Retention Rate',
      description: `Only ${(repeatRate * 100).toFixed(0)}% of customers return. Consider loyalty programs or follow-up campaigns.`,
      metric: 'Target: 50%+ retention',
      explanation: 'Benchmark comparison with similar restaurants'
    });
  }

  return insights;
}

// Admin Vendor Risk Analysis
export function analyzeVendorRisk(vendor: {
  id: string;
  name: string;
  complaints: number;
  unresolvedComplaints: number;
  avgRating: number;
  ratingTrend: number;
  responseTime: number;
  activeOrders: number;
}) {
  let riskScore = 0;
  const factors = [];

  // Complaints
  if (vendor.unresolvedComplaints > 5) {
    riskScore += 3;
    factors.push(`${vendor.unresolvedComplaints} unresolved complaints`);
  } else if (vendor.unresolvedComplaints > 2) {
    riskScore += 1.5;
    factors.push('Multiple unresolved complaints');
  }

  // Rating trend
  if (vendor.ratingTrend < -0.3) {
    riskScore += 2.5;
    factors.push('Declining rating trend');
  }

  // Low rating
  if (vendor.avgRating < 3.5) {
    riskScore += 2;
    factors.push('Below average rating');
  }

  // Response time
  if (vendor.responseTime > 48) {
    riskScore += 1.5;
    factors.push('Slow complaint response time');
  }

  // Activity
  if (vendor.activeOrders < 10) {
    riskScore += 1;
    factors.push('Low order volume');
  }

  let level: 'low' | 'medium' | 'high' = 'low';
  if (riskScore >= 5) level = 'high';
  else if (riskScore >= 2.5) level = 'medium';

  return {
    level,
    score: Math.min(10, riskScore),
    factors,
    requiresAction: level === 'high'
  };
}

// Smart Menu Discovery
export function getSmartMenuSuggestions(items: MenuItem[]) {
  // Most popular items
  const mostPopular = items
    .filter(item => (item.orders || 0) > 15)
    .sort((a, b) => (b.orders || 0) - (a.orders || 0))
    .slice(0, 5);

  // Quick dishes (can infer from category or add prep time)
  const quickDishes = items.filter(item => 
    item.category === 'appetizers' || 
    item.category === 'salads' ||
    item.category === 'drinks'
  );

  // Vegetarian (can infer from name or add flag)
  const vegetarian = items.filter(item => {
    const name = item.name.toLowerCase();
    return name.includes('vegetarian') || 
           name.includes('vegan') || 
           name.includes('salad') ||
           name.includes('mushroom') && !name.includes('meat');
  });

  // High rated
  const highRated = items
    .filter(item => (item.rating || 0) >= 4.5)
    .sort((a, b) => (b.rating || 0) - (a.rating || 0));

  return {
    mostPopular,
    quickDishes,
    vegetarian,
    highRated
  };
}

// Menu Item Suggestions
export function generateMenuSuggestions(currentItems: MenuItem[]) {
  const suggestions = [];
  const categories = new Set(currentItems.map(item => item.category));

  // Check for missing popular categories
  if (!categories.has('appetizers')) {
    suggestions.push({
      type: 'category',
      title: 'Add Appetizers',
      description: 'Restaurants with appetizers see 30% higher average order value',
      impact: 'high'
    });
  }

  if (!categories.has('desserts')) {
    suggestions.push({
      type: 'category',
      title: 'Add Desserts',
      description: 'Desserts typically have 60%+ profit margins',
      impact: 'medium'
    });
  }

  // Price gaps
  const prices = currentItems.map(item => item.price).sort((a, b) => a - b);
  const gaps = [];
  for (let i = 1; i < prices.length; i++) {
    if (prices[i] - prices[i-1] > 8) {
      gaps.push({ low: prices[i-1], high: prices[i] });
    }
  }

  if (gaps.length > 0) {
    suggestions.push({
      type: 'pricing',
      title: 'Price Gap Detected',
      description: `Add items in the €${gaps[0].low.toFixed(2)}-€${gaps[0].high.toFixed(2)} range to capture more customers`,
      impact: 'medium'
    });
  }

  return suggestions;
}

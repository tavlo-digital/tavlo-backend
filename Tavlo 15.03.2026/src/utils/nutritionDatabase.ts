/**
 * Open Food Facts API Integration
 * https://world.openfoodfacts.org/data
 */

export interface NutritionData {
  calories: number; // kcal per 100g
  fat: number; // g per 100g
  protein: number; // g per 100g
  carbs: number; // g per 100g
}

export interface FoodSearchResult {
  id: string;
  name: string;
  brand?: string;
  nutrition: NutritionData;
  source: 'database' | 'manual';
}

/**
 * Search Open Food Facts database for ingredients
 */
export async function searchFoodDatabase(query: string): Promise<FoodSearchResult[]> {
  if (!query || query.length < 2) {
    return [];
  }

  try {
    const response = await fetch(
      `https://world.openfoodfacts.org/cgi/search.pl?search_terms=${encodeURIComponent(query)}&search_simple=1&action=process&json=1&page_size=10`
    );

    if (!response.ok) {
      console.error('Open Food Facts API error:', response.status);
      return [];
    }

    const data = await response.json();

    if (!data.products || data.products.length === 0) {
      return [];
    }

    // Transform API results to our format
    const results: FoodSearchResult[] = data.products
      .filter((product: any) => {
        // Only include products with nutrition data
        return product.nutriments && (
          product.nutriments['energy-kcal_100g'] !== undefined ||
          product.nutriments['energy_100g'] !== undefined
        );
      })
      .map((product: any) => {
        const nutriments = product.nutriments;
        
        return {
          id: product.id || product.code,
          name: product.product_name || product.product_name_en || 'Unknown',
          brand: product.brands,
          nutrition: {
            calories: Math.round(
              nutriments['energy-kcal_100g'] || 
              nutriments['energy_100g'] / 4.184 || 
              0
            ),
            fat: parseFloat((nutriments['fat_100g'] || 0).toFixed(1)),
            protein: parseFloat((nutriments['proteins_100g'] || 0).toFixed(1)),
            carbs: parseFloat((nutriments['carbohydrates_100g'] || 0).toFixed(1))
          },
          source: 'database' as const
        };
      })
      .slice(0, 10); // Limit to 10 results

    return results;
  } catch (error) {
    console.error('Error searching food database:', error);
    return [];
  }
}

/**
 * Calculate nutrition for a dish based on ingredients and quantities
 */
export interface IngredientWithNutrition {
  ingredientId: string;
  ingredientName: string;
  quantity: number;
  unit: 'g' | 'ml' | 'piece';
  nutrition: NutritionData; // per 100g/100ml/piece
}

export function calculateDishNutrition(ingredients: IngredientWithNutrition[]): NutritionData {
  let totalCalories = 0;
  let totalFat = 0;
  let totalProtein = 0;
  let totalCarbs = 0;

  for (const ingredient of ingredients) {
    const { quantity, unit, nutrition } = ingredient;
    
    // Calculate multiplier based on quantity
    let multiplier = 1;
    if (unit === 'g' || unit === 'ml') {
      // Nutrition is per 100g/100ml, so divide quantity by 100
      multiplier = quantity / 100;
    } else if (unit === 'piece') {
      // Nutrition is already per piece
      multiplier = quantity;
    }

    totalCalories += nutrition.calories * multiplier;
    totalFat += nutrition.fat * multiplier;
    totalProtein += nutrition.protein * multiplier;
    totalCarbs += nutrition.carbs * multiplier;
  }

  return {
    calories: Math.round(totalCalories),
    fat: parseFloat(totalFat.toFixed(1)),
    protein: parseFloat(totalProtein.toFixed(1)),
    carbs: parseFloat(totalCarbs.toFixed(1))
  };
}

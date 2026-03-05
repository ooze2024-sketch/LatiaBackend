<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create roles
        $adminRole = Role::create([
            'name' => 'admin',
            'description' => 'Administrator - Full system access',
        ]);

        $cashierRole = Role::create([
            'name' => 'cashier',
            'description' => 'Cashier - Can process sales and manage orders',
        ]);

        $managerRole = Role::create([
            'name' => 'manager',
            'description' => 'Manager - Can view reports and manage inventory',
        ]);

        // Create users
        User::create([
            'username' => 'admin',
            'email' => 'owner@latia.local',
            'password_hash' => Hash::make('admin123'),
            'role_id' => $adminRole->id,
            'full_name' => 'Owner',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'cashier1',
            'email' => 'maria@latia.local',
            'password_hash' => Hash::make('cashier123'),
            'role_id' => $cashierRole->id,
            'full_name' => 'Maria Santos',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'cashier2',
            'email' => 'juan@latia.local',
            'password_hash' => Hash::make('cashier123'),
            'role_id' => $cashierRole->id,
            'full_name' => 'Juan Dela Cruz',
            'is_active' => true,
        ]);

        User::create([
            'username' => 'manager',
            'email' => 'manager@latia.local',
            'password_hash' => Hash::make('manager123'),
            'role_id' => $managerRole->id,
            'full_name' => 'Manager',
            'is_active' => true,
        ]);

        // Create categories
        $mealCategory = Category::create([
            'name' => 'Meals',
            'description' => 'Main meal items',
        ]);

        $drinksCategory = Category::create([
            'name' => 'Drinks',
            'description' => 'Beverages',
        ]);

        $dessertsCategory = Category::create([
            'name' => 'Desserts',
            'description' => 'Sweet items',
        ]);

        $sidesCategory = Category::create([
            'name' => 'Sides',
            'description' => 'Side dishes',
        ]);

        // Create products
        $products = [
            ['sku' => 'ADB-001', 'name' => 'Adobo', 'category_id' => $mealCategory->id, 'cost' => 20.00, 'price' => 25.00, 'description' => 'Classic Filipino adobo'],
            ['sku' => 'FR-001', 'name' => 'Fried Rice', 'category_id' => $mealCategory->id, 'cost' => 10.00, 'price' => 25.00, 'description' => 'Garlic fried rice'],
            ['sku' => 'SIL-001', 'name' => 'Sinigang', 'category_id' => $mealCategory->id, 'cost' => 25.00, 'price' => 30.00, 'description' => 'Pork sinigang'],
            ['sku' => 'REC-001', 'name' => 'Reco', 'category_id' => $mealCategory->id, 'cost' => 15.00, 'price' => 20.00, 'description' => 'Beef reco'],
            ['sku' => 'CK-001', 'name' => 'Fried Chicken', 'category_id' => $mealCategory->id, 'cost' => 18.00, 'price' => 28.00, 'description' => '2 pieces fried chicken'],
            ['sku' => 'PAP-001', 'name' => 'Pap Buwan', 'category_id' => $drinksCategory->id, 'cost' => 5.00, 'price' => 10.00, 'description' => 'Papaya shake'],
            ['sku' => 'MIN-001', 'name' => 'Minesohe', 'category_id' => $drinksCategory->id, 'cost' => 8.00, 'price' => 12.00, 'description' => 'Minesohe drink'],
            ['sku' => 'CB-001', 'name' => 'Cold Brew', 'category_id' => $drinksCategory->id, 'cost' => 6.00, 'price' => 12.00, 'description' => 'Cold brew coffee'],
            ['sku' => 'HT-001', 'name' => 'Hot Tea', 'category_id' => $drinksCategory->id, 'cost' => 3.00, 'price' => 8.00, 'description' => 'Iced or hot tea'],
            ['sku' => 'LE-001', 'name' => 'Leche Flan', 'category_id' => $dessertsCategory->id, 'cost' => 8.00, 'price' => 15.00, 'description' => 'Homemade leche flan'],
            ['sku' => 'TIR-001', 'name' => 'Tiramisu', 'category_id' => $dessertsCategory->id, 'cost' => 12.00, 'price' => 18.00, 'description' => 'Italian tiramisu'],
            ['sku' => 'BR-001', 'name' => 'Brown Rice', 'category_id' => $sidesCategory->id, 'cost' => 5.00, 'price' => 8.00, 'description' => 'Brown rice side'],
            ['sku' => 'VEG-001', 'name' => 'Mixed Vegetables', 'category_id' => $sidesCategory->id, 'cost' => 10.00, 'price' => 15.00, 'description' => 'Cooked vegetables'],
        ];

        foreach ($products as $product) {
            Product::create(array_merge($product, ['is_active' => true]));
        }
    }
}


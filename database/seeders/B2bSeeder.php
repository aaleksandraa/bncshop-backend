<?php

namespace Database\Seeders;

use App\Models\B2bCategory;
use App\Models\B2bCustomer;
use App\Models\B2bProduct;
use App\Models\B2bProductImage;
use App\Models\B2bSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class B2bSeeder extends Seeder
{
    public function run(): void
    {
        B2bSetting::query()->firstOrCreate([], [
            'default_customer_discount_percent' => 0,
            'admin_notification_email' => env('B2B_ADMIN_EMAIL', env('ADMIN_EMAIL', 'admin@bncshop.test')),
            'notify_customers_on_new_product' => false,
        ]);

        if (! App::environment('production')) {
            $this->seedB2bAdminUser();
            $this->seedB2bTestCustomer();
        }

        $this->seedCatalog();
    }

    private function seedB2bAdminUser(): void
    {
        $email = (string) env('B2B_ADMIN_EMAIL', 'b2badmin@bncshop.test');
        $password = $this->resolvePassword('B2B_ADMIN_PASSWORD', 'B2bAdmin123!');
        $role = Role::findOrCreate('B2B Admin');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) env('B2B_ADMIN_NAME', 'BNC B2B Admin'),
                'password' => $password,
                'phone' => null,
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill([
            'is_customer' => false,
            'is_b2b_customer' => false,
        ])->save();

        $user->syncRoles([$role->name]);

        $this->command?->info("B2B admin: {$email} / B2bAdmin123! (panel: /b2b-admin)");
    }

    private function seedB2bTestCustomer(): void
    {
        $email = (string) env('B2B_CUSTOMER_EMAIL', 'b2bkupac@bncshop.test');
        $password = $this->resolvePassword('B2B_CUSTOMER_PASSWORD', 'B2bKupac123!');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Test B2B Kupac',
                'password' => $password,
                'phone' => '061000000',
                'email_verified_at' => now(),
            ],
        );
        $user->forceFill([
            'is_customer' => false,
            'is_b2b_customer' => true,
        ])->save();

        B2bCustomer::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'company_name' => 'Test Firma d.o.o.',
                'company_address' => 'Ulica Testnih Kupaca 1, Sarajevo',
                'jib' => '1234567890123',
                'pdv_number' => '200000000',
                'phone' => '061000000',
                'discount_percent' => null,
                'is_active' => true,
            ],
        );

        $this->command?->info("B2B test kupac: {$email} / B2bKupac123! (portal: /b2b)");
    }

    private function seedCatalog(): void
    {
        /** @var array<string, list<array{name: string, description: string, regular_price: float, sale_price?: float|null, sku: string, stock_quantity?: int, exclude_customer_discount?: bool}>> $catalog */
        $catalog = [
            'Laptopi' => [
                [
                    'name' => 'Dell Latitude 5540',
                    'description' => 'Poslovni laptop sa Intel Core i5 procesorom, 16GB RAM i 512GB SSD.',
                    'regular_price' => 1899.00,
                    'sku' => 'B2B-LAP-001',
                    'stock_quantity' => 12,
                ],
                [
                    'name' => 'Lenovo ThinkPad E14 Gen 5',
                    'description' => 'Robusan poslovni laptop sa 14" ekranom, idealan za terenski rad.',
                    'regular_price' => 1649.00,
                    'sale_price' => 1499.00,
                    'sku' => 'B2B-LAP-002',
                    'stock_quantity' => 8,
                ],
                [
                    'name' => 'HP ProBook 450 G10',
                    'description' => '15.6" laptop sa dugotrajnom baterijom i Windows 11 Pro.',
                    'regular_price' => 1750.00,
                    'sku' => 'B2B-LAP-003',
                    'stock_quantity' => 15,
                    'exclude_customer_discount' => true,
                ],
            ],
            'Racunari' => [
                [
                    'name' => 'HP ProDesk 400 G9',
                    'description' => 'Desktop računar za uredsku upotrebu sa Windows 11 Pro licencom.',
                    'regular_price' => 1299.00,
                    'sku' => 'B2B-PC-001',
                    'stock_quantity' => 10,
                ],
                [
                    'name' => 'Dell OptiPlex 7010 SFF',
                    'description' => 'Kompaktan small form factor desktop sa Intel Core i5 i 16GB RAM.',
                    'regular_price' => 1189.00,
                    'sale_price' => 1099.00,
                    'sku' => 'B2B-PC-002',
                    'stock_quantity' => 6,
                ],
                [
                    'name' => 'Lenovo ThinkCentre M70q Gen 4',
                    'description' => 'Mini PC sa niskom potrošnjom energije, pogodan za POS i ured.',
                    'regular_price' => 899.00,
                    'sku' => 'B2B-PC-003',
                    'stock_quantity' => 20,
                ],
            ],
            'Monitori' => [
                [
                    'name' => 'Dell P2422H 24"',
                    'description' => 'Full HD IPS monitor sa tan kim okvirima, idealan za ured.',
                    'regular_price' => 349.00,
                    'sku' => 'B2B-MON-001',
                    'stock_quantity' => 25,
                ],
                [
                    'name' => 'LG 27UP850K-W 27" 4K',
                    'description' => '4K UHD IPS monitor sa USB-C i HDR podrškom.',
                    'regular_price' => 649.00,
                    'sale_price' => 579.00,
                    'sku' => 'B2B-MON-002',
                    'stock_quantity' => 7,
                ],
                [
                    'name' => 'Samsung Odyssey G5 27"',
                    'description' => 'QHD gaming monitor 165Hz — pogodan za dizajn i multimediju.',
                    'regular_price' => 429.00,
                    'sku' => 'B2B-MON-003',
                    'stock_quantity' => 11,
                    'exclude_customer_discount' => true,
                ],
            ],
            'Printeri' => [
                [
                    'name' => 'HP LaserJet Pro M404dn',
                    'description' => 'Crno-bijeli laserski printer sa mrežnom konekcijom i duplex štampom.',
                    'regular_price' => 459.00,
                    'sku' => 'B2B-PRN-001',
                    'stock_quantity' => 14,
                ],
                [
                    'name' => 'Canon imageRUNNER 1643i',
                    'description' => 'Multifunkcijski A4 printer/skaner/kopir za veće uredske timove.',
                    'regular_price' => 1890.00,
                    'sale_price' => 1690.00,
                    'sku' => 'B2B-PRN-002',
                    'stock_quantity' => 4,
                ],
                [
                    'name' => 'Epson EcoTank L6290',
                    'description' => 'Inkjet multifunkcijski printer sa Wi-Fi i velikim rezervoarima tinte.',
                    'regular_price' => 549.00,
                    'sku' => 'B2B-PRN-003',
                    'stock_quantity' => 9,
                ],
            ],
        ];

        $categorySort = 0;
        $productCount = 0;

        foreach ($catalog as $categoryName => $products) {
            $category = B2bCategory::query()->updateOrCreate(
                ['slug' => Str::slug($categoryName)],
                [
                    'name' => $categoryName,
                    'description' => "B2B kategorija: {$categoryName}",
                    'sort_order' => $categorySort++,
                    'is_active' => true,
                ],
            );

            foreach ($products as $index => $productData) {
                $product = B2bProduct::query()->updateOrCreate(
                    ['slug' => Str::slug($productData['name'])],
                    [
                        'b2b_category_id' => $category->id,
                        'name' => $productData['name'],
                        'description' => $productData['description'],
                        'regular_price' => $productData['regular_price'],
                        'sale_price' => $productData['sale_price'] ?? null,
                        'exclude_customer_discount' => $productData['exclude_customer_discount'] ?? false,
                        'stock_quantity' => $productData['stock_quantity'] ?? 10,
                        'sku' => $productData['sku'],
                        'is_active' => true,
                        'sort_order' => $index,
                    ],
                );

                if ($product->images()->count() === 0) {
                    B2bProductImage::query()->create([
                        'b2b_product_id' => $product->id,
                        'path' => 'https://placehold.co/600x600/e2e8f0/64748b?text='.urlencode($productData['name']),
                        'sort_order' => 0,
                        'is_primary' => true,
                    ]);
                }

                $productCount++;
            }
        }

        $this->command?->info("B2B katalog seedovan: 4 kategorije, {$productCount} proizvoda.");
    }

    private function resolvePassword(string $envKey, string $localDefault): string
    {
        $password = env($envKey);

        if (is_string($password) && $password !== '') {
            return $password;
        }

        if (App::environment('local', 'testing')) {
            return $localDefault;
        }

        throw new RuntimeException("Postavite {$envKey} prije pokretanja seedera.");
    }
}

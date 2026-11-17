<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = [
            [
                'id' => Str::uuid()->toString(),
                'supplier_id' => 'FLOW-WIN11-PRO',
                'slug' => 'windows-11-professionnel',
                'name' => 'Windows 11 Professionnel (Clé Digitale)',
                'display_name' => 'Windows 11 Pro — Clé d\'activation officielle',
                'category' => 'Système d\'exploitation',
                'shop_category' => 'Systèmes',
                'image_url' => 'https://images.unsplash.com/photo-1629654297299-c8506221ca97?auto=format&fit=crop&w=600&q=80',
                'delivery_kind' => 'key',
                'cost_usd_cents' => 350,
                'list_usd_cents' => 19999,
                'stock' => 150,
                'in_stock' => true,
                'preorder' => false,
                'expire_days' => 0,
                'warranty' => 'Garantie à vie',
                'requires_email' => false,
                'supplier_purchasable' => true,
                'supplier_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'featured_rank' => 1,
                'description' => 'Licence originale Microsoft Windows 11 Professionnel à vie pour 1 PC.',
                'instructions' => 'Accédez aux Paramètres Windows > Système > Activation > Modifier la clé de produit et collez votre licence.',
                'price_xof_override' => 5000,
                'synced_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'supplier_id' => 'FLOW-OFF21-PRO',
                'slug' => 'microsoft-office-2021-pro-plus',
                'name' => 'Microsoft Office 2021 Professionnel Plus',
                'display_name' => 'Office 2021 Pro Plus — Licence Permanente',
                'category' => 'Bureautique',
                'shop_category' => 'Bureautique',
                'image_url' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?auto=format&fit=crop&w=600&q=80',
                'delivery_kind' => 'key',
                'cost_usd_cents' => 450,
                'list_usd_cents' => 43999,
                'stock' => 80,
                'in_stock' => true,
                'preorder' => false,
                'expire_days' => 0,
                'warranty' => 'Garantie à vie',
                'requires_email' => false,
                'supplier_purchasable' => true,
                'supplier_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'featured_rank' => 2,
                'description' => 'Suite complète Office 2021 : Word, Excel, PowerPoint, Outlook, Access, Publisher. Activation en ligne officielle.',
                'instructions' => 'Téléchargez l\'installeur officiel depuis setup.office.com et entrez votre clé de produit.',
                'price_xof_override' => 7500,
                'synced_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'supplier_id' => 'FLOW-KASP-1Y',
                'slug' => 'kaspersky-internet-security-1an',
                'name' => 'Kaspersky Internet Security 1 An / 1 Appareil',
                'display_name' => 'Kaspersky Internet Security — 1 An',
                'category' => 'Antivirus & Sécurité',
                'shop_category' => 'Sécurité',
                'image_url' => 'https://images.unsplash.com/photo-1563986768609-322da13575f3?auto=format&fit=crop&w=600&q=80',
                'delivery_kind' => 'key',
                'cost_usd_cents' => 500,
                'list_usd_cents' => 3999,
                'stock' => 45,
                'in_stock' => true,
                'preorder' => false,
                'expire_days' => 365,
                'warranty' => 'Garantie 1 an',
                'requires_email' => false,
                'supplier_purchasable' => true,
                'supplier_active' => true,
                'is_published' => true,
                'is_featured' => true,
                'featured_rank' => 3,
                'description' => 'Protection complète contre les ransomwares, virus, malwares et cyberattaques sur Windows et Mac.',
                'instructions' => 'Téléchargez Kaspersky et activez avec votre code de licence à 20 caractères.',
                'price_xof_override' => 6000,
                'synced_at' => now(),
            ],
            [
                'id' => Str::uuid()->toString(),
                'supplier_id' => 'FLOW-CANVA-EDU',
                'slug' => 'canva-pro-education-1an',
                'name' => 'Canva Pro Éducation Invitation 1 An',
                'display_name' => 'Canva Pro — Équipe Éducation 1 An',
                'category' => 'Design & Création',
                'shop_category' => 'Design',
                'image_url' => 'https://images.unsplash.com/photo-1626785774573-4b799315345d?auto=format&fit=crop&w=600&q=80',
                'delivery_kind' => 'activation',
                'cost_usd_cents' => 200,
                'list_usd_cents' => 12999,
                'stock' => 200,
                'in_stock' => true,
                'preorder' => false,
                'expire_days' => 365,
                'warranty' => 'Garantie 1 an',
                'requires_email' => true,
                'supplier_purchasable' => true,
                'supplier_active' => true,
                'is_published' => true,
                'is_featured' => false,
                'featured_rank' => null,
                'description' => 'Activation directe de Canva Pro sur votre adresse e-mail personnelle.',
                'instructions' => 'Vous recevrez une invitation officielle par e-mail. Cliquez sur le lien pour rejoindre l\'espace Pro.',
                'price_xof_override' => 3500,
                'synced_at' => now(),
            ],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate(
                ['slug' => $p['slug']],
                $p
            );
        }
    }
}

<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Mise en avant automatique par marque, d'après le NOM des produits (voir config/shop.php) : rien à régler à la main.
 *
 * - Chaque produit publié dont le nom correspond à une marque devient « phare » et reçoit un rang selon l'ordre des marques
 *   puis, dans une marque, les produits disponibles d'abord et le moins cher en premier.
 * - Le carrousel prend, pour chaque marque, son meilleur produit DISPONIBLE (jamais un produit en rupture).
 * - Un rang défini à la main (backoffice) reste prioritaire sur le calcul automatique.
 */
class BrandHighlights
{
    /**
     * @param  Collection<int, array<string, mixed>>  $products  produits au format de la page d'accueil
     * @return array{products: Collection, carousel: Collection}
     */
    public static function apply(Collection $products): array
    {
        $brands = array_values((array) config('shop.highlight_brands', []));

        // Marque de chaque produit (première correspondance dans l'ordre configuré).
        $byBrand = [];
        foreach ($products as $i => $p) {
            if (!empty($p['featured_rank'])) {
                continue; // rang manuel : prioritaire
            }
            $haystack = trim(($p['name'] ?? '') . ' ' . ($p['display_name'] ?? ''));
            foreach ($brands as $b => $brand) {
                if (@preg_match($brand['pattern'], $haystack) === 1) {
                    $byBrand[$b][] = $i;
                    break;
                }
            }
        }

        $items = $products->all();
        $reps = [];
        foreach ($byBrand as $b => $indexes) {
            usort($indexes, function ($x, $y) use ($items) {
                $a = $items[$x];
                $c = $items[$y];

                return [(int) !$a['available'], (int) ($a['price_xof'] ?? 0)] <=> [(int) !$c['available'], (int) ($c['price_xof'] ?? 0)];
            });
            foreach ($indexes as $pos => $i) {
                // Après les rangs manuels (1..999), dans l'ordre des marques puis des produits de la marque.
                $items[$i]['featured_rank'] = 1000 + $b * 100 + $pos;
                $items[$i]['is_featured'] = true;
            }
            $best = $items[$indexes[0]];
            if (!empty($best['available'])) {
                $reps[$b] = $best;
            }
        }
        ksort($reps);

        return [
            'products' => collect($items),
            'carousel' => collect(array_values($reps))->take((int) config('shop.carousel_size', 10))->values(),
        ];
    }
}

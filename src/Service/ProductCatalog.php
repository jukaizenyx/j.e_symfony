<?php

namespace App\Service;

class ProductCatalog
{
    /**
     * @return list<array{id: int, name: string, image: string, description: string, sizes: array<string, int>}>
     */
    public function all(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Cream Moisturizer',
                'image' => 'images/first-png.svg',
                'description' => 'A lightweight, fast-absorbing moisturizer built around clean botanical actives. Softens and hydrates without leaving a heavy or greasy after-feel — made for daily use, morning and night.',
                'sizes' => ['30ml' => 149, '50ml' => 199, '100ml' => 329],
            ],
            [
                'id' => 2,
                'name' => 'Shea Butter Scrub',
                'image' => 'images/second-png.svg',
                'description' => 'A rich exfoliating scrub blending raw shea butter with fine botanical granules. Buffs away dry, dull skin while leaving behind a soft, conditioned finish.',
                'sizes' => ['100ml' => 299, '200ml' => 499],
            ],
            [
                'id' => 3,
                'name' => 'Whitening Lotion',
                'image' => 'images/third-png.svg',
                'description' => 'A brightening body lotion formulated with plant-based actives to even out tone and texture over time. Non-sticky, fast-absorbing, and safe for daily application.',
                'sizes' => ['100ml' => 399, '250ml' => 749],
            ],
            [
                'id' => 4,
                'name' => 'Rejuvinating Spray',
                'image' => 'images/fouth-png.svg',
                'description' => 'A fine botanical mist that refreshes and revives skin throughout the day. Mist onto clean or made-up skin for an instant boost of hydration and radiance.',
                'sizes' => ['50ml' => 249, '100ml' => 429],
            ],
        ];
    }

    /**
     * @return array{id: int, name: string, image: string, description: string, sizes: array<string, int>}|null
     */
    public function find(int $id): ?array
    {
        foreach ($this->all() as $product) {
            if ($product['id'] === $id) {
                return $product;
            }
        }

        return null;
    }
}

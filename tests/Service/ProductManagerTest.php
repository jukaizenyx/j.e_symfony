<?php

namespace App\Tests\Service;

use App\Entity\Product;
use App\Service\ProductManager;
use PHPUnit\Framework\TestCase;

class ProductManagerTest extends TestCase
{
    public function testAppliesSizePricesAndStartingPrice(): void
    {
        $product = new Product();
        $manager = new ProductManager();

        self::assertTrue($manager->applySizes($product, [
            ['size' => 'Small', 'price' => '12.50'],
            ['size' => 'Large', 'price' => '20.00'],
        ]));
        self::assertSame(['Small' => 12.5, 'Large' => 20], json_decode($product->getSizes(), true));
        self::assertSame('12.50', $product->getPrice());
    }

    public function testRejectsAnEmptyOrNegativeSizeSet(): void
    {
        $product = new Product();
        $manager = new ProductManager();

        self::assertFalse($manager->applySizes($product, [['size' => '', 'price' => '']]));
        self::assertFalse($manager->applySizes($product, [['size' => 'Small', 'price' => -1]]));
    }
}
